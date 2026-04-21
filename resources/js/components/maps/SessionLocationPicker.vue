<script setup lang="ts">
import L from 'leaflet';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { useMapTileStyles } from '@/lib/mapTiles';

type MapTarget = 'launch' | 'landing' | 'route';

interface DefaultView {
    lat: number;
    lng: number;
    zoom: number;
}

interface RouteWaypoint {
    lat: number;
    lng: number;
}

const props = withDefaults(
    defineProps<{
        launchLat?: number | null;
        launchLng?: number | null;
        landingLat?: number | null;
        landingLng?: number | null;
        routeWaypointsJson?: string;
        defaultView?: DefaultView;
    }>(),
    {
        launchLat: null,
        launchLng: null,
        landingLat: null,
        landingLng: null,
        routeWaypointsJson: '',
        defaultView: () => ({
            lat: 64.1466,
            lng: -21.9426,
            zoom: 10,
        }),
    },
);

const emit = defineEmits<{
    'update:launchLat': [value: string];
    'update:launchLng': [value: string];
    'update:landingLat': [value: string];
    'update:landingLng': [value: string];
    'update:routeWaypointsJson': [value: string];
}>();

const mapElement = ref<HTMLElement | null>(null);
const activeTarget = ref<MapTarget>('launch');
const mapTileStyles = useMapTileStyles();

let map: L.Map | null = null;
let markerLayer: L.LayerGroup | null = null;
let lineLayer: L.Polyline | null = null;
let baseLayer: L.TileLayer | null = null;
let hasFitInitialView = false;

const launchPoint = computed(() => {
    if (props.launchLat === null || props.launchLng === null) {
        return null;
    }

    return {
        lat: props.launchLat,
        lng: props.launchLng,
    };
});

const landingPoint = computed(() => {
    if (props.landingLat === null || props.landingLng === null) {
        return null;
    }

    return {
        lat: props.landingLat,
        lng: props.landingLng,
    };
});

const routeWaypoints = computed<RouteWaypoint[]>(() => {
    if (!props.routeWaypointsJson) {
        return [];
    }

    try {
        const parsed = JSON.parse(props.routeWaypointsJson);

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed
            .filter(
                (point): point is { lat: number; lng: number } =>
                    typeof point?.lat === 'number' &&
                    typeof point?.lng === 'number',
            )
            .map((point) => ({
                lat: Number(point.lat.toFixed(6)),
                lng: Number(point.lng.toFixed(6)),
            }));
    } catch {
        return [];
    }
});

const renderedRoutePoints = computed(() => {
    if (routeWaypoints.value.length === 0) {
        return [];
    }

    return [
        launchPoint.value,
        ...routeWaypoints.value,
        landingPoint.value,
    ].filter((point): point is { lat: number; lng: number } => point !== null);
});

function buildBaseLayer() {
    const config = mapTileStyles.value.activity;

    return L.tileLayer(config.url, {
        maxZoom: config.max_zoom ?? 18,
        attribution: config.attribution,
    });
}

function sessionMarkerIcon(
    tone: 'launch' | 'landing' | 'route',
    label: string,
) {
    return L.divIcon({
        className: 'journal-session-map-marker-shell',
        html: `<span class="journal-session-map-marker journal-session-map-marker--${tone}">${label}</span>`,
        iconSize: tone === 'route' ? [28, 28] : [34, 34],
        iconAnchor: tone === 'route' ? [14, 14] : [17, 17],
    });
}

function fitToPoints() {
    if (!map) {
        return;
    }

    const points =
        renderedRoutePoints.value.length > 1
            ? renderedRoutePoints.value
            : [launchPoint.value, landingPoint.value].filter(
                  (point): point is { lat: number; lng: number } =>
                      point !== null,
              );

    if (!points.length) {
        map.setView(
            [props.defaultView.lat, props.defaultView.lng],
            props.defaultView.zoom,
        );

        return;
    }

    if (points.length === 1) {
        map.setView([points[0].lat, points[0].lng], 12);

        return;
    }

    const bounds = L.latLngBounds(
        points.map((point) => [point.lat, point.lng] as [number, number]),
    );
    map.fitBounds(bounds.pad(0.18));
}

function invalidateMapSize() {
    window.setTimeout(() => {
        map?.invalidateSize();
    }, 120);
}

function emitWaypoints(points: RouteWaypoint[]) {
    emit(
        'update:routeWaypointsJson',
        points.length ? JSON.stringify(points) : '',
    );
}

function updateTargetPoint(lat: number, lng: number) {
    const formattedLat = lat.toFixed(6);
    const formattedLng = lng.toFixed(6);

    if (activeTarget.value === 'launch') {
        emit('update:launchLat', formattedLat);
        emit('update:launchLng', formattedLng);

        return;
    }

    if (activeTarget.value === 'landing') {
        emit('update:landingLat', formattedLat);
        emit('update:landingLng', formattedLng);

        return;
    }

    emitWaypoints([
        ...routeWaypoints.value,
        {
            lat: Number(formattedLat),
            lng: Number(formattedLng),
        },
    ]);
}

function updateWaypoint(index: number, lat: number, lng: number) {
    emitWaypoints(
        routeWaypoints.value.map((point, pointIndex) =>
            pointIndex === index
                ? {
                      lat: Number(lat.toFixed(6)),
                      lng: Number(lng.toFixed(6)),
                  }
                : point,
        ),
    );
}

function removeWaypoint(index: number) {
    emitWaypoints(
        routeWaypoints.value.filter((_, pointIndex) => pointIndex !== index),
    );
}

function clearRouteTrace() {
    emitWaypoints([]);
}

function fitToCurrentCourse() {
    map?.invalidateSize();
    fitToPoints();
}

function renderMarkers() {
    if (!map || !markerLayer) {
        return;
    }

    const leafletMap = map;
    const markers = markerLayer;

    markers.clearLayers();

    if (lineLayer) {
        map.removeLayer(lineLayer);
        lineLayer = null;
    }

    if (launchPoint.value) {
        const launchMarker = L.marker(
            [launchPoint.value.lat, launchPoint.value.lng],
            {
                draggable: true,
                icon: sessionMarkerIcon('launch', 'L'),
            },
        )
            .bindTooltip('Launch')
            .addTo(markers);

        launchMarker.on('dragend', () => {
            const point = launchMarker.getLatLng();
            emit('update:launchLat', point.lat.toFixed(6));
            emit('update:launchLng', point.lng.toFixed(6));
        });
    }

    if (landingPoint.value) {
        const landingMarker = L.marker(
            [landingPoint.value.lat, landingPoint.value.lng],
            {
                draggable: true,
                icon: sessionMarkerIcon('landing', 'F'),
            },
        )
            .bindTooltip('Landing')
            .addTo(markers);

        landingMarker.on('dragend', () => {
            const point = landingMarker.getLatLng();
            emit('update:landingLat', point.lat.toFixed(6));
            emit('update:landingLng', point.lng.toFixed(6));
        });
    }

    routeWaypoints.value.forEach((point, index) => {
        const routeMarker = L.marker([point.lat, point.lng], {
            draggable: true,
            icon: sessionMarkerIcon('route', String(index + 1)),
        })
            .bindTooltip(`Route point ${index + 1}`)
            .addTo(markers);

        routeMarker.on('dragend', () => {
            const markerPoint = routeMarker.getLatLng();
            updateWaypoint(index, markerPoint.lat, markerPoint.lng);
        });

        routeMarker.on('dblclick', () => {
            removeWaypoint(index);
        });
    });

    if (renderedRoutePoints.value.length > 1) {
        lineLayer = L.polyline(
            renderedRoutePoints.value.map(
                (point) => [point.lat, point.lng] as [number, number],
            ),
            {
                color: '#6772ff',
                weight: 4,
                opacity: 0.9,
            },
        ).addTo(leafletMap);
    }

    if (!hasFitInitialView) {
        fitToPoints();
        hasFitInitialView = true;
    }
}

async function initializeMap() {
    await nextTick();

    if (!mapElement.value || map) {
        return;
    }

    map = L.map(mapElement.value, {
        zoomControl: true,
        scrollWheelZoom: false,
        doubleClickZoom: false,
    });

    markerLayer = L.layerGroup().addTo(map);
    baseLayer = buildBaseLayer();
    baseLayer.addTo(map);

    map.on('click', (event: L.LeafletMouseEvent) => {
        updateTargetPoint(event.latlng.lat, event.latlng.lng);
    });

    renderMarkers();
    fitToPoints();
    invalidateMapSize();
}

watch(
    () => [
        props.launchLat,
        props.launchLng,
        props.landingLat,
        props.landingLng,
        props.routeWaypointsJson,
    ],
    () => {
        renderMarkers();
    },
    { deep: true },
);

onMounted(() => {
    initializeMap();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <section
        class="rounded-[24px] border border-[color:var(--journal-line)] bg-white/72 p-4"
    >
        <div
            class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
        >
            <div class="space-y-2">
                <p class="journal-kicker">Geolocation</p>
                <h4
                    class="text-[1.2rem] leading-none text-[color:var(--journal-text)] sm:text-[1.35rem]"
                >
                    Place the session
                </h4>
                <p class="text-sm leading-6 text-[color:var(--journal-muted)]">
                    Pins alone save the place. If you want a visible route,
                    switch to
                    <strong class="text-[color:var(--journal-text)]"
                        >trace route</strong
                    >
                    and click the map to add editable route points.
                </p>
            </div>

            <div
                class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:pb-0 [&::-webkit-scrollbar]:hidden"
            >
                <button
                    type="button"
                    class="journal-utility-link shrink-0"
                    :class="
                        activeTarget === 'launch' ? 'journal-chip--primary' : ''
                    "
                    @click="activeTarget = 'launch'"
                >
                    Place launch
                </button>
                <button
                    type="button"
                    class="journal-utility-link shrink-0"
                    :class="
                        activeTarget === 'landing'
                            ? 'journal-chip--primary'
                            : ''
                    "
                    @click="activeTarget = 'landing'"
                >
                    Place landing
                </button>
                <button
                    type="button"
                    class="journal-utility-link shrink-0"
                    :class="
                        activeTarget === 'route' ? 'journal-chip--primary' : ''
                    "
                    @click="activeTarget = 'route'"
                >
                    Trace route
                </button>
                <button
                    type="button"
                    class="journal-utility-link shrink-0"
                    :disabled="routeWaypoints.length === 0"
                    @click="clearRouteTrace"
                >
                    Clear route
                </button>
                <button
                    type="button"
                    class="journal-utility-link shrink-0"
                    @click="fitToCurrentCourse"
                >
                    Fit view
                </button>
            </div>
        </div>

        <div
            class="mt-4 overflow-hidden rounded-[20px] border border-[color:var(--journal-line)] bg-white/78 shadow-[inset_0_1px_0_rgba(255,255,255,0.72)]"
        >
            <div ref="mapElement" class="h-[260px] sm:h-[320px]" />
        </div>

        <div
            class="mt-4 flex gap-2 overflow-x-auto pb-1 text-xs font-medium text-[color:var(--journal-muted)] [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:pb-0 [&::-webkit-scrollbar]:hidden"
        >
            <span class="journal-chip shrink-0">Launch = green</span>
            <span class="journal-chip shrink-0">Landing = orange</span>
            <span class="journal-chip shrink-0">Trace route = blue points</span>
            <span class="journal-chip shrink-0">Drag markers to refine</span>
            <span class="journal-chip shrink-0"
                >Double-click a route point to remove it</span
            >
        </div>
    </section>
</template>
