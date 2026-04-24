<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import * as maptilersdk from '@maptiler/sdk';
import {
    ColorRamp,
    PrecipitationLayer,
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
type WeatherLayerKey = 'wind' | 'precipitation' | 'temperature';
type WeatherAnimationPresetKey = 'calm' | 'normal' | 'scan';
type PlanningUnitSystem = 'metric' | 'marine';
type MappableLayer = Parameters<maptilersdk.Map['addLayer']>[0] & {
    id: string;
    animateByFactor?: (factor: number) => void;
    animate?: (timePerSecond: number) => void;
    getAnimationStart?: () => number;
    getAnimationEnd?: () => number;
    getAnimationTime?: () => number;
    getAnimationTimeDate?: () => Date;
    setAnimationTime?: (time: number) => void;
    setOpacity?: (opacity: number) => void;
    pickAt?: (lng: number, lat: number) => unknown;
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
        unitSystem?: PlanningUnitSystem;
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
        unitSystem: 'metric',
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
const segmentSourceId = 'ykj-planning-weather-segments';
const routeGlowLayerId = 'ykj-planning-weather-route-glow';
const routeLineLayerId = 'ykj-planning-weather-route-line';
const routeSegmentLabelLayerId = 'ykj-planning-weather-segment-labels';
const routePointLayerId = 'ykj-planning-weather-points';
const routePointLabelLayerId = 'ykj-planning-weather-point-labels';

const page = usePage();
const activeLayer = ref<WeatherLayerKey>('wind');
const activeAnimationPreset = ref<WeatherAnimationPresetKey>('normal');
const weatherVisibilityPercent = ref(92);
const mapElement = ref<HTMLElement | null>(null);
const mapError = ref<string | null>(null);
const isMapReady = ref(false);
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
const weatherProbe = ref<string | null>(null);

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
    {
        key: 'temperature',
        label: 'Temperature',
        meta: 'air forecast',
        icon: 'T',
    },
    {
        key: 'precipitation',
        label: 'Precipitation',
        meta: 'rain / snow rate',
        icon: 'P',
    },
    { key: 'wind', label: 'Wind', meta: 'speed + flow', icon: 'W' },
];

const weatherAnimationPresets: {
    key: WeatherAnimationPresetKey;
    label: string;
    meta: string;
    factor: number;
    opacityScale: number;
    windDensity: number;
    windParticleSize: number;
    windParticleSpeed: number;
}[] = [
    {
        key: 'calm',
        label: 'Calm',
        meta: 'slow + readable',
        factor: 1800,
        opacityScale: 0.98,
        windDensity: 1.2,
        windParticleSize: 2.6,
        windParticleSpeed: 0.00082,
    },
    {
        key: 'normal',
        label: 'Clear',
        meta: 'larger streamlines',
        factor: 3600,
        opacityScale: 1.06,
        windDensity: 1.65,
        windParticleSize: 3.05,
        windParticleSpeed: 0.0011,
    },
    {
        key: 'scan',
        label: 'Scan',
        meta: 'faster preview',
        factor: 7200,
        opacityScale: 1.14,
        windDensity: 2,
        windParticleSize: 3.35,
        windParticleSpeed: 0.00145,
    },
];

const marineWindRamp = ColorRamp.fromArrayDefinition([
    [0, [224, 242, 254, 42]],
    [1.6, [125, 211, 252, 118]],
    [3.4, [45, 212, 191, 156]],
    [5.5, [74, 222, 128, 186]],
    [8, [250, 204, 21, 214]],
    [10.8, [249, 115, 22, 236]],
    [13.9, [239, 68, 68, 250]],
    [17.2, [147, 51, 234, 252]],
    [25, [88, 28, 135, 255]],
]);

const marineTemperatureRamp = ColorRamp.fromArrayDefinition([
    [-15, [51, 89, 173, 112]],
    [-5, [68, 167, 201, 108]],
    [3, [122, 215, 208, 82]],
    [10, [250, 228, 154, 92]],
    [18, [244, 155, 107, 112]],
    [28, [205, 78, 85, 128]],
]);

const marinePrecipitationRamp = ColorRamp.fromArrayDefinition([
    [0, [255, 255, 255, 0]],
    [0.2, [180, 224, 236, 50]],
    [1, [99, 179, 211, 88]],
    [3, [49, 111, 194, 122]],
    [8, [43, 67, 136, 150]],
    [20, [90, 55, 122, 166]],
]);

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
        unit: 'Beaufort-style colour bands, m/s at 10 m',
        stops: [
            { label: 'F0-F2 light', color: '#99f6e4' },
            { label: 'F3 gentle', color: '#2dd4bf' },
            { label: 'F4 moderate', color: '#4ade80' },
            { label: 'F5 fresh', color: '#facc15' },
            { label: 'F6 strong', color: '#f97316' },
            { label: 'F7+ hard', color: '#ef4444' },
        ],
    },
    precipitation: {
        title: 'Precipitation',
        unit: 'mm/h',
        stops: [
            { label: 'Dry', color: '#f8fafc' },
            { label: 'Light', color: '#63b3d3' },
            { label: 'Rain', color: '#316fc2' },
            { label: 'Heavy', color: '#5a377a' },
        ],
    },
    temperature: {
        title: 'Temperature',
        unit: 'C',
        stops: [
            { label: 'Cold', color: '#3359ad' },
            { label: 'Cool', color: '#44a7c9' },
            { label: 'Mild', color: '#fae49a' },
            { label: 'Warm', color: '#cd4e55' },
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
const animationPreset = computed(
    () =>
        weatherAnimationPresets.find(
            (preset) => preset.key === activeAnimationPreset.value,
        ) ?? weatherAnimationPresets[0],
);
const weatherVisibilityScale = computed(
    () => weatherVisibilityPercent.value / 82,
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

    const segmentFeatures = routePoints.value
        .slice(0, -1)
        .map((point, index) => {
            const nextPoint = routePoints.value[index + 1];
            const distanceKm = haversineKm(point, nextPoint);

            return {
                type: 'Feature' as const,
                properties: {
                    label: formatSegmentDistance(distanceKm),
                },
                geometry: {
                    type: 'LineString' as const,
                    coordinates: [
                        [point.lng, point.lat],
                        [nextPoint.lng, nextPoint.lat],
                    ],
                },
            };
        });

    return {
        line: {
            type: 'FeatureCollection' as const,
            features: lineFeatures,
        },
        segments: {
            type: 'FeatureCollection' as const,
            features: segmentFeatures,
        },
        points: {
            type: 'FeatureCollection' as const,
            features: pointFeatures,
        },
    };
});

const activeLegend = computed(() => legends[activeLayer.value]);
const distanceUnitLabel = computed(() =>
    props.unitSystem === 'marine' ? 'nm' : 'km',
);

const routeSummary = computed(() => {
    if (routePoints.value.length < 2) {
        return 'Click the map to add course points. Click point 1 again to close the loop.';
    }

    const context = liveWeatherEnabled.value
        ? `over ${weatherLayerLabel(activeLayer.value).toLowerCase()}`
        : 'on basemap';

    if (isClosedCourse.value) {
        return `Closed course, ${formatRouteDistance(totalDistanceKm.value)}, ${context}.`;
    }

    return `${routePoints.value.length} points, ${formatRouteDistance(totalDistanceKm.value)}, ${context}.`;
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

function formatSegmentDistance(distanceKm: number): string {
    if (props.unitSystem === 'marine') {
        return `${(distanceKm / 1.852).toFixed(1)} nm`;
    }

    if (distanceKm < 1) {
        return `${Math.round(distanceKm * 1000)} m`;
    }

    return `${distanceKm.toFixed(1)} km`;
}

function formatRouteDistance(distanceKm: number): string {
    const converted =
        props.unitSystem === 'marine' ? distanceKm / 1.852 : distanceKm;

    return `${converted.toFixed(1)} ${distanceUnitLabel.value}`;
}

function weatherLayerLabel(layer: WeatherLayerKey): string {
    return (
        weatherLayerOptions.find((option) => option.key === layer)?.label ??
        'Weather'
    );
}

function baseWeatherOpacity(layer: WeatherLayerKey): number {
    if (layer === 'wind') {
        return 0.66;
    }

    if (layer === 'temperature') {
        return 0.24;
    }

    return 0.3;
}

function weatherOpacity(layer: WeatherLayerKey): number {
    return Math.min(
        0.94,
        baseWeatherOpacity(layer) *
            animationPreset.value.opacityScale *
            weatherVisibilityScale.value,
    );
}

function applyWeatherOpacity() {
    weatherLayer?.setOpacity?.(weatherOpacity(activeLayer.value));
}

function buildWeatherLayer(layer: WeatherLayerKey): MappableLayer {
    if (layer === 'precipitation') {
        return new PrecipitationLayer({
            id: 'ykj-weather-precipitation',
            colorramp: marinePrecipitationRamp,
            opacity: weatherOpacity(layer),
        }) as unknown as MappableLayer;
    }

    if (layer === 'temperature') {
        return new TemperatureLayer({
            id: 'ykj-weather-temperature',
            colorramp: marineTemperatureRamp,
            opacity: weatherOpacity(layer),
        }) as unknown as MappableLayer;
    }

    return new WindLayer({
        id: 'ykj-weather-wind',
        color: [15, 23, 42, 232],
        fastColor: [239, 68, 68, 255],
        fastIsLarger: true,
        fastSpeed: 1.04,
        colorramp: marineWindRamp,
        opacity: weatherOpacity(layer),
        density: animationPreset.value.windDensity,
        pixelRatio: 2,
        size: animationPreset.value.windParticleSize,
        speed: animationPreset.value.windParticleSpeed,
        fadeFactor: 0.058,
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
        weatherProbe.value = null;

        return;
    }

    detachWeatherEvents();

    if (map.getLayer(weatherLayer.id)) {
        map.removeLayer(weatherLayer.id);
    }

    weatherLayer = null;
    weatherProbe.value = null;
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
            layer.animateByFactor?.(animationPreset.value.factor);
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

    weatherLayer.animateByFactor?.(animationPreset.value.factor);
    isPlaying.value = true;
}

function selectAnimationPreset(preset: WeatherAnimationPresetKey) {
    activeAnimationPreset.value = preset;
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

function numericWeatherValue(
    picked: Record<string, unknown>,
    key: string,
): number | null {
    const value = picked[key];

    return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function formatWeatherProbe(picked: unknown): string | null {
    if (!picked || typeof picked !== 'object') {
        return null;
    }

    const values = picked as Record<string, unknown>;

    if (activeLayer.value === 'wind') {
        const speedMetersPerSecond = numericWeatherValue(
            values,
            'speedMetersPerSecond',
        );
        const speedKnots = numericWeatherValue(values, 'speedKnots');
        const compassDirection =
            typeof values.compassDirection === 'string'
                ? values.compassDirection
                : null;

        if (speedMetersPerSecond === null) {
            return null;
        }

        const displayWind =
            props.unitSystem === 'marine'
                ? `Wind ${(speedMetersPerSecond * 1.943844).toFixed(1)} kt`
                : `Wind ${(speedMetersPerSecond * 3.6).toFixed(0)} km/h`;

        return [
            displayWind,
            props.unitSystem === 'marine' || speedKnots === null
                ? null
                : `${speedKnots.toFixed(1)} kt marine`,
            compassDirection,
        ]
            .filter(Boolean)
            .join(' · ');
    }

    const value = numericWeatherValue(values, 'value');

    if (value === null) {
        return null;
    }

    if (activeLayer.value === 'temperature') {
        return `Air ${value.toFixed(1)} C`;
    }

    if (activeLayer.value === 'precipitation') {
        return `Precip ${value.toFixed(1)} mm/h`;
    }

    return null;
}

function updateWeatherProbe(event: LayerMouseEvent) {
    if (!liveWeatherEnabled.value || !weatherLayer?.pickAt) {
        weatherProbe.value = null;

        return;
    }

    weatherProbe.value = formatWeatherProbe(
        weatherLayer.pickAt(event.lngLat.lng, event.lngLat.lat),
    );
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

    if (!map.getSource(segmentSourceId)) {
        map.addSource(segmentSourceId, {
            type: 'geojson',
            data: routeGeoJson.value.segments,
        });
    } else {
        updateSourceData(segmentSourceId, routeGeoJson.value.segments);
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

    if (!map.getLayer(routeSegmentLabelLayerId)) {
        map.addLayer({
            id: routeSegmentLabelLayerId,
            type: 'symbol',
            source: segmentSourceId,
            layout: {
                'symbol-placement': 'line-center',
                'text-allow-overlap': true,
                'text-field': ['get', 'label'],
                'text-font': ['Open Sans Bold'],
                'text-keep-upright': true,
                'text-offset': [0, -0.75],
                'text-size': 11,
            },
            paint: {
                'text-color': '#1d2438',
                'text-halo-blur': 0.5,
                'text-halo-color': '#ffffff',
                'text-halo-width': 2,
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
        updateWeatherProbe(event);

        if (draggedPointIndex !== null) {
            updateCoursePoint(
                draggedPointIndex,
                event.lngLat.lat,
                event.lngLat.lng,
            );
        }
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
        if (!force) {
            return;
        }

        map.easeTo({
            center: [props.defaultView.lng, props.defaultView.lat],
            zoom: props.defaultView.zoom,
            duration: force ? 300 : 0,
        });

        return;
    }

    if (points.length === 1) {
        if (!force) {
            return;
        }

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
            style: maptilersdk.MapStyle.TOPO.DEFAULT,
            center: [props.defaultView.lng, props.defaultView.lat],
            zoom: props.defaultView.zoom,
            pitch: 0,
            doubleClickZoom: false,
            fullscreenControl: false,
            geolocateControl: false,
            navigationControl: false,
            scaleControl: false,
            terrainControl: false,
            attributionControl: {
                compact: 'auto',
            },
        });

        map.on('click', handleMapClick);

        map.on('load', () => {
            isMapReady.value = true;
            applyWeatherLayer();
            upsertRouteOverlay();

            if (routePoints.value.length > 1) {
                fitRouteOrDefault();
            }
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

watch(activeAnimationPreset, () => {
    applyWeatherLayer();
});

watch(weatherVisibilityPercent, () => {
    applyWeatherOpacity();
});

watch(liveWeatherEnabled, () => {
    if (!liveWeatherEnabled.value) {
        showLegend.value = false;
        weatherProbe.value = null;
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
        class="planning-weather-map overflow-hidden rounded-[28px] border border-[color:var(--journal-line)] bg-[#dcebf2] text-white shadow-[0_24px_70px_rgba(15,23,42,0.18)]"
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
                <div class="relative">
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
                        <div
                            v-if="liveWeatherEnabled"
                            class="grid grid-cols-3 gap-1 rounded-[8px] bg-white/62 p-1 shadow-[0_8px_18px_rgba(0,0,0,0.1)]"
                        >
                            <button
                                v-for="preset in weatherAnimationPresets"
                                :key="preset.key"
                                type="button"
                                class="rounded-[6px] px-1.5 py-1.5 text-center text-[0.62rem] leading-none font-black transition"
                                :class="
                                    preset.key === activeAnimationPreset
                                        ? 'bg-[#111827] text-white'
                                        : 'bg-white/64 text-[#29304f] hover:bg-white'
                                "
                                :title="preset.meta"
                                @click="selectAnimationPreset(preset.key)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                        <label
                            v-if="liveWeatherEnabled"
                            class="rounded-[8px] bg-white/72 px-2.5 py-2 text-[#29304f] shadow-[0_8px_18px_rgba(0,0,0,0.1)]"
                        >
                            <span
                                class="flex items-center justify-between gap-2 text-[0.58rem] font-black tracking-[0.12em] uppercase opacity-68"
                            >
                                <span>Visibility</span>
                                <span>{{ weatherVisibilityPercent }}%</span>
                            </span>
                            <input
                                v-model.number="weatherVisibilityPercent"
                                class="mt-1.5 h-1 w-full accent-[#ef4444]"
                                type="range"
                                min="25"
                                max="100"
                                step="5"
                            />
                        </label>
                        <p
                            v-if="liveWeatherEnabled"
                            class="rounded-[8px] bg-white/72 px-2.5 py-2 text-[0.66rem] leading-4 font-semibold text-[#29304f]/78 shadow-[0_8px_18px_rgba(0,0,0,0.1)]"
                        >
                            {{ animationPreset.label }} animation. Wind uses
                            Beaufort-style colour bands; exact marine planning
                            values still come from the route forecast board.
                        </p>
                        <p
                            v-if="!liveWeatherEnabled"
                            class="rounded-[8px] bg-white/72 px-2.5 py-2 text-[0.66rem] leading-4 font-semibold text-[#29304f]/78 shadow-[0_8px_18px_rgba(0,0,0,0.1)]"
                        >
                            Basemap mode for cleaner course drawing.
                        </p>
                    </div>

                    <form
                        class="pointer-events-auto absolute top-0 left-1/2 hidden w-[min(22rem,42vw)] -translate-x-1/2 sm:block"
                        @submit.prevent="searchPlace"
                    >
                        <input
                            v-model="searchQuery"
                            type="search"
                            class="h-8 w-full rounded-[6px] border border-white/20 bg-white/90 px-3 pr-14 text-xs font-semibold text-[#1d2438] shadow-[0_8px_20px_rgba(0,0,0,0.14)] outline-none"
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
                        class="pointer-events-auto absolute top-[9.5rem] right-0 hidden flex-col gap-1.5 rounded-full border border-white/32 bg-white/46 p-1.5 shadow-[0_8px_18px_rgba(0,0,0,0.12)] backdrop-blur sm:flex"
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
                        <div
                            v-if="liveWeatherEnabled"
                            class="flex gap-2 overflow-x-auto"
                        >
                            <button
                                v-for="preset in weatherAnimationPresets"
                                :key="`mobile-preset-${preset.key}`"
                                type="button"
                                class="shrink-0 rounded-[6px] px-2.5 py-1.5 text-[0.68rem] font-bold"
                                :class="
                                    preset.key === activeAnimationPreset
                                        ? 'bg-[#111827] text-white'
                                        : 'bg-white/82 text-[#29304f]'
                                "
                                @click="selectAnimationPreset(preset.key)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                        <label
                            v-if="liveWeatherEnabled"
                            class="rounded-[6px] bg-white/68 px-2.5 py-2 text-[#29304f]"
                        >
                            <span
                                class="flex items-center justify-between text-[0.62rem] font-black tracking-[0.1em] uppercase opacity-70"
                            >
                                <span>Visibility</span>
                                <span>{{ weatherVisibilityPercent }}%</span>
                            </span>
                            <input
                                v-model.number="weatherVisibilityPercent"
                                class="mt-1.5 h-1 w-full accent-[#ef4444]"
                                type="range"
                                min="25"
                                max="100"
                                step="5"
                            />
                        </label>
                        <p
                            v-if="liveWeatherEnabled"
                            class="text-[0.66rem] leading-4 font-semibold text-[#29304f]/76"
                        >
                            {{ animationPreset.label }} animation. Wind colour
                            bands show light to hard wind; use the forecast
                            board for tide/current/swell.
                        </p>
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
                                <span
                                    v-if="weatherProbe"
                                    class="rounded-[6px] bg-[#111827]/86 px-2 py-0.5 font-mono text-white"
                                    >{{ weatherProbe }}</span
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
                        >{{ formatRouteDistance(totalDistanceKm) }}</strong
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
