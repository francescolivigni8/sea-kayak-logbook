<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Spinner } from '@/components/ui/spinner';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    profile: {
        name: string;
        homeWater: string;
        timezone: string;
    };
    weatherAutofillAvailable: boolean;
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
    autofill_weather: false,
});

const selectedGpxCount = computed(() => form.gpx_files.length);
const selectedFitCount = computed(() => form.fit_files.length);
const metaPills = computed(() => [
    props.profile.homeWater,
    props.profile.timezone,
    'CSV required',
    'GPX / FIT optional',
]);

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
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <p class="journal-kicker">Garmin import</p>
                    <div class="space-y-2">
                        <h2 class="text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96]">
                            Bring in Garmin history
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            Bring in Garmin history with one CSV, then layer GPX or FIT files on top when you want routes and richer track data.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="pill in metaPills"
                        :key="pill"
                        class="journal-chip"
                        :class="pill === props.profile.homeWater ? 'journal-chip--primary' : ''"
                    >
                        {{ pill }}
                    </span>
                </div>
            </div>
        </section>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="journal-panel grid gap-6 px-5 py-5 md:px-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div class="space-y-5">
                    <div class="rounded-[24px] border border-[color:var(--journal-line)] bg-white/78 px-5 py-5">
                        <p class="text-base font-semibold text-[color:var(--journal-text)]">
                            CSV stays required. GPX and FIT remain optional, but they unlock the route map, timing, and the cleaner session views.
                        </p>
                        <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                            Repeated imports stay safer than one-off scripts because existing paddles are updated by external reference where possible.
                        </p>
                    </div>

                    <label
                        class="rounded-[24px] border border-[color:var(--journal-line)] bg-white/78 px-5 py-5 flex items-start gap-3 text-sm text-[color:var(--journal-text)]"
                        :class="!weatherAutofillAvailable ? 'opacity-70' : ''"
                    >
                        <input
                            v-model="form.autofill_weather"
                            type="checkbox"
                            class="mt-1 size-4 rounded border-[color:var(--journal-line)]"
                            :disabled="!weatherAutofillAvailable"
                        />
                        <span class="space-y-1">
                            <strong class="block font-medium">Fill weather from Stormglass after import</strong>
                            <span class="block text-[color:var(--journal-muted)]">
                                Tries to enrich imported sessions once they have a timestamp and a saved point from GPX or FIT data. Beaufort is derived from Stormglass wind speed.
                                <template v-if="!weatherAutofillAvailable"> Add your Stormglass API key first to enable this.</template>
                            </span>
                        </span>
                    </label>

                    <div class="grid gap-5">
                        <article class="journal-soft-card grid gap-2">
                            <label class="journal-field-label" for="csv_file">Garmin activities CSV</label>
                            <input id="csv_file" class="journal-input px-3 py-2 file:mr-3 file:rounded-full file:border-0 file:bg-[rgba(103,114,255,0.12)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[color:var(--journal-text)]" type="file" accept=".csv,text/csv" @change="assignCsv" />
                            <p class="text-sm text-[color:var(--journal-muted)]">
                                Use the Garmin export with date, type, distance, and time.
                            </p>
                            <InputError :message="form.errors.csv_file" />
                        </article>

                        <div class="grid gap-4 xl:grid-cols-2">
                            <article class="journal-soft-card grid gap-2">
                                <label class="journal-field-label" for="gpx_files">Matching GPX files</label>
                                <input id="gpx_files" class="journal-input px-3 py-2 file:mr-3 file:rounded-full file:border-0 file:bg-[rgba(122,215,208,0.2)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[color:var(--journal-text)]" type="file" accept=".gpx,.xml" multiple @change="assignGpx" />
                                <p class="text-sm text-[color:var(--journal-muted)]">
                                    Optional. Best for route geometry and timing.
                                </p>
                                <InputError :message="form.errors.gpx_files" />
                                <InputError :message="form.errors['gpx_files.0']" />
                            </article>

                            <article class="journal-soft-card grid gap-2">
                                <label class="journal-field-label" for="fit_files">Matching FIT files</label>
                                <input id="fit_files" class="journal-input px-3 py-2 file:mr-3 file:rounded-full file:border-0 file:bg-[rgba(255,156,107,0.18)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[color:var(--journal-text)]" type="file" accept=".fit,application/octet-stream" multiple @change="assignFit" />
                                <p class="text-sm text-[color:var(--journal-muted)]">
                                    Optional. Best when GPX is missing and you want Garmin-native detail.
                                </p>
                                <InputError :message="form.errors.fit_files" />
                                <InputError :message="form.errors['fit_files.0']" />
                            </article>
                        </div>
                    </div>
                </div>

                <aside
                    class="journal-card p-5"
                    style="background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(103,114,255,0.06))"
                >
                    <p class="journal-kicker">Import summary</p>

                    <div class="mt-5 grid gap-3">
                        <article class="journal-soft-card">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">Current journal</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                                <div>
                                    <p class="text-2xl font-semibold text-[color:var(--journal-text)]">{{ props.stats.sessionCount }}</p>
                                    <p class="text-sm text-[color:var(--journal-muted)]">sessions logged</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-semibold text-[color:var(--journal-text)]">{{ props.stats.distanceKm.toFixed(1) }} km</p>
                                    <p class="text-sm text-[color:var(--journal-muted)]">journal distance</p>
                                </div>
                            </div>
                        </article>
                        <article class="journal-soft-card">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">CSV selected</p>
                            <p class="mt-2 text-sm font-semibold text-[color:var(--journal-text)]">
                                {{ form.csv_file?.name ?? 'Not selected' }}
                            </p>
                        </article>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                            <article class="journal-soft-card">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">GPX files</p>
                                <p class="mt-2 text-2xl font-semibold text-[color:var(--journal-text)]">
                                    {{ selectedGpxCount }}
                                </p>
                            </article>
                            <article class="journal-soft-card">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">FIT files</p>
                                <p class="mt-2 text-2xl font-semibold text-[color:var(--journal-text)]">
                                    {{ selectedFitCount }}
                                </p>
                            </article>
                        </div>
                        <article class="journal-soft-card">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">Route coverage</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                                <div>
                                    <p class="text-xl font-semibold text-[color:var(--journal-text)]">{{ props.stats.trackSessions }}</p>
                                    <p class="text-sm text-[color:var(--journal-muted)]">sessions with track data</p>
                                </div>
                                <div>
                                    <p class="text-xl font-semibold text-[color:var(--journal-text)]">{{ props.stats.fitSessions }}</p>
                                    <p class="text-sm text-[color:var(--journal-muted)]">FIT-backed entries</p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="journal-banner journal-banner--soft mt-5 text-xs leading-6">
                        GPX takes priority for route geometry. FIT fills timing and Garmin-native gaps.
                    </div>

                    <button class="journal-primary-link mt-6 w-full disabled:cursor-not-allowed disabled:opacity-70" type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Importing...' : 'Import history' }}
                    </button>
                </aside>
            </section>
        </form>
    </div>
</template>
