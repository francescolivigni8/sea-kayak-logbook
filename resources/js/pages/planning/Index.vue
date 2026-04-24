<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import { dashboard } from '@/routes';

interface ProfileSummary {
    name: string;
    slug: string;
    homeWater: string;
    timezone: string;
    planningUnitSystem: 'metric' | 'marine';
    hasCustomDefaultMapView: boolean;
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
    gpxUrl: string | null;
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
    precipitation_mm?: number | null;
    cloud_cover_percent?: number | null;
    rain_severity?: string | null;
    visibility_code?: string | null;
    wind_severity?: string | null;
    temperature_severity?: string | null;
    forecast_severity?: string | null;
    weather_summary?: string | null;
}

interface ForecastTimelinePoint {
    time: string;
    dayLabel: string;
    hourLabel: string;
    status: 'filled' | 'skipped' | 'failed';
    filledFields?: number;
    fields?: ForecastFields;
}

interface ForecastResult {
    status: 'idle' | 'loading' | 'filled' | 'skipped' | 'failed';
    message?: string;
    httpStatus?: number;
    provider?: string | null;
    fallbackFrom?: ForecastFallback | null;
    marineFallback?: ForecastFallback | null;
    timeline?: ForecastTimelinePoint[];
    fields?: ForecastFields;
}

interface ForecastPayload {
    status?: ForecastResult['status'];
    message?: string;
    reason?: string;
    httpStatus?: number;
    provider?: string | null;
    fallbackFrom?: ForecastFallback | null;
    marineFallback?: ForecastFallback | null;
    timeline?: ForecastTimelinePoint[];
    fields?: ForecastFields;
}

interface ForecastFallback {
    provider?: string | null;
    status?: string | null;
    reason?: string | null;
    httpStatus?: number | null;
    providerMessage?: string | null;
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

interface PlanningNotesFields {
    tideWindows: string;
    flowChanges: string;
    shuttleAccess: string;
    safetyFallback: string;
    general: string;
}

const AREA_FORECAST_KEY = 'area';
const MAX_FORECAST_OFFSET_MINUTES = 1440;
const KM_PER_NAUTICAL_MILE = 1.852;
const PlanningWeatherMap = defineAsyncComponent(
    () => import('@/components/maps/PlanningWeatherMap.vue'),
);

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
const parsedPlanningNotes = parsePlanningNotes(props.formDefaults.notes);
const tideWindows = ref(parsedPlanningNotes.tideWindows);
const flowChanges = ref(parsedPlanningNotes.flowChanges);
const shuttleAccess = ref(parsedPlanningNotes.shuttleAccess);
const safetyFallback = ref(parsedPlanningNotes.safetyFallback);
const generalPlanningNotes = ref(parsedPlanningNotes.general);
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

const unitSystem = computed<'metric' | 'marine'>(() =>
    props.profile.planningUnitSystem === 'marine' ? 'marine' : 'metric',
);
const distanceUnitLabel = computed(() =>
    unitSystem.value === 'marine' ? 'nm' : 'km',
);
const speedUnitLabel = computed(() =>
    unitSystem.value === 'marine' ? 'kt' : 'km/h',
);
const windBoardUnitLabel = computed(() =>
    unitSystem.value === 'marine' ? 'kt' : 'km/h',
);
const currentUnitLabel = computed(() =>
    unitSystem.value === 'marine' ? 'kt' : 'km/h',
);
const speedDisplayValue = computed({
    get() {
        const knots = Math.max(parseFloat(speedKnots.value) || 0, 0);

        return formatEditableNumber(
            unitSystem.value === 'marine' ? knots : knots * KM_PER_NAUTICAL_MILE,
        );
    },
    set(value: string) {
        if (value.trim() === '') {
            speedKnots.value = '';

            return;
        }

        const parsed = parseFloat(value);

        if (!Number.isFinite(parsed)) {
            speedKnots.value = '';

            return;
        }

        const knots =
            unitSystem.value === 'marine'
                ? parsed
                : parsed / KM_PER_NAUTICAL_MILE;

        speedKnots.value = formatEditableNumber(Math.max(knots, 0));
    },
});
const speedKmh = computed(
    () => Math.max(parseFloat(speedKnots.value) || 0, 0) * KM_PER_NAUTICAL_MILE,
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

    if (!routeLegs.value.length || totalDistanceKm.value <= 0) {
        const [point] = routePoints.value;

        return {
            ...point,
            key: AREA_FORECAST_KEY,
            label: 'Route area',
            shortLabel: 'A',
        };
    }

    const targetDistanceKm = totalDistanceKm.value / 2;
    let coveredDistanceKm = 0;

    for (const leg of routeLegs.value) {
        const nextDistanceKm = coveredDistanceKm + leg.distanceKm;

        if (targetDistanceKm <= nextDistanceKm) {
            const fraction =
                leg.distanceKm > 0
                    ? (targetDistanceKm - coveredDistanceKm) / leg.distanceKm
                    : 0;

            return {
                key: AREA_FORECAST_KEY,
                label: 'Route area',
                shortLabel: 'A',
                lat: interpolateCoordinate(leg.from.lat, leg.to.lat, fraction),
                lng: interpolateCoordinate(leg.from.lng, leg.to.lng, fraction),
            };
        }

        coveredDistanceKm = nextDistanceKm;
    }

    const point = routePoints.value[routePoints.value.length - 1];

    return {
        key: AREA_FORECAST_KEY,
        label: 'Route area',
        shortLabel: 'A',
        lat: point.lat,
        lng: point.lng,
    };
});

const rawForecastAreaOffsetMinutes = computed(() =>
    estimatedMinutes.value ? Math.round(estimatedMinutes.value / 2) : 0,
);
const forecastAreaOffsetMinutes = computed(() =>
    Math.min(rawForecastAreaOffsetMinutes.value, MAX_FORECAST_OFFSET_MINUTES),
);
const isForecastAreaOffsetCapped = computed(
    () => rawForecastAreaOffsetMinutes.value > MAX_FORECAST_OFFSET_MINUTES,
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

const forecastTimeline = computed<ForecastTimelinePoint[]>(() => {
    if (areaForecast.value.timeline?.length) {
        return areaForecast.value.timeline;
    }

    if (!hasForecastFields(areaForecast.value)) {
        return [];
    }

    return [
        {
            time: new Date().toISOString(),
            dayLabel: 'AREA',
            hourLabel: areaSampleTimeLabel.value,
            status: 'filled',
            filledFields: Object.values(areaForecast.value.fields ?? {}).filter(
                (value) =>
                    value !== null && value !== undefined && value !== '',
            ).length,
            fields: areaForecast.value.fields,
        },
    ];
});

const forecastDayGroups = computed(() => {
    const groups: { label: string; span: number }[] = [];

    forecastTimeline.value.forEach((slot) => {
        const lastGroup = groups[groups.length - 1];

        if (lastGroup?.label === slot.dayLabel) {
            lastGroup.span++;

            return;
        }

        groups.push({
            label: slot.dayLabel,
            span: 1,
        });
    });

    return groups;
});

const forecastBoardGridStyle = computed(() => ({
    gridTemplateColumns: `76px repeat(${Math.max(forecastTimeline.value.length, 1)}, minmax(38px, 1fr))`,
}));

const estimatedForecastRequests = computed(() =>
    forecastAreaPoint.value ? 2 : 0,
);

const forecastRequestEstimate = computed(() => {
    if (!forecastAreaPoint.value) {
        return 'Add route points to estimate forecast usage.';
    }

    return `About ${estimatedForecastRequests.value} forecast requests per provider: 1 weather + 1 marine/tide.`;
});

const forecastProviderLabel = computed(() =>
    providerLabel(areaForecast.value.provider),
);
const canExportRoute = computed(
    () => Boolean(props.plannedSession?.gpxUrl) && routePoints.value.length > 1,
);
const forecastTrustNote = computed(() => {
    if (!hasForecastFields(areaForecast.value)) {
        return 'Forecast values are guidance only until the route area is checked.';
    }

    return `Source: ${forecastProviderLabel.value}. Gusts are model peaks and can read hotter than harbour or inshore forecasts, so cross-check with a local marine forecast and tide table before launching.`;
});

const areaSampleTimeLabel = computed(() => {
    const offset = forecastAreaOffsetMinutes.value;

    if (offset === 0) {
        return startTimeLocal.value || 'Start';
    }

    const cappedLabel = isForecastAreaOffsetCapped.value ? ' (24h cap)' : '';

    return `${startTimeLocal.value || 'Start'} + ${formatMinutes(offset)}${cappedLabel}`;
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
        return 'Forecast not configured';
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

function interpolateCoordinate(
    start: number,
    end: number,
    fraction: number,
): number {
    return Number(
        (start + (end - start) * Math.min(Math.max(fraction, 0), 1)).toFixed(6),
    );
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

function formatEditableNumber(value: number): string {
    return Number(value.toFixed(1)).toString();
}

function toDisplayDistance(km: number): number {
    return unitSystem.value === 'marine' ? km / KM_PER_NAUTICAL_MILE : km;
}

function formatDistance(km: number, digits = 1): string {
    return `${toDisplayDistance(km).toFixed(digits)} ${distanceUnitLabel.value}`;
}

function formatWindSpeed(value?: number | null): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const converted =
        unitSystem.value === 'marine'
            ? value * 1.943844
            : value * 3.6;
    const digits = unitSystem.value === 'marine' ? 1 : 0;

    return converted.toFixed(digits);
}

function formatCurrentSpeed(value?: number | null): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const converted =
        unitSystem.value === 'marine'
            ? value
            : value * KM_PER_NAUTICAL_MILE;

    return converted.toFixed(1);
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

function providerLabel(provider?: string | null): string {
    if (provider === 'stormglass') {
        return 'Stormglass';
    }

    if (provider === 'open_meteo') {
        return 'Open-Meteo';
    }

    if (provider === 'met_no') {
        return 'MET Norway';
    }

    return 'Forecast';
}

function slotTideLabel(slot: ForecastTimelinePoint): string {
    if (!slot.fields) {
        return '—';
    }

    return slot.fields.tide_state ?? '—';
}

function beaufortCellClass(value?: number | null): string {
    if (value === null || value === undefined) {
        return 'bg-slate-100 text-slate-400';
    }

    if (value <= 2) {
        return 'bg-cyan-100 text-slate-800';
    }

    if (value <= 4) {
        return 'bg-lime-300 text-slate-900';
    }

    if (value <= 5) {
        return 'bg-yellow-300 text-slate-900';
    }

    if (value <= 6) {
        return 'bg-orange-300 text-slate-950';
    }

    return 'bg-red-500 text-white';
}

function gustCellClass(value?: number | null): string {
    if (value === null || value === undefined) {
        return 'bg-slate-100 text-slate-400';
    }

    if (value < 6) {
        return 'bg-emerald-100 text-slate-800';
    }

    if (value < 11) {
        return 'bg-yellow-200 text-slate-900';
    }

    if (value < 17) {
        return 'bg-orange-300 text-slate-950';
    }

    return 'bg-red-500 text-white';
}

function rainCellClass(value?: number | null): string {
    if (value === null || value === undefined) {
        return 'bg-slate-50 text-slate-300';
    }

    if (value < 0.5) {
        return 'bg-slate-50 text-slate-500';
    }

    if (value < 2.5) {
        return 'bg-sky-100 text-sky-900';
    }

    if (value < 7.5) {
        return 'bg-blue-300 text-slate-950';
    }

    return 'bg-blue-700 text-white';
}

function cloudOpacityStyle(value?: number | null) {
    const opacity =
        value === null || value === undefined
            ? 0.16
            : Math.min(Math.max(value / 100, 0.16), 0.92);

    return {
        opacity: opacity.toFixed(2),
    };
}

function windArrowStyle(value?: number | null) {
    return {
        transform: `rotate(${value ?? 0}deg)`,
    };
}

function windArrowLabel(value?: number | null): string {
    return value === null || value === undefined ? '·' : '↓';
}

function compactNumber(value?: number | null, digits = 0): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return value.toFixed(digits);
}

function parsePlanningNotes(value: string): PlanningNotesFields {
    if (!value.trim()) {
        return {
            tideWindows: '',
            flowChanges: '',
            shuttleAccess: '',
            safetyFallback: '',
            general: '',
        };
    }

    const markers = [
        {
            key: 'tideWindows',
            label: 'HW / LW and tide windows',
        },
        {
            key: 'flowChanges',
            label: 'Flow changes / tidal gates',
        },
        {
            key: 'shuttleAccess',
            label: 'Shuttle / access',
        },
        {
            key: 'safetyFallback',
            label: 'Safety / fallback',
        },
        {
            key: 'general',
            label: 'General planning notes',
        },
    ] as const;

    const parsed: PlanningNotesFields = {
        tideWindows: '',
        flowChanges: '',
        shuttleAccess: '',
        safetyFallback: '',
        general: '',
    };

    const pattern = new RegExp(
        `(${markers.map((marker) => `${marker.label}:`).join('|')})`,
        'g',
    );

    if (!pattern.test(value)) {
        parsed.general = value.trim();

        return parsed;
    }

    const parts = value.split(pattern).filter(Boolean);

    for (let index = 0; index < parts.length; index += 2) {
        const label = parts[index]?.replace(/:$/, '').trim();
        const content = parts[index + 1]?.trim() ?? '';
        const marker = markers.find((candidate) => candidate.label === label);

        if (marker) {
            parsed[marker.key] = content;
        }
    }

    return parsed;
}

function serializePlanningNotes(fields: PlanningNotesFields): string {
    const sections = [
        ['HW / LW and tide windows', fields.tideWindows],
        ['Flow changes / tidal gates', fields.flowChanges],
        ['Shuttle / access', fields.shuttleAccess],
        ['Safety / fallback', fields.safetyFallback],
        ['General planning notes', fields.general],
    ]
        .map(([label, value]) => [label, value.trim()] as const)
        .filter(([, value]) => value !== '')
        .map(([label, value]) => `${label}:\n${value}`);

    return sections.join('\n\n');
}

function uppercaseLabel(value?: string | null): string {
    return value ? value.toUpperCase() : '—';
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
    saveForm.notes = serializePlanningNotes({
        tideWindows: tideWindows.value,
        flowChanges: flowChanges.value,
        shuttleAccess: shuttleAccess.value,
        safetyFallback: safetyFallback.value,
        general: generalPlanningNotes.value,
    });
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
        forecastMessage.value = 'No forecast provider is configured yet.';

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
                provider: payload.provider,
                fallbackFrom: payload.fallbackFrom,
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
            provider: payload.provider,
            fallbackFrom: payload.fallbackFrom,
            marineFallback: payload.marineFallback,
            timeline: payload.timeline ?? [],
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
    const marineFallbackMessage = forecast.marineFallback?.provider
        ? ` Marine gaps filled with ${providerLabel(forecast.marineFallback.provider)}.`
        : '';

    forecastStatus.value = hasFields ? 'filled' : 'warning';
    forecastMessage.value = hasFields
        ? `Area conditions refreshed with ${providerLabel(forecast.provider)} for the route midpoint at ${areaSampleTimeLabel.value}.${forecast.fallbackFrom ? ` Fallback used after ${providerLabel(forecast.fallbackFrom.provider)} returned no usable board.` : ''}${marineFallbackMessage} ${forecastRequestEstimate.value} Treat this as planning guidance, not a go/no-go forecast.`
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
                        <span class="journal-stat-pill__value">{{
                            formatDistance(totalDistanceKm)
                        }}</span>
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

        <section
            v-if="!profile.hasCustomDefaultMapView"
            class="journal-banner journal-banner--soft"
        >
            Planner maps are still opening on the fallback Iceland view. Save
            your own local map area once in
            <Link href="/settings/profile" class="font-semibold underline">
                Account settings
            </Link>
            and new plans will start there.
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="flex flex-col gap-5">
                <PlanningWeatherMap
                    v-model:launch-lat="launchLat"
                    v-model:launch-lng="launchLng"
                    v-model:landing-lat="landingLat"
                    v-model:landing-lng="landingLng"
                    v-model:route-waypoints-json="routeWaypointsJson"
                    :default-view="profile.defaultMapView"
                    :sample-time-label="areaSampleTimeLabel"
                    :unit-system="unitSystem"
                    height-class="h-[760px] lg:h-[920px]"
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
                                    v-model="speedDisplayValue"
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    class="journal-input"
                                />
                                <span class="journal-chip">{{
                                    speedUnitLabel
                                }}</span>
                            </div>
                            <p
                                class="mt-2 text-xs leading-5 text-[color:var(--journal-muted)]"
                            >
                                {{
                                    unitSystem === 'marine'
                                        ? 'Saved internally in knots for route timing and GPX export.'
                                        : 'Metric mode keeps speed and distance aligned while you plan.'
                                }}
                            </p>
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
                            <label class="journal-field-label"
                                >Planning notes</label
                            >
                            <div class="grid gap-3">
                                <div class="space-y-2">
                                    <p class="journal-field-label">
                                        HW / LW and tide windows
                                    </p>
                                    <textarea
                                        v-model="tideWindows"
                                        rows="3"
                                        class="journal-input min-h-24"
                                        placeholder="HW / LW times, tidal windows, stand times..."
                                    />
                                </div>
                                <div class="space-y-2">
                                    <p class="journal-field-label">
                                        Flow changes / tidal gates
                                    </p>
                                    <textarea
                                        v-model="flowChanges"
                                        rows="3"
                                        class="journal-input min-h-24"
                                        placeholder="Flow changes, overfalls, tidal gates, commit points..."
                                    />
                                </div>
                                <div class="space-y-2">
                                    <p class="journal-field-label">
                                        Shuttle / access
                                    </p>
                                    <textarea
                                        v-model="shuttleAccess"
                                        rows="2"
                                        class="journal-input min-h-20"
                                        placeholder="Shuttle, parking, access, landing logistics..."
                                    />
                                </div>
                                <div class="space-y-2">
                                    <p class="journal-field-label">
                                        Safety / fallback
                                    </p>
                                    <textarea
                                        v-model="safetyFallback"
                                        rows="2"
                                        class="journal-input min-h-20"
                                        placeholder="Bail-out options, shelters, call-offs, group safety notes..."
                                    />
                                </div>
                                <div class="space-y-2">
                                    <p class="journal-field-label">
                                        General planning notes
                                    </p>
                                    <textarea
                                        v-model="generalPlanningNotes"
                                        rows="3"
                                        class="journal-input min-h-24"
                                        placeholder="Anything else worth keeping with the plan."
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            class="journal-surface-shell rounded-[22px] p-4 text-sm leading-6 text-[color:var(--journal-muted)]"
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
                            <a
                                v-if="canExportRoute && plannedSession?.gpxUrl"
                                :href="plannedSession.gpxUrl"
                                class="journal-utility-link justify-center"
                            >
                                Export GPX
                            </a>
                        </div>
                        <p
                            v-if="!canExportRoute"
                            class="text-xs leading-5 text-[color:var(--journal-muted)]"
                        >
                            Save the plan with at least two route points to
                            export a GPX route for GPS use.
                        </p>
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
                        Pull one forecast for the midpoint of the planned route,
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

            <div v-if="forecastAreaPoint" class="mt-5">
                <section class="journal-card overflow-hidden rounded-[28px]">
                    <div
                        class="flex flex-col gap-4 border-b border-[color:var(--journal-line)] px-5 py-4 sm:flex-row sm:items-end sm:justify-between"
                        style="
                            background: linear-gradient(
                                90deg,
                                var(--journal-card-top),
                                color-mix(
                                    in srgb,
                                    var(--journal-mint) 12%,
                                    var(--journal-panel-soft)
                                )
                            );
                        "
                    >
                        <div>
                            <p class="journal-kicker">
                                Route area / Forecast board
                            </p>
                            <div
                                class="mt-2 flex flex-wrap items-baseline gap-x-4 gap-y-2"
                            >
                                <h4
                                    class="text-[clamp(2.15rem,5vw,3.85rem)] leading-none tracking-[-0.06em]"
                                >
                                    F{{
                                        fieldLabel(
                                            areaForecast.fields?.wind_beaufort,
                                        )
                                    }}
                                    <span
                                        class="border-b border-dashed border-[color:var(--journal-muted)] text-[0.46em] tracking-normal text-[color:var(--journal-muted)]"
                                        >bft</span
                                    >
                                </h4>
                                <p
                                    class="max-w-xl text-sm leading-6 text-[color:var(--journal-muted)]"
                                >
                                    Area sample from the route midpoint, shown
                                    as a planning strip instead of per-waypoint
                                    weather.
                                </p>
                                <p
                                    class="text-xs font-semibold leading-5 text-[color:var(--journal-muted)]"
                                >
                                    Avg
                                    {{
                                        formatWindSpeed(
                                            areaForecast.fields?.wind_avg_ms,
                                        )
                                    }}
                                    {{ windBoardUnitLabel }} · gust
                                    {{
                                        formatWindSpeed(
                                            areaForecast.fields?.wind_gust_ms,
                                        )
                                    }}
                                    {{ windBoardUnitLabel }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-2 text-xs font-semibold"
                        >
                            <span
                                class="journal-surface-shell rounded-full px-3 py-1.5 font-mono text-[color:var(--journal-text)]"
                            >
                                {{ areaSampleTimeLabel }}
                            </span>
                            <span
                                class="rounded-full bg-[#1b243f] px-3 py-1.5 text-white"
                            >
                                {{ forecastProviderLabel }}
                            </span>
                            <span
                                class="journal-surface-shell rounded-full px-3 py-1.5 text-[color:var(--journal-muted)]"
                            >
                                {{ forecastProgressLabel }}
                            </span>
                        </div>
                    </div>

                    <div v-if="forecastTimeline.length" class="overflow-x-auto">
                        <div class="min-w-[1180px] p-4">
                            <div
                                class="grid items-stretch gap-px text-[0.68rem] font-semibold tracking-[0.08em] text-[color:var(--journal-muted)] uppercase"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="rounded-l-[14px] bg-slate-50 px-2 py-2 text-right"
                                >
                                    day
                                </div>
                                <div
                                    v-for="group in forecastDayGroups"
                                    :key="group.label"
                                    class="bg-slate-50 px-2 py-2 text-center first:rounded-l-[14px] last:rounded-r-[14px]"
                                    :style="{
                                        gridColumn: `span ${group.span} / span ${group.span}`,
                                    }"
                                >
                                    {{ group.label }}
                                </div>
                            </div>

                            <div
                                class="mt-1 grid items-stretch gap-px text-xs font-semibold text-[color:var(--journal-muted)]"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right font-mono"
                                >
                                    time
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`time-${slot.time}`"
                                    class="bg-white px-1 py-2 text-center font-mono"
                                >
                                    {{ slot.hourLabel }}
                                </div>
                            </div>

                            <div
                                class="grid items-stretch gap-px text-xs"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right text-[color:var(--journal-muted)]"
                                >
                                    wind
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`wind-${slot.time}`"
                                    class="flex items-center justify-center bg-white px-1 py-2 text-base text-[#123047]"
                                >
                                    <span
                                        class="inline-block"
                                        :style="
                                            windArrowStyle(
                                                slot.fields?.wind_direction_deg,
                                            )
                                        "
                                        >{{
                                            windArrowLabel(
                                                slot.fields?.wind_direction_deg,
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>

                            <div
                                class="grid items-stretch gap-px text-xs font-bold"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right text-[color:var(--journal-muted)]"
                                >
                                    avg {{ windBoardUnitLabel }}
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`avg-${slot.time}`"
                                    class="bg-white px-1 py-2 text-center font-semibold text-slate-700"
                                >
                                    {{
                                        formatWindSpeed(
                                            slot.fields?.wind_avg_ms,
                                        )
                                    }}
                                </div>
                            </div>

                            <div
                                class="grid items-stretch gap-px text-xs font-bold"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right text-[color:var(--journal-muted)]"
                                >
                                    bft
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`bft-${slot.time}`"
                                    class="px-1 py-2 text-center"
                                    :class="
                                        beaufortCellClass(
                                            slot.fields?.wind_beaufort,
                                        )
                                    "
                                >
                                    {{
                                        compactNumber(
                                            slot.fields?.wind_beaufort,
                                        )
                                    }}
                                </div>
                            </div>

                            <div
                                class="grid items-stretch gap-px text-xs font-bold"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right text-[color:var(--journal-muted)]"
                                >
                                    gust {{ windBoardUnitLabel }}
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`gust-${slot.time}`"
                                    class="px-1 py-2 text-center"
                                    :class="
                                        gustCellClass(slot.fields?.wind_gust_ms)
                                    "
                                    >
                                    {{
                                        formatWindSpeed(slot.fields?.wind_gust_ms)
                                    }}
                                </div>
                            </div>

                            <div
                                class="grid items-stretch gap-px text-xs"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right text-[color:var(--journal-muted)]"
                                >
                                    temp C
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`temp-${slot.time}`"
                                    class="bg-sky-50 px-1 py-2 text-center font-semibold text-slate-700"
                                >
                                    {{ compactNumber(slot.fields?.air_temp_c) }}
                                </div>
                            </div>

                            <div
                                class="grid items-stretch gap-px text-xs"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right text-[color:var(--journal-muted)]"
                                >
                                    clouds
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`cloud-${slot.time}`"
                                    class="flex items-center justify-center bg-white px-1 py-2"
                                >
                                    <span
                                        class="h-3 w-6 rounded-full bg-slate-500"
                                        :style="
                                            cloudOpacityStyle(
                                                slot.fields
                                                    ?.cloud_cover_percent,
                                            )
                                        "
                                    />
                                </div>
                            </div>

                            <div
                                class="grid items-stretch gap-px text-xs"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right text-[color:var(--journal-muted)]"
                                >
                                    rain mm
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`rain-${slot.time}`"
                                    class="px-1 py-2 text-center font-semibold"
                                    :class="
                                        rainCellClass(
                                            slot.fields?.precipitation_mm,
                                        )
                                    "
                                >
                                    {{
                                        compactNumber(
                                            slot.fields?.precipitation_mm,
                                            1,
                                        )
                                    }}
                                </div>
                            </div>

                            <div
                                class="grid items-stretch gap-px text-xs"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right text-[color:var(--journal-muted)]"
                                >
                                    vis
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`visibility-${slot.time}`"
                                    class="bg-white px-1 py-2 text-center font-mono text-[0.62rem] text-slate-600"
                                >
                                    {{
                                        uppercaseLabel(
                                            slot.fields?.visibility_code,
                                        )
                                    }}
                                </div>
                            </div>

                            <div
                                class="grid items-stretch gap-px text-xs"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right text-[color:var(--journal-muted)]"
                                >
                                    sea m
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`sea-${slot.time}`"
                                    class="bg-blue-100 px-1 py-2 text-center font-semibold text-slate-800"
                                >
                                    {{
                                        compactNumber(
                                            slot.fields?.wave_height_m,
                                            1,
                                        )
                                    }}
                                </div>
                            </div>

                            <div
                                class="grid items-stretch gap-px text-xs"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right text-[color:var(--journal-muted)]"
                                >
                                    swell
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`swell-${slot.time}`"
                                    class="bg-blue-50 px-1 py-2 text-center font-mono text-[0.66rem] text-slate-700"
                                >
                                    {{
                                        compactNumber(
                                            slot.fields?.swell_height_m,
                                            1,
                                        )
                                    }}
                                    /
                                    {{
                                        compactNumber(
                                            slot.fields?.swell_period_s,
                                        )
                                    }}
                                </div>
                            </div>

                            <div
                                class="grid items-stretch gap-px text-xs"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right text-[color:var(--journal-muted)]"
                                >
                                    current {{ currentUnitLabel }}
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`current-${slot.time}`"
                                    class="bg-white px-1 py-2 text-center font-semibold text-slate-700"
                                >
                                    {{
                                        formatCurrentSpeed(
                                            slot.fields?.current_knots,
                                        )
                                    }}
                                </div>
                            </div>

                            <div
                                class="grid items-stretch gap-px text-xs"
                                :style="forecastBoardGridStyle"
                            >
                                <div
                                    class="bg-white px-2 py-2 text-right text-[color:var(--journal-muted)]"
                                >
                                    tide
                                </div>
                                <div
                                    v-for="slot in forecastTimeline"
                                    :key="`tide-${slot.time}`"
                                    class="bg-[#eef6fb] px-1 py-2 text-center font-mono text-[0.62rem] text-slate-700"
                                >
                                    {{ slotTideLabel(slot) }}
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-2 border-t border-[color:var(--journal-line)] bg-slate-50/70 px-5 py-3 text-xs leading-5 text-[color:var(--journal-muted)] sm:flex-row sm:items-center sm:justify-between"
                        >
                            <span>
                                Route midpoint:
                                <span class="font-mono">
                                    {{
                                        formatCoordinate(forecastAreaPoint.lat)
                                    }},
                                    {{
                                        formatCoordinate(forecastAreaPoint.lng)
                                    }}
                                </span>
                            </span>
                            <span>{{ forecastRequestEstimate }}</span>
                        </div>
                        <div
                            class="border-t border-[color:var(--journal-line)] bg-white/74 px-5 py-3 text-xs leading-5 text-[color:var(--journal-muted)]"
                        >
                            {{ forecastTrustNote }}
                        </div>
                    </div>
                    <div v-else class="p-5">
                        <p
                            class="rounded-[20px] border border-dashed border-[color:var(--journal-line)] bg-white/72 p-4 text-sm leading-6 text-[color:var(--journal-muted)]"
                        >
                            {{ forecastCellMessage(areaForecast) }}
                        </p>
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
