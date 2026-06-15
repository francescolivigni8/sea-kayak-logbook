<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type ImportItem = {
    id: number;
    sessionId: number | null;
    csvRow: number | null;
    action: 'created' | 'updated' | 'skipped';
    title: string | null;
    date: string | null;
    distanceKm: number | null;
    durationMinutes: number | null;
};

type ImportBatch = {
    id: number;
    kind: string;
    fileName: string | null;
    status: string;
    rowsCount: number;
    selectedCount: number;
    createdCount: number;
    updatedCount: number;
    skippedCount: number;
    summary: Record<string, unknown>;
    createdAt: string | null;
    undoneAt: string | null;
    items: ImportItem[];
};

type MaintenanceSession = {
    id: number;
    date: string | null;
    start: string | null;
    title: string;
    distanceKm: number;
    durationMinutes: number;
    hasRoute: boolean;
    hasGpx: boolean;
    hasFit: boolean;
};

type DuplicateGroup = {
    key: string;
    keep: MaintenanceSession;
    sessions: MaintenanceSession[];
};

interface FlashPageProps {
    flash?: {
        success?: string;
    };
}

const props = defineProps<{
    batches: ImportBatch[];
    maintenance: {
        duplicateGroups: DuplicateGroup[];
        csvOnlySessions: MaintenanceSession[];
    };
}>();

const page = usePage();
const successMessage = computed(
    () => (page.props as FlashPageProps).flash?.success,
);
const openBatchIds = ref<number[]>([]);

function isOpen(batchId: number) {
    return openBatchIds.value.includes(batchId);
}

function toggleBatch(batchId: number) {
    openBatchIds.value = isOpen(batchId)
        ? openBatchIds.value.filter((id) => id !== batchId)
        : [...openBatchIds.value, batchId];
}

function undoBatch(batch: ImportBatch) {
    if (batch.undoneAt) {
        return;
    }

    if (
        !window.confirm(
            `Undo import ${batch.fileName ?? `#${batch.id}`}? Created sessions will be deleted and updated sessions restored.`,
        )
    ) {
        return;
    }

    router.post(
        `/imports/history/${batch.id}/undo`,
        {},
        {
            preserveScroll: true,
        },
    );
}

function mergeDuplicates() {
    if (
        !window.confirm(
            'Merge all listed duplicate groups? The best route-rich session in each group will be kept.',
        )
    ) {
        return;
    }

    router.post('/imports/maintenance/merge-duplicates', {}, { preserveScroll: true });
}

function deleteCsvOnly() {
    if (
        !window.confirm(
            'Delete all listed CSV-only sessions without route data? This cannot be undone unless they came from a tracked import batch.',
        )
    ) {
        return;
    }

    router.post('/imports/maintenance/delete-csv-only', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Import history" />

    <div class="space-y-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="space-y-2">
                    <p class="journal-kicker">Import safety</p>
                    <h2
                        class="text-[clamp(1.8rem,3vw,2.5rem)] leading-[0.98]"
                    >
                        Import history
                    </h2>
                    <p class="journal-copy max-w-3xl text-sm md:text-base">
                        Review recent Garmin imports, inspect affected rows,
                        and undo a batch if it did not do what you expected.
                    </p>
                </div>

                <Link class="journal-primary-link" href="/imports/garmin">
                    Garmin import
                </Link>
            </div>
        </section>

        <div
            v-if="successMessage"
            class="journal-banner journal-banner--success"
        >
            {{ successMessage }}
        </div>

        <section class="journal-panel px-5 py-5 md:px-6">
            <div
                class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <p class="journal-kicker">Recovery tools</p>
                    <p class="mt-2 text-sm text-[color:var(--journal-muted)]">
                        Duplicate groups and CSV-only sessions are detected
                        from the current logbook.
                    </p>
                </div>
                <a class="journal-primary-link" href="/imports/export">
                    Download backup
                </a>
            </div>

            <div class="mb-6 grid gap-4 xl:grid-cols-2">
                <article class="journal-soft-card">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-base font-semibold">
                                Possible duplicates
                            </p>
                            <p class="mt-1 text-sm text-[color:var(--journal-muted)]">
                                {{ maintenance.duplicateGroups.length }} groups
                                found by date and distance.
                            </p>
                        </div>
                        <button
                            class="journal-utility-link disabled:cursor-not-allowed disabled:opacity-50"
                            type="button"
                            :disabled="maintenance.duplicateGroups.length === 0"
                            @click="mergeDuplicates"
                        >
                            Merge all
                        </button>
                    </div>

                    <div
                        v-if="maintenance.duplicateGroups.length > 0"
                        class="mt-4 max-h-64 space-y-3 overflow-auto"
                    >
                        <div
                            v-for="group in maintenance.duplicateGroups"
                            :key="group.key"
                            class="rounded-[16px] border border-[color:var(--journal-line)] bg-white px-3 py-3"
                        >
                            <p class="text-sm font-semibold">{{ group.key }}</p>
                            <p class="mt-1 text-xs text-[color:var(--journal-muted)]">
                                Keep #{{ group.keep.id }}:
                                {{ group.keep.title }}
                                <template v-if="group.keep.hasRoute">
                                    · route data</template
                                >
                            </p>
                        </div>
                    </div>
                </article>

                <article class="journal-soft-card">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-base font-semibold">
                                CSV-only without routes
                            </p>
                            <p class="mt-1 text-sm text-[color:var(--journal-muted)]">
                                {{ maintenance.csvOnlySessions.length }}
                                sessions have Garmin CSV data but no route,
                                GPX, or FIT.
                            </p>
                        </div>
                        <button
                            class="journal-utility-link disabled:cursor-not-allowed disabled:opacity-50"
                            type="button"
                            :disabled="maintenance.csvOnlySessions.length === 0"
                            @click="deleteCsvOnly"
                        >
                            Delete all
                        </button>
                    </div>

                    <div
                        v-if="maintenance.csvOnlySessions.length > 0"
                        class="mt-4 max-h-64 space-y-3 overflow-auto"
                    >
                        <div
                            v-for="session in maintenance.csvOnlySessions"
                            :key="session.id"
                            class="rounded-[16px] border border-[color:var(--journal-line)] bg-white px-3 py-3"
                        >
                            <p class="text-sm font-semibold">
                                #{{ session.id }} · {{ session.title }}
                            </p>
                            <p class="mt-1 text-xs text-[color:var(--journal-muted)]">
                                {{ session.date }} · {{ session.distanceKm }} km
                                · {{ session.durationMinutes }}m
                            </p>
                        </div>
                    </div>
                </article>
            </div>

            <div v-if="batches.length === 0" class="journal-soft-card">
                <p class="text-sm text-[color:var(--journal-muted)]">
                    No tracked imports yet. Future CSV imports will appear here.
                </p>
            </div>

            <div v-else class="grid gap-4">
                <article
                    v-for="batch in batches"
                    :key="batch.id"
                    class="journal-soft-card"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <p
                                    class="truncate text-base font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ batch.fileName ?? `Import #${batch.id}` }}
                                </p>
                                <span
                                    class="journal-chip"
                                    :class="
                                        batch.undoneAt
                                            ? ''
                                            : 'journal-chip--primary'
                                    "
                                >
                                    {{
                                        batch.undoneAt
                                            ? 'Undone'
                                            : batch.status
                                    }}
                                </span>
                            </div>
                            <p class="text-sm text-[color:var(--journal-muted)]">
                                {{ batch.createdAt }} ·
                                {{ batch.selectedCount }} selected ·
                                {{ batch.createdCount }} created ·
                                {{ batch.updatedCount }} updated
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                class="journal-utility-link"
                                type="button"
                                @click="toggleBatch(batch.id)"
                            >
                                {{ isOpen(batch.id) ? 'Hide rows' : 'Show rows' }}
                            </button>
                            <button
                                class="journal-utility-link disabled:cursor-not-allowed disabled:opacity-50"
                                type="button"
                                :disabled="Boolean(batch.undoneAt)"
                                @click="undoBatch(batch)"
                            >
                                Undo import
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="isOpen(batch.id)"
                        class="mt-4 overflow-auto rounded-[18px] border border-[color:var(--journal-line)] bg-white"
                    >
                        <table class="min-w-full text-left text-sm">
                            <thead
                                class="bg-[color:var(--journal-soft)] text-xs font-semibold tracking-[0.14em] text-[color:var(--journal-faint)] uppercase"
                            >
                                <tr>
                                    <th class="px-3 py-3">Row</th>
                                    <th class="px-3 py-3">Action</th>
                                    <th class="px-3 py-3">Date</th>
                                    <th class="px-3 py-3">Session</th>
                                    <th class="px-3 py-3">Distance</th>
                                    <th class="px-3 py-3">Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in batch.items"
                                    :key="item.id"
                                    class="border-t border-[color:var(--journal-line)]"
                                >
                                    <td class="px-3 py-3">
                                        {{ item.csvRow ?? '-' }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="journal-chip">
                                            {{ item.action }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3">
                                        {{ item.date ?? '-' }}
                                    </td>
                                    <td class="min-w-[220px] px-3 py-3">
                                        <Link
                                            v-if="item.sessionId"
                                            class="journal-utility-link"
                                            :href="`/sessions/${item.sessionId}`"
                                        >
                                            {{ item.title ?? 'Session' }}
                                        </Link>
                                        <span v-else>{{ item.title ?? '-' }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3">
                                        {{
                                            item.distanceKm === null
                                                ? '-'
                                                : `${item.distanceKm} km`
                                        }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3">
                                        {{
                                            item.durationMinutes === null
                                                ? '-'
                                                : `${item.durationMinutes}m`
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
