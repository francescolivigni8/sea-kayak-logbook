<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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

const textareaClass =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 min-h-28 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px]';

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

onBeforeUnmount(() => {
    if (activeObjectUrl) {
        URL.revokeObjectURL(activeObjectUrl);
    }
});

const pageTitle = computed(() =>
    props.mode === 'create' ? 'Add paddle session' : 'Edit paddle session',
);

const pageDescription = computed(() =>
    props.mode === 'create'
        ? 'Capture journey data, sea conditions, development notes, expedition fields, and files in one place.'
        : 'Refine the session details, uploads, and notes for this paddle.',
);

const submitLabel = computed(() =>
    props.mode === 'create' ? 'Save session' : 'Update session',
);

const routeCategoryOptions = [
    'journey',
    'training',
    'benchmark',
    'navigation',
    'rescue-practice',
    'expedition',
];

const bodyOfWaterOptions = ['sea', 'ocean', 'fjord', 'lake', 'river', 'canal', 'other'];
const severityOptions = ['low', 'moderate', 'high', 'extreme'];
const tideStateOptions = ['slack', 'flooding', 'high', 'ebbing', 'low'];
const visibilityOptions = ['clear', 'good', 'moderate', 'poor', 'fog'];

function assignFile(key: 'gpx_file' | 'fit_file' | 'session_photo', event: Event) {
    const target = event.target as HTMLInputElement;
    form[key] = target.files?.[0] ?? null;
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
    <div class="space-y-6">
        <section class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/90 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading :title="pageTitle" :description="pageDescription" />

                <div class="flex flex-wrap gap-2 text-xs font-medium text-slate-500">
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                        {{ profile.name }}
                    </span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                        {{ profile.homeWater }}
                    </span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                        {{ profile.timezone }}
                    </span>
                </div>
            </div>
        </section>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="grid gap-6 rounded-[1.75rem] border border-sidebar-border/70 bg-white/90 p-5 shadow-sm lg:grid-cols-2">
                <div class="space-y-4">
                    <Heading title="Journey" description="The core trip record." variant="small" />

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="title">Title</Label>
                            <Input id="title" v-model="form.title" placeholder="Morning benchmark paddle" />
                            <InputError :message="form.errors.title" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="session_date">Date</Label>
                            <Input id="session_date" v-model="form.session_date" type="date" />
                            <InputError :message="form.errors.session_date" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="start_time_local">Start time</Label>
                            <Input id="start_time_local" v-model="form.start_time_local" type="time" />
                            <InputError :message="form.errors.start_time_local" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="launch_name">Launch</Label>
                            <Input id="launch_name" v-model="form.launch_name" placeholder="Reykjavik" />
                            <InputError :message="form.errors.launch_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="landing_name">Landing</Label>
                            <Input id="landing_name" v-model="form.landing_name" placeholder="Reykjavik" />
                            <InputError :message="form.errors.landing_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="area_name">Area</Label>
                            <Input id="area_name" v-model="form.area_name" placeholder="Faxafloi" />
                            <InputError :message="form.errors.area_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="route_category">Category</Label>
                            <select
                                id="route_category"
                                v-model="form.route_category"
                                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            >
                                <option v-for="option in routeCategoryOptions" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                            <InputError :message="form.errors.route_category" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="body_of_water">Body of water</Label>
                            <select
                                id="body_of_water"
                                v-model="form.body_of_water"
                                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            >
                                <option value="">Select...</option>
                                <option v-for="option in bodyOfWaterOptions" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                            <InputError :message="form.errors.body_of_water" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="distance_km">Distance (km)</Label>
                            <Input id="distance_km" v-model="form.distance_km" type="number" step="0.1" min="0" />
                            <InputError :message="form.errors.distance_km" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="duration_minutes">Duration (min)</Label>
                            <Input id="duration_minutes" v-model="form.duration_minutes" type="number" min="0" />
                            <InputError :message="form.errors.duration_minutes" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="moving_minutes">Moving time (min)</Label>
                            <Input id="moving_minutes" v-model="form.moving_minutes" type="number" min="0" />
                            <InputError :message="form.errors.moving_minutes" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="route_tags_text">Tags</Label>
                            <Input
                                id="route_tags_text"
                                v-model="form.route_tags_text"
                                placeholder="benchmark, faxafloi, spring, harbor"
                            />
                            <InputError :message="form.errors.route_tags_text" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="partners_text">Partners</Label>
                            <Input id="partners_text" v-model="form.partners_text" placeholder="Anna, team night paddle" />
                            <InputError :message="form.errors.partners_text" />
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <Heading title="Sea" description="Conditions and exposure." variant="small" />

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="wind_avg_ms">Wind avg (m/s)</Label>
                            <Input id="wind_avg_ms" v-model="form.wind_avg_ms" type="number" step="0.1" min="0" />
                            <InputError :message="form.errors.wind_avg_ms" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="wind_gust_ms">Wind gust (m/s)</Label>
                            <Input id="wind_gust_ms" v-model="form.wind_gust_ms" type="number" step="0.1" min="0" />
                            <InputError :message="form.errors.wind_gust_ms" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="wind_direction_deg">Wind direction</Label>
                            <Input id="wind_direction_deg" v-model="form.wind_direction_deg" type="number" min="0" max="360" />
                            <InputError :message="form.errors.wind_direction_deg" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="wind_beaufort">Beaufort</Label>
                            <Input id="wind_beaufort" v-model="form.wind_beaufort" type="number" min="0" max="12" />
                            <InputError :message="form.errors.wind_beaufort" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="tide_state">Tide</Label>
                            <select
                                id="tide_state"
                                v-model="form.tide_state"
                                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            >
                                <option value="">Select...</option>
                                <option v-for="option in tideStateOptions" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                            <InputError :message="form.errors.tide_state" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="current_knots">Current (kt)</Label>
                            <Input id="current_knots" v-model="form.current_knots" type="number" step="0.1" min="0" />
                            <InputError :message="form.errors.current_knots" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="wave_height_m">Wave (m)</Label>
                            <Input id="wave_height_m" v-model="form.wave_height_m" type="number" step="0.1" min="0" />
                            <InputError :message="form.errors.wave_height_m" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="swell_height_m">Swell (m)</Label>
                            <Input id="swell_height_m" v-model="form.swell_height_m" type="number" step="0.1" min="0" />
                            <InputError :message="form.errors.swell_height_m" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="swell_period_s">Swell period (s)</Label>
                            <Input id="swell_period_s" v-model="form.swell_period_s" type="number" step="0.1" min="0" />
                            <InputError :message="form.errors.swell_period_s" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="air_temp_c">Air temp (C)</Label>
                            <Input id="air_temp_c" v-model="form.air_temp_c" type="number" step="0.1" />
                            <InputError :message="form.errors.air_temp_c" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="sea_temp_c">Sea temp (C)</Label>
                            <Input id="sea_temp_c" v-model="form.sea_temp_c" type="number" step="0.1" />
                            <InputError :message="form.errors.sea_temp_c" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="visibility_code">Visibility</Label>
                            <select
                                id="visibility_code"
                                v-model="form.visibility_code"
                                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            >
                                <option value="">Select...</option>
                                <option v-for="option in visibilityOptions" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                            <InputError :message="form.errors.visibility_code" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="rain_severity">Rain checklist</Label>
                            <select
                                id="rain_severity"
                                v-model="form.rain_severity"
                                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            >
                                <option value="">Not set</option>
                                <option v-for="option in severityOptions" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                            <InputError :message="form.errors.rain_severity" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="wind_severity">Wind checklist</Label>
                            <select
                                id="wind_severity"
                                v-model="form.wind_severity"
                                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            >
                                <option value="">Not set</option>
                                <option v-for="option in severityOptions" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                            <InputError :message="form.errors.wind_severity" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="temperature_severity">Temp checklist</Label>
                            <select
                                id="temperature_severity"
                                v-model="form.temperature_severity"
                                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            >
                                <option value="">Not set</option>
                                <option v-for="option in severityOptions" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                            <InputError :message="form.errors.temperature_severity" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="forecast_severity">Forecast checklist</Label>
                            <select
                                id="forecast_severity"
                                v-model="form.forecast_severity"
                                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            >
                                <option value="">Not set</option>
                                <option v-for="option in severityOptions" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                            <InputError :message="form.errors.forecast_severity" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="weather_summary">Conditions summary</Label>
                            <textarea
                                id="weather_summary"
                                v-model="form.weather_summary"
                                :class="textareaClass"
                                placeholder="Cold water, light chop, onshore breeze, forecast held steady."
                            />
                            <InputError :message="form.errors.weather_summary" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="route_summary">Route summary</Label>
                            <textarea
                                id="route_summary"
                                v-model="form.route_summary"
                                :class="textareaClass"
                                placeholder="Benchmark loop from Reykjavik with short stop outside the harbor."
                            />
                            <InputError :message="form.errors.route_summary" />
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 rounded-[1.75rem] border border-sidebar-border/70 bg-white/90 p-5 shadow-sm lg:grid-cols-2">
                <div class="space-y-4">
                    <Heading title="Rescue and development" description="Checklist events and reflection." variant="small" />

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="successful_rolls_count">Successful rolls</Label>
                            <Input id="successful_rolls_count" v-model="form.successful_rolls_count" type="number" min="0" />
                            <InputError :message="form.errors.successful_rolls_count" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="wet_exits_count">Wet exits (swims)</Label>
                            <Input id="wet_exits_count" v-model="form.wet_exits_count" type="number" min="0" />
                            <InputError :message="form.errors.wet_exits_count" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="tow_rescues_count">Tow rescues</Label>
                            <Input id="tow_rescues_count" v-model="form.tow_rescues_count" type="number" min="0" />
                            <InputError :message="form.errors.tow_rescues_count" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="skills_text">Skills practiced</Label>
                            <Input id="skills_text" v-model="form.skills_text" placeholder="rolling, surf launch, navigation, towing" />
                            <InputError :message="form.errors.skills_text" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="what_went_well">What went well</Label>
                            <textarea id="what_went_well" v-model="form.what_went_well" :class="textareaClass" />
                            <InputError :message="form.errors.what_went_well" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="improve_next">Improve next time</Label>
                            <textarea id="improve_next" v-model="form.improve_next" :class="textareaClass" />
                            <InputError :message="form.errors.improve_next" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="confidence_score">Confidence (1-5)</Label>
                            <Input id="confidence_score" v-model="form.confidence_score" type="number" min="1" max="5" />
                            <InputError :message="form.errors.confidence_score" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="fatigue_score">Fatigue (1-5)</Label>
                            <Input id="fatigue_score" v-model="form.fatigue_score" type="number" min="1" max="5" />
                            <InputError :message="form.errors.fatigue_score" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="decision_score">Decision quality (1-5)</Label>
                            <Input id="decision_score" v-model="form.decision_score" type="number" min="1" max="5" />
                            <InputError :message="form.errors.decision_score" />
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <Heading title="Expedition, notes, and files" description="Public notes, private notes, and attachments." variant="small" />

                    <div class="space-y-4">
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm font-medium text-slate-700">
                            <input v-model="form.is_public" type="checkbox" class="size-4 rounded border-slate-300 text-slate-900" />
                            Visible on public profile
                        </label>

                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm font-medium text-slate-700">
                            <input v-model="form.is_expedition" type="checkbox" class="size-4 rounded border-slate-300 text-slate-900" />
                            Tag as expedition / multiday
                        </label>

                        <div v-if="form.is_expedition" class="grid gap-2">
                            <Label for="expedition_days">Days out</Label>
                            <Input id="expedition_days" v-model="form.expedition_days" type="number" min="2" max="100" />
                            <InputError :message="form.errors.expedition_days" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="notes_public">Observations</Label>
                            <textarea
                                id="notes_public"
                                v-model="form.notes_public"
                                :class="textareaClass"
                                placeholder="Wildlife, route notes, or public-facing observations."
                            />
                            <InputError :message="form.errors.notes_public" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="notes_private">Private notes</Label>
                            <textarea
                                id="notes_private"
                                v-model="form.notes_private"
                                :class="textareaClass"
                                placeholder="Personal reflections, concerns, or lessons for yourself."
                            />
                            <InputError :message="form.errors.notes_private" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="expedition_notes">Expedition notes</Label>
                            <textarea
                                id="expedition_notes"
                                v-model="form.expedition_notes"
                                :class="textareaClass"
                                placeholder="Food, gear, camp, and multiday notes for the next expedition."
                            />
                            <InputError :message="form.errors.expedition_notes" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="gpx_file">GPX file</Label>
                            <input
                                id="gpx_file"
                                type="file"
                                accept=".gpx,.xml"
                                class="file:text-foreground border-input h-11 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium"
                                @change="assignFile('gpx_file', $event)"
                            />
                            <p v-if="form.gpx_file || existingAssets.gpxName" class="text-xs text-muted-foreground">
                                {{ form.gpx_file?.name ?? existingAssets.gpxName }}
                            </p>
                            <InputError :message="form.errors.gpx_file" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="fit_file">FIT file</Label>
                            <input
                                id="fit_file"
                                type="file"
                                accept=".fit"
                                class="file:text-foreground border-input h-11 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium"
                                @change="assignFile('fit_file', $event)"
                            />
                            <p v-if="form.fit_file || existingAssets.fitName" class="text-xs text-muted-foreground">
                                {{ form.fit_file?.name ?? existingAssets.fitName }}
                            </p>
                            <InputError :message="form.errors.fit_file" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="session_photo">Session photo</Label>
                            <input
                                id="session_photo"
                                type="file"
                                accept="image/*"
                                class="file:text-foreground border-input h-11 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium"
                                @change="assignFile('session_photo', $event)"
                            />
                            <p v-if="form.session_photo || existingAssets.photoName" class="text-xs text-muted-foreground">
                                {{ form.session_photo?.name ?? existingAssets.photoName }}
                            </p>
                            <div
                                v-if="photoPreviewUrl"
                                class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50"
                            >
                                <img :src="photoPreviewUrl" alt="Session photo preview" class="h-52 w-full object-cover" />
                            </div>
                            <InputError :message="form.errors.session_photo" />
                        </div>
                    </div>
                </div>
            </section>

            <section class="flex flex-wrap items-center justify-between gap-3 rounded-[1.75rem] border border-sidebar-border/70 bg-white/90 p-5 shadow-sm">
                <p class="text-sm text-muted-foreground">
                    Minimum save requirement: title, date, launch, and distance or a route file.
                </p>

                <Button type="submit" :disabled="form.processing" class="min-w-40">
                    <Spinner v-if="form.processing" />
                    {{ submitLabel }}
                </Button>
            </section>
        </form>
    </div>
</template>
