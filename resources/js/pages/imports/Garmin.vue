<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sea Kayak Logbook',
                href: dashboard(),
            },
            {
                title: 'Garmin import',
                href: '/imports/garmin',
            },
        ],
    },
});

const props = defineProps<{
    profile: {
        name: string;
        homeWater: string;
        timezone: string;
    };
    stats: {
        sessionCount: number;
        distanceKm: number;
        trackSessions: number;
        fitSessions: number;
    };
}>();

const form = useForm({
    csv_file: null as File | null,
    gpx_files: [] as File[],
    fit_files: [] as File[],
});

const selectedGpxCount = computed(() => form.gpx_files.length);
const selectedFitCount = computed(() => form.fit_files.length);

function assignCsv(event: Event) {
    const target = event.target as HTMLInputElement;
    form.csv_file = target.files?.[0] ?? null;
}

function assignGpx(event: Event) {
    const target = event.target as HTMLInputElement;
    form.gpx_files = Array.from(target.files ?? []);
}

function assignFit(event: Event) {
    const target = event.target as HTMLInputElement;
    form.fit_files = Array.from(target.files ?? []);
}

function submit() {
    form.post('/imports/garmin', {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Garmin import" />

    <div class="space-y-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <p class="journal-kicker">Garmin import</p>
                    <Heading
                        title="Bring in Garmin history"
                        description="Upload a Garmin CSV export and, when you have them, the matching GPX or FIT files. The import stays profile-scoped and updates existing paddles by external reference when possible."
                    />
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="journal-chip journal-chip--primary">
                        {{ props.profile.name }}
                    </span>
                    <span class="journal-chip">
                        {{ props.profile.homeWater }}
                    </span>
                    <span class="journal-chip">
                        {{ props.profile.timezone }}
                    </span>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="journal-metric-card" style="background: linear-gradient(135deg, rgba(103,114,255,0.14), rgba(255,255,255,0.9))">
                <p class="journal-kicker">Current sessions</p>
                <p class="mt-4 text-3xl font-semibold text-[color:var(--journal-text)]">{{ props.stats.sessionCount }}</p>
            </article>
            <article class="journal-metric-card" style="background: linear-gradient(135deg, rgba(122,215,208,0.18), rgba(255,255,255,0.9))">
                <p class="journal-kicker">Current distance</p>
                <p class="mt-4 text-3xl font-semibold text-[color:var(--journal-text)]">{{ props.stats.distanceKm.toFixed(1) }} km</p>
            </article>
            <article class="journal-metric-card" style="background: linear-gradient(135deg, rgba(255,156,107,0.16), rgba(255,255,255,0.9))">
                <p class="journal-kicker">Track-backed sessions</p>
                <p class="mt-4 text-3xl font-semibold text-[color:var(--journal-text)]">{{ props.stats.trackSessions }}</p>
            </article>
            <article class="journal-metric-card" style="background: linear-gradient(135deg, rgba(148,141,255,0.16), rgba(255,255,255,0.9))">
                <p class="journal-kicker">FIT attached</p>
                <p class="mt-4 text-3xl font-semibold text-[color:var(--journal-text)]">{{ props.stats.fitSessions }}</p>
            </article>
        </section>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="journal-panel grid gap-6 px-5 py-5 md:px-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="space-y-6">
                    <Heading title="Import files" description="CSV is required. GPX and FIT stay optional, but they unlock route previews, timing, and richer track data." variant="small" />

                    <div class="grid gap-5">
                        <article class="journal-soft-card grid gap-2">
                            <Label class="journal-field-label" for="csv_file">Garmin activities CSV</Label>
                            <Input id="csv_file" class="journal-input px-3 py-2 file:mr-3 file:rounded-full file:border-0 file:bg-[rgba(103,114,255,0.12)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[color:var(--journal-text)]" type="file" accept=".csv,text/csv" @change="assignCsv" />
                            <p class="text-sm text-[color:var(--journal-muted)]">
                                Use the Garmin export that includes activity date, type, distance, and time.
                            </p>
                            <InputError :message="form.errors.csv_file" />
                        </article>

                        <article class="journal-soft-card grid gap-2">
                            <Label class="journal-field-label" for="gpx_files">Matching GPX files</Label>
                            <Input id="gpx_files" class="journal-input px-3 py-2 file:mr-3 file:rounded-full file:border-0 file:bg-[rgba(122,215,208,0.2)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[color:var(--journal-text)]" type="file" accept=".gpx,.xml" multiple @change="assignGpx" />
                            <p class="text-sm text-[color:var(--journal-muted)]">
                                Optional. Upload one or many GPX files and the importer will match them to sessions by date/time.
                            </p>
                            <InputError :message="form.errors.gpx_files" />
                            <InputError :message="form.errors['gpx_files.0']" />
                        </article>

                        <article class="journal-soft-card grid gap-2">
                            <Label class="journal-field-label" for="fit_files">Matching FIT files</Label>
                            <Input id="fit_files" class="journal-input px-3 py-2 file:mr-3 file:rounded-full file:border-0 file:bg-[rgba(255,156,107,0.18)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[color:var(--journal-text)]" type="file" accept=".fit,application/octet-stream" multiple @change="assignFit" />
                            <p class="text-sm text-[color:var(--journal-muted)]">
                                Optional. FIT files can fill route geometry, timing, start coordinates, and Garmin-native metrics when GPX is missing.
                            </p>
                            <InputError :message="form.errors.fit_files" />
                            <InputError :message="form.errors['fit_files.0']" />
                        </article>
                    </div>
                </div>

                <aside class="journal-card p-5">
                    <p class="journal-kicker">Import summary</p>
                    <dl class="mt-5 space-y-4 text-sm text-slate-600">
                        <div class="flex items-center justify-between gap-3">
                            <dt>CSV selected</dt>
                            <dd class="font-medium text-[color:var(--journal-text)]">
                                {{ form.csv_file?.name ?? 'Not selected' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt>GPX files</dt>
                            <dd class="font-medium text-[color:var(--journal-text)]">
                                {{ selectedGpxCount }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt>FIT files</dt>
                            <dd class="font-medium text-[color:var(--journal-text)]">
                                {{ selectedFitCount }}
                            </dd>
                        </div>
                        <div class="journal-banner journal-banner--soft text-xs leading-6">
                            Repeated imports stay safer than one-off scripts because existing paddles are updated by external reference when possible. GPX takes priority for route geometry, while FIT fills timing and Garmin-native gaps.
                        </div>
                    </dl>

                    <button class="journal-primary-link mt-6 w-full disabled:cursor-not-allowed disabled:opacity-70" type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Importing...' : 'Run Garmin import' }}
                    </button>
                </aside>
            </section>
        </form>
    </div>
</template>
