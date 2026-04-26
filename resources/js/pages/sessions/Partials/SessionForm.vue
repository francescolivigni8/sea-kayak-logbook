<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { useUnitPreferences } from '@/composables/useUnitPreferences';
import {
    convertCurrentKnots,
    convertCurrentToKnots,
    convertDistanceKm,
    convertDistanceToKm,
    convertTemperatureC,
    convertTemperatureToC,
    convertWindMs,
    convertWindToMs,
} from '@/lib/units';
import InputError from '@/components/InputError.vue';
import SessionLocationPicker from '@/components/maps/SessionLocationPicker.vue';
import type {
    SessionExistingAssets,
    SessionFormDefaults,
    SessionProfileSummary,
} from '@/types/sessions';

interface FlashPageProps {
    flash?: {
        success?: string;
    };
}

interface RouteWaypoint {
    lat: number;
    lng: number;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    profile: SessionProfileSummary;
    weatherAutofillAvailable: boolean;
    formDefaults: SessionFormDefaults;
    existingAssets: SessionExistingAssets;
    sessionId?: number;
    initialStep?: number;
}>();

const page = usePage();
const {
    unitPreferences,
    currentUnitLabel,
    distanceUnitLabel,
    temperatureUnitLabel,
    windUnitLabel,
} = useUnitPreferences();
const form = useForm({
    ...props.formDefaults,
    gpx_file: null as File | null,
    fit_file: null as File | null,
    session_photo: null as File | null,
});

function formatEditableNumber(value: number, digits = 1): string {
    return Number(value.toFixed(digits)).toString();
}

function normalizeEditableDecimal(value: string): string {
    return value.replace(',', '.').trim();
}

function haversineKm(
    leftLat: number,
    leftLng: number,
    rightLat: number,
    rightLng: number,
): number {
    const earthRadiusKm = 6371;
    const toRadians = (degrees: number) => (degrees * Math.PI) / 180;
    const dLat = toRadians(rightLat - leftLat);
    const dLng = toRadians(rightLng - leftLng);
    const lat1 = toRadians(leftLat);
    const lat2 = toRadians(rightLat);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.sin(dLng / 2) ** 2 * Math.cos(lat1) * Math.cos(lat2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return earthRadiusKm * c;
}

function createConvertedNumberField(
    getRaw: () => string,
    setRaw: (value: string) => void,
    toDisplay: (value: number) => number,
    toRaw: (value: number) => number,
    digits = 1,
) {
    return computed({
        get() {
            const parsed = parseFloat(getRaw());

            if (!Number.isFinite(parsed)) {
                return '';
            }

            return formatEditableNumber(toDisplay(parsed), digits);
        },
        set(value: string) {
            const normalized = normalizeEditableDecimal(value);

            if (normalized === '') {
                setRaw('');

                return;
            }

            const parsed = parseFloat(normalized);

            if (!Number.isFinite(parsed)) {
                setRaw('');

                return;
            }

            setRaw(formatEditableNumber(toRaw(parsed), digits));
        },
    });
}

const distanceDisplay = createConvertedNumberField(
    () => form.distance_km,
    (value) => {
        form.distance_km = value;
    },
    (value) => convertDistanceKm(value, unitPreferences.value),
    (value) => Math.max(convertDistanceToKm(value, unitPreferences.value), 0),
);

const windAvgDisplay = createConvertedNumberField(
    () => form.wind_avg_ms,
    (value) => {
        form.wind_avg_ms = value;
    },
    (value) => convertWindMs(value, unitPreferences.value),
    (value) => Math.max(convertWindToMs(value, unitPreferences.value), 0),
);

const windGustDisplay = createConvertedNumberField(
    () => form.wind_gust_ms,
    (value) => {
        form.wind_gust_ms = value;
    },
    (value) => convertWindMs(value, unitPreferences.value),
    (value) => Math.max(convertWindToMs(value, unitPreferences.value), 0),
);

const currentDisplay = createConvertedNumberField(
    () => form.current_knots,
    (value) => {
        form.current_knots = value;
    },
    (value) => convertCurrentKnots(value, unitPreferences.value),
    (value) => Math.max(convertCurrentToKnots(value, unitPreferences.value), 0),
);

const airTemperatureDisplay = createConvertedNumberField(
    () => form.air_temp_c,
    (value) => {
        form.air_temp_c = value;
    },
    (value) => convertTemperatureC(value, unitPreferences.value),
    (value) => convertTemperatureToC(value, unitPreferences.value),
);

const seaTemperatureDisplay = createConvertedNumberField(
    () => form.sea_temp_c,
    (value) => {
        form.sea_temp_c = value;
    },
    (value) => convertTemperatureC(value, unitPreferences.value),
    (value) => convertTemperatureToC(value, unitPreferences.value),
);

const steps = [
    {
        key: 'journey',
        title: 'Journey',
        description: 'Where, when, and what kind of paddle it was.',
    },
    {
        key: 'sea',
        title: 'Sea',
        description: 'Wind, swell, tide, temperatures, and checklist ratings.',
    },
    {
        key: 'development',
        title: 'Rescue and development',
        description: 'Rescue events, skills, reflections, and scores.',
    },
    {
        key: 'notes',
        title: 'Notes and files',
        description: 'Observations, expedition fields, GPX/FIT, and a photo.',
    },
] as const;

const currentStep = ref(
    Math.max(0, Math.min(props.initialStep ?? 0, steps.length - 1)),
);
const currentStepMeta = computed(() => steps[currentStep.value]);
const notesTextarea = ref<HTMLTextAreaElement | null>(null);
const weatherPreviewState = ref<
    'idle' | 'loading' | 'filled' | 'warning' | 'error'
>('idle');
const weatherPreviewMessage = ref<string | null>(null);
const flashSuccessMessage = computed(
    () => (page.props as FlashPageProps).flash?.success,
);
const formErrorEntries = computed(() =>
    Object.entries(form.errors).filter((entry): entry is [string, string] =>
        Boolean(entry[1]),
    ),
);

const routeCategoryOptions = [
    'journey',
    'training',
    'benchmark',
    'navigation',
    'rescue-practice',
    'expedition',
];
const bodyOfWaterOptions = [
    'sea',
    'ocean',
    'fjord',
    'lake',
    'river',
    'canal',
    'other',
];
const severityOptions = ['low', 'moderate', 'high', 'extreme'];
const tideStateOptions = ['slack', 'flooding', 'high', 'ebbing', 'low'];
const visibilityOptions = ['clear', 'good', 'moderate', 'poor', 'fog'];

const stepFieldMap: Record<string, number> = {
    title: 0,
    session_date: 0,
    start_time_local: 0,
    launch_name: 0,
    launch_lat: 0,
    launch_lng: 0,
    landing_name: 0,
    landing_lat: 0,
    landing_lng: 0,
    area_name: 0,
    route_category: 0,
    body_of_water: 0,
    kayak_used: 0,
    paddle_used: 0,
    distance_km: 0,
    duration_minutes: 0,
    moving_minutes: 0,
    route_tags_text: 0,
    category_names_text: 0,
    partners_text: 0,
    manual_route_waypoints: 0,

    wind_avg_ms: 1,
    wind_gust_ms: 1,
    wind_direction_deg: 1,
    wind_beaufort: 1,
    tide_state: 1,
    current_knots: 1,
    current_direction_deg: 1,
    wave_height_m: 1,
    swell_height_m: 1,
    swell_period_s: 1,
    swell_direction_deg: 1,
    air_temp_c: 1,
    sea_temp_c: 1,
    rain_severity: 1,
    wind_severity: 1,
    temperature_severity: 1,
    forecast_severity: 1,
    visibility_code: 1,
    weather_summary: 1,
    route_summary: 1,

    successful_rolls_count: 2,
    wet_exits_count: 2,
    tow_rescues_count: 2,
    skills_text: 2,
    what_went_well: 2,
    improve_next: 2,
    confidence_score: 2,
    fatigue_score: 2,
    decision_score: 2,

    notes_public: 3,
    notes_private: 3,
    is_public: 3,
    is_expedition: 3,
    expedition_days: 3,
    expedition_notes: 3,
    gpx_file: 3,
    fit_file: 3,
    session_photo: 3,
};

const photoPreviewUrl = ref<string | null>(props.existingAssets.photoUrl);
let activeObjectUrl: string | null = null;
let weatherPreviewTimer: ReturnType<typeof setTimeout> | null = null;
let weatherPreviewAbortController: AbortController | null = null;

watch(
    () => form.session_photo,
    (file) => {
        if (activeObjectUrl) {
            URL.revokeObjectURL(activeObjectUrl);
            activeObjectUrl = null;
        }

        if (file instanceof File) {
            activeObjectUrl = URL.createObjectURL(file);
            photoPreviewUrl.value = activeObjectUrl;

            return;
        }

        photoPreviewUrl.value = props.existingAssets.photoUrl;
    },
);

watch(
    () => form.is_expedition,
    (isExpedition) => {
        if (!isExpedition) {
            form.expedition_days = '';
        }
    },
);

watch(
    () => form.autofill_weather,
    (enabled) => {
        if (!enabled) {
            weatherPreviewState.value = 'idle';
            weatherPreviewMessage.value = null;

            if (weatherPreviewTimer) {
                clearTimeout(weatherPreviewTimer);
                weatherPreviewTimer = null;
            }

            weatherPreviewAbortController?.abort();
            weatherPreviewAbortController = null;

            return;
        }

        scheduleWeatherPreview();
    },
);

watch(
    () => [
        form.session_date,
        form.start_time_local,
        form.launch_lat,
        form.launch_lng,
        form.landing_lat,
        form.landing_lng,
    ],
    () => {
        if (!form.autofill_weather) {
            return;
        }

        scheduleWeatherPreview();
    },
);

watch(
    () => ({ ...form.errors }),
    (errors) => {
        const firstStepWithError = Object.keys(errors)
            .map((field) => stepFieldMap[field])
            .find((step): step is number => step !== undefined);

        if (firstStepWithError !== undefined) {
            currentStep.value = firstStepWithError;
        }
    },
    { deep: true },
);

onBeforeUnmount(() => {
    if (activeObjectUrl) {
        URL.revokeObjectURL(activeObjectUrl);
    }

    if (weatherPreviewTimer) {
        clearTimeout(weatherPreviewTimer);
    }

    weatherPreviewAbortController?.abort();
});

const pageTitle = computed(() =>
    props.mode === 'create' ? 'Add paddle session' : 'Edit paddle session',
);
const pageDescription = computed(() =>
    props.mode === 'create'
        ? 'Capture the paddle, mark the sea state, and attach the files.'
        : 'Refine the paddle, notes, files, and expedition fields.',
);

const submitLabel = computed(() =>
    props.mode === 'create' ? 'Save session' : 'Update session',
);
const fileInputClass =
    'journal-input file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium';
const stepProgressLabel = computed(
    () => `Step ${currentStep.value + 1} of ${steps.length}`,
);
const launchLatNumber = computed(() =>
    form.launch_lat === '' ? null : Number(form.launch_lat),
);
const launchLngNumber = computed(() =>
    form.launch_lng === '' ? null : Number(form.launch_lng),
);
const landingLatNumber = computed(() =>
    form.landing_lat === '' ? null : Number(form.landing_lat),
);
const landingLngNumber = computed(() =>
    form.landing_lng === '' ? null : Number(form.landing_lng),
);
const manualTracePoints = computed<RouteWaypoint[]>(() => {
    if (!form.manual_route_waypoints) {
        return [];
    }

    try {
        const parsed = JSON.parse(form.manual_route_waypoints);

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
const manualTraceDistanceKm = computed(() => {
    if (manualTracePoints.value.length < 2) {
        return 0;
    }

    return manualTracePoints.value.slice(1).reduce((total, point, index) => {
        const previous = manualTracePoints.value[index];

        return total + haversineKm(previous.lat, previous.lng, point.lat, point.lng);
    }, 0);
});
const hasManualTraceDistance = computed(
    () => manualTracePoints.value.length >= 2,
);
const hasWeatherPreviewCoordinates = computed(() => {
    const hasLaunchPoint =
        launchLatNumber.value !== null && launchLngNumber.value !== null;
    const hasLandingPoint =
        landingLatNumber.value !== null && landingLngNumber.value !== null;

    return hasLaunchPoint || hasLandingPoint;
});
const durationHours = computed({
    get: () => {
        if (form.duration_minutes === '') {
            return '';
        }

        return String(
            Math.floor(Number.parseInt(form.duration_minutes, 10) / 60),
        );
    },
    set: (value: string) => {
        syncDuration(value, durationRemainingMinutes.value);
    },
});
const durationRemainingMinutes = computed({
    get: () => {
        if (form.duration_minutes === '') {
            return '';
        }

        return String(Number.parseInt(form.duration_minutes, 10) % 60);
    },
    set: (value: string) => {
        syncDuration(durationHours.value, value);
    },
});
const hasExpeditionMapPointSource = computed(() => {
    const hasLaunchPoint =
        launchLatNumber.value !== null && launchLngNumber.value !== null;
    const hasLandingPoint =
        landingLatNumber.value !== null && landingLngNumber.value !== null;
    const hasRouteFile = Boolean(
        form.gpx_file ||
        form.fit_file ||
        props.existingAssets.gpxName ||
        props.existingAssets.fitName,
    );

    return hasLaunchPoint || hasLandingPoint || hasRouteFile;
});
const expeditionMapWarning = computed(() => {
    if (!form.is_expedition || hasExpeditionMapPointSource.value) {
        return null;
    }

    return 'This expedition will count in your totals, but it will not appear on the world map until you attach a GPX/FIT file or place at least one point on the map.';
});
const traceHasEverBeenActive = ref(Boolean(props.formDefaults.manual_route_waypoints));

watch(
    () => form.manual_route_waypoints,
    () => {
        if (manualTracePoints.value.length === 0) {
            if (traceHasEverBeenActive.value) {
                form.launch_lat = '';
                form.launch_lng = '';
                form.landing_lat = '';
                form.landing_lng = '';
                traceHasEverBeenActive.value = false;
            }

            return;
        }

        traceHasEverBeenActive.value = true;

        const firstPoint = manualTracePoints.value[0];
        const lastPoint =
            manualTracePoints.value[manualTracePoints.value.length - 1];

        form.launch_lat = firstPoint.lat.toFixed(6);
        form.launch_lng = firstPoint.lng.toFixed(6);
        form.landing_lat =
            manualTracePoints.value.length > 1 ? lastPoint.lat.toFixed(6) : '';
        form.landing_lng =
            manualTracePoints.value.length > 1 ? lastPoint.lng.toFixed(6) : '';

        if (manualTracePoints.value.length > 1) {
            form.distance_km = formatEditableNumber(manualTraceDistanceKm.value, 2);
        }
    },
);

function assignPreviewFields(fields: Record<string, string | number | null>) {
    const weatherFieldKeys = [
        'wind_avg_ms',
        'wind_gust_ms',
        'wind_direction_deg',
        'wind_beaufort',
        'tide_state',
        'current_knots',
        'current_direction_deg',
        'wave_height_m',
        'swell_height_m',
        'swell_period_s',
        'swell_direction_deg',
        'air_temp_c',
        'sea_temp_c',
        'visibility_code',
        'weather_summary',
    ] as const;

    for (const key of weatherFieldKeys) {
        const value = fields[key];
        form[key] = value === null || value === undefined ? '' : String(value);
    }
}

function scheduleWeatherPreview() {
    if (!props.weatherAutofillAvailable) {
        weatherPreviewState.value = 'warning';
        weatherPreviewMessage.value =
            'Add your Stormglass API key first to preview weather on the form.';

        return;
    }

    if (weatherPreviewTimer) {
        clearTimeout(weatherPreviewTimer);
    }

    weatherPreviewTimer = setTimeout(() => {
        void previewWeatherNow();
    }, 320);
}

async function previewWeatherNow() {
    if (!form.autofill_weather) {
        return;
    }

    if (!form.session_date) {
        weatherPreviewState.value = 'warning';
        weatherPreviewMessage.value =
            'Pick the session date first, then Stormglass can preview the sea state.';

        return;
    }

    if (!hasWeatherPreviewCoordinates.value) {
        weatherPreviewState.value = 'warning';
        weatherPreviewMessage.value =
            'Place at least one point on the map first, then Stormglass can fill the weather right away.';

        return;
    }

    weatherPreviewAbortController?.abort();
    weatherPreviewAbortController = new AbortController();
    weatherPreviewState.value = 'loading';
    weatherPreviewMessage.value =
        'Previewing the nearest Stormglass weather point...';

    const params = new URLSearchParams({
        session_date: form.session_date,
    });

    if (form.start_time_local) {
        params.set('start_time_local', form.start_time_local);
    }

    if (form.launch_lat) {
        params.set('launch_lat', form.launch_lat);
    }

    if (form.launch_lng) {
        params.set('launch_lng', form.launch_lng);
    }

    if (form.landing_lat) {
        params.set('landing_lat', form.landing_lat);
    }

    if (form.landing_lng) {
        params.set('landing_lng', form.landing_lng);
    }

    try {
        const response = await fetch(
            `/sessions/weather-preview?${params.toString()}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: weatherPreviewAbortController.signal,
            },
        );

        const payload = (await response.json()) as {
            status?: string;
            message?: string;
            reason?: string | null;
            fields?: Record<string, string | number | null>;
            filledFields?: number;
        };

        if (!response.ok) {
            throw new Error(
                payload.message ||
                    payload.reason ||
                    'Stormglass preview failed.',
            );
        }

        if (payload.status === 'filled' && payload.fields) {
            assignPreviewFields(payload.fields);
            weatherPreviewState.value = 'filled';
            weatherPreviewMessage.value =
                payload.message ??
                `Stormglass filled ${payload.filledFields ?? 0} fields on the form.`;

            return;
        }

        weatherPreviewState.value =
            payload.status === 'failed' ? 'error' : 'warning';
        weatherPreviewMessage.value =
            payload.message ||
            payload.reason ||
            'Stormglass could not fill the weather yet.';
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        weatherPreviewState.value = 'error';
        weatherPreviewMessage.value =
            error instanceof Error
                ? error.message
                : 'Stormglass preview failed.';
    }
}

function assignFile(
    key: 'gpx_file' | 'fit_file' | 'session_photo',
    event: Event,
) {
    const target = event.target as HTMLInputElement;
    form[key] = target.files?.[0] ?? null;
}

function goToStep(stepIndex: number) {
    currentStep.value = stepIndex;
}

function addCategoryName(categoryName: string) {
    const existing = form.category_names_text
        .split(',')
        .map((name) => name.trim())
        .filter(Boolean);
    const alreadyAdded = existing.some(
        (name) => name.toLowerCase() === categoryName.toLowerCase(),
    );

    if (!alreadyAdded) {
        existing.push(categoryName);
    }

    form.category_names_text = existing.join(', ');
}

function syncDuration(hoursValue: string, minutesValue: string) {
    const hasHours = hoursValue.trim() !== '';
    const hasMinutes = minutesValue.trim() !== '';

    if (!hasHours && !hasMinutes) {
        form.duration_minutes = '';

        return;
    }

    const safeHours = Math.max(0, Number.parseInt(hoursValue || '0', 10) || 0);
    const safeMinutes = Math.max(
        0,
        Math.min(59, Number.parseInt(minutesValue || '0', 10) || 0),
    );

    form.duration_minutes = String(safeHours * 60 + safeMinutes);
}

function nextStep() {
    currentStep.value = Math.min(currentStep.value + 1, steps.length - 1);
}

function previousStep() {
    currentStep.value = Math.max(currentStep.value - 1, 0);
}

function submit() {
    if (props.mode === 'edit' && props.sessionId) {
        form.transform((data) => ({
            ...data,
            _method: 'patch',
        })).post(`/sessions/${props.sessionId}`, {
            forceFormData: true,
            preserveScroll: true,
        });

        return;
    }

    form.post('/sessions', {
        forceFormData: true,
        preserveScroll: true,
    });
}

onMounted(async () => {
    if (currentStep.value !== 3) {
        return;
    }

    await nextTick();
    notesTextarea.value?.focus();
});
</script>

<template>
    <div class="space-y-4 sm:space-y-5">
        <section
            v-if="flashSuccessMessage"
            class="journal-banner journal-banner--success-strong"
        >
            <p class="journal-kicker text-[color:#256a48]">Session saved</p>
            <p class="mt-2 text-sm leading-6 font-semibold md:text-base">
                {{ flashSuccessMessage }}
            </p>
        </section>

        <section
            v-if="formErrorEntries.length"
            class="journal-banner journal-banner--danger"
        >
            <p class="journal-kicker">Session not saved</p>
            <p class="mt-2 text-sm leading-6 font-semibold md:text-base">
                A required detail needs fixing. I moved you to the first step
                with a problem.
            </p>
            <ul class="mt-3 space-y-1 text-sm leading-6">
                <li
                    v-for="[field, message] in formErrorEntries.slice(0, 3)"
                    :key="field"
                >
                    {{ message }}
                </li>
            </ul>
        </section>

        <section
            class="journal-panel px-4 py-4 sm:px-5 sm:py-5 md:px-6 md:py-6"
        >
            <div class="space-y-3">
                <div class="space-y-3">
                    <p class="journal-kicker">
                        {{ mode === 'create' ? 'Add session' : 'Edit session' }}
                    </p>
                    <div class="space-y-2">
                        <h2
                            class="text-[1.75rem] leading-[0.96] sm:text-[clamp(1.9rem,3vw,2.5rem)]"
                        >
                            {{ pageTitle }}
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            {{ pageDescription }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="journal-banner journal-banner--soft">
            Keep this flow lightweight: journey first, sea next, then rescue and
            notes once the core paddle is captured.
        </section>

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <section class="journal-panel px-4 py-4 sm:px-5 sm:py-5 md:px-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p
                        class="text-sm font-medium text-[color:var(--journal-muted)]"
                    >
                        {{ stepProgressLabel }}
                    </p>
                    <span
                        class="w-full text-xs font-medium text-[color:var(--journal-muted)] sm:w-auto sm:text-sm"
                        >Required: title, date, and distance, a manual trace, or a route file</span
                    >
                </div>

                <div
                    class="mt-4 flex gap-3 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] md:mt-5 md:grid md:grid-cols-2 md:overflow-visible md:pb-0 xl:grid-cols-4 [&::-webkit-scrollbar]:hidden"
                >
                    <button
                        v-for="(step, index) in steps"
                        :key="step.key"
                        type="button"
                        :class="[
                            'journal-step',
                            'min-w-[220px] shrink-0 md:min-w-0',
                            currentStep === index ? 'journal-step--active' : '',
                        ]"
                        @click="goToStep(index)"
                    >
                        <span class="journal-kicker">{{
                            `Step ${index + 1}`
                        }}</span>
                        <strong
                            class="text-[1rem] text-[color:var(--journal-text)]"
                            >{{ step.title }}</strong
                        >
                        <span
                            class="text-sm leading-6 text-[color:var(--journal-muted)]"
                            >{{ step.description }}</span
                        >
                    </button>
                </div>
            </section>

            <section class="journal-panel px-4 py-4 sm:px-5 sm:py-5 md:px-6">
                <div
                    class="mb-6 flex flex-wrap items-start justify-between gap-3"
                >
                    <div class="space-y-2">
                        <p class="journal-kicker">
                            {{ currentStepMeta.title }}
                        </p>
                        <h3 class="text-[1.5rem] leading-none sm:text-[1.9rem]">
                            {{ currentStepMeta.title }}
                        </h3>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            {{ currentStepMeta.description }}
                        </p>
                    </div>

                    <span
                        class="text-sm font-medium text-[color:var(--journal-muted)]"
                    >
                        {{
                            currentStep === steps.length - 1
                                ? 'Ready to save'
                                : 'Keep going'
                        }}
                    </span>
                </div>

                <div
                    v-show="currentStep === 0"
                    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <div class="xl:col-span-2">
                        <label class="journal-field-label" for="title"
                            >Title</label
                        >
                        <input
                            id="title"
                            v-model="form.title"
                            class="journal-input"
                            placeholder="Morning benchmark paddle"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="route_category"
                            >Paddle type</label
                        >
                        <select
                            id="route_category"
                            v-model="form.route_category"
                            class="journal-select"
                        >
                            <option
                                v-for="option in routeCategoryOptions"
                                :key="option"
                                :value="option"
                            >
                                {{ option }}
                            </option>
                        </select>
                        <InputError :message="form.errors.route_category" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="session_date"
                            >Date</label
                        >
                        <input
                            id="session_date"
                            v-model="form.session_date"
                            type="date"
                            class="journal-input"
                        />
                        <InputError :message="form.errors.session_date" />
                    </div>

                    <div>
                        <label
                            class="journal-field-label"
                            for="start_time_local"
                            >Start time</label
                        >
                        <input
                            id="start_time_local"
                            v-model="form.start_time_local"
                            type="time"
                            class="journal-input"
                        />
                        <InputError :message="form.errors.start_time_local" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="body_of_water"
                            >Body of water</label
                        >
                        <select
                            id="body_of_water"
                            v-model="form.body_of_water"
                            class="journal-select"
                        >
                            <option value="">Select...</option>
                            <option
                                v-for="option in bodyOfWaterOptions"
                                :key="option"
                                :value="option"
                            >
                                {{ option }}
                            </option>
                        </select>
                        <InputError :message="form.errors.body_of_water" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="launch_name"
                            >Launch name</label
                        >
                        <input
                            id="launch_name"
                            v-model="form.launch_name"
                            class="journal-input"
                            placeholder="Optional"
                        />
                        <InputError :message="form.errors.launch_name" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="landing_name"
                            >Landing name</label
                        >
                        <input
                            id="landing_name"
                            v-model="form.landing_name"
                            class="journal-input"
                            placeholder="Optional"
                        />
                        <InputError :message="form.errors.landing_name" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="area_name"
                            >Area</label
                        >
                        <input
                            id="area_name"
                            v-model="form.area_name"
                            class="journal-input"
                            placeholder="Faxafloi"
                        />
                        <InputError :message="form.errors.area_name" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="kayak_used"
                            >Kayak used</label
                        >
                        <input
                            id="kayak_used"
                            v-model="form.kayak_used"
                            class="journal-input"
                            list="kayaks-owned-options"
                            placeholder="Select or type kayak model"
                        />
                        <datalist id="kayaks-owned-options">
                            <option
                                v-for="kayak in profile.kayaksOwned ?? []"
                                :key="kayak"
                                :value="kayak"
                            />
                        </datalist>
                        <InputError :message="form.errors.kayak_used" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="paddle_used"
                            >Paddle used</label
                        >
                        <input
                            id="paddle_used"
                            v-model="form.paddle_used"
                            class="journal-input"
                            list="paddles-owned-options"
                            placeholder="Select or type paddle model"
                        />
                        <datalist id="paddles-owned-options">
                            <option
                                v-for="paddle in profile.paddlesOwned ?? []"
                                :key="paddle"
                                :value="paddle"
                            />
                        </datalist>
                        <InputError :message="form.errors.paddle_used" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="distance_km"
                            >Distance ({{ distanceUnitLabel }})</label
                        >
                        <input
                            id="distance_km"
                            v-model="distanceDisplay"
                            type="text"
                            inputmode="decimal"
                            class="journal-input"
                            :readonly="hasManualTraceDistance"
                            :class="
                                hasManualTraceDistance
                                    ? 'bg-[color:var(--journal-surface-soft)] text-[color:var(--journal-muted)]'
                                    : ''
                            "
                            :placeholder="
                                hasManualTraceDistance
                                    ? 'Calculated from trace'
                                    : '9.3'
                            "
                        />
                        <p
                            class="mt-2 text-xs leading-5 text-[color:var(--journal-muted)]"
                        >
                            {{
                                hasManualTraceDistance
                                    ? 'Distance is being calculated from the traced route below.'
                                    : 'Type a distance only if you are not tracing the route on the map.'
                            }}
                        </p>
                        <InputError :message="form.errors.distance_km" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="duration_hours"
                            >Duration</label
                        >
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-2">
                                <span
                                    class="text-xs font-semibold tracking-[0.14em] text-[color:var(--journal-faint)] uppercase"
                                    >Hours</span
                                >
                                <input
                                    id="duration_hours"
                                    v-model="durationHours"
                                    type="number"
                                    min="0"
                                    class="journal-input"
                                    placeholder="1"
                                />
                            </div>
                            <div class="space-y-2">
                                <span
                                    class="text-xs font-semibold tracking-[0.14em] text-[color:var(--journal-faint)] uppercase"
                                    >Minutes</span
                                >
                                <input
                                    id="duration_minutes"
                                    v-model="durationRemainingMinutes"
                                    type="number"
                                    min="0"
                                    max="59"
                                    class="journal-input"
                                    placeholder="30"
                                />
                            </div>
                        </div>
                        <InputError :message="form.errors.duration_minutes" />
                    </div>

                    <div class="sm:col-span-2 xl:col-span-2">
                        <label class="journal-field-label" for="route_tags_text"
                            >Tags</label
                        >
                        <input
                            id="route_tags_text"
                            v-model="form.route_tags_text"
                            class="journal-input"
                            placeholder="benchmark, faxafloi, spring, harbor"
                        />
                        <InputError :message="form.errors.route_tags_text" />
                    </div>

                    <div class="sm:col-span-2 xl:col-span-2">
                        <label
                            class="journal-field-label"
                            for="category_names_text"
                            >Collections / folders</label
                        >
                        <input
                            id="category_names_text"
                            v-model="form.category_names_text"
                            class="journal-input"
                            placeholder="Anglesey 2026, Club paddles"
                        />
                        <p
                            class="mt-2 text-xs leading-5 text-[color:var(--journal-muted)]"
                        >
                            Separate with commas. New collection names are
                            created automatically and appear in Library.
                        </p>
                        <div
                            v-if="profile.sessionCategories?.length"
                            class="mt-2 flex flex-wrap gap-2"
                        >
                            <button
                                v-for="categoryName in profile.sessionCategories"
                                :key="categoryName"
                                type="button"
                                class="journal-chip transition hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                                @click="addCategoryName(categoryName)"
                            >
                                {{ categoryName }}
                            </button>
                        </div>
                        <InputError
                            :message="form.errors.category_names_text"
                        />
                    </div>

                    <div class="sm:col-span-2 xl:col-span-1">
                        <label class="journal-field-label" for="partners_text"
                            >Partners</label
                        >
                        <input
                            id="partners_text"
                            v-model="form.partners_text"
                            class="journal-input"
                            placeholder="Anna, team night paddle"
                        />
                        <InputError :message="form.errors.partners_text" />
                    </div>

                    <div class="sm:col-span-2 xl:col-span-3">
                        <SessionLocationPicker
                            v-if="currentStep === 0"
                            :launch-lat="launchLatNumber"
                            :launch-lng="launchLngNumber"
                            :landing-lat="landingLatNumber"
                            :landing-lng="landingLngNumber"
                            :route-waypoints-json="form.manual_route_waypoints"
                            :default-view="profile.defaultMapView"
                            @update:launch-lat="form.launch_lat = $event"
                            @update:launch-lng="form.launch_lng = $event"
                            @update:landing-lat="form.landing_lat = $event"
                            @update:landing-lng="form.landing_lng = $event"
                            @update:route-waypoints-json="
                                form.manual_route_waypoints = $event
                            "
                        />
                    </div>
                </div>

                <div v-show="currentStep === 1" class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <label
                            class="flex items-start gap-3 rounded-[22px] border border-[color:var(--journal-line)] bg-white/72 px-4 py-4 text-sm text-[color:var(--journal-text)] sm:col-span-2 xl:col-span-4"
                            :class="
                                !weatherAutofillAvailable ? 'opacity-70' : ''
                            "
                        >
                            <input
                                v-model="form.autofill_weather"
                                type="checkbox"
                                class="mt-1 size-4 rounded border-[color:var(--journal-line)]"
                                :disabled="!weatherAutofillAvailable"
                            />
                            <span class="space-y-1">
                                <strong class="block font-medium"
                                    >Fill weather from Stormglass now and on
                                    save</strong
                                >
                                <span
                                    class="block text-[color:var(--journal-muted)]"
                                >
                                    Uses the current session point and time to
                                    preview wind, tide, swell, current,
                                    temperatures, Beaufort, and the
                                    environmental checklist before you save.
                                    <template v-if="!weatherAutofillAvailable">
                                        Add your Stormglass API key first to
                                        enable this.</template
                                    >
                                </span>
                            </span>
                        </label>

                        <div
                            v-if="
                                weatherPreviewState !== 'idle' &&
                                weatherPreviewMessage
                            "
                            class="journal-banner text-sm sm:col-span-2 xl:col-span-4"
                            :class="{
                                'journal-banner--soft':
                                    weatherPreviewState === 'loading',
                                'journal-banner--danger':
                                    weatherPreviewState === 'warning' ||
                                    weatherPreviewState === 'error',
                            }"
                        >
                            {{ weatherPreviewMessage }}
                        </div>

                        <div>
                            <label class="journal-field-label" for="wind_avg_ms"
                                >Wind avg ({{ windUnitLabel }})</label
                            >
                            <input
                                id="wind_avg_ms"
                                v-model="windAvgDisplay"
                                type="number"
                                step="0.1"
                                min="0"
                                class="journal-input"
                            />
                            <InputError :message="form.errors.wind_avg_ms" />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="wind_gust_ms"
                                >Wind gust ({{ windUnitLabel }})</label
                            >
                            <input
                                id="wind_gust_ms"
                                v-model="windGustDisplay"
                                type="number"
                                step="0.1"
                                min="0"
                                class="journal-input"
                            />
                            <InputError :message="form.errors.wind_gust_ms" />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="wind_direction_deg"
                                >Wind direction</label
                            >
                            <input
                                id="wind_direction_deg"
                                v-model="form.wind_direction_deg"
                                type="number"
                                min="0"
                                max="360"
                                class="journal-input"
                            />
                            <InputError
                                :message="form.errors.wind_direction_deg"
                            />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="wind_beaufort"
                                >Beaufort</label
                            >
                            <input
                                id="wind_beaufort"
                                v-model="form.wind_beaufort"
                                type="number"
                                min="0"
                                max="12"
                                class="journal-input"
                            />
                            <InputError :message="form.errors.wind_beaufort" />
                        </div>

                        <div>
                            <label class="journal-field-label" for="tide_state"
                                >Tide</label
                            >
                            <select
                                id="tide_state"
                                v-model="form.tide_state"
                                class="journal-select"
                            >
                                <option value="">Select...</option>
                                <option
                                    v-for="option in tideStateOptions"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ option }}
                                </option>
                            </select>
                            <InputError :message="form.errors.tide_state" />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="current_knots"
                                >Current ({{ currentUnitLabel }})</label
                            >
                            <input
                                id="current_knots"
                                v-model="currentDisplay"
                                type="number"
                                step="0.1"
                                min="0"
                                class="journal-input"
                            />
                            <InputError :message="form.errors.current_knots" />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="wave_height_m"
                                >Wave (m)</label
                            >
                            <input
                                id="wave_height_m"
                                v-model="form.wave_height_m"
                                type="number"
                                step="0.1"
                                min="0"
                                class="journal-input"
                            />
                            <InputError :message="form.errors.wave_height_m" />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="swell_height_m"
                                >Swell (m)</label
                            >
                            <input
                                id="swell_height_m"
                                v-model="form.swell_height_m"
                                type="number"
                                step="0.1"
                                min="0"
                                class="journal-input"
                            />
                            <InputError :message="form.errors.swell_height_m" />
                        </div>

                        <div>
                            <label
                                class="journal-field-label"
                                for="swell_period_s"
                                >Swell period (s)</label
                            >
                            <input
                                id="swell_period_s"
                                v-model="form.swell_period_s"
                                type="number"
                                step="0.1"
                                min="0"
                                class="journal-input"
                            />
                            <InputError :message="form.errors.swell_period_s" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="air_temp_c"
                                >Air temp ({{ temperatureUnitLabel }})</label
                            >
                            <input
                                id="air_temp_c"
                                v-model="airTemperatureDisplay"
                                type="number"
                                step="0.1"
                                class="journal-input"
                            />
                            <InputError :message="form.errors.air_temp_c" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="sea_temp_c"
                                >Sea temp ({{ temperatureUnitLabel }})</label
                            >
                            <input
                                id="sea_temp_c"
                                v-model="seaTemperatureDisplay"
                                type="number"
                                step="0.1"
                                class="journal-input"
                            />
                            <InputError :message="form.errors.sea_temp_c" />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="visibility_code"
                                >Visibility</label
                            >
                            <select
                                id="visibility_code"
                                v-model="form.visibility_code"
                                class="journal-select"
                            >
                                <option value="">Select...</option>
                                <option
                                    v-for="option in visibilityOptions"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ option }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.visibility_code"
                            />
                        </div>
                    </div>

                    <section
                        class="rounded-[24px] border border-[color:var(--journal-line)] bg-white/72 p-4"
                    >
                        <div class="space-y-2">
                            <p class="journal-kicker">
                                Environmental conditions
                            </p>
                            <h4 class="text-[1.35rem] leading-none">
                                Session checklist
                            </h4>
                        </div>

                        <div
                            class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                        >
                            <div>
                                <label
                                    class="journal-field-label"
                                    for="rain_severity"
                                    >Rain</label
                                >
                                <select
                                    id="rain_severity"
                                    v-model="form.rain_severity"
                                    class="journal-select"
                                >
                                    <option value="">Not set</option>
                                    <option
                                        v-for="option in severityOptions"
                                        :key="option"
                                        :value="option"
                                    >
                                        {{ option }}
                                    </option>
                                </select>
                                <InputError
                                    :message="form.errors.rain_severity"
                                />
                            </div>
                            <div>
                                <label
                                    class="journal-field-label"
                                    for="wind_severity"
                                    >Wind</label
                                >
                                <select
                                    id="wind_severity"
                                    v-model="form.wind_severity"
                                    class="journal-select"
                                >
                                    <option value="">Not set</option>
                                    <option
                                        v-for="option in severityOptions"
                                        :key="option"
                                        :value="option"
                                    >
                                        {{ option }}
                                    </option>
                                </select>
                                <InputError
                                    :message="form.errors.wind_severity"
                                />
                            </div>
                            <div>
                                <label
                                    class="journal-field-label"
                                    for="temperature_severity"
                                    >Temperature</label
                                >
                                <select
                                    id="temperature_severity"
                                    v-model="form.temperature_severity"
                                    class="journal-select"
                                >
                                    <option value="">Not set</option>
                                    <option
                                        v-for="option in severityOptions"
                                        :key="option"
                                        :value="option"
                                    >
                                        {{ option }}
                                    </option>
                                </select>
                                <InputError
                                    :message="form.errors.temperature_severity"
                                />
                            </div>
                            <div>
                                <label
                                    class="journal-field-label"
                                    for="forecast_severity"
                                    >Forecast</label
                                >
                                <select
                                    id="forecast_severity"
                                    v-model="form.forecast_severity"
                                    class="journal-select"
                                >
                                    <option value="">Not set</option>
                                    <option
                                        v-for="option in severityOptions"
                                        :key="option"
                                        :value="option"
                                    >
                                        {{ option }}
                                    </option>
                                </select>
                                <InputError
                                    :message="form.errors.forecast_severity"
                                />
                            </div>
                        </div>
                    </section>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <label
                                class="journal-field-label"
                                for="weather_summary"
                                >Conditions summary</label
                            >
                            <textarea
                                id="weather_summary"
                                v-model="form.weather_summary"
                                class="journal-textarea"
                                placeholder="Cold water, light chop, onshore breeze, forecast held steady."
                            />
                            <InputError
                                :message="form.errors.weather_summary"
                            />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="route_summary"
                                >Route summary</label
                            >
                            <textarea
                                id="route_summary"
                                v-model="form.route_summary"
                                class="journal-textarea"
                                placeholder="Benchmark loop from Reykjavik with a short stop outside the harbor."
                            />
                            <InputError :message="form.errors.route_summary" />
                        </div>
                    </div>
                </div>

                <div v-show="currentStep === 2" class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label
                                class="journal-field-label"
                                for="successful_rolls_count"
                                >Successful rolls</label
                            >
                            <input
                                id="successful_rolls_count"
                                v-model="form.successful_rolls_count"
                                type="number"
                                min="0"
                                class="journal-input"
                            />
                            <InputError
                                :message="form.errors.successful_rolls_count"
                            />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="wet_exits_count"
                                >Wet exits (swims)</label
                            >
                            <input
                                id="wet_exits_count"
                                v-model="form.wet_exits_count"
                                type="number"
                                min="0"
                                class="journal-input"
                            />
                            <InputError
                                :message="form.errors.wet_exits_count"
                            />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="tow_rescues_count"
                                >Tow rescues</label
                            >
                            <input
                                id="tow_rescues_count"
                                v-model="form.tow_rescues_count"
                                type="number"
                                min="0"
                                class="journal-input"
                            />
                            <InputError
                                :message="form.errors.tow_rescues_count"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="journal-field-label" for="skills_text"
                            >Skills practiced</label
                        >
                        <input
                            id="skills_text"
                            v-model="form.skills_text"
                            class="journal-input"
                            placeholder="rolling, surf launch, navigation, towing"
                        />
                        <InputError :message="form.errors.skills_text" />
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <label
                                class="journal-field-label"
                                for="what_went_well"
                                >What went well</label
                            >
                            <textarea
                                id="what_went_well"
                                v-model="form.what_went_well"
                                class="journal-textarea"
                            />
                            <InputError :message="form.errors.what_went_well" />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="improve_next"
                                >Improve next time</label
                            >
                            <textarea
                                id="improve_next"
                                v-model="form.improve_next"
                                class="journal-textarea"
                            />
                            <InputError :message="form.errors.improve_next" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label
                                class="journal-field-label"
                                for="confidence_score"
                                >Confidence (1-5)</label
                            >
                            <input
                                id="confidence_score"
                                v-model="form.confidence_score"
                                type="number"
                                min="1"
                                max="5"
                                class="journal-input"
                            />
                            <InputError
                                :message="form.errors.confidence_score"
                            />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="fatigue_score"
                                >Fatigue (1-5)</label
                            >
                            <input
                                id="fatigue_score"
                                v-model="form.fatigue_score"
                                type="number"
                                min="1"
                                max="5"
                                class="journal-input"
                            />
                            <InputError :message="form.errors.fatigue_score" />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="decision_score"
                                >Decision quality (1-5)</label
                            >
                            <input
                                id="decision_score"
                                v-model="form.decision_score"
                                type="number"
                                min="1"
                                max="5"
                                class="journal-input"
                            />
                            <InputError :message="form.errors.decision_score" />
                        </div>
                    </div>
                </div>

                <div v-show="currentStep === 3" class="space-y-5">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label
                            class="flex items-center gap-3 rounded-[22px] border border-[color:var(--journal-line)] bg-white/72 px-4 py-4 text-sm font-medium text-[color:var(--journal-text)]"
                        >
                            <input
                                v-model="form.is_expedition"
                                type="checkbox"
                                class="size-4 rounded border-[color:var(--journal-line)]"
                            />
                            Tag as expedition / multiday
                        </label>
                    </div>

                    <div v-if="form.is_expedition" class="max-w-sm">
                        <label class="journal-field-label" for="expedition_days"
                            >Days out</label
                        >
                        <input
                            id="expedition_days"
                            v-model="form.expedition_days"
                            type="number"
                            min="2"
                            max="100"
                            class="journal-input"
                        />
                        <InputError :message="form.errors.expedition_days" />
                    </div>

                    <section
                        v-if="expeditionMapWarning"
                        class="journal-banner journal-banner--danger"
                    >
                        {{ expeditionMapWarning }}
                    </section>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <div>
                            <label
                                class="journal-field-label"
                                for="notes_public"
                                >Observations</label
                            >
                            <textarea
                                id="notes_public"
                                ref="notesTextarea"
                                v-model="form.notes_public"
                                class="journal-textarea"
                                placeholder="What should improve next time, what went wrong, and any mistakes or lessons from the paddle."
                            />
                            <InputError :message="form.errors.notes_public" />
                        </div>
                        <div>
                            <label
                                class="journal-field-label"
                                for="expedition_notes"
                                >Expedition notes</label
                            >
                            <textarea
                                id="expedition_notes"
                                v-model="form.expedition_notes"
                                class="journal-textarea"
                                placeholder="Food, gear, camp, and multiday notes for next time."
                            />
                            <InputError
                                :message="form.errors.expedition_notes"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <div>
                            <label class="journal-field-label" for="gpx_file"
                                >GPX file</label
                            >
                            <input
                                id="gpx_file"
                                type="file"
                                accept=".gpx,.xml"
                                :class="fileInputClass"
                                @change="assignFile('gpx_file', $event)"
                            />
                            <p
                                v-if="form.gpx_file || existingAssets.gpxName"
                                class="mt-2 text-xs text-[color:var(--journal-muted)]"
                            >
                                {{
                                    form.gpx_file?.name ??
                                    existingAssets.gpxName
                                }}
                            </p>
                            <InputError :message="form.errors.gpx_file" />
                        </div>

                        <div>
                            <label class="journal-field-label" for="fit_file"
                                >FIT file</label
                            >
                            <input
                                id="fit_file"
                                type="file"
                                accept=".fit"
                                :class="fileInputClass"
                                @change="assignFile('fit_file', $event)"
                            />
                            <p
                                v-if="form.fit_file || existingAssets.fitName"
                                class="mt-2 text-xs text-[color:var(--journal-muted)]"
                            >
                                {{
                                    form.fit_file?.name ??
                                    existingAssets.fitName
                                }}
                            </p>
                            <InputError :message="form.errors.fit_file" />
                        </div>

                        <div>
                            <label
                                class="journal-field-label"
                                for="session_photo"
                                >Session photo</label
                            >
                            <input
                                id="session_photo"
                                type="file"
                                accept="image/*"
                                :class="fileInputClass"
                                @change="assignFile('session_photo', $event)"
                            />
                            <p
                                v-if="
                                    form.session_photo ||
                                    existingAssets.photoName
                                "
                                class="mt-2 text-xs text-[color:var(--journal-muted)]"
                            >
                                {{
                                    form.session_photo?.name ??
                                    existingAssets.photoName
                                }}
                            </p>
                            <InputError :message="form.errors.session_photo" />
                        </div>
                    </div>

                    <div
                        v-if="photoPreviewUrl"
                        class="overflow-hidden rounded-[24px] border border-[color:var(--journal-line)] bg-white/72"
                    >
                        <img
                            :src="photoPreviewUrl"
                            alt="Session photo preview"
                            class="h-52 w-full object-cover sm:h-64"
                        />
                    </div>
                </div>
            </section>

            <section
                class="journal-panel sticky bottom-3 z-20 flex flex-col gap-3 px-4 py-4 backdrop-blur md:static md:flex-row md:flex-wrap md:items-center md:justify-between md:px-6 md:py-5"
            >
                <p class="text-sm text-[color:var(--journal-muted)]">
                    Minimum save requirement: title, date, and distance or a
                    route file.
                </p>

                <div
                    class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center md:w-auto"
                >
                    <button
                        type="button"
                        class="journal-utility-link w-full disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                        :disabled="currentStep === 0"
                        @click="previousStep"
                    >
                        Back
                    </button>

                    <button
                        v-if="currentStep < steps.length - 1"
                        type="button"
                        class="journal-primary-link w-full sm:w-auto"
                        @click="nextStep"
                    >
                        Next
                    </button>

                    <button
                        v-else
                        type="submit"
                        class="journal-primary-link w-full disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : submitLabel }}
                    </button>
                </div>
            </section>
        </form>
    </div>
</template>
