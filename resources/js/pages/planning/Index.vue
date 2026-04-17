<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import PlanningRouteMap from '@/components/maps/PlanningRouteMap.vue';
import { dashboard } from '@/routes';

interface ProfileSummary {
    name: string;
    slug: string;
    homeWater: string;
    timezone: string;
    defaultMapView: {
        lat: number;
        lng: number;
        zoom: number;
    };
}

interface FormDefaults {
    title: string;
    plan_date: string;
    start_time_local: string;
    speed_knots: string;
    launch_name: string;
    launch_lat: string;
    launch_lng: string;
    landing_name: string;
    landing_lat: string;
    landing_lng: string;
    route_waypoints: string;
    forecast_points: string;
    notes: string;
}

interface PlannedSessionSummary {
    id: number;
    status: string;
    title: string;
    planDate: string | null;
    startTimeLocal: string | null;
    timezone: string;
    launchName: string | null;
    launchLat: string;
    launchLng: string;
    landingName: string | null;
    landingLat: string;
    landingLng: string;
    speedKnots: string;
    distanceKm: number;
    estimatedDurationMinutes: number | null;
    routeWaypointsJson: string;
    forecastByPoint: Record<string, ForecastResult>;
    notes: string;
    updatedAt: string | null;
}

interface FlashPageProps {
    flash?: {
        success?: string;
    };
}

interface ForecastFields {
    wind_avg_ms?: number | null;
    wind_gust_ms?: number | null;
    wind_direction_deg?: number | null;
    wind_beaufort?: number | null;
    tide_state?: string | null;
    current_knots?: number | null;
    current_direction_deg?: number | null;
    wave_height_m?: number | null;
    swell_height_m?: number | null;
    swell_period_s?: number | null;
    air_temp_c?: number | null;
    sea_temp_c?: number | null;
    forecast_severity?: string | null;
    weather_summary?: string | null;
}

interface ForecastResult {
    status: 'idle' | 'loading' | 'filled' | 'skipped' | 'failed';
    message?: string;
    httpStatus?: number;
    fields?: ForecastFields;
}

interface ForecastPayload {
    status?: ForecastResult['status'];
    message?: string;
    reason?: string;
    httpStatus?: number;
    fields?: ForecastFields;
}

interface RoutePoint {
    key: string;
    label: string;
    shortLabel: string;
    lat: number;
    lng: number;
}

interface RouteLeg {
    key: string;
    from: RoutePoint;
    to: RoutePoint;
    distanceKm: number;
    bearingDeg: number;
}

const AREA_FORECAST_KEY = 'area';

const props = defineProps<{
    profile: ProfileSummary;
    weatherAutofillAvailable: boolean;
    formDefaults: FormDefaults;
    plannedSession: PlannedSessionSummary | null;
}>();

const page = usePage();
const successMessage = computed(
    () => (page.props as FlashPageProps).flash?.success,
);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sea Kayak Logbook',
                href: dashboard(),
            },
            {
                title: 'Planning',
                href: '/planning',
            },
        ],
    },
});

const initialForecastByPoint =
    props.plannedSession?.forecastByPoint ??
    parseForecastMap(props.formDefaults.forecast_points);

const title = ref(props.formDefaults.title);
const planDate = ref(props.formDefaults.plan_date);
const startTimeLocal = ref(props.formDefaults.start_time_local);
const speedKnots = ref(props.formDefaults.speed_knots);
const launchName = ref(props.formDefaults.launch_name);
const launchLat = ref(props.formDefaults.launch_lat);
const launchLng = ref(props.formDefaults.launch_lng);
const landingName = ref(props.formDefaults.landing_name);
const landingLat = ref(props.formDefaults.landing_lat);
const landingLng = ref(props.formDefaults.landing_lng);
const routeWaypointsJson = ref(props.formDefaults.route_waypoints);
const notes = ref(props.formDefaults.notes);
const forecastStatus = ref<
    'idle' | 'loading' | 'filled' | 'warning' | 'error' | 'stale'
>(Object.keys(initialForecastByPoint).length ? 'filled' : 'idle');
const forecastMessage = ref<string | null>(
    Object.keys(initialForecastByPoint).length
        ? 'Saved area forecast loaded. Refresh conditions if the plan changes.'
        : null,
);
const forecastByPoint = ref<Record<string, ForecastResult>>(
    initialForecastByPoint,
);
const hasFetchedForecast = ref(Object.keys(initialForecastByPoint).length > 0);

let forecastAbortController: AbortController | null = null;

const saveForm = useForm({
    title: '',
    plan_date: '',
    start_time_local: '',
    speed_knots: '',
    launch_name: '',
    launch_lat: '',
    launch_lng: '',
    landing_name: '',
    landing_lat: '',
    landing_lng: '',
    route_waypoints: '',
    forecast_points: '',
    notes: '',
});

const speedKmh = computed(
    () => Math.max(parseFloat(speedKnots.value) || 0, 0) * 1.852,
);

const isEditing = computed(() => props.plannedSession !== null);
const plannerTitle = computed(() =>
    isEditing.value ? 'Edit planned session' : 'Plan a day out',
);
const saveButtonLabel = computed(() => {
    if (saveForm.processing) {
        return isEditing.value ? 'Updating plan...' : 'Saving plan...';
    }

    return isEditing.value ? 'Update plan' : 'Save plan';
});

const parsedRouteWaypoints = computed(() => {
    if (!routeWaypointsJson.value) {
        return [];
    }

    try {
        const parsed = JSON.parse(routeWaypointsJson.value);

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

const routePoints = computed<RoutePoint[]>(() => {
    const points: RoutePoint[] = [];
    const launch = coordinatePair(launchLat.value, launchLng.value);

    if (launch) {
        points.push({
            key: 'launch',
            label: launchName.value.trim() || 'Launch',
            shortLabel: 'L',
            ...launch,
        });
    }

    parsedRouteWaypoints.value.forEach((point, index) => {
        points.push({
            key: `course-${index + 1}`,
            label: `Course ${index + 1}`,
            shortLabel: String(index + 1),
            lat: point.lat,
            lng: point.lng,
        });
    });

    const landing = coordinatePair(landingLat.value, landingLng.value);

    if (landing) {
        points.push({
            key: 'landing',
            label: landingName.value.trim() || 'Landing',
            shortLabel: 'F',
            ...landing,
        });
    }

    return points;
});

const routeLegs = computed<RouteLeg[]>(() =>
    routePoints.value.slice(0, -1).map((point, index) => {
        const nextPoint = routePoints.value[index + 1];

        return {
            key: `${point.key}-${nextPoint.key}`,
            from: point,
            to: nextPoint,
            distanceKm: haversineKm(point, nextPoint),
            bearingDeg: bearingDeg(point, nextPoint),
        };
    }),
);

const totalDistanceKm = computed(() =>
    routeLegs.value.reduce((sum, leg) => sum + leg.distanceKm, 0),
);

const estimatedMinutes = computed(() => {
    if (speedKmh.value <= 0 || totalDistanceKm.value <= 0) {
        return null;
    }

    return Math.round((totalDistanceKm.value / speedKmh.value) * 60);
});

const forecastAreaPoint = computed<RoutePoint | null>(() => {
    if (!routePoints.value.length) {
        return null;
    }

    const lats = routePoints.value.map((point) => point.lat);
    const lngs = routePoints.value.map((point) => point.lng);

    return {
        key: AREA_FORECAST_KEY,
        label: 'Route area',
        shortLabel: 'A',
        lat: (Math.min(...lats) + Math.max(...lats)) / 2,
        lng: (Math.min(...lngs) + Math.max(...lngs)) / 2,
    };
});

const forecastAreaOffsetMinutes = computed(() =>
    estimatedMinutes.value ? Math.round(estimatedMinutes.value / 2) : 0,
);

const areaForecast = computed<ForecastResult>(() => {
    const savedArea = forecastByPoint.value[AREA_FORECAST_KEY];

    if (savedArea) {
        return savedArea;
    }

    return (
        Object.values(forecastByPoint.value).find((forecast) =>
            hasForecastFields(forecast),
        ) ??
        Object.values(forecastByPoint.value)[0] ?? { status: 'idle' }
    );
});

const estimatedStormglassRequests = computed(() =>
    forecastAreaPoint.value ? 2 : 0,
);

const forecastRequestEstimate = computed(() => {
    if (!forecastAreaPoint.value) {
        return 'Add route points to estimate Stormglass usage.';
    }

    return `About ${estimatedStormglassRequests.value} Stormglass requests: 1 area weather + 1 tide.`;
});

const areaSampleTimeLabel = computed(() => {
    const offset = forecastAreaOffsetMinutes.value;

    return offset === 0
        ? startTimeLocal.value || 'Start'
        : `${startTimeLocal.value || 'Start'} + ${formatMinutes(offset)}`;
});

const forecastProgressLabel = computed(() => {
    if (forecastStatus.value === 'loading') {
        return 'Loading area forecast';
    }

    if (forecastStatus.value === 'filled') {
        return 'Area checked';
    }

    if (forecastStatus.value === 'stale') {
        return 'Needs refresh';
    }

    if (!props.weatherAutofillAvailable) {
        return 'Stormglass not configured';
    }

    return 'Ready';
});

function parseForecastMap(value: string): Record<string, ForecastResult> {
    if (!value) {
        return {};
    }

    try {
        const parsed = JSON.parse(value);

        if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') {
            return {};
        }

        return parsed as Record<string, ForecastResult>;
    } catch {
        return {};
    }
}

function coordinatePair(lat: string, lng: string) {
    const parsedLat = parseFloat(lat);
    const parsedLng = parseFloat(lng);

    if (!Number.isFinite(parsedLat) || !Number.isFinite(parsedLng)) {
        return null;
    }

    return {
        lat: parsedLat,
        lng: parsedLng,
    };
}

function haversineKm(left: RoutePoint, right: RoutePoint): number {
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

function bearingDeg(left: RoutePoint, right: RoutePoint): number {
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

function formatMinutes(minutes: number | null): string {
    if (minutes === null) {
        return '—';
    }

    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    if (hours <= 0) {
        return `${remainder} min`;
    }

    return `${hours} h ${remainder} min`;
}

function fieldLabel(value?: number | string | null, suffix = ''): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'number') {
        return `${Number.isInteger(value) ? value.toFixed(0) : value.toFixed(1)}${suffix}`;
    }

    return `${value}${suffix}`;
}

function formatCoordinate(value: number): string {
    return value.toFixed(5);
}

function hasForecastFields(forecast: ForecastResult): boolean {
    if (!forecast.fields) {
        return false;
    }

    return Object.values(forecast.fields).some(
        (value) => value !== null && value !== undefined && value !== '',
    );
}

function forecastCellMessage(forecast: ForecastResult): string {
    if (forecast.status === 'loading') {
        return 'Loading...';
    }

    if (forecastStatus.value === 'stale') {
        return 'Refresh needed.';
    }

    return forecast.message || 'No forecast data.';
}

function tideLabel(forecast: ForecastResult): string {
    if (!hasForecastFields(forecast)) {
        return '—';
    }

    return forecast.fields?.tide_state ?? 'No tide';
}

function defaultPlanTitle(): string {
    return title.value.trim() || `Planned paddle ${planDate.value}`;
}

function syncSaveForm() {
    saveForm.title = defaultPlanTitle();
    saveForm.plan_date = planDate.value;
    saveForm.start_time_local = startTimeLocal.value;
    saveForm.speed_knots = speedKnots.value;
    saveForm.launch_name = launchName.value;
    saveForm.launch_lat = launchLat.value;
    saveForm.launch_lng = launchLng.value;
    saveForm.landing_name = landingName.value;
    saveForm.landing_lat = landingLat.value;
    saveForm.landing_lng = landingLng.value;
    saveForm.route_waypoints = routeWaypointsJson.value;
    saveForm.forecast_points =
        forecastStatus.value !== 'stale' &&
        Object.keys(forecastByPoint.value).length
            ? JSON.stringify(forecastByPoint.value)
            : '';
    saveForm.notes = notes.value;
}

function savePlan() {
    syncSaveForm();

    if (props.plannedSession) {
        saveForm.put(`/planning/${props.plannedSession.id}`, {
            preserveScroll: true,
        });

        return;
    }

    saveForm.post('/planning', {
        preserveScroll: false,
    });
}

function markForecastStale() {
    if (
        !hasFetchedForecast.value &&
        !Object.keys(forecastByPoint.value).length
    ) {
        return;
    }

    if (forecastStatus.value === 'loading') {
        forecastAbortController?.abort();
    }

    forecastByPoint.value = {};
    forecastStatus.value = 'stale';
    forecastMessage.value = `Route, timing, or speed changed. Refresh the area forecast once the line is stable. ${forecastRequestEstimate.value} Cached repeats may use fewer calls.`;
}

async function refreshForecasts() {
    if (!props.weatherAutofillAvailable) {
        forecastStatus.value = 'warning';
        forecastMessage.value = 'Stormglass is not configured yet.';

        return;
    }

    if (!forecastAreaPoint.value) {
        forecastStatus.value = 'warning';
        forecastMessage.value =
            'Place at least one point before checking the route area.';

        return;
    }

    forecastAbortController?.abort();
    forecastAbortController = new AbortController();
    forecastStatus.value = 'loading';
    forecastMessage.value = `Checking wind, tide, current, swell, and temperatures for the route area. ${forecastRequestEstimate.value} Cached repeats may use fewer calls.`;
    hasFetchedForecast.value = true;

    const point = forecastAreaPoint.value;
    const nextForecasts: Record<string, ForecastResult> = {
        [AREA_FORECAST_KEY]: { status: 'loading' },
    };

    forecastByPoint.value = { ...nextForecasts };

    const params = new URLSearchParams({
        plan_date: planDate.value,
        start_time_local: startTimeLocal.value,
        lat: point.lat.toFixed(6),
        lng: point.lng.toFixed(6),
        label: point.label,
        offset_minutes: String(forecastAreaOffsetMinutes.value),
    });

    try {
        const response = await fetch(`/planning/weather-preview?${params}`, {
            headers: {
                Accept: 'application/json',
            },
            signal: forecastAbortController.signal,
        });

        const payload = (await response
            .json()
            .catch(() => ({}))) as ForecastPayload;

        if (!response.ok) {
            nextForecasts[AREA_FORECAST_KEY] = {
                status: 'failed',
                message: payload.message || payload.reason,
                httpStatus: response.status,
            };

            forecastByPoint.value = nextForecasts;
            forecastStatus.value = 'warning';
            forecastMessage.value =
                payload.message ||
                payload.reason ||
                `Area forecast request failed (${response.status}).`;

            return;
        }

        nextForecasts[AREA_FORECAST_KEY] = {
            status: payload.status ?? 'failed',
            message: payload.message || payload.reason,
            httpStatus: payload.httpStatus,
            fields: payload.fields ?? {},
        };
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        nextForecasts[AREA_FORECAST_KEY] = {
            status: 'failed',
            message: 'Area forecast request failed.',
        };
    }

    forecastByPoint.value = nextForecasts;
    const forecast = nextForecasts[AREA_FORECAST_KEY];
    const hasFields = hasForecastFields(forecast);

    forecastStatus.value = hasFields ? 'filled' : 'warning';
    forecastMessage.value = hasFields
        ? `Area conditions refreshed for the route centre at ${areaSampleTimeLabel.value}. ${forecastRequestEstimate.value} Treat this as planning guidance, not a go/no-go forecast.`
        : `No usable area forecast data returned yet. ${forecastRequestEstimate.value}${forecast.message ? ` ${forecast.message}` : ''}`;
}

watch(
    () => [
        planDate.value,
        startTimeLocal.value,
        speedKnots.value,
        launchLat.value,
        launchLng.value,
        landingLat.value,
        landingLng.value,
        routeWaypointsJson.value,
    ],
    () => {
        markForecastStale();
    },
);
</script>

<template>
    <Head :title="plannerTitle" />

    <div class="flex flex-col gap-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div
                class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
            >
                <div class="space-y-3">
                    <p class="journal-kicker">Planning</p>
                    <div class="space-y-2">
                        <h2
                            class="text-[clamp(1.9rem,3vw,2.7rem)] leading-[0.96]"
                        >
                            {{ plannerTitle }}
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            Sketch launch, landing, and course points before the
                            paddle. The planner estimates distance and can pull
                            one area forecast for wind, tide, current, swell,
                            and temperature.
                        </p>
                    </div>
                    <div class="max-w-2xl">
                        <label class="journal-field-label" for="plan_title"
                            >Plan name</label
                        >
                        <input
                            id="plan_title"
                            v-model="title"
                            type="text"
                            class="journal-input bg-white/82 text-lg font-semibold"
                            placeholder="Name this plan, e.g. Saturday island loop"
                        />
                        <p
                            v-if="saveForm.errors.title"
                            class="mt-2 text-xs font-semibold text-red-500"
                        >
                            {{ saveForm.errors.title }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:w-[520px]">
                    <div class="journal-stat-pill">
                        <span class="journal-stat-pill__label">Distance</span>
                        <span class="journal-stat-pill__value"
                            >{{ totalDistanceKm.toFixed(1) }} km</span
                        >
                    </div>
                    <div class="journal-stat-pill">
                        <span class="journal-stat-pill__label">ETA</span>
                        <span class="journal-stat-pill__value">{{
                            formatMinutes(estimatedMinutes)
                        }}</span>
                    </div>
                    <div class="journal-stat-pill">
                        <span class="journal-stat-pill__label">Points</span>
                        <span class="journal-stat-pill__value">{{
                            routePoints.length
                        }}</span>
                    </div>
                    <div class="journal-stat-pill">
                        <span class="journal-stat-pill__label">Forecast</span>
                        <span class="journal-stat-pill__value">{{
                            forecastProgressLabel
                        }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="successMessage" class="journal-banner">
            {{ successMessage }}
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="flex flex-col gap-5">
                <PlanningRouteMap
                    v-model:launch-lat="launchLat"
                    v-model:launch-lng="launchLng"
                    v-model:landing-lat="landingLat"
                    v-model:landing-lng="landingLng"
                    v-model:route-waypoints-json="routeWaypointsJson"
                    :default-view="profile.defaultMapView"
                    height-class="h-[640px] lg:h-[820px]"
                />
            </div>

            <aside class="flex flex-col gap-5">
                <section class="journal-card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="journal-kicker">Plan settings</p>
                            <h3 class="mt-2 text-[1.35rem] leading-none">
                                Save the day out
                            </h3>
                        </div>
                        <span v-if="isEditing" class="journal-chip">Saved</span>
                    </div>
                    <div class="mt-4 grid gap-4">
                        <div>
                            <label class="journal-field-label" for="plan_date"
                                >Date</label
                            >
                            <input
                                id="plan_date"
                                v-model="planDate"
                                type="date"
                                class="journal-input"
                            />
                            <p
                                v-if="saveForm.errors.plan_date"
                                class="mt-2 text-xs font-semibold text-red-500"
                            >
                                {{ saveForm.errors.plan_date }}
                            </p>
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="start_time_local"
                                >Start time</label
                            >
                            <input
                                id="start_time_local"
                                v-model="startTimeLocal"
                                type="time"
                                class="journal-input"
                            />
                        </div>
                        <div>
                            <label class="journal-field-label" for="speed_knots"
                                >Cruising speed</label
                            >
                            <div class="flex items-center gap-2">
                                <input
                                    id="speed_knots"
                                    v-model="speedKnots"
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    class="journal-input"
                                />
                                <span class="journal-chip">kt</span>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <div>
                                <label
                                    class="journal-field-label"
                                    for="launch_name"
                                    >Launch name</label
                                >
                                <input
                                    id="launch_name"
                                    v-model="launchName"
                                    type="text"
                                    class="journal-input"
                                    placeholder="Optional"
                                />
                            </div>
                            <div>
                                <label
                                    class="journal-field-label"
                                    for="landing_name"
                                    >Landing name</label
                                >
                                <input
                                    id="landing_name"
                                    v-model="landingName"
                                    type="text"
                                    class="journal-input"
                                    placeholder="Optional"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="journal-field-label" for="notes"
                                >Planning notes</label
                            >
                            <textarea
                                id="notes"
                                v-model="notes"
                                rows="4"
                                class="journal-input min-h-28"
                                placeholder="Parking, bail-out options, timings, food, shuttle, kit checks..."
                            />
                        </div>

                        <div
                            class="rounded-[22px] border border-[color:var(--journal-line)] bg-white/68 p-4 text-sm leading-6 text-[color:var(--journal-muted)]"
                        >
                            Saved plans appear in Library under
                            <span
                                class="font-semibold text-[color:var(--journal-text)]"
                                >Planned sessions</span
                            >. They do not count as logged paddles until we add
                            a dedicated “convert to session” step.
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button
                                type="button"
                                class="journal-primary-link justify-center disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="saveForm.processing"
                                @click="savePlan"
                            >
                                {{ saveButtonLabel }}
                            </button>
                            <Link
                                href="/sessions"
                                class="journal-utility-link justify-center"
                            >
                                Open Library
                            </Link>
                        </div>
                    </div>
                </section>
            </aside>
        </section>

        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <p class="journal-kicker">Area conditions</p>
                    <h3 class="mt-2 text-[1.55rem] leading-none">
                        Route area forecast
                    </h3>
                    <p class="journal-copy mt-2 max-w-3xl text-sm md:text-base">
                        Pull one forecast for the centre of the planned route,
                        sampled around the midpoint of the paddle. This keeps
                        the planner readable and avoids spending requests on
                        every waypoint.
                    </p>
                </div>
                <div class="flex flex-col items-start gap-2 lg:items-end">
                    <button
                        type="button"
                        class="journal-primary-link disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="forecastStatus === 'loading'"
                        @click="refreshForecasts"
                    >
                        {{
                            forecastStatus === 'loading'
                                ? 'Checking conditions...'
                                : 'Refresh conditions'
                        }}
                    </button>
                    <p
                        class="max-w-[22rem] text-xs leading-5 text-[color:var(--journal-muted)] lg:text-right"
                    >
                        {{ forecastRequestEstimate }} Draw first, refresh once.
                    </p>
                </div>
            </div>

            <section
                v-if="forecastMessage"
                class="journal-banner mt-4"
                :class="
                    forecastStatus === 'error' ||
                    forecastStatus === 'warning' ||
                    forecastStatus === 'stale'
                        ? 'journal-banner--danger'
                        : 'journal-banner--soft'
                "
            >
                {{ forecastMessage }}
            </section>

            <div
                v-if="forecastAreaPoint"
                class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]"
            >
                <section
                    class="overflow-hidden rounded-[28px] border border-[rgba(122,16,20,0.22)] bg-[#b61018] text-white shadow-[0_22px_50px_rgba(89,18,25,0.16)]"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-white/15 bg-[#8f0d13] px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.22em] uppercase opacity-75"
                            >
                                Route area
                            </p>
                            <h4 class="mt-1 text-[1.45rem] leading-none">
                                Area forecast
                            </h4>
                        </div>
                        <span
                            class="inline-flex w-fit items-center rounded-full bg-white px-3 py-1.5 font-mono text-xs font-bold text-[#b61018]"
                        >
                            {{ areaSampleTimeLabel }}
                        </span>
                    </div>

                    <div v-if="hasForecastFields(areaForecast)" class="p-5">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-[20px] bg-white/12 p-4">
                                <p class="text-xs font-semibold uppercase">
                                    Wind
                                </p>
                                <p class="mt-2 text-2xl font-black">
                                    F{{
                                        fieldLabel(
                                            areaForecast.fields?.wind_beaufort,
                                        )
                                    }}
                                </p>
                                <p class="mt-1 text-sm text-white/72">
                                    {{
                                        fieldLabel(
                                            areaForecast.fields?.wind_avg_ms,
                                            ' m/s',
                                        )
                                    }}
                                    avg ·
                                    {{
                                        fieldLabel(
                                            areaForecast.fields?.wind_gust_ms,
                                            ' m/s',
                                        )
                                    }}
                                    gust
                                </p>
                            </div>
                            <div class="rounded-[20px] bg-white/12 p-4">
                                <p class="text-xs font-semibold uppercase">
                                    Tide
                                </p>
                                <p class="mt-2 text-2xl font-black capitalize">
                                    {{ tideLabel(areaForecast) }}
                                </p>
                                <p class="mt-1 text-sm text-white/72">
                                    Area sample, not per waypoint.
                                </p>
                            </div>
                            <div class="rounded-[20px] bg-white/12 p-4">
                                <p class="text-xs font-semibold uppercase">
                                    Current
                                </p>
                                <p class="mt-2 text-2xl font-black">
                                    {{
                                        fieldLabel(
                                            areaForecast.fields?.current_knots,
                                            ' kt',
                                        )
                                    }}
                                </p>
                                <p class="mt-1 text-sm text-white/72">
                                    {{
                                        fieldLabel(
                                            areaForecast.fields
                                                ?.current_direction_deg,
                                            '°',
                                        )
                                    }}
                                    direction
                                </p>
                            </div>
                            <div class="rounded-[20px] bg-white/12 p-4">
                                <p class="text-xs font-semibold uppercase">
                                    Sea
                                </p>
                                <p class="mt-2 text-2xl font-black">
                                    {{
                                        fieldLabel(
                                            areaForecast.fields?.wave_height_m,
                                            ' m',
                                        )
                                    }}
                                </p>
                                <p class="mt-1 text-sm text-white/72">
                                    Swell
                                    {{
                                        fieldLabel(
                                            areaForecast.fields?.swell_height_m,
                                            ' m',
                                        )
                                    }}
                                    @
                                    {{
                                        fieldLabel(
                                            areaForecast.fields?.swell_period_s,
                                            ' s',
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="rounded-[20px] bg-white/12 p-4">
                                <p class="text-xs font-semibold uppercase">
                                    Temp
                                </p>
                                <p class="mt-2 text-2xl font-black">
                                    {{
                                        fieldLabel(
                                            areaForecast.fields?.air_temp_c,
                                            ' C',
                                        )
                                    }}
                                </p>
                                <p class="mt-1 text-sm text-white/72">
                                    Sea
                                    {{
                                        fieldLabel(
                                            areaForecast.fields?.sea_temp_c,
                                            ' C',
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="rounded-[20px] bg-white/12 p-4">
                                <p class="text-xs font-semibold uppercase">
                                    Severity
                                </p>
                                <p class="mt-2 text-2xl font-black capitalize">
                                    {{
                                        areaForecast.fields
                                            ?.forecast_severity ?? '—'
                                    }}
                                </p>
                                <p class="mt-1 text-sm text-white/72">
                                    Planning guidance only.
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="areaForecast.fields?.weather_summary"
                            class="mt-4 rounded-[20px] bg-black/14 p-4 text-sm leading-6 text-white/82"
                        >
                            {{ areaForecast.fields.weather_summary }}
                        </p>
                    </div>
                    <div v-else class="p-5">
                        <p
                            class="rounded-[20px] bg-white/12 p-4 text-sm leading-6 text-white/80"
                        >
                            {{ forecastCellMessage(areaForecast) }}
                        </p>
                    </div>
                </section>

                <section class="journal-card p-5">
                    <p class="journal-kicker">Sample point</p>
                    <h4 class="mt-2 text-[1.35rem] leading-none">
                        Route centre
                    </h4>
                    <div class="mt-4 grid gap-3">
                        <div
                            class="rounded-[18px] border border-[color:var(--journal-line)] bg-white/70 p-3"
                        >
                            <p class="journal-field-label">Coordinates</p>
                            <p
                                class="mt-1 font-mono text-sm text-[color:var(--journal-text)]"
                            >
                                {{ formatCoordinate(forecastAreaPoint.lat) }},
                                {{ formatCoordinate(forecastAreaPoint.lng) }}
                            </p>
                        </div>
                        <div
                            class="rounded-[18px] border border-[color:var(--journal-line)] bg-white/70 p-3"
                        >
                            <p class="journal-field-label">Sample time</p>
                            <p
                                class="mt-1 font-semibold text-[color:var(--journal-text)]"
                            >
                                {{ areaSampleTimeLabel }}
                            </p>
                        </div>
                        <div
                            class="rounded-[18px] border border-[color:var(--journal-line)] bg-white/70 p-3"
                        >
                            <p class="journal-field-label">API use</p>
                            <p
                                class="mt-1 text-sm leading-6 text-[color:var(--journal-muted)]"
                            >
                                {{ forecastRequestEstimate }} Cached repeats may
                                use fewer calls.
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <p
                v-else
                class="mt-5 rounded-[22px] border border-dashed border-[color:var(--journal-line)] bg-white/56 p-5 text-sm leading-6 text-[color:var(--journal-muted)]"
            >
                Place route points on the map to build the area forecast.
            </p>
        </section>
    </div>
</template>
