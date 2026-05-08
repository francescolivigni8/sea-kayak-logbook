<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useUnitPreferences } from '@/composables/useUnitPreferences';
import { formatDistanceKm, formatSpeedKnots } from '@/lib/units';

interface ProfileSummary {
    name: string;
    homeWater: string;
}

interface SessionStats {
    plannedCount: number;
    sessionCount: number;
    folderCount: number;
    distanceKm: number;
    expeditionTrips: number;
    expeditionDays: number;
}

interface SessionFolderPill {
    id: number;
    name: string;
    slug: string;
}

interface SessionListItem {
    id: number;
    title: string;
    date: string | null;
    launchName: string | null;
    distanceKm: number;
    durationMinutes: number;
    beaufort: number | null;
    routeCategoryLabel: string;
    isExpedition: boolean;
    expeditionDays: number | null;
    hasTrack: boolean;
    hasObservation: boolean;
    photoUrl: string | null;
    folders: SessionFolderPill[];
}

interface PlannedSessionListItem {
    id: number;
    title: string;
    date: string | null;
    startTimeLocal: string | null;
    launchName: string | null;
    landingName: string | null;
    distanceKm: number;
    estimatedDurationMinutes: number | null;
    speedKnots: number;
    pointCount: number;
    hasForecast: boolean;
    notes: string | null;
    gpxUrl: string | null;
}

interface FolderGroup {
    id: number;
    name: string;
    slug: string;
    sessionCount: number;
    distanceKm: number;
    latestDate: string | null;
    sessions: Array<{
        id: number;
        title: string;
        date: string | null;
    }>;
}

interface FlashPageProps {
    flash?: {
        success?: string;
    };
}

const props = defineProps<{
    profile: ProfileSummary;
    stats: SessionStats;
    plannedSessions: PlannedSessionListItem[];
    sessions: SessionListItem[];
    folderGroups: FolderGroup[];
}>();

const page = usePage();
const { unitPreferences } = useUnitPreferences();
const successMessage = computed(
    () => (page.props as FlashPageProps).flash?.success,
);
const showPlannedSessions = ref(true);
const showLoggedSessions = ref(true);
const showFolders = ref(true);
const activeFolderId = ref<number | null>(null);
const sortingMode = ref(false);
const draggedSessionId = ref<number | null>(null);
const draggedSessionIds = ref<number[]>([]);
const selectedSessionIds = ref<number[]>([]);
const dropTargetFolderId = ref<number | null>(null);
const createFolderForm = useForm({
    name: '',
});
const activeFolder = computed(
    () =>
        props.folderGroups.find(
            (folder) => folder.id === activeFolderId.value,
        ) ?? null,
);
const visibleSessions = computed(() => {
    if (activeFolderId.value === null) {
        return props.sessions;
    }

    return props.sessions.filter((session) =>
        session.folders.some(
            (folder) => folder.id === activeFolderId.value,
        ),
    );
});
const activeFolderSessions = computed(() =>
    activeFolder.value ? visibleSessions.value : [],
);
const selectedSessionCount = computed(() => selectedSessionIds.value.length);
const allVisibleSessionsSelected = computed(
    () =>
        visibleSessions.value.length > 0 &&
        visibleSessions.value.every((session) =>
            selectedSessionIds.value.includes(session.id),
        ),
);

const statCards = computed(() => [
    {
        label: 'Planned',
        value: String(props.stats.plannedCount),
        detail: 'Future sessions',
    },
    {
        label: 'Paddles',
        value: String(props.stats.sessionCount),
        detail: 'Logged in the journal',
    },
    {
        label: 'Folders',
        value: String(props.stats.folderCount),
        detail: 'Folders for grouped paddles',
    },
    {
        label: 'Distance',
        value: formatDistanceKm(props.stats.distanceKm, unitPreferences.value),
        detail: 'All-time total',
    },
    {
        label: 'Expeditions',
        value: String(props.stats.expeditionTrips),
        detail: 'Checklist tagged',
    },
    {
        label: 'Days out',
        value: String(props.stats.expeditionDays),
        detail: 'Multiday total',
    },
]);

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

function formatPlanningDistance(distanceKm: number): string {
    return formatDistanceKm(distanceKm, unitPreferences.value);
}

function formatPlanningSpeed(speedKnots: number): string {
    return formatSpeedKnots(speedKnots, unitPreferences.value);
}

function submitFolder() {
    createFolderForm.post('/session-categories', {
        preserveScroll: true,
        onSuccess: () => {
            createFolderForm.reset('name');
            showFolders.value = true;
        },
    });
}

function openFolder(folderId: number) {
    if (sortingMode.value) {
        return;
    }

    activeFolderId.value = folderId;
    showFolders.value = true;
    showLoggedSessions.value = false;
}

function closeFolder() {
    activeFolderId.value = null;
    showLoggedSessions.value = true;
}

function isSessionSelected(sessionId: number): boolean {
    return selectedSessionIds.value.includes(sessionId);
}

function toggleSessionSelection(sessionId: number) {
    if (isSessionSelected(sessionId)) {
        selectedSessionIds.value = selectedSessionIds.value.filter(
            (id) => id !== sessionId,
        );

        return;
    }

    selectedSessionIds.value = [...selectedSessionIds.value, sessionId];
}

function selectAllVisibleSessions() {
    selectedSessionIds.value = visibleSessions.value.map(
        (session) => session.id,
    );
}

function clearSelectedSessions() {
    selectedSessionIds.value = [];
}

function sessionIdsForDrag(sessionId: number): number[] {
    if (selectedSessionIds.value.includes(sessionId)) {
        return selectedSessionIds.value;
    }

    return [sessionId];
}

function beginSessionDrag(sessionId: number, event: DragEvent) {
    if (!sortingMode.value) {
        event.preventDefault();

        return;
    }

    draggedSessionId.value = sessionId;
    draggedSessionIds.value = sessionIdsForDrag(sessionId);
    event.dataTransfer?.setData(
        'text/plain',
        draggedSessionIds.value.join(','),
    );

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copy';
    }
}

function finishSessionDrag() {
    draggedSessionId.value = null;
    draggedSessionIds.value = [];
    dropTargetFolderId.value = null;
}

function allowFolderDrop(folderId: number, event: DragEvent) {
    if (!sortingMode.value) {
        return;
    }

    event.preventDefault();
    dropTargetFolderId.value = folderId;

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
    }
}

function dropSessionOnFolder(folderId: number, event: DragEvent) {
    if (!sortingMode.value) {
        return;
    }

    event.preventDefault();
    const dataTransferSessionIds = event.dataTransfer
        ?.getData('text/plain')
        .split(',')
        .map((id) => Number(id))
        .filter((id) => Number.isFinite(id));
    const sessionIds = dataTransferSessionIds?.length
        ? dataTransferSessionIds
        : draggedSessionIds.value;

    if (!sessionIds.length) {
        finishSessionDrag();

        return;
    }

    router.post(
        `/session-categories/${folderId}/sessions`,
        {
            session_ids: sessionIds,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                clearSelectedSessions();
                finishSessionDrag();
            },
        },
    );
}

watch(sortingMode, (isSorting) => {
    if (isSorting) {
        activeFolderId.value = null;
        showFolders.value = true;
        showLoggedSessions.value = true;
    }

    if (!isSorting) {
        clearSelectedSessions();
        finishSessionDrag();
    }
});

watch(visibleSessions, () => {
    const visibleSessionIds = new Set(
        visibleSessions.value.map((session) => session.id),
    );

    selectedSessionIds.value = selectedSessionIds.value.filter((id) =>
        visibleSessionIds.has(id),
    );
});
</script>

<template>
    <Head title="Sessions" />

    <div class="flex flex-col gap-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div
                class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
            >
                <div class="space-y-3">
                    <p class="journal-kicker">Sessions</p>
                    <div class="space-y-2">
                        <h2
                            class="text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96]"
                        >
                            Library
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            Planned sessions and logged paddles, separated so
                            future routes stay useful without touching the real
                            logbook totals.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col items-start gap-3 xl:items-end">
                    <p
                        class="text-sm font-medium text-[color:var(--journal-muted)]"
                    >
                        {{ props.stats.sessionCount }} paddles recorded.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Link href="/planning" class="journal-utility-link">
                            Plan session
                        </Link>
                        <Link
                            href="/imports/garmin"
                            class="journal-utility-link"
                        >
                            Import history
                        </Link>
                        <Link
                            href="/sessions/create"
                            class="journal-primary-link"
                        >
                            Add session
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="successMessage" class="journal-banner">
            {{ successMessage }}
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <article
                v-for="card in statCards"
                :key="card.label"
                class="journal-metric-card"
            >
                <p class="journal-kicker">{{ card.label }}</p>
                <p
                    class="mt-4 text-3xl font-semibold text-[color:var(--journal-text)]"
                >
                    {{ card.value }}
                </p>
                <p
                    class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                >
                    {{ card.detail }}
                </p>
            </article>
        </section>

        <section
            class="overflow-hidden rounded-[2.1rem] border border-[rgba(255,156,107,0.34)] bg-[linear-gradient(135deg,rgba(255,255,255,0.96),rgba(255,156,107,0.12))] shadow-[0_18px_60px_rgba(66,87,120,0.08)]"
        >
            <div class="px-5 py-5 md:px-6">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="flex gap-4">
                        <span
                            class="mt-1 h-16 w-1.5 rounded-full bg-[#ff9c6b]"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="journal-kicker">Folders</p>
                            <h3 class="mt-2 text-[1.75rem] leading-none">
                                Session folders
                            </h3>
                            <p
                                class="mt-2 max-w-2xl text-sm leading-6 text-[color:var(--journal-muted)]"
                            >
                                Group ordinary logged sessions without turning
                                them into expeditions. Use this for trips like
                                Anglesey, club paddles, courses, or recurring
                                training blocks.
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 lg:justify-end"
                    >
                        <button
                            type="button"
                            :class="[
                                'journal-utility-link',
                                activeFolderId === null
                                    ? 'border-[color:var(--journal-line-strong)] text-[color:var(--journal-text)]'
                                    : '',
                            ]"
                            @click="closeFolder"
                        >
                            All folders
                        </button>
                        <button
                            type="button"
                            :class="[
                                'journal-utility-link',
                                sortingMode
                                    ? 'border-[color:var(--journal-line-strong)] bg-white/90 text-[color:var(--journal-text)]'
                                    : '',
                            ]"
                            @click="sortingMode = !sortingMode"
                        >
                            {{
                                sortingMode ? 'Sorting mode on' : 'Drag sorting'
                            }}
                        </button>
                        <Link
                            href="/sessions/create"
                            class="journal-primary-link"
                        >
                            Add session
                        </Link>
                        <button
                            type="button"
                            class="journal-utility-link"
                            :aria-expanded="showFolders"
                            aria-controls="library-session-collections"
                            @click="showFolders = !showFolders"
                        >
                            {{ showFolders ? 'Collapse' : 'Expand' }}
                        </button>
                    </div>
                </div>

                <form
                    class="mt-5 grid gap-3 rounded-[1.65rem] border border-[rgba(255,156,107,0.24)] bg-white/62 p-3 sm:grid-cols-[1fr_auto] sm:items-end"
                    @submit.prevent="submitFolder"
                >
                    <div>
                        <label class="journal-field-label" for="folder_name"
                            >Create folder</label
                        >
                        <input
                            id="folder_name"
                            v-model="createFolderForm.name"
                            class="journal-input"
                            placeholder="Anglesey 2026, Club paddles, Skills weekends"
                        />
                        <p
                            v-if="createFolderForm.errors.name"
                            class="mt-2 text-sm font-medium text-red-600"
                        >
                            {{ createFolderForm.errors.name }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="journal-primary-link justify-center"
                        :disabled="createFolderForm.processing"
                    >
                        {{
                            createFolderForm.processing
                                ? 'Creating...'
                                : 'Create folder'
                        }}
                    </button>
                </form>

                <section
                    v-if="sortingMode"
                    class="journal-banner journal-banner--soft mt-4"
                >
                    Select one or more logged sessions, then drag any selected
                    title onto a folder. Sessions stay in the main logbook and
                    are added to that folder for easier sorting.
                </section>
            </div>

            <div
                v-if="showFolders"
                id="library-session-collections"
                class="border-t border-[rgba(255,156,107,0.2)] px-5 py-5 md:px-6"
            >
                <section v-if="activeFolder" class="space-y-4">
                    <div class="journal-card px-5 py-5 md:px-6">
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div>
                                <p class="journal-kicker">Open folder</p>
                                <h4
                                    class="mt-2 text-[1.6rem] leading-none text-[color:var(--journal-text)]"
                                >
                                    {{ activeFolder.name }}
                                </h4>
                                <p
                                    class="mt-2 max-w-2xl text-sm leading-6 text-[color:var(--journal-muted)]"
                                >
                                    Sessions inside this folder. They still
                                    remain part of the main logged sessions
                                    library and dashboard totals.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2 lg:justify-end">
                                <span class="journal-chip">
                                    {{ activeFolder.sessionCount }}
                                    {{
                                        activeFolder.sessionCount === 1
                                            ? 'session'
                                            : 'sessions'
                                    }}
                                </span>
                                <span class="journal-chip">
                                    {{
                                        formatDistanceKm(
                                            activeFolder.distanceKm,
                                            unitPreferences,
                                        )
                                    }}
                                </span>
                                <button
                                    type="button"
                                    class="journal-utility-link"
                                    @click="closeFolder"
                                >
                                    Back to folders
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        <Link
                            v-for="session in activeFolderSessions"
                            :key="session.id"
                            :href="`/sessions/${session.id}`"
                            class="journal-card block px-4 py-4 transition hover:-translate-y-0.5 hover:border-[color:var(--journal-line-strong)]"
                        >
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="journal-kicker">
                                            {{ session.date ?? 'No date' }}
                                        </p>
                                        <span
                                            v-if="session.beaufort !== null"
                                            class="journal-chip shrink-0"
                                        >
                                            F{{ session.beaufort }}
                                        </span>
                                    </div>
                                    <h5
                                        class="mt-2 truncate text-[1rem] font-semibold leading-tight text-[color:var(--journal-text)]"
                                    >
                                        {{ session.title }}
                                    </h5>
                                    <p
                                        class="mt-1 truncate text-sm text-[color:var(--journal-muted)]"
                                    >
                                        {{
                                            session.launchName ??
                                            props.profile.homeWater
                                        }}
                                    </p>
                                </div>
                                <img
                                    v-if="session.photoUrl"
                                    :src="session.photoUrl"
                                    alt="Session cover"
                                    class="h-14 w-14 shrink-0 rounded-2xl border border-[color:var(--journal-line)] object-cover"
                                />
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2 text-[0.76rem] font-medium text-[color:var(--journal-muted)]">
                                <span class="journal-chip">
                                    {{
                                        formatDistanceKm(
                                            session.distanceKm,
                                            unitPreferences,
                                        )
                                    }}
                                </span>
                                <span class="journal-chip">
                                    {{ formatMinutes(session.durationMinutes) }}
                                </span>
                                <span class="journal-chip">
                                    {{ session.routeCategoryLabel }}
                                </span>
                                <span
                                    v-if="session.hasTrack"
                                    class="journal-chip"
                                    >Track</span
                                >
                                <span
                                    v-if="session.hasObservation"
                                    class="journal-chip"
                                    >Observation</span
                                >
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="journal-chip">{{
                                    session.launchName ??
                                    props.profile.homeWater
                                }}</span>
                                <span
                                    v-if="session.isExpedition"
                                    class="journal-chip journal-chip--primary"
                                    >Expedition</span
                                >
                                <span
                                    v-if="
                                        session.isExpedition &&
                                        session.expeditionDays
                                    "
                                    class="journal-chip"
                                >
                                    {{ session.expeditionDays }} days out
                                </span>
                                <span
                                    v-for="category in session.folders"
                                    :key="category.id"
                                    class="journal-chip"
                                >
                                    {{ category.name }}
                                </span>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3">
                                <span
                                    class="text-xs font-medium text-[color:var(--journal-muted)]"
                                >
                                    Open from folder
                                </span>
                                <span
                                    class="text-sm font-semibold text-[color:var(--journal-text)]"
                                >
                                    View
                                </span>
                            </div>
                        </Link>

                        <article
                            v-if="!activeFolderSessions.length"
                            class="rounded-[1.75rem] border border-dashed border-[color:var(--journal-line)] bg-white/78 px-5 py-10 text-sm leading-7 text-[color:var(--journal-muted)]"
                        >
                            This folder is empty. Go back to folders, turn on
                            drag sorting, and drop logged sessions into it.
                        </article>
                    </div>
                </section>

                <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="folder in folderGroups"
                        :key="folder.id"
                        :class="[
                            'journal-card px-5 py-5 text-left transition hover:-translate-y-0.5 hover:border-[color:var(--journal-line-strong)]',
                            sortingMode
                                ? 'border-dashed ring-1 ring-transparent'
                                : 'cursor-pointer',
                            dropTargetFolderId === folder.id
                                ? 'ring-2 ring-[#ff9c6b]'
                                : '',
                        ]"
                        role="button"
                        tabindex="0"
                        @click="openFolder(folder.id)"
                        @keydown.enter.prevent="openFolder(folder.id)"
                        @keydown.space.prevent="openFolder(folder.id)"
                        @dragover="allowFolderDrop(folder.id, $event)"
                        @dragleave="dropTargetFolderId = null"
                        @drop="dropSessionOnFolder(folder.id, $event)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="journal-kicker">
                                    {{ folder.latestDate ?? 'No date' }}
                                </p>
                                <h4
                                    class="mt-2 text-[1.35rem] leading-none text-[color:var(--journal-text)]"
                                >
                                    {{ folder.name }}
                                </h4>
                            </div>
                            <span class="journal-chip"
                                >{{ folder.sessionCount }}
                                {{
                                    folder.sessionCount === 1
                                        ? 'session'
                                        : 'sessions'
                                }}</span
                            >
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="journal-soft-card">
                                <p
                                    class="text-xs font-semibold tracking-[0.18em] text-[color:var(--journal-faint)] uppercase"
                                >
                                    Distance
                                </p>
                                <p
                                    class="mt-2 text-base font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{
                                        formatDistanceKm(
                                            folder.distanceKm,
                                            unitPreferences,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="journal-soft-card">
                                <p
                                    class="text-xs font-semibold tracking-[0.18em] text-[color:var(--journal-faint)] uppercase"
                                >
                                    Folder
                                </p>
                                <p
                                    class="mt-2 text-base font-semibold text-[color:var(--journal-text)]"
                                >
                                    Open
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                v-for="session in folder.sessions"
                                :key="session.id"
                                :href="`/sessions/${session.id}`"
                                class="journal-chip transition hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                                @click.stop
                            >
                                {{ session.title }}
                            </Link>
                        </div>
                    </article>

                    <article
                        v-if="!folderGroups.length"
                        class="rounded-[1.75rem] border border-dashed border-[color:var(--journal-line)] bg-white/78 px-5 py-10 text-sm leading-7 text-[color:var(--journal-muted)]"
                    >
                        No folders yet. Create “Anglesey 2026” or “Club
                        paddles” above, then turn on drag sorting and drop
                        sessions into the folder.
                    </article>
                </div>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-[2.1rem] border border-[rgba(122,215,208,0.38)] bg-[linear-gradient(135deg,rgba(255,255,255,0.95),rgba(122,215,208,0.16))] shadow-[0_18px_60px_rgba(66,87,120,0.08)]"
        >
            <div class="px-5 py-5 md:px-6">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="flex gap-4">
                        <span
                            class="mt-1 h-16 w-1.5 rounded-full bg-[#55cfc3]"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="journal-kicker">Plans</p>
                            <h3 class="mt-2 text-[1.75rem] leading-none">
                                Planned sessions
                            </h3>
                            <p
                                class="mt-2 max-w-2xl text-sm leading-6 text-[color:var(--journal-muted)]"
                            >
                                Future paddles, route sketches, forecasts, and
                                ideas. These stay separate from real logbook
                                totals until you actually log the session.
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 lg:justify-end"
                    >
                        <span
                            class="rounded-full border border-[rgba(122,215,208,0.42)] bg-white/66 px-3 py-2 text-xs font-black tracking-[0.12em] text-[color:var(--journal-muted)] uppercase"
                        >
                            {{ plannedSessions.length }}
                            {{
                                plannedSessions.length === 1 ? 'plan' : 'plans'
                            }}
                        </span>
                        <Link href="/planning" class="journal-primary-link">
                            New plan
                        </Link>
                        <button
                            type="button"
                            class="journal-utility-link"
                            :aria-expanded="showPlannedSessions"
                            aria-controls="library-planned-sessions"
                            @click="showPlannedSessions = !showPlannedSessions"
                        >
                            {{ showPlannedSessions ? 'Collapse' : 'Expand' }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="showPlannedSessions"
                id="library-planned-sessions"
                class="grid gap-4 border-t border-[rgba(122,215,208,0.24)] px-5 py-5 md:grid-cols-2 md:px-6"
            >
                <article
                    v-for="plan in plannedSessions"
                    :key="plan.id"
                    class="journal-card overflow-hidden px-5 py-5 md:px-6"
                    style="
                        background: linear-gradient(
                            180deg,
                            var(--journal-card-top),
                            color-mix(
                                in srgb,
                                var(--journal-mint) 14%,
                                var(--journal-panel-soft)
                            )
                        );
                    "
                >
                    <div class="flex h-full flex-col gap-4">
                        <div class="flex items-start justify-between gap-3">
                            <div
                                class="flex flex-wrap gap-2 text-xs font-medium"
                            >
                                <span class="journal-kicker">{{
                                    plan.date ?? 'No date'
                                }}</span>
                                <span
                                    v-if="plan.startTimeLocal"
                                    class="journal-chip"
                                >
                                    {{ plan.startTimeLocal }}
                                </span>
                                <span class="journal-chip">Planned</span>
                            </div>

                            <Link
                                :href="`/planning/${plan.id}/edit`"
                                class="journal-utility-link"
                            >
                                Edit plan
                            </Link>
                        </div>

                        <div class="space-y-2">
                            <h3
                                class="text-[1.45rem] leading-none text-[color:var(--journal-text)]"
                            >
                                {{ plan.title }}
                            </h3>
                            <p
                                class="text-sm leading-6 text-[color:var(--journal-muted)]"
                            >
                                {{ plan.launchName ?? props.profile.homeWater }}
                                <span v-if="plan.landingName">
                                    → {{ plan.landingName }}
                                </span>
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="journal-soft-card">
                                <p
                                    class="text-xs font-semibold tracking-[0.2em] text-[color:var(--journal-faint)] uppercase"
                                >
                                    Distance
                                </p>
                                <p
                                    class="mt-2 text-base font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ formatPlanningDistance(plan.distanceKm) }}
                                </p>
                            </div>
                            <div class="journal-soft-card">
                                <p
                                    class="text-xs font-semibold tracking-[0.2em] text-[color:var(--journal-faint)] uppercase"
                                >
                                    ETA
                                </p>
                                <p
                                    class="mt-2 text-base font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{
                                        formatMinutes(
                                            plan.estimatedDurationMinutes,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="journal-soft-card">
                                <p
                                    class="text-xs font-semibold tracking-[0.2em] text-[color:var(--journal-faint)] uppercase"
                                >
                                    Forecast
                                </p>
                                <p
                                    class="mt-2 text-base font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ plan.hasForecast ? 'Saved' : 'None' }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]"
                        >
                            <span class="journal-chip">{{
                                formatPlanningSpeed(plan.speedKnots)
                            }}</span>
                            <span class="journal-chip"
                                >{{ plan.pointCount }} points</span
                            >
                            <span v-if="plan.notes" class="journal-chip"
                                >Notes</span
                            >
                        </div>

                        <div class="mt-auto flex flex-col gap-2">
                            <Link
                                :href="`/planning/${plan.id}/edit`"
                                class="journal-utility-link w-full justify-center"
                            >
                                Open planned session
                            </Link>
                            <a
                                v-if="plan.gpxUrl && plan.pointCount > 1"
                                :href="plan.gpxUrl"
                                class="journal-utility-link w-full justify-center"
                            >
                                Export GPX
                            </a>
                        </div>
                    </div>
                </article>

                <article
                    v-if="!plannedSessions.length"
                    class="rounded-[1.75rem] border border-dashed border-[color:var(--journal-line)] bg-white/78 px-5 py-10 text-sm leading-7 text-[color:var(--journal-muted)]"
                >
                    No planned sessions yet. Sketch a route from Planning and
                    save it here before the paddle.
                </article>
            </div>
        </section>

        <section
            v-if="!activeFolder"
            class="overflow-hidden rounded-[2.1rem] border border-[rgba(103,114,255,0.28)] bg-[linear-gradient(135deg,rgba(255,255,255,0.96),rgba(103,114,255,0.08))] shadow-[0_18px_60px_rgba(66,87,120,0.08)]"
        >
            <div class="px-5 py-5 md:px-6">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="flex gap-4">
                        <span
                            class="mt-1 h-16 w-1.5 rounded-full bg-[#6772ff]"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="journal-kicker">Logbook</p>
                            <h3 class="mt-2 text-[1.75rem] leading-none">
                                Logged sessions
                            </h3>
                            <p
                                class="mt-2 max-w-2xl text-sm leading-6 text-[color:var(--journal-muted)]"
                            >
                                Completed paddles. These are the sessions that
                                feed dashboard totals, diary days, observations,
                                maps, and expedition stats.
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 lg:justify-end"
                    >
                        <span
                            class="rounded-full border border-[rgba(103,114,255,0.3)] bg-white/66 px-3 py-2 text-xs font-black tracking-[0.12em] text-[color:var(--journal-muted)] uppercase"
                        >
                            {{ visibleSessions.length }}
                            {{
                                visibleSessions.length === 1
                                    ? 'session'
                                    : 'sessions'
                            }}
                        </span>
                        <template v-if="sortingMode">
                            <span
                                class="rounded-full border border-[rgba(255,156,107,0.32)] bg-white/76 px-3 py-2 text-xs font-black tracking-[0.12em] text-[color:var(--journal-muted)] uppercase"
                            >
                                {{ selectedSessionCount }} selected
                            </span>
                            <button
                                type="button"
                                class="journal-utility-link"
                                @click="
                                    allVisibleSessionsSelected
                                        ? clearSelectedSessions()
                                        : selectAllVisibleSessions()
                                "
                            >
                                {{
                                    allVisibleSessionsSelected
                                        ? 'Clear all'
                                        : 'Select all'
                                }}
                            </button>
                            <button
                                v-if="selectedSessionCount"
                                type="button"
                                class="journal-utility-link"
                                @click="clearSelectedSessions"
                            >
                                Clear selected
                            </button>
                        </template>
                        <Link
                            href="/sessions/create"
                            class="journal-primary-link"
                        >
                            Add session
                        </Link>
                        <button
                            type="button"
                            class="journal-utility-link"
                            :aria-expanded="showLoggedSessions"
                            aria-controls="library-logged-sessions"
                            @click="showLoggedSessions = !showLoggedSessions"
                        >
                            {{ showLoggedSessions ? 'Collapse' : 'Expand' }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="showLoggedSessions"
                id="library-logged-sessions"
                :class="[
                    'grid border-t border-[rgba(103,114,255,0.18)] px-5 py-5 md:px-6',
                    sortingMode
                        ? 'gap-2'
                        : 'gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4',
                ]"
            >
                <article
                    v-for="session in visibleSessions"
                    :key="session.id"
                    :draggable="sortingMode"
                    :class="[
                        'journal-card overflow-hidden',
                        sortingMode
                            ? 'cursor-grab px-3 py-2 ring-1 ring-[rgba(255,156,107,0.26)] select-none active:cursor-grabbing'
                            : 'px-4 py-4',
                        isSessionSelected(session.id)
                            ? 'border-[color:var(--journal-line-strong)] bg-white/92 shadow-[0_14px_36px_rgba(255,156,107,0.14)]'
                            : '',
                        draggedSessionId === session.id
                            ? 'opacity-55 ring-2 ring-[#ff9c6b]'
                            : '',
                    ]"
                    :style="{
                        background: session.isExpedition
                            ? 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(255,156,107,0.08))'
                            : session.photoUrl
                              ? 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(122,215,208,0.08))'
                              : 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(103,114,255,0.05))',
                    }"
                    @dragstart="beginSessionDrag(session.id, $event)"
                    @dragend="finishSessionDrag"
                >
                    <div class="flex h-full flex-col gap-4">
                        <div v-if="sortingMode" class="flex items-center gap-3">
                            <button
                                type="button"
                                class="grid h-8 w-8 shrink-0 place-items-center rounded-full border text-sm font-black transition"
                                :class="
                                    isSessionSelected(session.id)
                                        ? 'border-[#ff9c6b] bg-[#ff9c6b] text-white'
                                        : 'border-[color:var(--journal-line)] bg-white/78 text-[color:var(--journal-muted)] hover:border-[#ff9c6b]'
                                "
                                :aria-pressed="isSessionSelected(session.id)"
                                :aria-label="`Select ${session.title}`"
                                @click.stop="toggleSessionSelection(session.id)"
                            >
                                {{ isSessionSelected(session.id) ? '✓' : '+' }}
                            </button>
                            <h3
                                class="min-w-0 flex-1 truncate text-[1.05rem] leading-none text-[color:var(--journal-text)]"
                            >
                                {{ session.title }}
                            </h3>
                            <span
                                v-if="
                                    isSessionSelected(session.id) &&
                                    selectedSessionCount > 1
                                "
                                class="journal-chip shrink-0"
                            >
                                {{ selectedSessionCount }}
                            </span>
                        </div>

                        <template v-else>
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2 text-xs font-medium"
                                    >
                                        <span class="journal-kicker">{{
                                            session.date ?? 'No date'
                                        }}</span>
                                        <span
                                            v-if="session.beaufort !== null"
                                            class="journal-chip"
                                            >F{{ session.beaufort }}</span
                                        >
                                        <span
                                            v-if="sortingMode"
                                            class="journal-chip"
                                        >
                                            Drag to folder
                                        </span>
                                    </div>
                                    <h3
                                        class="mt-2 truncate text-[1rem] font-semibold leading-tight text-[color:var(--journal-text)]"
                                    >
                                        {{ session.title }}
                                    </h3>
                                    <p
                                        class="mt-1 truncate text-sm text-[color:var(--journal-muted)]"
                                    >
                                        {{
                                            session.launchName ??
                                            props.profile.homeWater
                                        }}
                                    </p>
                                </div>

                                <img
                                    v-if="session.photoUrl"
                                    :src="session.photoUrl"
                                    alt="Session cover"
                                    class="h-14 w-14 shrink-0 rounded-2xl border border-[color:var(--journal-line)] object-cover"
                                />
                            </div>

                            <div
                                class="flex flex-wrap gap-2 text-[0.76rem] font-medium text-[color:var(--journal-muted)]"
                            >
                                <span class="journal-chip">{{
                                    session.routeCategoryLabel
                                }}</span>
                                <span class="journal-chip">
                                    {{
                                        formatDistanceKm(
                                            session.distanceKm,
                                            unitPreferences,
                                        )
                                    }}
                                </span>
                                <span class="journal-chip">
                                    {{ formatMinutes(session.durationMinutes) }}
                                </span>
                                <span
                                    v-if="session.hasTrack"
                                    class="journal-chip"
                                >
                                    Track
                                </span>
                                <span
                                    v-if="session.isExpedition"
                                    class="journal-chip journal-chip--primary"
                                    >Expedition</span
                                >
                                <span
                                    v-if="
                                        session.isExpedition &&
                                        session.expeditionDays
                                    "
                                    class="journal-chip"
                                >
                                    {{ session.expeditionDays }} days out
                                </span>
                                <span
                                    v-if="session.hasObservation"
                                    class="journal-chip"
                                    >Observation</span
                                >
                                <button
                                    v-for="category in session.folders"
                                    :key="category.id"
                                    type="button"
                                    class="journal-chip transition hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                                    @click="openFolder(category.id)"
                                >
                                    {{ category.name }}
                                </button>
                            </div>

                            <div class="mt-auto flex gap-2">
                                <Link
                                    :href="
                                        session.hasObservation
                                            ? `/sessions/${session.id}/edit`
                                            : `/sessions/${session.id}/edit?step=notes`
                                    "
                                    class="journal-utility-link flex-1 justify-center"
                                >
                                    Edit session
                                </Link>
                                <Link
                                    :href="`/sessions/${session.id}`"
                                    class="journal-utility-link flex-1 justify-center"
                                >
                                    Open session
                                </Link>
                            </div>
                        </template>
                    </div>
                </article>

                <article
                    v-if="!visibleSessions.length"
                    class="rounded-[1.75rem] border border-dashed border-[color:var(--journal-line)] bg-white/78 px-5 py-10 text-sm leading-7 text-[color:var(--journal-muted)]"
                >
                    No sessions yet. Start by adding your first paddle or
                    importing Garmin history.
                </article>
            </div>
        </section>
    </div>
</template>
