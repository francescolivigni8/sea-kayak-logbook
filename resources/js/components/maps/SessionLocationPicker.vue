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
const mapTileStyles = useMapTileStyles();

let map: L.Map | null = null;
let markerLayer: L.LayerGroup | null = null;
let lineLayer: L.Polyline | null = null;
let baseLayer: L.TileLayer | null = null;

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
        const fallbackPoints: RouteWaypoint[] = [];

        if (launchPoint.value) {
            fallbackPoints.push(launchPoint.value);
        }

        if (
            landingPoint.value &&
            (!launchPoint.value ||
                launchPoint.value.lat !== landingPoint.value.lat ||
                launchPoint.value.lng !== landingPoint.value.lng)
        ) {
            fallbackPoints.push(landingPoint.value);
        }

        return fallbackPoints;
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
    return routeWaypoints.value;
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

function appendRoutePoint(lat: number, lng: number) {
    emitWaypoints([
        ...routeWaypoints.value,
        {
            lat: Number(lat.toFixed(6)),
            lng: Number(lng.toFixed(6)),
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

function refreshLayout() {
    map?.invalidateSize();
    fitToPoints();
    invalidateMapSize();
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

    renderedRoutePoints.value.forEach((point, index) => {
        const isFirstPoint = index === 0;
        const isLastPoint =
            renderedRoutePoints.value.length > 1 &&
            index === renderedRoutePoints.value.length - 1;
        const markerTone = isFirstPoint
            ? 'launch'
            : isLastPoint
              ? 'landing'
              : 'route';
        const markerLabel = isFirstPoint
            ? 'L'
            : isLastPoint
              ? 'F'
              : String(index + 1);
        const tooltipLabel = isFirstPoint
            ? 'Launch'
            : isLastPoint
              ? 'Landing'
              : `Route point ${index + 1}`;
        const routeMarker = L.marker([point.lat, point.lng], {
            draggable: true,
            icon: sessionMarkerIcon(markerTone, markerLabel),
        })
            .bindTooltip(tooltipLabel)
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
    map.setView(
        [props.defaultView.lat, props.defaultView.lng],
        props.defaultView.zoom,
    );

    map.on('click', (event: L.LeafletMouseEvent) => {
        appendRoutePoint(event.latlng.lat, event.latlng.lng);
    });

    renderMarkers();

    if (
        renderedRoutePoints.value.length > 0 ||
        launchPoint.value !== null ||
        landingPoint.value !== null
    ) {
        fitToPoints();
    }

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
        invalidateMapSize();
    },
    { deep: true },
);

function handleWindowResize() {
    invalidateMapSize();
}

onMounted(() => {
    initializeMap();
    window.addEventListener('resize', handleWindowResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleWindowResize);
    map?.remove();
    map = null;
});

defineExpose({
    refreshLayout,
    fitToCurrentCourse,
    clearRouteTrace,
});
</script>

<template>
    <section
        class="rounded-[22px] border border-[color:var(--journal-line)] bg-white/72 p-3 sm:rounded-[24px] sm:p-4"
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
                    Click the map to place the route. The first point becomes
                    launch, the last point becomes landing, and the traced line
                    drives the saved distance automatically.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:pb-0">
                <button
                    type="button"
                    class="journal-utility-link min-w-0 justify-center"
                    :disabled="renderedRoutePoints.length === 0"
                    @click="clearRouteTrace"
                >
                    Clear route
                </button>
                <button
                    type="button"
                    class="journal-utility-link min-w-0 justify-center"
                    @click="fitToCurrentCourse"
                >
                    Fit view
                </button>
            </div>
        </div>

        <div
            class="mt-4 overflow-hidden rounded-[20px] border border-[color:var(--journal-line)] bg-white/78 shadow-[inset_0_1px_0_rgba(255,255,255,0.72)]"
        >
            <div ref="mapElement" class="h-[240px] sm:h-[340px]" />
        </div>

        <div
            class="mt-4 flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]"
        >
            <span class="journal-chip shrink-0">First point = launch</span>
            <span class="journal-chip shrink-0">Last point = landing</span>
            <span class="journal-chip shrink-0">Drag points to refine</span>
            <span class="journal-chip shrink-0"
                >Double-click a point to remove it</span
            >
        </div>
    </section>
</template>
