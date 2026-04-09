<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

interface ProfileSummary {
    name: string;
    homeWater: string;
    timezone: string;
}

interface ExistingAssets {
    gpxName: string | null;
    fitName: string | null;
    photoName: string | null;
    photoUrl: string | null;
}

interface SessionFormDefaults {
    title: string;
    session_date: string;
    start_time_local: string;
    launch_name: string;
    landing_name: string;
    area_name: string;
    route_category: string;
    body_of_water: string;
    distance_km: string;
    duration_minutes: string;
    moving_minutes: string;
    wind_avg_ms: string;
    wind_gust_ms: string;
    wind_direction_deg: string;
    wind_beaufort: string;
    tide_state: string;
    current_knots: string;
    current_direction_deg: string;
    wave_height_m: string;
    swell_height_m: string;
    swell_period_s: string;
    swell_direction_deg: string;
    air_temp_c: string;
    sea_temp_c: string;
    rain_severity: string;
    wind_severity: string;
    temperature_severity: string;
    forecast_severity: string;
    visibility_code: string;
    weather_summary: string;
    route_summary: string;
    route_tags_text: string;
    partners_text: string;
    skills_text: string;
    successful_rolls_count: string;
    wet_exits_count: string;
    tow_rescues_count: string;
    what_went_well: string;
    improve_next: string;
    confidence_score: string;
    fatigue_score: string;
    decision_score: string;
    notes_public: string;
    notes_private: string;
    is_expedition: boolean;
    expedition_days: string;
    expedition_notes: string;
    is_public: boolean;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    profile: ProfileSummary;
    formDefaults: SessionFormDefaults;
    existingAssets: ExistingAssets;
    sessionId?: number;
}>();

const form = useForm({
    ...props.formDefaults,
    gpx_file: null as File | null,
    fit_file: null as File | null,
    session_photo: null as File | null,
});

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
        description: 'Visibility, expedition fields, notes, GPX/FIT, and a photo.',
    },
] as const;

const currentStep = ref(0);
const currentStepMeta = computed(() => steps[currentStep.value]);

const routeCategoryOptions = ['journey', 'training', 'benchmark', 'navigation', 'rescue-practice', 'expedition'];
const bodyOfWaterOptions = ['sea', 'ocean', 'fjord', 'lake', 'river', 'canal', 'other'];
const severityOptions = ['low', 'moderate', 'high', 'extreme'];
const tideStateOptions = ['slack', 'flooding', 'high', 'ebbing', 'low'];
const visibilityOptions = ['clear', 'good', 'moderate', 'poor', 'fog'];

const stepFieldMap: Record<string, number> = {
    title: 0,
    session_date: 0,
    start_time_local: 0,
    launch_name: 0,
    landing_name: 0,
    area_name: 0,
    route_category: 0,
    body_of_water: 0,
    distance_km: 0,
    duration_minutes: 0,
    moving_minutes: 0,
    route_tags_text: 0,
    partners_text: 0,

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
});

const pageTitle = computed(() => (props.mode === 'create' ? 'Add paddle session' : 'Edit paddle session'));
const pageDescription = computed(() =>
    props.mode === 'create'
        ? 'Capture the paddle, mark the sea state, and attach the files.'
        : 'Refine the paddle, notes, files, and expedition fields.',
);

const submitLabel = computed(() => (props.mode === 'create' ? 'Save session' : 'Update session'));
const fileInputClass =
    'journal-input file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium';
const metaPills = computed(() => [
    props.profile.homeWater,
    props.profile.timezone,
    '4-step flow',
    'GPX / FIT',
].filter(Boolean));
const stepProgressLabel = computed(() => `Step ${currentStep.value + 1} of ${steps.length}`);

function assignFile(key: 'gpx_file' | 'fit_file' | 'session_photo', event: Event) {
    const target = event.target as HTMLInputElement;
    form[key] = target.files?.[0] ?? null;
}

function goToStep(stepIndex: number) {
    currentStep.value = stepIndex;
}

function nextStep() {
    currentStep.value = Math.min(currentStep.value + 1, steps.length - 1);
}

function previousStep() {
    currentStep.value = Math.max(currentStep.value - 1, 0);
}

function submit() {
    if (props.mode === 'edit' && props.sessionId) {
        form
            .transform((data) => ({
                ...data,
                _method: 'patch',
            }))
            .post(`/sessions/${props.sessionId}`, {
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
</script>

<template>
    <div class="space-y-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <p class="journal-kicker">{{ mode === 'create' ? 'Add session' : 'Edit session' }}</p>
                    <div class="space-y-2">
                        <h2 class="text-[clamp(1.9rem,3vw,2.5rem)] leading-[0.96]">
                            {{ pageTitle }}
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            {{ pageDescription }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="pill in metaPills"
                        :key="pill"
                        class="journal-chip"
                        :class="pill === profile.homeWater ? 'journal-chip--primary' : ''"
                    >
                        {{ pill }}
                    </span>
                </div>
            </div>
        </section>

        <section class="journal-banner journal-banner--soft">
            Keep this flow lightweight: journey first, sea next, then rescue and notes once the core paddle is captured.
        </section>

        <form class="space-y-5" @submit.prevent="submit">
            <section class="journal-panel px-5 py-5 md:px-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-medium text-[color:var(--journal-muted)]">
                        {{ stepProgressLabel }}
                    </p>
                    <span class="text-sm font-medium text-[color:var(--journal-muted)]">Required: title, date, launch, distance or route file</span>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <button
                        v-for="(step, index) in steps"
                        :key="step.key"
                        type="button"
                        :class="['journal-step', currentStep === index ? 'journal-step--active' : '']"
                        @click="goToStep(index)"
                    >
                        <span class="journal-kicker">{{ `Step ${index + 1}` }}</span>
                        <strong class="text-[1rem] text-[color:var(--journal-text)]">{{ step.title }}</strong>
                        <span class="text-sm leading-6 text-[color:var(--journal-muted)]">{{ step.description }}</span>
                    </button>
                </div>
            </section>

            <section class="journal-panel px-5 py-5 md:px-6">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-2">
                        <p class="journal-kicker">{{ currentStepMeta.title }}</p>
                        <h3 class="text-[1.9rem] leading-none">{{ currentStepMeta.title }}</h3>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            {{ currentStepMeta.description }}
                        </p>
                    </div>

                    <span class="text-sm font-medium text-[color:var(--journal-muted)]">
                        {{ currentStep === steps.length - 1 ? 'Ready to save' : 'Keep going' }}
                    </span>
                </div>

                <div v-show="currentStep === 0" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div class="xl:col-span-2">
                        <label class="journal-field-label" for="title">Title</label>
                        <input id="title" v-model="form.title" class="journal-input" placeholder="Morning benchmark paddle" />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="route_category">Category</label>
                        <select id="route_category" v-model="form.route_category" class="journal-select">
                            <option v-for="option in routeCategoryOptions" :key="option" :value="option">
                                {{ option }}
                            </option>
                        </select>
                        <InputError :message="form.errors.route_category" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="session_date">Date</label>
                        <input id="session_date" v-model="form.session_date" type="date" class="journal-input" />
                        <InputError :message="form.errors.session_date" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="start_time_local">Start time</label>
                        <input id="start_time_local" v-model="form.start_time_local" type="time" class="journal-input" />
                        <InputError :message="form.errors.start_time_local" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="body_of_water">Body of water</label>
                        <select id="body_of_water" v-model="form.body_of_water" class="journal-select">
                            <option value="">Select...</option>
                            <option v-for="option in bodyOfWaterOptions" :key="option" :value="option">
                                {{ option }}
                            </option>
                        </select>
                        <InputError :message="form.errors.body_of_water" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="launch_name">Launch</label>
                        <input id="launch_name" v-model="form.launch_name" class="journal-input" placeholder="Reykjavik" />
                        <InputError :message="form.errors.launch_name" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="landing_name">Landing</label>
                        <input id="landing_name" v-model="form.landing_name" class="journal-input" placeholder="Reykjavik" />
                        <InputError :message="form.errors.landing_name" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="area_name">Area</label>
                        <input id="area_name" v-model="form.area_name" class="journal-input" placeholder="Faxafloi" />
                        <InputError :message="form.errors.area_name" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="distance_km">Distance (km)</label>
                        <input id="distance_km" v-model="form.distance_km" type="number" step="0.1" min="0" class="journal-input" />
                        <InputError :message="form.errors.distance_km" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="duration_minutes">Duration (min)</label>
                        <input id="duration_minutes" v-model="form.duration_minutes" type="number" min="0" class="journal-input" />
                        <InputError :message="form.errors.duration_minutes" />
                    </div>

                    <div>
                        <label class="journal-field-label" for="moving_minutes">Moving time (min)</label>
                        <input id="moving_minutes" v-model="form.moving_minutes" type="number" min="0" class="journal-input" />
                        <InputError :message="form.errors.moving_minutes" />
                    </div>

                    <div class="md:col-span-2 xl:col-span-2">
                        <label class="journal-field-label" for="route_tags_text">Tags</label>
                        <input
                            id="route_tags_text"
                            v-model="form.route_tags_text"
                            class="journal-input"
                            placeholder="benchmark, faxafloi, spring, harbor"
                        />
                        <InputError :message="form.errors.route_tags_text" />
                    </div>

                    <div class="md:col-span-2 xl:col-span-1">
                        <label class="journal-field-label" for="partners_text">Partners</label>
                        <input
                            id="partners_text"
                            v-model="form.partners_text"
                            class="journal-input"
                            placeholder="Anna, team night paddle"
                        />
                        <InputError :message="form.errors.partners_text" />
                    </div>
                </div>

                <div v-show="currentStep === 1" class="space-y-5">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label class="journal-field-label" for="wind_avg_ms">Wind avg (m/s)</label>
                            <input id="wind_avg_ms" v-model="form.wind_avg_ms" type="number" step="0.1" min="0" class="journal-input" />
                            <InputError :message="form.errors.wind_avg_ms" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="wind_gust_ms">Wind gust (m/s)</label>
                            <input id="wind_gust_ms" v-model="form.wind_gust_ms" type="number" step="0.1" min="0" class="journal-input" />
                            <InputError :message="form.errors.wind_gust_ms" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="wind_direction_deg">Wind direction</label>
                            <input id="wind_direction_deg" v-model="form.wind_direction_deg" type="number" min="0" max="360" class="journal-input" />
                            <InputError :message="form.errors.wind_direction_deg" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="wind_beaufort">Beaufort</label>
                            <input id="wind_beaufort" v-model="form.wind_beaufort" type="number" min="0" max="12" class="journal-input" />
                            <InputError :message="form.errors.wind_beaufort" />
                        </div>

                        <div>
                            <label class="journal-field-label" for="tide_state">Tide</label>
                            <select id="tide_state" v-model="form.tide_state" class="journal-select">
                                <option value="">Select...</option>
                                <option v-for="option in tideStateOptions" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                            <InputError :message="form.errors.tide_state" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="current_knots">Current (kt)</label>
                            <input id="current_knots" v-model="form.current_knots" type="number" step="0.1" min="0" class="journal-input" />
                            <InputError :message="form.errors.current_knots" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="wave_height_m">Wave (m)</label>
                            <input id="wave_height_m" v-model="form.wave_height_m" type="number" step="0.1" min="0" class="journal-input" />
                            <InputError :message="form.errors.wave_height_m" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="swell_height_m">Swell (m)</label>
                            <input id="swell_height_m" v-model="form.swell_height_m" type="number" step="0.1" min="0" class="journal-input" />
                            <InputError :message="form.errors.swell_height_m" />
                        </div>

                        <div>
                            <label class="journal-field-label" for="swell_period_s">Swell period (s)</label>
                            <input id="swell_period_s" v-model="form.swell_period_s" type="number" step="0.1" min="0" class="journal-input" />
                            <InputError :message="form.errors.swell_period_s" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="air_temp_c">Air temp (C)</label>
                            <input id="air_temp_c" v-model="form.air_temp_c" type="number" step="0.1" class="journal-input" />
                            <InputError :message="form.errors.air_temp_c" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="sea_temp_c">Sea temp (C)</label>
                            <input id="sea_temp_c" v-model="form.sea_temp_c" type="number" step="0.1" class="journal-input" />
                            <InputError :message="form.errors.sea_temp_c" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="visibility_code">Visibility</label>
                            <select id="visibility_code" v-model="form.visibility_code" class="journal-select">
                                <option value="">Select...</option>
                                <option v-for="option in visibilityOptions" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                            <InputError :message="form.errors.visibility_code" />
                        </div>
                    </div>

                    <section class="rounded-[24px] border border-[color:var(--journal-line)] bg-white/72 p-4">
                        <div class="space-y-2">
                            <p class="journal-kicker">Environmental conditions</p>
                            <h4 class="text-[1.35rem] leading-none">Session checklist</h4>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <label class="journal-field-label" for="rain_severity">Rain</label>
                                <select id="rain_severity" v-model="form.rain_severity" class="journal-select">
                                    <option value="">Not set</option>
                                    <option v-for="option in severityOptions" :key="option" :value="option">{{ option }}</option>
                                </select>
                                <InputError :message="form.errors.rain_severity" />
                            </div>
                            <div>
                                <label class="journal-field-label" for="wind_severity">Wind</label>
                                <select id="wind_severity" v-model="form.wind_severity" class="journal-select">
                                    <option value="">Not set</option>
                                    <option v-for="option in severityOptions" :key="option" :value="option">{{ option }}</option>
                                </select>
                                <InputError :message="form.errors.wind_severity" />
                            </div>
                            <div>
                                <label class="journal-field-label" for="temperature_severity">Temperature</label>
                                <select id="temperature_severity" v-model="form.temperature_severity" class="journal-select">
                                    <option value="">Not set</option>
                                    <option v-for="option in severityOptions" :key="option" :value="option">{{ option }}</option>
                                </select>
                                <InputError :message="form.errors.temperature_severity" />
                            </div>
                            <div>
                                <label class="journal-field-label" for="forecast_severity">Forecast</label>
                                <select id="forecast_severity" v-model="form.forecast_severity" class="journal-select">
                                    <option value="">Not set</option>
                                    <option v-for="option in severityOptions" :key="option" :value="option">{{ option }}</option>
                                </select>
                                <InputError :message="form.errors.forecast_severity" />
                            </div>
                        </div>
                    </section>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <div>
                            <label class="journal-field-label" for="weather_summary">Conditions summary</label>
                            <textarea
                                id="weather_summary"
                                v-model="form.weather_summary"
                                class="journal-textarea"
                                placeholder="Cold water, light chop, onshore breeze, forecast held steady."
                            />
                            <InputError :message="form.errors.weather_summary" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="route_summary">Route summary</label>
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
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label class="journal-field-label" for="successful_rolls_count">Successful rolls</label>
                            <input id="successful_rolls_count" v-model="form.successful_rolls_count" type="number" min="0" class="journal-input" />
                            <InputError :message="form.errors.successful_rolls_count" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="wet_exits_count">Wet exits (swims)</label>
                            <input id="wet_exits_count" v-model="form.wet_exits_count" type="number" min="0" class="journal-input" />
                            <InputError :message="form.errors.wet_exits_count" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="tow_rescues_count">Tow rescues</label>
                            <input id="tow_rescues_count" v-model="form.tow_rescues_count" type="number" min="0" class="journal-input" />
                            <InputError :message="form.errors.tow_rescues_count" />
                        </div>
                    </div>

                    <div>
                        <label class="journal-field-label" for="skills_text">Skills practiced</label>
                        <input id="skills_text" v-model="form.skills_text" class="journal-input" placeholder="rolling, surf launch, navigation, towing" />
                        <InputError :message="form.errors.skills_text" />
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <div>
                            <label class="journal-field-label" for="what_went_well">What went well</label>
                            <textarea id="what_went_well" v-model="form.what_went_well" class="journal-textarea" />
                            <InputError :message="form.errors.what_went_well" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="improve_next">Improve next time</label>
                            <textarea id="improve_next" v-model="form.improve_next" class="journal-textarea" />
                            <InputError :message="form.errors.improve_next" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="journal-field-label" for="confidence_score">Confidence (1-5)</label>
                            <input id="confidence_score" v-model="form.confidence_score" type="number" min="1" max="5" class="journal-input" />
                            <InputError :message="form.errors.confidence_score" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="fatigue_score">Fatigue (1-5)</label>
                            <input id="fatigue_score" v-model="form.fatigue_score" type="number" min="1" max="5" class="journal-input" />
                            <InputError :message="form.errors.fatigue_score" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="decision_score">Decision quality (1-5)</label>
                            <input id="decision_score" v-model="form.decision_score" type="number" min="1" max="5" class="journal-input" />
                            <InputError :message="form.errors.decision_score" />
                        </div>
                    </div>
                </div>

                <div v-show="currentStep === 3" class="space-y-5">
                    <div class="grid gap-4 xl:grid-cols-2">
                        <label class="flex items-center gap-3 rounded-[22px] border border-[color:var(--journal-line)] bg-white/72 px-4 py-4 text-sm font-medium text-[color:var(--journal-text)]">
                            <input v-model="form.is_public" type="checkbox" class="size-4 rounded border-[color:var(--journal-line)]" />
                            Visible on public profile
                        </label>

                        <label class="flex items-center gap-3 rounded-[22px] border border-[color:var(--journal-line)] bg-white/72 px-4 py-4 text-sm font-medium text-[color:var(--journal-text)]">
                            <input v-model="form.is_expedition" type="checkbox" class="size-4 rounded border-[color:var(--journal-line)]" />
                            Tag as expedition / multiday
                        </label>
                    </div>

                    <div v-if="form.is_expedition" class="max-w-sm">
                        <label class="journal-field-label" for="expedition_days">Days out</label>
                        <input id="expedition_days" v-model="form.expedition_days" type="number" min="2" max="100" class="journal-input" />
                        <InputError :message="form.errors.expedition_days" />
                    </div>

                    <div class="grid gap-4 xl:grid-cols-3">
                        <div>
                            <label class="journal-field-label" for="notes_public">Observations</label>
                            <textarea
                                id="notes_public"
                                v-model="form.notes_public"
                                class="journal-textarea"
                                placeholder="Wildlife, route notes, or public-facing observations."
                            />
                            <InputError :message="form.errors.notes_public" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="notes_private">Private notes</label>
                            <textarea
                                id="notes_private"
                                v-model="form.notes_private"
                                class="journal-textarea"
                                placeholder="Personal reflections or lessons for yourself."
                            />
                            <InputError :message="form.errors.notes_private" />
                        </div>
                        <div>
                            <label class="journal-field-label" for="expedition_notes">Expedition notes</label>
                            <textarea
                                id="expedition_notes"
                                v-model="form.expedition_notes"
                                class="journal-textarea"
                                placeholder="Food, gear, camp, and multiday notes for next time."
                            />
                            <InputError :message="form.errors.expedition_notes" />
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-3">
                        <div>
                            <label class="journal-field-label" for="gpx_file">GPX file</label>
                            <input id="gpx_file" type="file" accept=".gpx,.xml" :class="fileInputClass" @change="assignFile('gpx_file', $event)" />
                            <p v-if="form.gpx_file || existingAssets.gpxName" class="mt-2 text-xs text-[color:var(--journal-muted)]">
                                {{ form.gpx_file?.name ?? existingAssets.gpxName }}
                            </p>
                            <InputError :message="form.errors.gpx_file" />
                        </div>

                        <div>
                            <label class="journal-field-label" for="fit_file">FIT file</label>
                            <input id="fit_file" type="file" accept=".fit" :class="fileInputClass" @change="assignFile('fit_file', $event)" />
                            <p v-if="form.fit_file || existingAssets.fitName" class="mt-2 text-xs text-[color:var(--journal-muted)]">
                                {{ form.fit_file?.name ?? existingAssets.fitName }}
                            </p>
                            <InputError :message="form.errors.fit_file" />
                        </div>

                        <div>
                            <label class="journal-field-label" for="session_photo">Session photo</label>
                            <input id="session_photo" type="file" accept="image/*" :class="fileInputClass" @change="assignFile('session_photo', $event)" />
                            <p v-if="form.session_photo || existingAssets.photoName" class="mt-2 text-xs text-[color:var(--journal-muted)]">
                                {{ form.session_photo?.name ?? existingAssets.photoName }}
                            </p>
                            <InputError :message="form.errors.session_photo" />
                        </div>
                    </div>

                    <div
                        v-if="photoPreviewUrl"
                        class="overflow-hidden rounded-[24px] border border-[color:var(--journal-line)] bg-white/72"
                    >
                        <img :src="photoPreviewUrl" alt="Session photo preview" class="h-64 w-full object-cover" />
                    </div>
                </div>
            </section>

            <section class="journal-panel flex flex-wrap items-center justify-between gap-3 px-5 py-5 md:px-6">
                <p class="text-sm text-[color:var(--journal-muted)]">
                    Minimum save requirement: title, date, launch, and distance or a route file.
                </p>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="journal-utility-link disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="currentStep === 0"
                        @click="previousStep"
                    >
                        Back
                    </button>

                    <button
                        v-if="currentStep < steps.length - 1"
                        type="button"
                        class="journal-primary-link"
                        @click="nextStep"
                    >
                        Next
                    </button>

                    <button
                        v-else
                        type="submit"
                        class="journal-primary-link disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : submitLabel }}
                    </button>
                </div>
            </section>
        </form>
    </div>
</template>
