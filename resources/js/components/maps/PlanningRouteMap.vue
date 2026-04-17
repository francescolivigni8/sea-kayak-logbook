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

type MapTarget = 'launch' | 'landing' | 'route';
type CoordinateValue = string | number | null;

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
        launchLat?: CoordinateValue;
        launchLng?: CoordinateValue;
        landingLat?: CoordinateValue;
        landingLng?: CoordinateValue;
        routeWaypointsJson?: string;
        defaultView?: DefaultView;
        heightClass?: string;
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
        heightClass: 'h-[420px] lg:h-[560px]',
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

let map: L.Map | null = null;
let markerLayer: L.LayerGroup | null = null;
let routeLayer: L.LayerGroup | null = null;
let labelLayer: L.LayerGroup | null = null;
let baseLayer: L.TileLayer | null = null;

const launchPoint = computed(() =>
    coordinatePoint(props.launchLat, props.launchLng),
);

const landingPoint = computed(() =>
    coordinatePoint(props.landingLat, props.landingLng),
);

function coordinatePoint(
    lat: CoordinateValue | undefined,
    lng: CoordinateValue | undefined,
): RouteWaypoint | null {
    const parsedLat = parseFloat(String(lat ?? ''));
    const parsedLng = parseFloat(String(lng ?? ''));

    if (!Number.isFinite(parsedLat) || !Number.isFinite(parsedLng)) {
        return null;
    }

    return {
        lat: parsedLat,
        lng: parsedLng,
    };
}

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
                (point): point is RouteWaypoint =>
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

const renderedRoutePoints = computed(() =>
    [launchPoint.value, ...routeWaypoints.value, landingPoint.value].filter(
        (point): point is RouteWaypoint => point !== null,
    ),
);

function buildBaseLayer() {
    return L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
        maxZoom: 17,
        attribution:
            'Map data © OpenStreetMap contributors, SRTM | Map style © OpenTopoMap',
    });
}

function plannerMarkerIcon(
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

    const points = renderedRoutePoints.value;

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
    map.fitBounds(bounds.pad(0.22));
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

function clearAll() {
    emit('update:launchLat', '');
    emit('update:launchLng', '');
    emit('update:landingLat', '');
    emit('update:landingLng', '');
    clearRouteTrace();
}

function haversineKm(left: RouteWaypoint, right: RouteWaypoint): number {
    const earthRadiusKm = 6371;
    const toRadians = (degrees: number) => (degrees * Math.PI) / 180;
    const dLat = toRadians(right.lat - left.lat);
    const dLng = toRadians(right.lng - left.lng);
    const lat1 = toRadians(left.lat);
    const lat2 = toRadians(right.lat);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.sin(dLng / 2) ** 2 * Math.cos(lat1) * Math.cos(lat2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return earthRadiusKm * c;
}

function renderMarkers() {
    if (!map || !markerLayer || !routeLayer || !labelLayer) {
        return;
    }

    const leafletMap = map;
    const markers = markerLayer;
    const routes = routeLayer;
    const labels = labelLayer;

    markers.clearLayers();
    routes.clearLayers();
    labels.clearLayers();

    if (renderedRoutePoints.value.length > 1) {
        const latLngs = renderedRoutePoints.value.map(
            (point) => [point.lat, point.lng] as [number, number],
        );

        L.polyline(latLngs, {
            color: '#f9fafb',
            weight: 8,
            opacity: 0.86,
        }).addTo(routes);

        L.polyline(latLngs, {
            color: '#6772ff',
            weight: 4,
            opacity: 0.94,
            dashArray: '10 8',
        }).addTo(routes);

        renderedRoutePoints.value.slice(0, -1).forEach((point, index) => {
            const nextPoint = renderedRoutePoints.value[index + 1];
            const distanceKm = haversineKm(point, nextPoint);
            const midpoint = L.latLng(
                (point.lat + nextPoint.lat) / 2,
                (point.lng + nextPoint.lng) / 2,
            );

            L.marker(midpoint, {
                interactive: false,
                icon: L.divIcon({
                    className: 'journal-planning-leg-label-shell',
                    html: `<span class="journal-planning-leg-label">${distanceKm.toFixed(1)} km</span>`,
                    iconSize: [76, 26],
                    iconAnchor: [38, 13],
                }),
            }).addTo(labels);
        });
    }

    if (launchPoint.value) {
        const launchMarker = L.marker(
            [launchPoint.value.lat, launchPoint.value.lng],
            {
                draggable: true,
                icon: plannerMarkerIcon('launch', 'L'),
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

    routeWaypoints.value.forEach((point, index) => {
        const routeMarker = L.marker([point.lat, point.lng], {
            draggable: true,
            icon: plannerMarkerIcon('route', String(index + 1)),
        })
            .bindTooltip(`Course point ${index + 1}. Double-click to remove.`)
            .addTo(markers);

        routeMarker.on('dragend', () => {
            const markerPoint = routeMarker.getLatLng();
            updateWaypoint(index, markerPoint.lat, markerPoint.lng);
        });

        routeMarker.on('dblclick', () => {
            removeWaypoint(index);
        });
    });

    if (landingPoint.value) {
        const landingMarker = L.marker(
            [landingPoint.value.lat, landingPoint.value.lng],
            {
                draggable: true,
                icon: plannerMarkerIcon('landing', 'F'),
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

    fitToPoints();
    leafletMap.invalidateSize();
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

    routeLayer = L.layerGroup().addTo(map);
    labelLayer = L.layerGroup().addTo(map);
    markerLayer = L.layerGroup().addTo(map);
    baseLayer = buildBaseLayer();
    baseLayer.addTo(map);

    map.on('click', (event: L.LeafletMouseEvent) => {
        updateTargetPoint(event.latlng.lat, event.latlng.lng);
    });

    fitToPoints();
    renderMarkers();
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
                <p class="journal-kicker">Course map</p>
                <h4
                    class="text-[1.2rem] leading-none text-[color:var(--journal-text)] sm:text-[1.35rem]"
                >
                    Draw the day out
                </h4>
                <p class="text-sm leading-6 text-[color:var(--journal-muted)]">
                    Place launch and landing, then add course points. Drag any
                    marker to refine the line; double-click a numbered course
                    point to remove it.
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
                    Launch
                </button>
                <button
                    type="button"
                    class="journal-utility-link shrink-0"
                    :class="
                        activeTarget === 'route' ? 'journal-chip--primary' : ''
                    "
                    @click="activeTarget = 'route'"
                >
                    Course point
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
                    Landing
                </button>
                <button
                    type="button"
                    class="journal-utility-link shrink-0"
                    @click="clearRouteTrace"
                >
                    Clear course
                </button>
                <button
                    type="button"
                    class="journal-utility-link shrink-0"
                    @click="clearAll"
                >
                    Reset map
                </button>
            </div>
        </div>

        <div
            class="mt-4 overflow-hidden rounded-[22px] border border-[color:var(--journal-line)] bg-white"
        >
            <div ref="mapElement" :class="heightClass" />
        </div>
    </section>
</template>
