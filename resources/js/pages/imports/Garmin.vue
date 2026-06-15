<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useUnitPreferences } from '@/composables/useUnitPreferences';
import { formatDistanceKm } from '@/lib/units';
import InputError from '@/components/InputError.vue';
import { Spinner } from '@/components/ui/spinner';

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
const { unitPreferences } = useUnitPreferences();

type PreviewActivity = {
    row: number;
    date: string;
    title: string;
    activityType: string;
    distance: string;
    duration: string;
};

const form = useForm({
    csv_file: null as File | null,
    gpx_files: [] as File[],
    fit_files: [] as File[],
    selected_rows: [] as number[],
    use_selected_rows: false,
    autofill_weather: false,
});

const previewActivities = ref<PreviewActivity[]>([]);
const previewError = ref('');
const selectedGpxCount = computed(() => form.gpx_files.length);
const selectedFitCount = computed(() => form.fit_files.length);
const selectedActivityCount = computed(() => form.selected_rows.length);
const allPreviewSelected = computed(
    () =>
        previewActivities.value.length > 0 &&
        selectedActivityCount.value === previewActivities.value.length,
);
const metaPills = computed(() => [
    props.profile.homeWater,
    props.profile.timezone,
    'CSV creates sessions',
    'GPX / FIT can repair tracks',
]);

const submitLabel = computed(() => {
    if (form.processing) {
        return 'Working...';
    }

    if (!form.csv_file) {
        return 'Attach tracks';
    }

    if (previewActivities.value.length === 0) {
        return 'Import history';
    }

    return selectedActivityCount.value === 1
        ? 'Import 1 selected activity'
        : `Import ${selectedActivityCount.value} selected activities`;
});

async function assignCsv(event: Event) {
    const target = event.target as HTMLInputElement;
    form.csv_file = target.files?.[0] ?? null;
    previewActivities.value = [];
    previewError.value = '';
    form.selected_rows = [];
    form.use_selected_rows = form.csv_file !== null;

    if (!form.csv_file) {
        return;
    }

    try {
        previewActivities.value = parseActivitiesCsv(await form.csv_file.text());
        form.selected_rows = previewActivities.value.map((activity) => activity.row);

        if (previewActivities.value.length === 0) {
            previewError.value = 'No kayaking activities were found in this CSV.';
        }
    } catch {
        previewError.value = 'This CSV could not be previewed.';
    }
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

function toggleAllActivities() {
    form.selected_rows = allPreviewSelected.value
        ? []
        : previewActivities.value.map((activity) => activity.row);
}

function parseActivitiesCsv(contents: string): PreviewActivity[] {
    const lines = contents
        .replace(/^\uFEFF/, '')
        .split(/\r?\n/)
        .filter((line) => line.trim() !== '');

    if (lines.length < 2) {
        return [];
    }

    const delimiter = detectDelimiter(lines[0]);
    const header = parseCsvLine(lines[0], delimiter).map(normalizeHeader);

    return lines
        .slice(1)
        .map((line, index) => ({
            fields: parseCsvLine(line, delimiter),
            row: index + 2,
        }))
        .map(({ fields, row }) => {
            const value = (...names: string[]) => {
                const headerIndex = header.findIndex((name) =>
                    names.includes(name),
                );

                return headerIndex >= 0 ? (fields[headerIndex] ?? '') : '';
            };

            return {
                row,
                date: value('date', 'activity_date', 'data', 'datum', 'fecha'),
                title:
                    value('title', 'activity_name', 'name', 'titolo', 'nome') ||
                    'Kayaking',
                activityType: value(
                    'activity_type',
                    'type',
                    'tipo_attivita',
                    'tipo_de_actividad',
                    'type_d_activite',
                ),
                distance: value('distance', 'distanza', 'distancia', 'distanz'),
                duration: value(
                    'elapsed_time',
                    'time',
                    'tempo_trascorso',
                    'durata',
                ),
            };
        })
        .filter(
            (activity) =>
                activity.date !== '' &&
                activity.activityType.toLowerCase().includes('kayak'),
        );
}

function detectDelimiter(line: string) {
    return [',', ';', '\t'].sort(
        (left, right) =>
            parseCsvLine(line, right).length - parseCsvLine(line, left).length,
    )[0];
}

function parseCsvLine(line: string, delimiter: string) {
    const fields: string[] = [];
    let field = '';
    let quoted = false;

    for (let index = 0; index < line.length; index += 1) {
        const character = line[index];
        const next = line[index + 1];

        if (character === '"' && quoted && next === '"') {
            field += '"';
            index += 1;
            continue;
        }

        if (character === '"') {
            quoted = !quoted;
            continue;
        }

        if (character === delimiter && !quoted) {
            fields.push(field.trim());
            field = '';
            continue;
        }

        field += character;
    }

    fields.push(field.trim());

    return fields;
}

function normalizeHeader(value: string) {
    return value
        .trim()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}
</script>

<template>
    <Head title="Garmin import" />

    <div class="space-y-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div
                class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
            >
                <div class="space-y-3">
                    <p class="journal-kicker">Garmin import</p>
                    <div class="space-y-2">
                        <h2
                            class="text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96]"
                        >
                            Bring in Garmin history
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            Bring in Garmin history with one CSV, or upload GPX
                            and FIT files alone to match routes onto sessions
                            that already exist.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="pill in metaPills"
                        :key="pill"
                        class="journal-chip"
                        :class="
                            pill === props.profile.homeWater
                                ? 'journal-chip--primary'
                                : ''
                        "
                    >
                        {{ pill }}
                    </span>
                </div>
            </div>
        </section>

        <form class="space-y-6" @submit.prevent="submit">
            <section
                class="journal-panel grid gap-6 px-5 py-5 md:px-6 lg:grid-cols-[minmax(0,1fr)_340px]"
            >
                <div class="space-y-5">
                    <div
                        class="rounded-[24px] border border-[color:var(--journal-line)] bg-white/78 px-5 py-5"
                    >
                        <p
                            class="text-base font-semibold text-[color:var(--journal-text)]"
                        >
                            CSV creates or updates sessions. GPX and FIT can be
                            uploaded later by themselves; we match them to
                            existing sessions using Garmin timestamps and the
                            session date.
                        </p>
                        <p
                            class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                        >
                            This is useful when Garmin CSV synced first but the
                            GPX routes failed or arrived separately.
                        </p>
                    </div>

                    <label
                        class="flex items-start gap-3 rounded-[24px] border border-[color:var(--journal-line)] bg-white/78 px-5 py-5 text-sm text-[color:var(--journal-text)]"
                        :class="!weatherAutofillAvailable ? 'opacity-70' : ''"
                    >
                        <input
                            v-model="form.autofill_weather"
                            type="checkbox"
                            class="mt-1 size-4 rounded border-[color:var(--journal-line)]"
                            :disabled="!weatherAutofillAvailable"
                        />
                        <span class="space-y-1">
                            <strong class="block font-medium"
                                >Fill weather from Stormglass after
                                import</strong
                            >
                            <span
                                class="block text-[color:var(--journal-muted)]"
                            >
                                Tries to enrich imported sessions once they have
                                a timestamp and a saved point from GPX or FIT
                                data. Beaufort is derived from Stormglass wind
                                speed.
                                <template v-if="!weatherAutofillAvailable">
                                    Add your Stormglass API key first to enable
                                    this.</template
                                >
                            </span>
                        </span>
                    </label>

                    <div class="grid gap-5">
                        <article class="journal-soft-card grid gap-2">
                            <label class="journal-field-label" for="csv_file"
                                >Garmin activities CSV</label
                            >
                            <input
                                id="csv_file"
                                class="journal-input px-3 py-2 file:mr-3 file:rounded-full file:border-0 file:bg-[rgba(103,114,255,0.12)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[color:var(--journal-text)]"
                                type="file"
                                accept=".csv,text/csv"
                                @change="assignCsv"
                            />
                            <p
                                class="text-sm text-[color:var(--journal-muted)]"
                            >
                                Optional if you are only attaching GPX/FIT to
                                sessions already in your library.
                            </p>
                            <InputError :message="form.errors.csv_file" />
                        </article>

                        <article
                            v-if="form.csv_file"
                            class="journal-soft-card grid gap-4"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p class="journal-field-label">
                                        Activities to import
                                    </p>
                                    <p
                                        class="mt-1 text-sm text-[color:var(--journal-muted)]"
                                    >
                                        {{
                                            selectedActivityCount
                                        }}
                                        of
                                        {{ previewActivities.length }}
                                        selected
                                    </p>
                                </div>
                                <button
                                    class="journal-utility-link self-start"
                                    type="button"
                                    @click="toggleAllActivities"
                                >
                                    {{
                                        allPreviewSelected
                                            ? 'Clear all'
                                            : 'Select all'
                                    }}
                                </button>
                            </div>

                            <p
                                v-if="previewError"
                                class="journal-banner journal-banner--soft text-sm"
                            >
                                {{ previewError }}
                            </p>

                            <div
                                v-else
                                class="max-h-[420px] overflow-auto rounded-[18px] border border-[color:var(--journal-line)] bg-white"
                            >
                                <table class="min-w-full text-left text-sm">
                                    <thead
                                        class="sticky top-0 bg-[color:var(--journal-soft)] text-xs font-semibold tracking-[0.14em] text-[color:var(--journal-faint)] uppercase"
                                    >
                                        <tr>
                                            <th class="w-12 px-3 py-3">
                                                <span class="sr-only"
                                                    >Select</span
                                                >
                                            </th>
                                            <th class="px-3 py-3">Date</th>
                                            <th class="px-3 py-3">Activity</th>
                                            <th class="px-3 py-3">Distance</th>
                                            <th class="px-3 py-3">Duration</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="activity in previewActivities"
                                            :key="activity.row"
                                            class="border-t border-[color:var(--journal-line)]"
                                        >
                                            <td class="px-3 py-3 align-top">
                                                <input
                                                    v-model="form.selected_rows"
                                                    class="size-4 rounded border-[color:var(--journal-line)]"
                                                    type="checkbox"
                                                    :value="activity.row"
                                                />
                                            </td>
                                            <td
                                                class="whitespace-nowrap px-3 py-3 align-top font-medium text-[color:var(--journal-text)]"
                                            >
                                                {{ activity.date }}
                                            </td>
                                            <td
                                                class="min-w-[220px] px-3 py-3 align-top text-[color:var(--journal-text)]"
                                            >
                                                {{ activity.title }}
                                            </td>
                                            <td
                                                class="whitespace-nowrap px-3 py-3 align-top text-[color:var(--journal-muted)]"
                                            >
                                                {{ activity.distance }}
                                            </td>
                                            <td
                                                class="whitespace-nowrap px-3 py-3 align-top text-[color:var(--journal-muted)]"
                                            >
                                                {{ activity.duration }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <InputError :message="form.errors.selected_rows" />
                            <InputError
                                :message="form.errors['selected_rows.0']"
                            />
                        </article>

                        <div class="grid gap-4 xl:grid-cols-2">
                            <article class="journal-soft-card grid gap-2">
                                <label
                                    class="journal-field-label"
                                    for="gpx_files"
                                    >Matching GPX files</label
                                >
                                <input
                                    id="gpx_files"
                                    class="journal-input px-3 py-2 file:mr-3 file:rounded-full file:border-0 file:bg-[rgba(122,215,208,0.2)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[color:var(--journal-text)]"
                                    type="file"
                                    accept=".gpx,.xml"
                                    multiple
                                    @change="assignGpx"
                                />
                                <p
                                    class="text-sm text-[color:var(--journal-muted)]"
                                >
                                    Upload alone to repair existing sessions, or
                                    include with CSV during a full import.
                                </p>
                                <InputError :message="form.errors.gpx_files" />
                                <InputError
                                    :message="form.errors['gpx_files.0']"
                                />
                            </article>

                            <article class="journal-soft-card grid gap-2">
                                <label
                                    class="journal-field-label"
                                    for="fit_files"
                                    >Matching FIT files</label
                                >
                                <input
                                    id="fit_files"
                                    class="journal-input px-3 py-2 file:mr-3 file:rounded-full file:border-0 file:bg-[rgba(255,156,107,0.18)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[color:var(--journal-text)]"
                                    type="file"
                                    accept=".fit,application/octet-stream"
                                    multiple
                                    @change="assignFit"
                                />
                                <p
                                    class="text-sm text-[color:var(--journal-muted)]"
                                >
                                    Upload alone to repair existing sessions, or
                                    include with CSV when GPX is missing.
                                </p>
                                <InputError :message="form.errors.fit_files" />
                                <InputError
                                    :message="form.errors['fit_files.0']"
                                />
                            </article>
                        </div>
                    </div>
                </div>

                <aside
                    class="journal-card p-5"
                    style="
                        background: linear-gradient(
                            180deg,
                            rgba(255, 255, 255, 0.96),
                            rgba(103, 114, 255, 0.06)
                        );
                    "
                >
                    <p class="journal-kicker">Import summary</p>

                    <div class="mt-5 grid gap-3">
                        <article class="journal-soft-card">
                            <p
                                class="text-xs font-semibold tracking-[0.24em] text-[color:var(--journal-faint)] uppercase"
                            >
                                Current journal
                            </p>
                            <div
                                class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-1"
                            >
                                <div>
                                    <p
                                        class="text-2xl font-semibold text-[color:var(--journal-text)]"
                                    >
                                        {{ props.stats.sessionCount }}
                                    </p>
                                    <p
                                        class="text-sm text-[color:var(--journal-muted)]"
                                    >
                                        sessions logged
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-2xl font-semibold text-[color:var(--journal-text)]"
                                    >
                                        {{
                                            formatDistanceKm(
                                                props.stats.distanceKm,
                                                unitPreferences,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-sm text-[color:var(--journal-muted)]"
                                    >
                                        journal distance
                                    </p>
                                </div>
                            </div>
                        </article>
                        <article class="journal-soft-card">
                            <p
                                class="text-xs font-semibold tracking-[0.24em] text-[color:var(--journal-faint)] uppercase"
                            >
                                CSV activity selection
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-[color:var(--journal-text)]"
                            >
                                {{
                                    form.csv_file?.name ??
                                    'Not selected - attach mode'
                                }}
                            </p>
                            <p
                                v-if="form.csv_file"
                                class="mt-2 text-sm text-[color:var(--journal-muted)]"
                            >
                                {{ selectedActivityCount }} selected for import
                            </p>
                        </article>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                            <article class="journal-soft-card">
                                <p
                                    class="text-xs font-semibold tracking-[0.24em] text-[color:var(--journal-faint)] uppercase"
                                >
                                    GPX files
                                </p>
                                <p
                                    class="mt-2 text-2xl font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ selectedGpxCount }}
                                </p>
                            </article>
                            <article class="journal-soft-card">
                                <p
                                    class="text-xs font-semibold tracking-[0.24em] text-[color:var(--journal-faint)] uppercase"
                                >
                                    FIT files
                                </p>
                                <p
                                    class="mt-2 text-2xl font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ selectedFitCount }}
                                </p>
                            </article>
                        </div>
                        <article class="journal-soft-card">
                            <p
                                class="text-xs font-semibold tracking-[0.24em] text-[color:var(--journal-faint)] uppercase"
                            >
                                Route coverage
                            </p>
                            <div
                                class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-1"
                            >
                                <div>
                                    <p
                                        class="text-xl font-semibold text-[color:var(--journal-text)]"
                                    >
                                        {{ props.stats.trackSessions }}
                                    </p>
                                    <p
                                        class="text-sm text-[color:var(--journal-muted)]"
                                    >
                                        sessions with track data
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-xl font-semibold text-[color:var(--journal-text)]"
                                    >
                                        {{ props.stats.fitSessions }}
                                    </p>
                                    <p
                                        class="text-sm text-[color:var(--journal-muted)]"
                                    >
                                        FIT-backed entries
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div
                        class="journal-banner journal-banner--soft mt-5 text-xs leading-6"
                    >
                        GPX takes priority for route geometry. Without a CSV,
                        files are matched onto existing sessions.
                    </div>

                    <button
                        class="journal-primary-link mt-6 w-full disabled:cursor-not-allowed disabled:opacity-70"
                        type="submit"
                        :disabled="
                            form.processing ||
                            (Boolean(form.csv_file) &&
                                previewActivities.length > 0 &&
                                selectedActivityCount === 0)
                        "
                    >
                        <Spinner v-if="form.processing" class="mr-2 h-4 w-4" />
                        {{ submitLabel }}
                    </button>
                </aside>
            </section>
        </form>
    </div>
</template>
