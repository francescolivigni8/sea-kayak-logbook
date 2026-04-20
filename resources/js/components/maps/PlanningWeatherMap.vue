<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import * as maptilersdk from '@maptiler/sdk';
import {
    PrecipitationLayer,
    PressureLayer,
    RadarLayer,
    TemperatureLayer,
    WindLayer,
} from '@maptiler/weather';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

type CoordinateValue = string | number | null;
type WeatherLayerKey =
    | 'wind'
    | 'precipitation'
    | 'temperature'
    | 'pressure'
    | 'radar';
type MappableLayer = Parameters<maptilersdk.Map['addLayer']>[0] & {
    id: string;
    animateByFactor?: (factor: number) => void;
    animate?: (timePerSecond: number) => void;
    getAnimationStart?: () => number;
    getAnimationEnd?: () => number;
    getAnimationTime?: () => number;
    getAnimationTimeDate?: () => Date;
    setAnimationTime?: (time: number) => void;
    isPlaying?: () => boolean;
    on?: (event: string, callback: (event: { time?: number }) => void) => void;
    off?: (event: string, callback: (event: { time?: number }) => void) => void;
    onSourceReadyAsync?: () => Promise<void>;
};

interface DefaultView {
    lat: number;
    lng: number;
    zoom: number;
}

interface RouteWaypoint {
    lat: number;
    lng: number;
}

interface SharedIntegrations {
    maps?: {
        maptilerKey?: string | null;
        weatherEnabled?: boolean;
    };
}

interface RouteFeatureProperties {
    index?: number | string;
    label?: string;
}

interface LayerMouseEvent {
    lngLat: {
        lat: number;
        lng: number;
    };
    point?: maptilersdk.Point;
    features?: Array<{
        properties?: RouteFeatureProperties;
    }>;
    originalEvent?: Event;
}

interface GeocodingFeature {
    center?: [number, number];
    place_name?: string;
    text?: string;
    geometry?: {
        coordinates?: unknown;
    };
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
        sampleTimeLabel?: string;
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
            zoom: 9,
        }),
        heightClass: 'h-[720px] lg:h-[900px]',
        sampleTimeLabel: 'Start',
    },
);

const emit = defineEmits<{
    'update:launchLat': [value: string];
    'update:launchLng': [value: string];
    'update:landingLat': [value: string];
    'update:landingLng': [value: string];
    'update:routeWaypointsJson': [value: string];
}>();

const lineSourceId = 'ykj-planning-weather-route';
const pointSourceId = 'ykj-planning-weather-points';
const routeGlowLayerId = 'ykj-planning-weather-route-glow';
const routeLineLayerId = 'ykj-planning-weather-route-line';
const routePointLayerId = 'ykj-planning-weather-points';
const routePointLabelLayerId = 'ykj-planning-weather-point-labels';
const animationSpeedFactor = 3600;

const page = usePage();
const activeLayer = ref<WeatherLayerKey>('wind');
const mapElement = ref<HTMLElement | null>(null);
const mapError = ref<string | null>(null);
const isMapReady = ref(false);
const hasFitInitialRoute = ref(false);
const showLegend = ref(false);
const searchQuery = ref('');
const searchStatus = ref<string | null>(null);
const isSearching = ref(false);
const isGlobe = ref(false);
const liveWeatherEnabled = ref(false);
const animationStart = ref<number | null>(null);
const animationEnd = ref<number | null>(null);
const animationTime = ref<number | null>(null);
const timelineProgress = ref(0);
const isPlaying = ref(false);

let map: maptilersdk.Map | null = null;
let weatherLayer: MappableLayer | null = null;
let weatherTickHandler: ((event: { time?: number }) => void) | null = null;
let hasRegisteredRouteInteractions = false;
let draggedPointIndex: number | null = null;

const weatherLayerOptions: {
    key: WeatherLayerKey;
    label: string;
    meta: string;
    icon: string;
}[] = [
    { key: 'temperature', label: 'Temperature', meta: 'air mass', icon: 'T' },
    {
        key: 'precipitation',
        label: 'Precipitation',
        meta: 'rain / snow',
        icon: 'P',
    },
    { key: 'wind', label: 'Wind', meta: 'animated flow', icon: 'W' },
    { key: 'pressure', label: 'Pressure', meta: 'systems', icon: 'P' },
    { key: 'radar', label: 'Radar', meta: 'cloud + rain', icon: 'R' },
];

const legends: Record<
    WeatherLayerKey,
    {
        title: string;
        unit: string;
        stops: { label: string; color: string }[];
    }
> = {
    wind: {
        title: 'Wind',
        unit: 'm/s with animated streamlines',
        stops: [
            { label: 'Light', color: '#2dd4bf' },
            { label: 'Moderate', color: '#60a5fa' },
            { label: 'Strong', color: '#a78bfa' },
            { label: 'Hard', color: '#f97316' },
        ],
    },
    precipitation: {
        title: 'Precipitation',
        unit: 'mm/h',
        stops: [
            { label: 'Dry', color: '#0f172a' },
            { label: 'Light', color: '#38bdf8' },
            { label: 'Rain', color: '#2563eb' },
            { label: 'Heavy', color: '#7c3aed' },
        ],
    },
    temperature: {
        title: 'Temperature',
        unit: 'C',
        stops: [
            { label: 'Cold', color: '#2563eb' },
            { label: 'Cool', color: '#22d3ee' },
            { label: 'Mild', color: '#facc15' },
            { label: 'Warm', color: '#ef4444' },
        ],
    },
    pressure: {
        title: 'Pressure',
        unit: 'hPa',
        stops: [
            { label: 'Low', color: '#7c3aed' },
            { label: 'Normal', color: '#38bdf8' },
            { label: 'High', color: '#fb7185' },
        ],
    },
    radar: {
        title: 'Radar',
        unit: 'dBZ reflectivity',
        stops: [
            { label: 'Cloud', color: '#64748b' },
            { label: 'Showers', color: '#22c55e' },
            { label: 'Heavy', color: '#f97316' },
            { label: 'Severe', color: '#ef4444' },
        ],
    },
};

const integrations = computed(
    () => (page.props.integrations as SharedIntegrations | undefined)?.maps,
);

const maptilerKey = computed(() => integrations.value?.maptilerKey ?? null);
const weatherEnabled = computed(
    () => integrations.value?.weatherEnabled !== false,
);
const canShowPlanningMap = computed(() => Boolean(maptilerKey.value));
const weatherLayerAvailable = computed(
    () => weatherEnabled.value && Boolean(maptilerKey.value),
);

const launchPoint = computed(() =>
    coordinatePoint(props.launchLat, props.launchLng),
);
const landingPoint = computed(() =>
    coordinatePoint(props.landingLat, props.landingLng),
);

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

const routePoints = computed(() =>
    [launchPoint.value, ...routeWaypoints.value, landingPoint.value].filter(
        (point): point is RouteWaypoint => point !== null,
    ),
);

const isClosedCourse = computed(() => {
    const points = routePoints.value;

    return (
        points.length > 2 && isSamePoint(points[0], points[points.length - 1])
    );
});

const visibleRoutePoints = computed(() =>
    isClosedCourse.value ? routePoints.value.slice(0, -1) : routePoints.value,
);

const routeLegs = computed(() =>
    routePoints.value.slice(0, -1).map((point, index) => {
        const nextPoint = routePoints.value[index + 1];

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

const routeGeoJson = computed(() => {
    const pointFeatures = visibleRoutePoints.value.map((point, index) => ({
        type: 'Feature' as const,
        properties: {
            index,
            label: String(index + 1),
        },
        geometry: {
            type: 'Point' as const,
            coordinates: [point.lng, point.lat],
        },
    }));

    const lineFeatures =
        routePoints.value.length > 1
            ? [
                  {
                      type: 'Feature' as const,
                      properties: {},
                      geometry: {
                          type: 'LineString' as const,
                          coordinates: routePoints.value.map((point) => [
                              point.lng,
                              point.lat,
                          ]),
                      },
                  },
              ]
            : [];

    return {
        line: {
            type: 'FeatureCollection' as const,
            features: lineFeatures,
        },
        points: {
            type: 'FeatureCollection' as const,
            features: pointFeatures,
        },
    };
});

const activeLegend = computed(() => legends[activeLayer.value]);

const routeSummary = computed(() => {
    if (routePoints.value.length < 2) {
        return 'Click the map to add course points. Click point 1 again to close the loop.';
    }

    const context = liveWeatherEnabled.value
        ? `over ${weatherLayerLabel(activeLayer.value).toLowerCase()}`
        : 'on basemap';

    if (isClosedCourse.value) {
        return `Closed course, ${totalDistanceKm.value.toFixed(1)} km, ${context}.`;
    }

    return `${routePoints.value.length} points, ${totalDistanceKm.value.toFixed(1)} km, ${context}.`;
});

const animationTimeLabel = computed(() =>
    animationTime.value ? formatWeatherTime(animationTime.value) : 'Loading',
);

const timelineDayLabels = computed(() => {
    const start = animationStart.value;
    const end = animationEnd.value;

    if (!start || !end) {
        return ['Now', '+1 day', '+2 days', '+3 days', '+4 days'];
    }

    const span = end - start;

    return Array.from({ length: 5 }, (_, index) =>
        formatDayLabel(start + (span / 4) * index),
    );
});

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
    const points = routePoints.value;

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
    const points = routePoints.value;
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

    setCoursePoints(
        routePoints.value.filter((_, pointIndex) => pointIndex !== index),
    );
}

function closeCourse() {
    const points = routePoints.value;

    if (points.length < 2 || isClosedCourse.value) {
        return;
    }

    setCoursePoints([...points, points[0]]);
}

function clearCourse() {
    setCoursePoints([]);
    hasFitInitialRoute.value = false;
}

function formatPoint(lat: number, lng: number): RouteWaypoint {
    return {
        lat: Number(lat.toFixed(6)),
        lng: Number(lng.toFixed(6)),
    };
}

function routePointLabel(index: number): string {
    const points = routePoints.value;

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

function weatherLayerLabel(layer: WeatherLayerKey): string {
    return (
        weatherLayerOptions.find((option) => option.key === layer)?.label ??
        'Weather'
    );
}

function buildWeatherLayer(layer: WeatherLayerKey): MappableLayer {
    if (layer === 'precipitation') {
        return new PrecipitationLayer({
            id: 'ykj-weather-precipitation',
            opacity: 0.32,
        }) as unknown as MappableLayer;
    }

    if (layer === 'radar') {
        return new RadarLayer({
            id: 'ykj-weather-radar',
            opacity: 0.3,
        }) as unknown as MappableLayer;
    }

    if (layer === 'pressure') {
        return new PressureLayer({
            id: 'ykj-weather-pressure',
            opacity: 0.24,
        }) as unknown as MappableLayer;
    }

    if (layer === 'temperature') {
        return new TemperatureLayer({
            id: 'ykj-weather-temperature',
            opacity: 0.28,
        }) as unknown as MappableLayer;
    }

    return new WindLayer({
        id: 'ykj-weather-wind',
        color: [255, 255, 255, 105],
        fastColor: [103, 114, 255, 145],
        fastIsLarger: true,
        opacity: 0.38,
        density: 0.82,
        speed: 0.0014,
    }) as unknown as MappableLayer;
}

function weatherBeforeLayerId(): string | undefined {
    if (map?.getLayer(routeGlowLayerId)) {
        return routeGlowLayerId;
    }

    return map?.getStyle().layers?.find((layer) => layer.type === 'symbol')?.id;
}

function removeWeatherLayer() {
    if (!map || !weatherLayer) {
        weatherLayer = null;

        return;
    }

    detachWeatherEvents();

    if (map.getLayer(weatherLayer.id)) {
        map.removeLayer(weatherLayer.id);
    }

    weatherLayer = null;
}

function applyWeatherLayer() {
    if (!map || !isMapReady.value) {
        return;
    }

    removeWeatherLayer();
    mapError.value = null;

    if (!weatherLayerAvailable.value || !liveWeatherEnabled.value) {
        isPlaying.value = false;

        return;
    }

    try {
        weatherLayer = buildWeatherLayer(activeLayer.value);
        map.addLayer(weatherLayer, weatherBeforeLayerId());
        attachWeatherEvents(weatherLayer);
        upsertRouteOverlay();
    } catch (error) {
        mapError.value =
            error instanceof Error
                ? error.message
                : 'MapTiler weather layer could not be added.';
    }
}

function attachWeatherEvents(layer: MappableLayer) {
    weatherTickHandler = (event) => {
        if (typeof event.time === 'number') {
            syncAnimationTime(event.time);
        }
    };

    layer.on?.('tick', weatherTickHandler);
    layer.on?.('animationTimeSet', weatherTickHandler);
    layer.on?.('playAnimation', () => {
        isPlaying.value = true;
    });
    layer.on?.('pauseAnimation', () => {
        isPlaying.value = false;
    });

    layer
        .onSourceReadyAsync?.()
        .then(() => {
            if (weatherLayer !== layer) {
                return;
            }

            syncAnimationBounds();
            layer.animateByFactor?.(animationSpeedFactor);
            isPlaying.value = layer.isPlaying?.() ?? true;
        })
        .catch(() => {
            mapError.value = 'Weather animation data could not be loaded.';
        });
}

function detachWeatherEvents() {
    if (!weatherLayer || !weatherTickHandler) {
        weatherTickHandler = null;

        return;
    }

    weatherLayer.off?.('tick', weatherTickHandler);
    weatherLayer.off?.('animationTimeSet', weatherTickHandler);
    weatherTickHandler = null;
}

function syncAnimationBounds() {
    if (!weatherLayer) {
        return;
    }

    const start = weatherLayer.getAnimationStart?.();
    const end = weatherLayer.getAnimationEnd?.();
    const time = weatherLayer.getAnimationTime?.();

    animationStart.value =
        start !== undefined && Number.isFinite(start) ? start : null;
    animationEnd.value = end !== undefined && Number.isFinite(end) ? end : null;

    if (time !== undefined && Number.isFinite(time)) {
        syncAnimationTime(time);
    }
}

function syncAnimationTime(time: number) {
    animationTime.value = time;

    if (
        animationStart.value === null ||
        animationEnd.value === null ||
        animationEnd.value <= animationStart.value
    ) {
        return;
    }

    timelineProgress.value = Math.min(
        100,
        Math.max(
            0,
            ((time - animationStart.value) /
                (animationEnd.value - animationStart.value)) *
                100,
        ),
    );
}

function togglePlayback() {
    if (!weatherLayer) {
        return;
    }

    if (isPlaying.value) {
        weatherLayer.animateByFactor?.(0);
        weatherLayer.animate?.(0);
        isPlaying.value = false;

        return;
    }

    weatherLayer.animateByFactor?.(animationSpeedFactor);
    isPlaying.value = true;
}

function setTimelineProgress(value: string | number) {
    const progress = Number(value);
    timelineProgress.value = progress;

    if (
        !weatherLayer ||
        animationStart.value === null ||
        animationEnd.value === null
    ) {
        return;
    }

    const nextTime =
        animationStart.value +
        (animationEnd.value - animationStart.value) * (progress / 100);

    weatherLayer.setAnimationTime?.(nextTime);
    syncAnimationTime(nextTime);
}

function updateSourceData(sourceId: string, data: unknown) {
    const source = map?.getSource(sourceId) as
        | { setData?: (nextData: unknown) => void }
        | undefined;

    source?.setData?.(data);
}

function upsertRouteOverlay() {
    if (!map || !isMapReady.value) {
        return;
    }

    if (!map.getSource(lineSourceId)) {
        map.addSource(lineSourceId, {
            type: 'geojson',
            data: routeGeoJson.value.line,
        });
    } else {
        updateSourceData(lineSourceId, routeGeoJson.value.line);
    }

    if (!map.getSource(pointSourceId)) {
        map.addSource(pointSourceId, {
            type: 'geojson',
            data: routeGeoJson.value.points,
        });
    } else {
        updateSourceData(pointSourceId, routeGeoJson.value.points);
    }

    if (!map.getLayer(routeGlowLayerId)) {
        map.addLayer({
            id: routeGlowLayerId,
            type: 'line',
            source: lineSourceId,
            paint: {
                'line-color': '#020617',
                'line-opacity': 0.58,
                'line-width': 10,
            },
        });
    }

    if (!map.getLayer(routeLineLayerId)) {
        map.addLayer({
            id: routeLineLayerId,
            type: 'line',
            source: lineSourceId,
            paint: {
                'line-color': '#ffffff',
                'line-opacity': 0.96,
                'line-width': 5,
            },
        });
    }

    if (!map.getLayer(routePointLayerId)) {
        map.addLayer({
            id: routePointLayerId,
            type: 'circle',
            source: pointSourceId,
            paint: {
                'circle-color': '#ffffff',
                'circle-radius': 14,
                'circle-stroke-color': '#d71920',
                'circle-stroke-width': 5,
            },
        });
    }

    if (!map.getLayer(routePointLabelLayerId)) {
        map.addLayer({
            id: routePointLabelLayerId,
            type: 'symbol',
            source: pointSourceId,
            layout: {
                'text-allow-overlap': true,
                'text-field': ['get', 'label'],
                'text-font': ['Open Sans Bold'],
                'text-size': 11,
            },
            paint: {
                'text-color': '#b30f18',
            },
        });
    }

    registerRouteInteractions();

    if (!hasFitInitialRoute.value && routePoints.value.length) {
        hasFitInitialRoute.value = true;
        fitRouteOrDefault();
    }
}

function registerRouteInteractions() {
    if (!map || hasRegisteredRouteInteractions) {
        return;
    }

    hasRegisteredRouteInteractions = true;

    map.on('click', routePointLayerId, (event: LayerMouseEvent) => {
        const index = pointIndexFromEvent(event);

        if (index === 0) {
            closeCourse();
        }
    });

    map.on('dblclick', routePointLayerId, (event: LayerMouseEvent) => {
        const index = pointIndexFromEvent(event);

        if (index !== null) {
            removeCoursePoint(index);
        }
    });

    map.on('mousedown', routePointLayerId, (event: LayerMouseEvent) => {
        const index = pointIndexFromEvent(event);

        if (index === null) {
            return;
        }

        draggedPointIndex = index;
        map?.dragPan.disable();
        map?.getCanvas().classList.add('cursor-grabbing');
        updateCoursePoint(index, event.lngLat.lat, event.lngLat.lng);
    });

    map.on('mouseenter', routePointLayerId, () => {
        map?.getCanvas().classList.add('cursor-pointer');
    });

    map.on('mouseleave', routePointLayerId, () => {
        map?.getCanvas().classList.remove('cursor-pointer');
    });

    map.on('mousemove', (event: LayerMouseEvent) => {
        if (draggedPointIndex === null) {
            return;
        }

        updateCoursePoint(
            draggedPointIndex,
            event.lngLat.lat,
            event.lngLat.lng,
        );
    });

    map.on('mouseup', () => {
        if (draggedPointIndex === null) {
            return;
        }

        draggedPointIndex = null;
        map?.dragPan.enable();
        map?.getCanvas().classList.remove('cursor-grabbing');
    });
}

function pointIndexFromEvent(event: LayerMouseEvent): number | null {
    const rawIndex = event.features?.[0]?.properties?.index;
    const index = Number(rawIndex);

    return Number.isFinite(index) ? index : null;
}

function handleMapClick(event: maptilersdk.MapMouseEvent) {
    if (!map) {
        return;
    }

    const clickedPoints = event.point
        ? map.queryRenderedFeatures(event.point, {
              layers: [routePointLayerId],
          })
        : [];

    if (clickedPoints.length) {
        return;
    }

    appendCoursePoint(event.lngLat.lat, event.lngLat.lng);
}

function fitRouteOrDefault(force = false) {
    if (!map) {
        return;
    }

    const points = routePoints.value;

    if (!points.length) {
        map.easeTo({
            center: [props.defaultView.lng, props.defaultView.lat],
            zoom: props.defaultView.zoom,
            duration: force ? 300 : 0,
        });

        return;
    }

    if (points.length === 1) {
        map.easeTo({
            center: [points[0].lng, points[0].lat],
            zoom: Math.max(props.defaultView.zoom, 10),
            duration: force ? 300 : 0,
        });

        return;
    }

    const lngs = points.map((point) => point.lng);
    const lats = points.map((point) => point.lat);

    map.fitBounds(
        [
            [Math.min(...lngs), Math.min(...lats)],
            [Math.max(...lngs), Math.max(...lats)],
        ],
        {
            padding: 86,
            maxZoom: 12,
            duration: force ? 450 : 0,
        },
    );
}

function selectWeatherLayer(key: WeatherLayerKey) {
    activeLayer.value = key;
}

function toggleLiveWeather() {
    if (!weatherLayerAvailable.value) {
        mapError.value =
            'Live weather needs MapTiler weather enabled in the environment.';

        return;
    }

    liveWeatherEnabled.value = !liveWeatherEnabled.value;
}

function zoomIn() {
    map?.zoomIn();
}

function zoomOut() {
    map?.zoomOut();
}

function resetBearing() {
    map?.easeTo({ bearing: 0, pitch: 0, duration: 300 });
}

function locateUser() {
    if (!navigator.geolocation) {
        mapError.value = 'Browser location is not available.';

        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            map?.flyTo({
                center: [position.coords.longitude, position.coords.latitude],
                zoom: 12,
                duration: 650,
            });
        },
        () => {
            mapError.value = 'Location permission was not granted.';
        },
        {
            enableHighAccuracy: true,
            timeout: 8000,
        },
    );
}

function toggleProjection() {
    if (!map) {
        return;
    }

    isGlobe.value = !isGlobe.value;
    map.setProjection(isGlobe.value ? 'globe' : 'mercator', {
        persist: true,
    });
}

async function searchPlace() {
    const query = searchQuery.value.trim();

    if (!query) {
        return;
    }

    isSearching.value = true;
    searchStatus.value = null;
    mapError.value = null;

    try {
        const result = await maptilersdk.geocoding.forward(query, {
            limit: 1,
        });
        const feature = result.features?.[0] as unknown as
            | GeocodingFeature
            | undefined;
        const center = featureCenter(feature);

        if (!center) {
            searchStatus.value = 'No place found.';

            return;
        }

        map?.flyTo({
            center,
            zoom: 11,
            duration: 700,
        });
        searchStatus.value = feature?.place_name ?? feature?.text ?? query;
    } catch {
        mapError.value = 'Search failed. Check the MapTiler key and try again.';
    } finally {
        isSearching.value = false;
    }
}

function featureCenter(
    feature: GeocodingFeature | undefined,
): [number, number] | null {
    if (feature?.center) {
        return feature.center;
    }

    const coordinates = feature?.geometry?.coordinates;

    if (
        Array.isArray(coordinates) &&
        typeof coordinates[0] === 'number' &&
        typeof coordinates[1] === 'number'
    ) {
        return [coordinates[0], coordinates[1]];
    }

    return null;
}

async function initializeMap() {
    await nextTick();

    if (!mapElement.value || map || !canShowPlanningMap.value) {
        return;
    }

    mapError.value = null;
    maptilersdk.config.apiKey = maptilerKey.value ?? '';

    try {
        map = new maptilersdk.Map({
            container: mapElement.value,
            style: maptilersdk.MapStyle.TOPO.PASTEL,
            center: [props.defaultView.lng, props.defaultView.lat],
            zoom: props.defaultView.zoom,
            pitch: 0,
            doubleClickZoom: false,
            attributionControl: {
                compact: 'auto',
            },
        });

        map.on('click', handleMapClick);

        map.on('load', () => {
            isMapReady.value = true;
            applyWeatherLayer();
            upsertRouteOverlay();
            fitRouteOrDefault();
        });

        map.on('error', (event) => {
            mapError.value =
                event.error?.message ?? 'MapTiler map could not load.';
        });
    } catch (error) {
        mapError.value =
            error instanceof Error
                ? error.message
                : 'MapTiler map could not be initialized.';
    }
}

function formatWeatherTime(timestampSeconds: number): string {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(timestampSeconds * 1000));
}

function formatDayLabel(timestampSeconds: number): string {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
    }).format(new Date(timestampSeconds * 1000));
}

watch(activeLayer, () => {
    applyWeatherLayer();
});

watch(liveWeatherEnabled, () => {
    if (!liveWeatherEnabled.value) {
        showLegend.value = false;
    }

    applyWeatherLayer();
});

watch(
    () => [
        props.launchLat,
        props.launchLng,
        props.landingLat,
        props.landingLng,
        props.routeWaypointsJson,
    ],
    () => {
        upsertRouteOverlay();
    },
);

watch(canShowPlanningMap, () => {
    if (canShowPlanningMap.value) {
        initializeMap();
    }
});

onMounted(() => {
    initializeMap();
});

onBeforeUnmount(() => {
    removeWeatherLayer();
    map?.remove();
    map = null;
});
</script>

<template>
    <section
        class="overflow-hidden rounded-[28px] border border-[color:var(--journal-line)] bg-[#dcebf2] text-white shadow-[0_24px_70px_rgba(15,23,42,0.18)]"
    >
        <div class="relative">
            <div
                v-if="canShowPlanningMap"
                ref="mapElement"
                :class="heightClass"
                class="bg-[#dcebf2]"
            />
            <div
                v-else
                :class="heightClass"
                class="flex items-center justify-center bg-[radial-gradient(circle_at_top,#213d62,transparent_42%),#09111f] p-6"
            >
                <div
                    class="max-w-xl rounded-[26px] border border-white/14 bg-white/10 p-5 text-center shadow-[0_24px_70px_rgba(0,0,0,0.22)] backdrop-blur"
                >
                    <p class="journal-kicker text-white/58">Waiting for key</p>
                    <h5 class="mt-2 text-2xl leading-none text-white">
                        MapTiler planning map is ready
                    </h5>
                    <p class="mt-3 text-sm leading-6 text-white/70">
                        Add <span class="font-mono">MAPTILER_API_KEY</span> and
                        keep
                        <span class="font-mono"
                            >MAPTILER_WEATHER_ENABLED=true</span
                        >
                        in Laravel Cloud to use the light route-planning basemap
                        and optional animated weather in one map.
                    </p>
                </div>
            </div>

            <div
                class="pointer-events-none absolute inset-0 z-10 flex flex-col justify-between p-3 sm:p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div
                        class="pointer-events-auto hidden w-[142px] flex-col gap-1.5 sm:flex"
                    >
                        <button
                            type="button"
                            class="flex items-center justify-between gap-2 rounded-[8px] px-2.5 py-2 text-left text-[0.72rem] font-black shadow-[0_8px_18px_rgba(0,0,0,0.14)] transition disabled:cursor-not-allowed disabled:opacity-60"
                            :class="
                                liveWeatherEnabled
                                    ? 'bg-[#2f7df6]/92 text-white'
                                    : 'bg-white/84 text-[#29304f] hover:bg-white'
                            "
                            :disabled="!weatherLayerAvailable"
                            @click="toggleLiveWeather"
                        >
                            <span>
                                <span class="block">Live weather</span>
                                <span
                                    class="block text-[0.54rem] tracking-[0.1em] uppercase opacity-62"
                                >
                                    {{ liveWeatherEnabled ? 'On' : 'Off' }}
                                </span>
                            </span>
                            <span
                                class="h-3 w-3 rounded-full"
                                :class="
                                    liveWeatherEnabled
                                        ? 'bg-white'
                                        : 'bg-[#55cfc3]'
                                "
                            />
                        </button>
                        <template v-if="liveWeatherEnabled">
                            <button
                                v-for="option in weatherLayerOptions"
                                :key="option.key"
                                type="button"
                                class="flex items-center gap-1.5 rounded-[6px] px-2.5 py-2 text-left text-[0.72rem] font-bold shadow-[0_8px_18px_rgba(0,0,0,0.14)] transition"
                                :class="
                                    option.key === activeLayer
                                        ? 'bg-[#2f7df6]/90 text-white'
                                        : 'bg-white/78 text-[#29304f] hover:bg-white/92'
                                "
                                @click="selectWeatherLayer(option.key)"
                            >
                                <span
                                    class="grid h-4 w-4 place-items-center rounded-full border border-current/20 font-mono text-[0.55rem]"
                                    >{{ option.icon }}</span
                                >
                                <span>
                                    <span class="block">{{
                                        option.label
                                    }}</span>
                                    <span
                                        class="block text-[0.54rem] font-semibold tracking-[0.1em] uppercase opacity-58"
                                        >{{ option.meta }}</span
                                    >
                                </span>
                            </button>
                        </template>
                        <p
                            v-if="!liveWeatherEnabled"
                            class="rounded-[8px] bg-white/72 px-2.5 py-2 text-[0.66rem] leading-4 font-semibold text-[#29304f]/78 shadow-[0_8px_18px_rgba(0,0,0,0.1)]"
                        >
                            Basemap mode for cleaner course drawing.
                        </p>
                    </div>

                    <div
                        class="pointer-events-auto ml-auto flex flex-col items-end gap-2"
                    >
                        <form
                            class="relative hidden sm:block"
                            @submit.prevent="searchPlace"
                        >
                            <input
                                v-model="searchQuery"
                                type="search"
                                class="h-8 w-[220px] rounded-[6px] border border-white/16 bg-white/88 px-3 pr-14 text-xs font-semibold text-[#1d2438] shadow-[0_8px_20px_rgba(0,0,0,0.14)] outline-none"
                                placeholder="Search"
                            />
                            <button
                                type="submit"
                                class="absolute top-0.5 right-0.5 rounded-[5px] bg-[#111827] px-2.5 py-1.5 text-[0.65rem] font-bold text-white disabled:opacity-55"
                                :disabled="isSearching"
                            >
                                {{ isSearching ? '...' : 'Go' }}
                            </button>
                        </form>

                        <div
                            class="grid grid-cols-5 gap-1.5 rounded-full border border-white/30 bg-white/42 p-1.5 shadow-[0_8px_18px_rgba(0,0,0,0.12)] backdrop-blur"
                        >
                            <button
                                type="button"
                                class="grid h-7 w-7 place-items-center rounded-full bg-white/88 text-sm font-black text-[#1d2438]"
                                title="Zoom in"
                                @click="zoomIn"
                            >
                                +
                            </button>
                            <button
                                type="button"
                                class="grid h-7 w-7 place-items-center rounded-full bg-white/88 text-sm font-black text-[#1d2438]"
                                title="Zoom out"
                                @click="zoomOut"
                            >
                                -
                            </button>
                            <button
                                type="button"
                                class="grid h-7 w-7 place-items-center rounded-full bg-white/88 text-[0.62rem] font-black text-[#1d2438]"
                                title="Reset north"
                                @click="resetBearing"
                            >
                                N
                            </button>
                            <button
                                type="button"
                                class="grid h-7 w-7 place-items-center rounded-full bg-white/88 text-[0.62rem] font-black text-[#1d2438]"
                                title="Locate me"
                                @click="locateUser"
                            >
                                ⌖
                            </button>
                            <button
                                type="button"
                                class="grid h-7 w-7 place-items-center rounded-full bg-white/88 text-[0.62rem] font-black text-[#1d2438]"
                                title="Toggle globe"
                                @click="toggleProjection"
                            >
                                ◎
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pointer-events-auto flex flex-col gap-3">
                    <div
                        class="flex flex-col gap-2 rounded-[8px] bg-white/62 p-2 text-[#29304f] shadow-[0_14px_32px_rgba(0,0,0,0.16)] backdrop-blur sm:hidden"
                    >
                        <button
                            type="button"
                            class="rounded-[6px] px-3 py-1.5 text-left text-[0.72rem] font-black disabled:cursor-not-allowed disabled:opacity-60"
                            :class="
                                liveWeatherEnabled
                                    ? 'bg-[#2f7df6]/90 text-white'
                                    : 'bg-white/82 text-[#29304f]'
                            "
                            :disabled="!weatherLayerAvailable"
                            @click="toggleLiveWeather"
                        >
                            Live weather {{ liveWeatherEnabled ? 'on' : 'off' }}
                        </button>
                        <div
                            v-if="liveWeatherEnabled"
                            class="flex gap-2 overflow-x-auto"
                        >
                            <button
                                v-for="option in weatherLayerOptions"
                                :key="`mobile-${option.key}`"
                                type="button"
                                class="shrink-0 rounded-[6px] px-2.5 py-1.5 text-[0.68rem] font-bold"
                                :class="
                                    option.key === activeLayer
                                        ? 'bg-[#2f7df6]/90 text-white'
                                        : 'bg-white/82 text-[#29304f]'
                                "
                                @click="selectWeatherLayer(option.key)"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                        <form class="flex gap-2" @submit.prevent="searchPlace">
                            <input
                                v-model="searchQuery"
                                type="search"
                                class="min-w-0 flex-1 rounded-[6px] border border-slate-200 bg-white/88 px-3 py-1.5 text-xs font-semibold outline-none"
                                placeholder="Search"
                            />
                            <button
                                type="submit"
                                class="rounded-[6px] bg-[#111827] px-3 py-1.5 text-[0.68rem] font-bold text-white"
                            >
                                Go
                            </button>
                        </form>
                    </div>

                    <div
                        v-if="liveWeatherEnabled"
                        class="flex flex-col gap-1.5 rounded-[8px] bg-white/36 p-1.5 text-[#29304f] shadow-[0_10px_26px_rgba(0,0,0,0.13)] backdrop-blur sm:flex-row sm:items-center"
                    >
                        <button
                            type="button"
                            class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-white/68 text-sm font-bold text-[#1d2438] shadow-[0_6px_14px_rgba(0,0,0,0.1)]"
                            @click="togglePlayback"
                        >
                            {{ isPlaying ? 'Ⅱ' : '▶' }}
                        </button>

                        <div class="min-w-0 flex-1">
                            <div
                                class="mb-1 flex flex-wrap items-center justify-between gap-2 text-[0.66rem] font-bold"
                            >
                                <span
                                    class="rounded-[6px] bg-white/58 px-2 py-0.5 font-mono text-[#2f4fdb]"
                                    >{{ animationTimeLabel }}</span
                                >
                                <span
                                    class="rounded-[6px] bg-white/58 px-2 py-0.5 font-mono"
                                    >{{ routeSummary }}</span
                                >
                            </div>
                            <input
                                class="h-1 w-full accent-[#2f7df6]"
                                type="range"
                                min="0"
                                max="100"
                                step="0.1"
                                :value="timelineProgress"
                                @input="
                                    setTimelineProgress(
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />
                            <div
                                class="mt-0.5 grid grid-cols-5 text-center text-[0.62rem] font-bold text-[#29304f]/58"
                            >
                                <span
                                    v-for="label in timelineDayLabels"
                                    :key="label"
                                    >{{ label }}</span
                                >
                            </div>
                        </div>

                        <button
                            type="button"
                            class="rounded-[6px] bg-white/58 px-2.5 py-1.5 text-[0.62rem] font-black tracking-[0.08em] uppercase shadow-[0_6px_14px_rgba(0,0,0,0.1)]"
                            @click="showLegend = !showLegend"
                        >
                            Legend
                        </button>
                    </div>
                </div>
            </div>

            <div
                class="pointer-events-none absolute top-2 left-2 z-20 flex flex-wrap gap-2 sm:top-auto"
                :class="
                    liveWeatherEnabled ? 'sm:bottom-[6.4rem]' : 'sm:bottom-4'
                "
            >
                <button
                    type="button"
                    class="pointer-events-auto rounded-full border border-white/40 bg-white/48 px-3 py-1.5 text-[0.65rem] font-black tracking-[0.08em] text-[#29304f] uppercase shadow-[0_8px_20px_rgba(0,0,0,0.12)] backdrop-blur"
                    @click="clearCourse"
                >
                    Clear course
                </button>
                <span
                    v-if="searchStatus"
                    class="pointer-events-auto rounded-full bg-white/62 px-3 py-1.5 text-[0.68rem] font-bold text-[#29304f] shadow-[0_8px_20px_rgba(0,0,0,0.12)] backdrop-blur"
                    >{{ searchStatus }}</span
                >
            </div>

            <div
                v-if="routeLegs.length"
                class="pointer-events-none absolute right-2 z-20 sm:right-auto sm:left-[8.4rem]"
                :class="
                    liveWeatherEnabled ? 'sm:bottom-[6.4rem]' : 'sm:bottom-4'
                "
            >
                <div
                    class="pointer-events-auto flex max-w-[260px] items-center gap-2 rounded-full border border-white/38 bg-white/46 px-3 py-1.5 text-[#29304f] shadow-[0_8px_22px_rgba(0,0,0,0.12)] backdrop-blur"
                >
                    <span
                        class="font-mono text-[0.62rem] font-black tracking-[0.1em] uppercase opacity-70"
                        >Course</span
                    >
                    <strong class="text-sm leading-none"
                        >{{ totalDistanceKm.toFixed(1) }} km</strong
                    >
                    <span class="text-[0.65rem] font-bold opacity-70"
                        >{{ routeLegs.length }} legs</span
                    >
                </div>
            </div>

            <div
                v-if="showLegend"
                class="absolute right-3 bottom-[11.5rem] z-30 w-[260px] rounded-[3px] bg-white p-4 text-[#29304f] shadow-[0_18px_44px_rgba(0,0,0,0.22)]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Legend</p>
                        <h5 class="mt-1 text-lg leading-none">
                            {{ activeLegend.title }}
                        </h5>
                        <p class="mt-1 text-xs font-semibold opacity-65">
                            {{ activeLegend.unit }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-sm font-black"
                        @click="showLegend = false"
                    >
                        ×
                    </button>
                </div>
                <div class="mt-4 grid gap-2">
                    <div
                        v-for="stop in activeLegend.stops"
                        :key="stop.label"
                        class="flex items-center gap-2 text-xs font-bold"
                    >
                        <span
                            class="h-3 w-10 rounded-full"
                            :style="{ background: stop.color }"
                        />
                        <span>{{ stop.label }}</span>
                    </div>
                </div>
            </div>

            <div
                v-if="mapError"
                class="absolute right-3 bottom-3 left-3 z-40 rounded-[18px] border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 shadow-xl sm:right-auto sm:max-w-[520px]"
            >
                {{ mapError }}
            </div>
        </div>
    </section>
</template>
