<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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

    <div class="flex flex-1 flex-col gap-6 rounded-[2rem] p-4 md:p-6">
        <section class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Garmin import"
                    description="Upload a Garmin CSV export and optionally the matching GPX files. The importer will attach sessions to your active profile, match GPX routes when possible, and refresh the dashboard."
                />

                <div class="flex flex-wrap gap-2 text-xs font-medium text-slate-500">
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                        {{ props.profile.name }}
                    </span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                        {{ props.profile.homeWater }}
                    </span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                        {{ props.profile.timezone }}
                    </span>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-[1.5rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Current sessions</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ props.stats.sessionCount }}</p>
            </article>
            <article class="rounded-[1.5rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Current distance</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ props.stats.distanceKm.toFixed(1) }} km</p>
            </article>
            <article class="rounded-[1.5rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Track-backed sessions</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ props.stats.trackSessions }}</p>
            </article>
            <article class="rounded-[1.5rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">FIT attached</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ props.stats.fitSessions }}</p>
            </article>
        </section>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="grid gap-6 rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-6 shadow-sm lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="space-y-6">
                    <div class="space-y-2">
                        <Heading title="Import files" description="CSV is required. GPX and FIT files are optional, but they unlock route previews, timing, and richer Garmin track data." variant="small" />
                    </div>

                    <div class="grid gap-5">
                        <div class="grid gap-2">
                            <Label for="csv_file">Garmin activities CSV</Label>
                            <Input id="csv_file" type="file" accept=".csv,text/csv" @change="assignCsv" />
                            <p class="text-sm text-slate-500">
                                Use the Garmin export that includes activity date, type, distance, and time.
                            </p>
                            <InputError :message="form.errors.csv_file" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="gpx_files">Matching GPX files</Label>
                            <Input id="gpx_files" type="file" accept=".gpx,.xml" multiple @change="assignGpx" />
                            <p class="text-sm text-slate-500">
                                Optional. Upload one or many GPX files and the importer will match them to sessions by date/time.
                            </p>
                            <InputError :message="form.errors.gpx_files" />
                            <InputError :message="form.errors['gpx_files.0']" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="fit_files">Matching FIT files</Label>
                            <Input id="fit_files" type="file" accept=".fit,application/octet-stream" multiple @change="assignFit" />
                            <p class="text-sm text-slate-500">
                                Optional. FIT files can fill route geometry, timing, start coordinates, and Garmin-native metrics when GPX is missing.
                            </p>
                            <InputError :message="form.errors.fit_files" />
                            <InputError :message="form.errors['fit_files.0']" />
                        </div>
                    </div>
                </div>

                <aside class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">
                        Import summary
                    </p>
                    <dl class="mt-5 space-y-4 text-sm text-slate-600">
                        <div class="flex items-center justify-between gap-3">
                            <dt>CSV selected</dt>
                            <dd class="font-medium text-slate-900">
                                {{ form.csv_file?.name ?? 'Not selected' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt>GPX files</dt>
                            <dd class="font-medium text-slate-900">
                                {{ selectedGpxCount }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt>FIT files</dt>
                            <dd class="font-medium text-slate-900">
                                {{ selectedFitCount }}
                            </dd>
                        </div>
                        <div class="rounded-[1.15rem] border border-dashed border-slate-300 bg-white/80 p-4 text-xs leading-6 text-slate-500">
                            Existing sessions are updated by external reference when possible, so repeated imports stay safer than a one-off script. GPX takes priority for route geometry, while FIT fills missing timing and Garmin-native track data.
                        </div>
                    </dl>

                    <Button class="mt-6 w-full" type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Importing...' : 'Run Garmin import' }}
                    </Button>
                </aside>
            </section>
        </form>
    </div>
</template>
