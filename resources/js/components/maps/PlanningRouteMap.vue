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
        heightClass: 'h-[620px] lg:h-[760px]',
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

const isClosedCourse = computed(() => {
    const points = renderedRoutePoints.value;

    return (
        points.length > 2 && isSamePoint(points[0], points[points.length - 1])
    );
});

const visibleRoutePoints = computed(() =>
    isClosedCourse.value
        ? renderedRoutePoints.value.slice(0, -1)
        : renderedRoutePoints.value,
);

const routeLegs = computed(() =>
    renderedRoutePoints.value.slice(0, -1).map((point, index) => {
        const nextPoint = renderedRoutePoints.value[index + 1];

        return {
            key: `${point.lat}-${point.lng}-${nextPoint.lat}-${nextPoint.lng}`,
            fromLabel: routePointLabel(index),
            toLabel: routePointLabel(index + 1),
            distanceKm: haversineKm(point, nextPoint),
            bearingDeg: bearingDeg(point, nextPoint),
        };
    }),
);

const totalDistanceKm = computed(() =>
    routeLegs.value.reduce((sum, leg) => sum + leg.distanceKm, 0),
);

function buildBaseLayer() {
    const config = mapTileStyles.value.chart;

    return L.tileLayer(config.url, {
        maxZoom: config.max_zoom ?? 17,
        attribution: config.attribution,
    });
}

function plannerMarkerIcon(label: string) {
    return L.divIcon({
        className: 'journal-planning-map-marker-shell',
        html: `<span class="journal-planning-map-marker journal-planning-map-marker--route">${label}</span>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
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

function setCoursePoints(points: RouteWaypoint[]) {
    if (!points.length) {
        emit('update:launchLat', '');
        emit('update:launchLng', '');
        emit('update:landingLat', '');
        emit('update:landingLng', '');
        emitWaypoints([]);

        return;
    }

    const [firstPoint] = points;
    const lastPoint = points.length > 1 ? points[points.length - 1] : null;

    emit('update:launchLat', firstPoint.lat.toFixed(6));
    emit('update:launchLng', firstPoint.lng.toFixed(6));
    emitWaypoints(points.slice(1, -1));
    emit('update:landingLat', lastPoint ? lastPoint.lat.toFixed(6) : '');
    emit('update:landingLng', lastPoint ? lastPoint.lng.toFixed(6) : '');
}

function appendCoursePoint(lat: number, lng: number) {
    const nextPoint = formatPoint(lat, lng);
    const points = renderedRoutePoints.value;

    if (!points.length) {
        setCoursePoints([nextPoint]);

        return;
    }

    if (isClosedCourse.value) {
        setCoursePoints([...points.slice(0, -1), nextPoint, points[0]]);

        return;
    }

    setCoursePoints([...points, nextPoint]);
}

function updateCoursePoint(index: number, lat: number, lng: number) {
    const nextPoint = formatPoint(lat, lng);
    const points = renderedRoutePoints.value;
    const updatedPoints = points.map((point, pointIndex) => {
        if (pointIndex === index) {
            return nextPoint;
        }

        if (
            index === 0 &&
            isClosedCourse.value &&
            pointIndex === points.length - 1
        ) {
            return nextPoint;
        }

        return point;
    });

    setCoursePoints(updatedPoints);
}

function removeCoursePoint(index: number) {
    if (index === 0) {
        return;
    }

    const points = renderedRoutePoints.value.filter(
        (_, pointIndex) => pointIndex !== index,
    );

    setCoursePoints(points);
}

function closeCourse() {
    const points = renderedRoutePoints.value;

    if (points.length < 2 || isClosedCourse.value) {
        return;
    }

    setCoursePoints([...points, points[0]]);
}

function clearCourse() {
    setCoursePoints([]);
}

function formatPoint(lat: number, lng: number): RouteWaypoint {
    return {
        lat: Number(lat.toFixed(6)),
        lng: Number(lng.toFixed(6)),
    };
}

function routePointLabel(index: number): string {
    const points = renderedRoutePoints.value;

    if (isClosedCourse.value && index === points.length - 1) {
        return '1';
    }

    return String(index + 1);
}

function isSamePoint(left: RouteWaypoint, right: RouteWaypoint): boolean {
    return (
        Math.abs(left.lat - right.lat) < 0.000001 &&
        Math.abs(left.lng - right.lng) < 0.000001
    );
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

function bearingDeg(left: RouteWaypoint, right: RouteWaypoint): number {
    const toRadians = (degrees: number) => (degrees * Math.PI) / 180;
    const toDegrees = (radians: number) => (radians * 180) / Math.PI;
    const lat1 = toRadians(left.lat);
    const lat2 = toRadians(right.lat);
    const dLng = toRadians(right.lng - left.lng);
    const y = Math.sin(dLng) * Math.cos(lat2);
    const x =
        Math.cos(lat1) * Math.sin(lat2) -
        Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);

    return (toDegrees(Math.atan2(y, x)) + 360) % 360;
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
            weight: 9,
            opacity: 0.9,
        }).addTo(routes);

        L.polyline(latLngs, {
            color: '#d71920',
            weight: 3,
            opacity: 0.92,
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

    visibleRoutePoints.value.forEach((point, index) => {
        const isFirstPoint = index === 0;
        const routeMarker = L.marker([point.lat, point.lng], {
            draggable: true,
            icon: plannerMarkerIcon(routePointLabel(index)),
        })
            .bindTooltip(
                isFirstPoint
                    ? 'Point 1. Click again to close the loop.'
                    : `Point ${routePointLabel(index)}. Drag to refine; double-click to remove.`,
            )
            .addTo(markers);

        routeMarker.on('dragend', () => {
            const markerPoint = routeMarker.getLatLng();
            updateCoursePoint(index, markerPoint.lat, markerPoint.lng);
        });

        routeMarker.on('click', (event) => {
            L.DomEvent.stopPropagation(event);

            if (isFirstPoint) {
                closeCourse();
            }
        });

        routeMarker.on('dblclick', () => {
            removeCoursePoint(index);
        });
    });

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
        appendCoursePoint(event.latlng.lat, event.latlng.lng);
    });

    map.setView(
        [props.defaultView.lat, props.defaultView.lng],
        props.defaultView.zoom,
    );
    renderMarkers();

    if (renderedRoutePoints.value.length > 1) {
        fitToPoints();
    }
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
                    Click the map to add points in order. Drag any marker to
                    refine the line; click point 1 again to close the loop.
                </p>
            </div>

            <div
                class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:pb-0 [&::-webkit-scrollbar]:hidden"
            >
                <button
                    type="button"
                    class="journal-utility-link shrink-0"
                    @click="clearCourse"
                >
                    Clear course
                </button>
            </div>
        </div>

        <div
            class="relative mt-4 overflow-hidden rounded-[22px] border border-[color:var(--journal-line)] bg-white"
        >
            <div ref="mapElement" :class="heightClass" />
            <div
                v-if="routeLegs.length"
                class="pointer-events-none absolute right-3 bottom-3 left-3 z-[500] sm:right-auto sm:left-4 sm:max-w-[520px]"
            >
                <div
                    class="pointer-events-auto rounded-[22px] border border-white/78 bg-white/90 p-3 shadow-[0_18px_42px_rgba(34,40,78,0.18)] backdrop-blur"
                >
                    <div
                        class="flex items-center justify-between gap-4 border-b border-[color:var(--journal-line)] pb-2"
                    >
                        <div>
                            <p class="journal-kicker">Course estimate</p>
                            <p
                                class="mt-1 text-[1.35rem] leading-none font-semibold text-[color:var(--journal-text)]"
                            >
                                {{ totalDistanceKm.toFixed(1) }} km
                            </p>
                        </div>
                        <span class="journal-chip"
                            >{{ routeLegs.length }} legs</span
                        >
                    </div>
                    <div
                        class="mt-3 flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    >
                        <span
                            v-for="leg in routeLegs"
                            :key="leg.key"
                            class="shrink-0 rounded-full border border-[color:var(--journal-line)] bg-white/84 px-3 py-1.5 font-mono text-xs text-[color:var(--journal-text)]"
                        >
                            {{ leg.fromLabel }}→{{ leg.toLabel }}
                            {{ leg.distanceKm.toFixed(1) }} km ·
                            {{ leg.bearingDeg.toFixed(0) }}°
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
