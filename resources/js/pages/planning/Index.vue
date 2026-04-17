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
    fields?: ForecastFields;
}

interface ForecastPayload {
    status?: ForecastResult['status'];
    message?: string;
    reason?: string;
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
const forecastStatus = ref<'idle' | 'loading' | 'filled' | 'warning' | 'error'>(
    Object.keys(initialForecastByPoint).length ? 'filled' : 'idle',
);
const forecastMessage = ref<string | null>(
    Object.keys(initialForecastByPoint).length
        ? 'Saved waypoint forecast loaded. Refresh conditions if the plan changes.'
        : null,
);
const forecastByPoint = ref<Record<string, ForecastResult>>(
    initialForecastByPoint,
);
const hasFetchedForecast = ref(Object.keys(initialForecastByPoint).length > 0);

let forecastTimer: ReturnType<typeof setTimeout> | null = null;
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

const routePointOffsets = computed<Record<string, number>>(() => {
    const offsets: Record<string, number> = {};
    let cumulativeDistanceKm = 0;

    routePoints.value.forEach((point, index) => {
        offsets[point.key] =
            speedKmh.value > 0
                ? Math.round((cumulativeDistanceKm / speedKmh.value) * 60)
                : 0;

        if (routeLegs.value[index]) {
            cumulativeDistanceKm += routeLegs.value[index].distanceKm;
        }
    });

    return offsets;
});

const conditionGridStyle = computed(() => ({
    gridTemplateColumns: `140px repeat(${Math.max(routePoints.value.length, 1)}, minmax(112px, 1fr))`,
}));

const forecastProgressLabel = computed(() => {
    if (forecastStatus.value === 'loading') {
        return 'Loading waypoint forecast';
    }

    if (forecastStatus.value === 'filled') {
        return `${Object.keys(forecastByPoint.value).length} points checked`;
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

function pointForecast(point: RoutePoint): ForecastResult {
    return forecastByPoint.value[point.key] ?? { status: 'idle' };
}

function hasForecastFields(forecast: ForecastResult): boolean {
    if (!forecast.fields) {
        return false;
    }

    return Object.values(forecast.fields).some(
        (value) => value !== null && value !== undefined && value !== '',
    );
}

function pointOffsetLabel(point: RoutePoint): string {
    const offset = routePointOffsets.value[point.key] ?? 0;

    return offset === 0
        ? startTimeLocal.value || 'Start'
        : `+${formatMinutes(offset)}`;
}

function forecastCellMessage(point: RoutePoint): string {
    const forecast = pointForecast(point);

    if (forecast.status === 'loading') {
        return 'Loading...';
    }

    return forecast.message || 'No forecast data.';
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
    saveForm.forecast_points = Object.keys(forecastByPoint.value).length
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

function scheduleForecastRefresh() {
    if (!hasFetchedForecast.value) {
        return;
    }

    if (forecastTimer) {
        clearTimeout(forecastTimer);
    }

    forecastTimer = setTimeout(() => {
        refreshForecasts();
    }, 900);
}

async function refreshForecasts() {
    if (!props.weatherAutofillAvailable) {
        forecastStatus.value = 'warning';
        forecastMessage.value = 'Stormglass is not configured yet.';

        return;
    }

    if (!routePoints.value.length) {
        forecastStatus.value = 'warning';
        forecastMessage.value =
            'Place at least one point before checking conditions.';

        return;
    }

    forecastAbortController?.abort();
    forecastAbortController = new AbortController();
    forecastStatus.value = 'loading';
    forecastMessage.value =
        'Checking wind, tide, current, swell, and temperatures along the plan.';
    hasFetchedForecast.value = true;

    const nextForecasts: Record<string, ForecastResult> = {};

    for (const point of routePoints.value) {
        nextForecasts[point.key] = { status: 'loading' };
        forecastByPoint.value = { ...nextForecasts };

        const params = new URLSearchParams({
            plan_date: planDate.value,
            start_time_local: startTimeLocal.value,
            lat: point.lat.toFixed(6),
            lng: point.lng.toFixed(6),
            label: point.label,
            offset_minutes: String(routePointOffsets.value[point.key] ?? 0),
        });

        try {
            const response = await fetch(
                `/planning/weather-preview?${params}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                    signal: forecastAbortController.signal,
                },
            );

            const payload = (await response
                .json()
                .catch(() => ({}))) as ForecastPayload;

            if (!response.ok) {
                nextForecasts[point.key] = {
                    status: 'failed',
                    message:
                        payload.message ||
                        payload.reason ||
                        `Forecast request failed (${response.status}).`,
                };
                continue;
            }

            nextForecasts[point.key] = {
                status: payload.status ?? 'failed',
                message: payload.message || payload.reason,
                fields: payload.fields ?? {},
            };
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            nextForecasts[point.key] = {
                status: 'failed',
                message: 'Forecast request failed for this point.',
            };
        }
    }

    forecastByPoint.value = nextForecasts;
    const filledCount = Object.values(nextForecasts).filter((forecast) =>
        hasForecastFields(forecast),
    ).length;
    const missingMessages = [
        ...new Set(
            Object.values(nextForecasts)
                .filter((forecast) => !hasForecastFields(forecast))
                .map((forecast) => forecast.message)
                .filter(Boolean),
        ),
    ];

    forecastStatus.value = filledCount > 0 ? 'filled' : 'warning';
    forecastMessage.value =
        filledCount > 0
            ? `Waypoint conditions refreshed for ${filledCount}/${routePoints.value.length} points. Treat this as planning guidance, not a go/no-go forecast.${missingMessages.length ? ` Missing points: ${missingMessages.join(' ')}` : ''}`
            : `No waypoint returned usable forecast data yet.${missingMessages.length ? ` ${missingMessages.join(' ')}` : ''}`;
}

watch(
    () => [
        planDate.value,
        startTimeLocal.value,
        launchLat.value,
        launchLng.value,
        landingLat.value,
        landingLng.value,
        launchName.value,
        landingName.value,
        routeWaypointsJson.value,
    ],
    () => {
        scheduleForecastRefresh();
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
                            weather, tide, current, swell, and temperature for
                            each point.
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

                <section class="journal-card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="journal-kicker">Route legs</p>
                            <h3 class="mt-2 text-[1.35rem] leading-none">
                                Course estimate
                            </h3>
                        </div>
                        <span class="journal-chip"
                            >{{ routeLegs.length }} legs</span
                        >
                    </div>

                    <div v-if="routeLegs.length" class="mt-4 space-y-2">
                        <div
                            v-for="leg in routeLegs"
                            :key="leg.key"
                            class="rounded-[18px] border border-[color:var(--journal-line)] bg-white/70 p-3"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p
                                    class="text-sm font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ leg.from.shortLabel }} →
                                    {{ leg.to.shortLabel }}
                                </p>
                                <p
                                    class="font-mono text-sm text-[color:var(--journal-text)]"
                                >
                                    {{ leg.distanceKm.toFixed(1) }} km
                                </p>
                            </div>
                            <p
                                class="mt-1 text-xs text-[color:var(--journal-muted)]"
                            >
                                Bearing {{ leg.bearingDeg.toFixed(0) }}°
                            </p>
                        </div>
                    </div>

                    <p
                        v-else
                        class="mt-4 text-sm leading-6 text-[color:var(--journal-muted)]"
                    >
                        Add at least two points on the map to get distance and
                        leg bearings.
                    </p>
                </section>
            </aside>
        </section>

        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <p class="journal-kicker">Conditions timeline</p>
                    <h3 class="mt-2 text-[1.55rem] leading-none">
                        Waypoint forecast
                    </h3>
                    <p class="journal-copy mt-2 max-w-3xl text-sm md:text-base">
                        Pull a point-by-point preview for the plan. This checks
                        the nearest available Stormglass hour for the selected
                        date and start time.
                    </p>
                </div>
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
            </div>

            <section
                v-if="forecastMessage"
                class="journal-banner mt-4"
                :class="
                    forecastStatus === 'error' || forecastStatus === 'warning'
                        ? 'journal-banner--danger'
                        : 'journal-banner--soft'
                "
            >
                {{ forecastMessage }}
            </section>

            <div
                v-if="routePoints.length"
                class="mt-5 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                <div
                    class="min-w-[900px] overflow-hidden rounded-[26px] border border-[rgba(122,16,20,0.22)] bg-[#b61018] text-white shadow-[0_22px_50px_rgba(89,18,25,0.16)]"
                >
                    <div
                        class="grid border-b border-white/15 bg-[#8f0d13] text-xs font-semibold tracking-[0.18em] uppercase"
                        :style="conditionGridStyle"
                    >
                        <div class="px-4 py-3">Route</div>
                        <div
                            v-for="point in routePoints"
                            :key="`${point.key}-header`"
                            class="border-l border-white/10 px-4 py-3"
                        >
                            <span
                                class="inline-grid size-6 place-items-center rounded-full bg-white text-[0.72rem] font-black text-[#b61018]"
                            >
                                {{ point.shortLabel }}
                            </span>
                            <span class="ml-2">{{ point.label }}</span>
                        </div>
                    </div>

                    <div
                        class="grid border-b border-white/10 text-sm"
                        :style="conditionGridStyle"
                    >
                        <div class="bg-black/12 px-4 py-3 font-semibold">
                            Time
                        </div>
                        <div
                            v-for="point in routePoints"
                            :key="`${point.key}-time`"
                            class="border-l border-white/10 px-4 py-3 font-mono"
                        >
                            {{ pointOffsetLabel(point) }}
                        </div>
                    </div>

                    <div class="grid text-sm" :style="conditionGridStyle">
                        <div class="bg-black/12 px-4 py-3 font-semibold">
                            Wind
                        </div>
                        <div
                            v-for="point in routePoints"
                            :key="`${point.key}-wind`"
                            class="border-l border-white/10 px-4 py-3"
                        >
                            <template
                                v-if="hasForecastFields(pointForecast(point))"
                            >
                                <span class="font-black">
                                    F{{
                                        fieldLabel(
                                            pointForecast(point).fields
                                                ?.wind_beaufort,
                                        )
                                    }}
                                </span>
                                <span class="ml-2 text-white/72">
                                    {{
                                        fieldLabel(
                                            pointForecast(point).fields
                                                ?.wind_avg_ms,
                                            ' m/s',
                                        )
                                    }}
                                </span>
                            </template>
                            <span v-else class="text-white/62">{{
                                forecastCellMessage(point)
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="grid border-t border-white/10 text-sm"
                        :style="conditionGridStyle"
                    >
                        <div class="bg-black/12 px-4 py-3 font-semibold">
                            Current
                        </div>
                        <div
                            v-for="point in routePoints"
                            :key="`${point.key}-current`"
                            class="border-l border-white/10 px-4 py-3"
                        >
                            {{
                                fieldLabel(
                                    pointForecast(point).fields?.current_knots,
                                    ' kt',
                                )
                            }}
                            <span class="text-white/62">
                                {{
                                    fieldLabel(
                                        pointForecast(point).fields
                                            ?.current_direction_deg,
                                        '°',
                                    )
                                }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="grid border-t border-white/10 text-sm"
                        :style="conditionGridStyle"
                    >
                        <div class="bg-black/12 px-4 py-3 font-semibold">
                            Sea
                        </div>
                        <div
                            v-for="point in routePoints"
                            :key="`${point.key}-sea`"
                            class="border-l border-white/10 px-4 py-3"
                        >
                            W
                            {{
                                fieldLabel(
                                    pointForecast(point).fields?.wave_height_m,
                                    ' m',
                                )
                            }}
                            <span class="ml-2 text-white/62">
                                S
                                {{
                                    fieldLabel(
                                        pointForecast(point).fields
                                            ?.swell_height_m,
                                        ' m',
                                    )
                                }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="grid border-t border-white/10 text-sm"
                        :style="conditionGridStyle"
                    >
                        <div class="bg-black/12 px-4 py-3 font-semibold">
                            Tide / temp
                        </div>
                        <div
                            v-for="point in routePoints"
                            :key="`${point.key}-tide`"
                            class="border-l border-white/10 px-4 py-3 capitalize"
                        >
                            {{ pointForecast(point).fields?.tide_state ?? '—' }}
                            <span class="ml-2 text-white/62 normal-case">
                                {{
                                    fieldLabel(
                                        pointForecast(point).fields?.air_temp_c,
                                        ' C',
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="routePoints.length"
                class="mt-5 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                <div
                    class="min-w-[980px] overflow-hidden rounded-[24px] border border-[color:var(--journal-line)] bg-white/72"
                >
                    <div
                        class="grid grid-cols-[130px_repeat(7,minmax(100px,1fr))] border-b border-[color:var(--journal-line)] bg-[rgba(255,255,255,0.74)] text-xs font-semibold tracking-[0.12em] text-[color:var(--journal-faint)] uppercase"
                    >
                        <div class="px-4 py-3">Point</div>
                        <div class="px-4 py-3">Wind</div>
                        <div class="px-4 py-3">Tide</div>
                        <div class="px-4 py-3">Current</div>
                        <div class="px-4 py-3">Sea</div>
                        <div class="px-4 py-3">Temp</div>
                        <div class="px-4 py-3">Severity</div>
                        <div class="px-4 py-3">Coords</div>
                    </div>

                    <div
                        v-for="point in routePoints"
                        :key="point.key"
                        class="grid grid-cols-[130px_repeat(7,minmax(100px,1fr))] border-b border-[color:var(--journal-line)] text-sm last:border-b-0"
                    >
                        <div class="px-4 py-3">
                            <span class="journal-chip journal-chip--primary">{{
                                point.shortLabel
                            }}</span>
                            <p class="mt-2 font-semibold">
                                {{ point.label }}
                            </p>
                        </div>
                        <div class="px-4 py-3">
                            <template
                                v-if="hasForecastFields(pointForecast(point))"
                            >
                                <p class="font-semibold">
                                    F{{
                                        fieldLabel(
                                            pointForecast(point).fields
                                                ?.wind_beaufort,
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-xs text-[color:var(--journal-muted)]"
                                >
                                    {{
                                        fieldLabel(
                                            pointForecast(point).fields
                                                ?.wind_avg_ms,
                                            ' m/s',
                                        )
                                    }}
                                    avg
                                </p>
                                <p
                                    class="text-xs text-[color:var(--journal-muted)]"
                                >
                                    {{
                                        fieldLabel(
                                            pointForecast(point).fields
                                                ?.wind_gust_ms,
                                            ' m/s',
                                        )
                                    }}
                                    gust
                                </p>
                            </template>
                            <span
                                v-else
                                class="text-[color:var(--journal-faint)]"
                            >
                                {{ forecastCellMessage(point) }}
                            </span>
                        </div>
                        <div class="px-4 py-3 capitalize">
                            {{ pointForecast(point).fields?.tide_state ?? '—' }}
                        </div>
                        <div class="px-4 py-3">
                            <p>
                                {{
                                    fieldLabel(
                                        pointForecast(point).fields
                                            ?.current_knots,
                                        ' kt',
                                    )
                                }}
                            </p>
                            <p
                                class="text-xs text-[color:var(--journal-muted)]"
                            >
                                {{
                                    fieldLabel(
                                        pointForecast(point).fields
                                            ?.current_direction_deg,
                                        '°',
                                    )
                                }}
                            </p>
                        </div>
                        <div class="px-4 py-3">
                            <p>
                                Wave
                                {{
                                    fieldLabel(
                                        pointForecast(point).fields
                                            ?.wave_height_m,
                                        ' m',
                                    )
                                }}
                            </p>
                            <p
                                class="text-xs text-[color:var(--journal-muted)]"
                            >
                                Swell
                                {{
                                    fieldLabel(
                                        pointForecast(point).fields
                                            ?.swell_height_m,
                                        ' m',
                                    )
                                }}
                                @
                                {{
                                    fieldLabel(
                                        pointForecast(point).fields
                                            ?.swell_period_s,
                                        ' s',
                                    )
                                }}
                            </p>
                        </div>
                        <div class="px-4 py-3">
                            <p>
                                Air
                                {{
                                    fieldLabel(
                                        pointForecast(point).fields?.air_temp_c,
                                        ' C',
                                    )
                                }}
                            </p>
                            <p
                                class="text-xs text-[color:var(--journal-muted)]"
                            >
                                Sea
                                {{
                                    fieldLabel(
                                        pointForecast(point).fields?.sea_temp_c,
                                        ' C',
                                    )
                                }}
                            </p>
                        </div>
                        <div class="px-4 py-3 capitalize">
                            {{
                                pointForecast(point).fields
                                    ?.forecast_severity ??
                                pointForecast(point).status
                            }}
                        </div>
                        <div
                            class="px-4 py-3 font-mono text-xs text-[color:var(--journal-muted)]"
                        >
                            {{ formatCoordinate(point.lat) }}<br />
                            {{ formatCoordinate(point.lng) }}
                        </div>
                    </div>
                </div>
            </div>

            <p
                v-else
                class="mt-5 rounded-[22px] border border-dashed border-[color:var(--journal-line)] bg-white/56 p-5 text-sm leading-6 text-[color:var(--journal-muted)]"
            >
                Place route points on the map to build the planning timeline.
            </p>
        </section>
    </div>
</template>
