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
    | 'radar'
    | 'precipitation'
    | 'pressure'
    | 'temperature';
type MappableLayer = Parameters<maptilersdk.Map['addLayer']>[0] & {
    id: string;
    animateByFactor?: (factor: number) => void;
    animate?: (timePerSecond: number) => void;
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
        heightClass: 'h-[420px] lg:h-[520px]',
        sampleTimeLabel: 'Start',
    },
);

const page = usePage();
const activeLayer = ref<WeatherLayerKey>('wind');
const mapElement = ref<HTMLElement | null>(null);
const mapError = ref<string | null>(null);
const isMapReady = ref(false);
const hasFitInitialRoute = ref(false);

let map: maptilersdk.Map | null = null;
let weatherLayer: MappableLayer | null = null;

const weatherLayerOptions: {
    key: WeatherLayerKey;
    label: string;
    meta: string;
}[] = [
    { key: 'wind', label: 'Wind', meta: 'animated flow' },
    { key: 'precipitation', label: 'Rain', meta: 'mm/h' },
    { key: 'radar', label: 'Radar', meta: 'cloud + rain' },
    { key: 'pressure', label: 'Pressure', meta: 'systems' },
    { key: 'temperature', label: 'Temp', meta: 'air mass' },
];

const integrations = computed(
    () => (page.props.integrations as SharedIntegrations | undefined)?.maps,
);

const maptilerKey = computed(() => integrations.value?.maptilerKey ?? null);
const weatherEnabled = computed(
    () => integrations.value?.weatherEnabled !== false,
);
const canShowWeatherMap = computed(
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

const routeGeoJson = computed(() => {
    const pointFeatures = routePoints.value.map((point, index) => ({
        type: 'Feature' as const,
        properties: {
            label:
                index === 0
                    ? 'L'
                    : index === routePoints.value.length - 1
                      ? 'F'
                      : String(index),
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

const routeSummary = computed(() => {
    if (routePoints.value.length < 2) {
        return 'Draw a course above, then this map mirrors it over live weather.';
    }

    return `${routePoints.value.length} route points mirrored over ${weatherLayerLabel(activeLayer.value).toLowerCase()}.`;
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
            opacity: 0.78,
        }) as unknown as MappableLayer;
    }

    if (layer === 'radar') {
        return new RadarLayer({
            id: 'ykj-weather-radar',
            opacity: 0.74,
        }) as unknown as MappableLayer;
    }

    if (layer === 'pressure') {
        return new PressureLayer({
            id: 'ykj-weather-pressure',
            opacity: 0.58,
        }) as unknown as MappableLayer;
    }

    if (layer === 'temperature') {
        return new TemperatureLayer({
            id: 'ykj-weather-temperature',
            opacity: 0.62,
        }) as unknown as MappableLayer;
    }

    return new WindLayer({
        id: 'ykj-weather-wind',
        color: [255, 255, 255, 215],
        fastColor: [103, 114, 255, 235],
        fastIsLarger: true,
        opacity: 0.92,
        density: 1.55,
        speed: 0.0014,
    }) as unknown as MappableLayer;
}

function firstSymbolLayerId(): string | undefined {
    return map?.getStyle().layers?.find((layer) => layer.type === 'symbol')?.id;
}

function removeWeatherLayer() {
    if (!map || !weatherLayer) {
        weatherLayer = null;

        return;
    }

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

    try {
        weatherLayer = buildWeatherLayer(activeLayer.value);
        map.addLayer(weatherLayer, firstSymbolLayerId());
        weatherLayer.animateByFactor?.(3200);
    } catch (error) {
        mapError.value =
            error instanceof Error
                ? error.message
                : 'MapTiler weather layer could not be added.';
    }
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
            padding: 74,
            maxZoom: 12,
            duration: force ? 450 : 0,
        },
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

    const lineSourceId = 'ykj-planning-weather-route';
    const pointSourceId = 'ykj-planning-weather-points';

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

    if (!map.getLayer('ykj-planning-weather-route-glow')) {
        map.addLayer({
            id: 'ykj-planning-weather-route-glow',
            type: 'line',
            source: lineSourceId,
            paint: {
                'line-color': '#020617',
                'line-opacity': 0.56,
                'line-width': 9,
            },
        });
    }

    if (!map.getLayer('ykj-planning-weather-route-line')) {
        map.addLayer({
            id: 'ykj-planning-weather-route-line',
            type: 'line',
            source: lineSourceId,
            paint: {
                'line-color': '#f97356',
                'line-opacity': 0.96,
                'line-width': 3.5,
            },
        });
    }

    if (!map.getLayer('ykj-planning-weather-points')) {
        map.addLayer({
            id: 'ykj-planning-weather-points',
            type: 'circle',
            source: pointSourceId,
            paint: {
                'circle-color': '#ffffff',
                'circle-radius': 13,
                'circle-stroke-color': '#d71920',
                'circle-stroke-width': 4,
            },
        });
    }

    if (!map.getLayer('ykj-planning-weather-point-labels')) {
        map.addLayer({
            id: 'ykj-planning-weather-point-labels',
            type: 'symbol',
            source: pointSourceId,
            layout: {
                'text-field': ['get', 'label'],
                'text-font': ['Open Sans Bold'],
                'text-size': 11,
            },
            paint: {
                'text-color': '#b30f18',
            },
        });
    }

    if (!hasFitInitialRoute.value && routePoints.value.length) {
        hasFitInitialRoute.value = true;
        fitRouteOrDefault();
    }
}

async function initializeMap() {
    await nextTick();

    if (!mapElement.value || map || !canShowWeatherMap.value) {
        return;
    }

    mapError.value = null;
    maptilersdk.config.apiKey = maptilerKey.value ?? '';

    try {
        map = new maptilersdk.Map({
            container: mapElement.value,
            style: maptilersdk.MapStyle.DATAVIZ.DARK,
            center: [props.defaultView.lng, props.defaultView.lat],
            zoom: props.defaultView.zoom,
            pitch: 0,
            attributionControl: {
                compact: 'auto',
            },
        });

        map.addControl(
            new maptilersdk.NavigationControl({
                visualizePitch: false,
            }),
            'top-right',
        );

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

watch(activeLayer, () => {
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

watch(canShowWeatherMap, () => {
    if (canShowWeatherMap.value) {
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
        class="overflow-hidden rounded-[24px] border border-[color:var(--journal-line)] bg-[#09111f] text-white shadow-[0_24px_70px_rgba(15,23,42,0.18)]"
    >
        <div
            class="flex flex-col gap-4 border-b border-white/10 bg-gradient-to-r from-[#101a31] via-[#132340] to-[#0d2d3b] p-4 md:flex-row md:items-start md:justify-between"
        >
            <div class="space-y-2">
                <p class="journal-kicker text-white/58">Weather animation</p>
                <h4 class="text-[1.25rem] leading-none text-white sm:text-2xl">
                    MapTiler live planning layer
                </h4>
                <p class="max-w-3xl text-sm leading-6 text-white/68">
                    {{ routeSummary }} Use this as a visual weather read, then
                    confirm the numeric tide, current, swell, and Beaufort board
                    below.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="rounded-full border border-white/14 bg-white/10 px-3 py-1.5 font-mono text-xs text-white/78"
                >
                    {{ sampleTimeLabel }}
                </span>
                <button
                    type="button"
                    class="rounded-full border border-white/18 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/18"
                    @click="fitRouteOrDefault(true)"
                >
                    Fit course
                </button>
            </div>
        </div>

        <div class="flex gap-2 overflow-x-auto px-4 py-3">
            <button
                v-for="option in weatherLayerOptions"
                :key="option.key"
                type="button"
                class="shrink-0 rounded-full border px-3 py-2 text-left transition"
                :class="
                    activeLayer === option.key
                        ? 'border-white bg-white text-[#151b31]'
                        : 'border-white/12 bg-white/8 text-white/76 hover:bg-white/14'
                "
                @click="activeLayer = option.key"
            >
                <span class="block text-xs font-semibold">{{
                    option.label
                }}</span>
                <span
                    class="block text-[0.66rem] tracking-[0.14em] uppercase"
                    >{{ option.meta }}</span
                >
            </button>
        </div>

        <div class="relative border-t border-white/10">
            <div
                v-if="canShowWeatherMap"
                ref="mapElement"
                :class="heightClass"
                class="bg-[#09111f]"
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
                        MapTiler weather is ready
                    </h5>
                    <p class="mt-3 text-sm leading-6 text-white/70">
                        Add <span class="font-mono">MAPTILER_API_KEY</span> and
                        keep
                        <span class="font-mono"
                            >MAPTILER_WEATHER_ENABLED=true</span
                        >
                        in Laravel Cloud to turn on the animated weather map.
                    </p>
                </div>
            </div>

            <div
                v-if="mapError"
                class="absolute right-3 bottom-3 left-3 rounded-[18px] border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 shadow-xl"
            >
                {{ mapError }}
            </div>
        </div>
    </section>
</template>
