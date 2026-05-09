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
const selectedSessionCount = computed(() => selectedSessionIds.value.length);
const allVisibleSessionsSelected = computed(
    () =>
        visibleSessions.value.length > 0 &&
        visibleSessions.value.every((session) =>
            selectedSessionIds.value.includes(session.id),
        ),
);

const overviewItems = computed(() => [
    {
        label: 'Plans',
        value: String(props.stats.plannedCount),
        detail: 'saved routes',
    },
    {
        label: 'Paddles',
        value: String(props.stats.sessionCount),
        detail: 'logged',
    },
    {
        label: 'Folders',
        value: String(props.stats.folderCount),
        detail: 'groups',
    },
    {
        label: 'Distance',
        value: formatDistanceKm(props.stats.distanceKm, unitPreferences.value),
        detail: 'all time',
    },
    {
        label: 'Expeditions',
        value: String(props.stats.expeditionTrips),
        detail: 'tagged',
    },
    {
        label: 'Days out',
        value: String(props.stats.expeditionDays),
        detail: 'multiday',
    },
]);

const loggedSectionTitle = computed(() =>
    activeFolder.value ? activeFolder.value.name : 'Logged sessions',
);
const loggedSectionDescription = computed(() =>
    activeFolder.value
        ? 'Showing only the sessions inside this folder. They still remain part of the full logbook totals.'
        : 'Completed paddles from the real logbook, kept denser and easier to scan.',
);
const loggedSectionCountLabel = computed(() =>
    `${visibleSessions.value.length} ${
        visibleSessions.value.length === 1 ? 'session' : 'sessions'
    }`,
);

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
    showLoggedSessions.value = true;
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
                class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
            >
                <div class="space-y-2">
                    <p class="journal-kicker">Sessions</p>
                    <h2 class="text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96]">
                        Library
                    </h2>
                    <p class="journal-copy max-w-2xl text-sm md:text-base">
                        Plans, folders, and the full logbook in one calmer working space.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                    <Link href="/planning" class="journal-utility-link">
                        New plan
                    </Link>
                    <Link href="/imports/garmin" class="journal-utility-link">
                        Import history
                    </Link>
                    <Link href="/sessions/create" class="journal-primary-link">
                        Add session
                    </Link>
                </div>
            </div>
        </section>

        <section v-if="successMessage" class="journal-banner">
            {{ successMessage }}
        </section>

        <div class="grid gap-5 xl:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="space-y-5">
                <section class="journal-card px-4 py-4 sm:px-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="journal-kicker">Overview</p>
                            <h3 class="mt-2 text-[1.2rem] leading-none text-[color:var(--journal-text)]">
                                At a glance
                            </h3>
                        </div>
                        <span class="journal-chip">{{ props.stats.sessionCount }} logged</span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <article
                            v-for="item in overviewItems"
                            :key="item.label"
                            class="rounded-[1.1rem] border border-[color:var(--journal-line)] bg-white/72 px-3 py-3"
                        >
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-[color:var(--journal-faint)]">
                                {{ item.label }}
                            </p>
                            <p class="mt-1.5 text-[1.05rem] font-semibold leading-none text-[color:var(--journal-text)]">
                                {{ item.value }}
                            </p>
                            <p class="mt-1 text-[0.72rem] text-[color:var(--journal-muted)]">
                                {{ item.detail }}
                            </p>
                        </article>
                    </div>
                </section>

                <section class="journal-card overflow-hidden">
                    <div class="px-4 py-4 sm:px-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="journal-kicker">Folders</p>
                                <h3 class="mt-2 text-[1.2rem] leading-none text-[color:var(--journal-text)]">
                                    Group sessions
                                </h3>
                                <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                                    Keep trips, club paddles, and training blocks together without affecting the main logbook logic.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="journal-utility-link shrink-0"
                                :aria-expanded="showFolders"
                                aria-controls="library-folders"
                                @click="showFolders = !showFolders"
                            >
                                {{ showFolders ? 'Hide' : 'Show' }}
                            </button>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                :class="[
                                    'journal-utility-link',
                                    activeFolderId === null
                                        ? 'journal-utility-link--active'
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
                                    sortingMode ? 'journal-utility-link--active' : '',
                                ]"
                                @click="sortingMode = !sortingMode"
                            >
                                {{ sortingMode ? 'Sorting on' : 'Sort into folders' }}
                            </button>
                        </div>

                        <form class="mt-4 space-y-2" @submit.prevent="submitFolder">
                            <label class="journal-field-label" for="folder_name"
                                >Create folder</label
                            >
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input
                                    id="folder_name"
                                    v-model="createFolderForm.name"
                                    class="journal-input"
                                    placeholder="Anglesey 2026"
                                />
                                <button
                                    type="submit"
                                    class="journal-primary-link justify-center whitespace-nowrap"
                                    :disabled="createFolderForm.processing"
                                >
                                    {{ createFolderForm.processing ? 'Creating...' : 'Create' }}
                                </button>
                            </div>
                            <p
                                v-if="createFolderForm.errors.name"
                                class="text-sm font-medium text-red-600"
                            >
                                {{ createFolderForm.errors.name }}
                            </p>
                        </form>

                        <section
                            v-if="sortingMode"
                            class="journal-banner journal-banner--soft mt-4 px-4 py-3 text-sm"
                        >
                            Select one or more logged sessions, then drag them onto a folder.
                        </section>
                    </div>

                    <div
                        v-if="showFolders"
                        id="library-folders"
                        class="border-t border-[color:var(--journal-line)] px-3 py-3 sm:px-4"
                    >
                        <div class="space-y-2">
                            <article
                                v-for="folder in folderGroups"
                                :key="folder.id"
                                :class="[
                                    'rounded-[1.2rem] border bg-white/72 px-3 py-3 text-left transition',
                                    activeFolderId === folder.id
                                        ? 'border-[color:var(--journal-line-strong)] shadow-[0_12px_28px_rgba(103,114,255,0.12)]'
                                        : 'border-[color:var(--journal-line)]',
                                    sortingMode ? 'border-dashed' : 'cursor-pointer hover:-translate-y-0.5 hover:border-[color:var(--journal-line-strong)]',
                                    dropTargetFolderId === folder.id ? 'ring-2 ring-[#ff9c6b]' : '',
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
                                    <div class="min-w-0">
                                        <h4 class="truncate text-[0.98rem] font-semibold text-[color:var(--journal-text)]">
                                            {{ folder.name }}
                                        </h4>
                                        <p class="mt-1 text-[0.78rem] text-[color:var(--journal-muted)]">
                                            {{ folder.latestDate ?? 'No date yet' }}
                                        </p>
                                    </div>
                                    <span class="journal-chip shrink-0 text-[0.72rem]">
                                        {{ folder.sessionCount }}
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <span class="journal-chip text-[0.7rem]">
                                        {{ formatDistanceKm(folder.distanceKm, unitPreferences) }}
                                    </span>
                                    <span
                                        v-for="session in folder.sessions.slice(0, 2)"
                                        :key="session.id"
                                        class="journal-chip text-[0.7rem]"
                                    >
                                        {{ session.title }}
                                    </span>
                                </div>
                            </article>

                            <article
                                v-if="!folderGroups.length"
                                class="rounded-[1.2rem] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-4 py-6 text-sm leading-7 text-[color:var(--journal-muted)]"
                            >
                                No folders yet. Create one here, then drop sessions into it.
                            </article>
                        </div>
                    </div>
                </section>
            </aside>

            <div class="space-y-5">
                <section class="journal-card overflow-hidden">
                    <div class="px-4 py-4 sm:px-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="journal-kicker">Plans</p>
                                <h3 class="mt-2 text-[1.2rem] leading-none text-[color:var(--journal-text)]">
                                    Planned sessions
                                </h3>
                                <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                                    Future paddles stay separate from the real logbook until they happen.
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="journal-chip">
                                    {{ plannedSessions.length }} {{ plannedSessions.length === 1 ? 'plan' : 'plans' }}
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
                                    {{ showPlannedSessions ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="showPlannedSessions"
                        id="library-planned-sessions"
                        class="border-t border-[color:var(--journal-line)] px-3 py-3 sm:px-4"
                    >
                        <div class="space-y-2">
                            <article
                                v-for="plan in plannedSessions"
                                :key="plan.id"
                                class="rounded-[1.25rem] border border-[rgba(122,215,208,0.28)] bg-[linear-gradient(180deg,rgba(255,255,255,0.95),rgba(122,215,208,0.08))] px-4 py-4"
                            >
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="journal-kicker">{{ plan.date ?? 'No date' }}</span>
                                            <span v-if="plan.startTimeLocal" class="journal-chip text-[0.72rem]">
                                                {{ plan.startTimeLocal }}
                                            </span>
                                            <span v-if="plan.hasForecast" class="journal-chip text-[0.72rem]">
                                                Forecast
                                            </span>
                                        </div>
                                        <h4 class="mt-2 text-[1rem] font-semibold text-[color:var(--journal-text)]">
                                            {{ plan.title }}
                                        </h4>
                                        <p class="mt-1 text-sm text-[color:var(--journal-muted)]">
                                            {{ plan.launchName ?? props.profile.homeWater }}
                                            <span v-if="plan.landingName">→ {{ plan.landingName }}</span>
                                        </p>
                                        <p class="mt-2 text-[0.82rem] text-[color:var(--journal-muted)]">
                                            {{ formatPlanningDistance(plan.distanceKm) }} ·
                                            {{ formatMinutes(plan.estimatedDurationMinutes) }} ·
                                            {{ formatPlanningSpeed(plan.speedKnots) }} ·
                                            {{ plan.pointCount }} points
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap gap-2 lg:justify-end">
                                        <Link
                                            :href="`/planning/${plan.id}/edit`"
                                            class="journal-utility-link"
                                        >
                                            Open plan
                                        </Link>
                                        <a
                                            v-if="plan.gpxUrl && plan.pointCount > 1"
                                            :href="plan.gpxUrl"
                                            class="journal-utility-link"
                                        >
                                            Export GPX
                                        </a>
                                    </div>
                                </div>
                            </article>

                            <article
                                v-if="!plannedSessions.length"
                                class="rounded-[1.2rem] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-4 py-6 text-sm leading-7 text-[color:var(--journal-muted)]"
                            >
                                No planned sessions yet. Save a route from Planning and it will land here.
                            </article>
                        </div>
                    </div>
                </section>

                <section class="journal-card overflow-hidden">
                    <div class="px-4 py-4 sm:px-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="journal-kicker">Logbook</p>
                                <h3 class="mt-2 truncate text-[1.2rem] leading-none text-[color:var(--journal-text)]">
                                    {{ loggedSectionTitle }}
                                </h3>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-[color:var(--journal-muted)]">
                                    {{ loggedSectionDescription }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="journal-chip">{{ loggedSectionCountLabel }}</span>
                                <template v-if="sortingMode">
                                    <span class="journal-chip">{{ selectedSessionCount }} selected</span>
                                    <button
                                        type="button"
                                        class="journal-utility-link"
                                        @click="
                                            allVisibleSessionsSelected
                                                ? clearSelectedSessions()
                                                : selectAllVisibleSessions()
                                        "
                                    >
                                        {{ allVisibleSessionsSelected ? 'Clear all' : 'Select all' }}
                                    </button>
                                </template>
                                <button
                                    v-if="activeFolder"
                                    type="button"
                                    class="journal-utility-link"
                                    @click="closeFolder"
                                >
                                    Back to all sessions
                                </button>
                                <button
                                    type="button"
                                    class="journal-utility-link"
                                    :aria-expanded="showLoggedSessions"
                                    aria-controls="library-logged-sessions"
                                    @click="showLoggedSessions = !showLoggedSessions"
                                >
                                    {{ showLoggedSessions ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="activeFolder"
                            class="mt-4 flex flex-wrap items-center gap-2 rounded-[1.1rem] border border-[rgba(255,156,107,0.22)] bg-[rgba(255,156,107,0.08)] px-3 py-3 text-sm text-[color:var(--journal-muted)]"
                        >
                            <span class="journal-chip">
                                {{ activeFolder.sessionCount }} {{ activeFolder.sessionCount === 1 ? 'session' : 'sessions' }}
                            </span>
                            <span class="journal-chip">
                                {{ formatDistanceKm(activeFolder.distanceKm, unitPreferences) }}
                            </span>
                            <span>Active folder filter</span>
                        </div>
                    </div>

                    <div
                        v-if="showLoggedSessions"
                        id="library-logged-sessions"
                        class="border-t border-[color:var(--journal-line)] px-3 py-3 sm:px-4"
                    >
                        <div class="space-y-2">
                            <article
                                v-for="session in visibleSessions"
                                :key="session.id"
                                :draggable="sortingMode"
                                :class="[
                                    'rounded-[1.25rem] border bg-white/78 px-4 py-4 transition',
                                    sortingMode
                                        ? 'cursor-grab select-none border-dashed active:cursor-grabbing'
                                        : 'hover:border-[color:var(--journal-line-strong)]',
                                    isSessionSelected(session.id)
                                        ? 'border-[color:var(--journal-line-strong)] shadow-[0_14px_32px_rgba(255,156,107,0.12)]'
                                        : 'border-[color:var(--journal-line)]',
                                    draggedSessionId === session.id ? 'opacity-55 ring-2 ring-[#ff9c6b]' : '',
                                ]"
                                @dragstart="beginSessionDrag(session.id, $event)"
                                @dragend="finishSessionDrag"
                            >
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <div class="min-w-0 flex-1">
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
                                            <div class="min-w-0 flex-1">
                                                <h4 class="truncate text-[1rem] font-semibold text-[color:var(--journal-text)]">
                                                    {{ session.title }}
                                                </h4>
                                                <p class="mt-1 text-sm text-[color:var(--journal-muted)]">
                                                    {{ session.date ?? 'No date' }} ·
                                                    {{ session.launchName ?? props.profile.homeWater }}
                                                </p>
                                            </div>
                                        </div>

                                        <template v-else>
                                            <div class="flex items-start gap-3">
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="journal-kicker">{{ session.date ?? 'No date' }}</span>
                                                        <span
                                                            v-if="session.beaufort !== null"
                                                            class="journal-chip text-[0.72rem]"
                                                        >
                                                            F{{ session.beaufort }}
                                                        </span>
                                                    </div>
                                                    <h4 class="mt-2 truncate text-[1rem] font-semibold text-[color:var(--journal-text)]">
                                                        {{ session.title }}
                                                    </h4>
                                                    <p class="mt-1 text-sm text-[color:var(--journal-muted)]">
                                                        {{ session.launchName ?? props.profile.homeWater }}
                                                    </p>
                                                </div>

                                                <img
                                                    v-if="session.photoUrl"
                                                    :src="session.photoUrl"
                                                    alt="Session cover"
                                                    class="h-12 w-12 shrink-0 rounded-[1rem] border border-[color:var(--journal-line)] object-cover"
                                                />
                                            </div>

                                            <p class="mt-2 text-[0.82rem] text-[color:var(--journal-muted)]">
                                                {{ formatDistanceKm(session.distanceKm, unitPreferences) }} ·
                                                {{ formatMinutes(session.durationMinutes) }} ·
                                                {{ session.routeCategoryLabel }}
                                                <span v-if="session.hasTrack"> · Track</span>
                                                <span v-if="session.hasObservation"> · Notes</span>
                                            </p>

                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                <span
                                                    v-if="session.isExpedition"
                                                    class="journal-chip journal-chip--primary text-[0.72rem]"
                                                >
                                                    Expedition
                                                </span>
                                                <span
                                                    v-if="session.isExpedition && session.expeditionDays"
                                                    class="journal-chip text-[0.72rem]"
                                                >
                                                    {{ session.expeditionDays }} days out
                                                </span>
                                                <button
                                                    v-for="folder in session.folders"
                                                    :key="folder.id"
                                                    type="button"
                                                    class="journal-chip text-[0.72rem] transition hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                                                    @click="openFolder(folder.id)"
                                                >
                                                    {{ folder.name }}
                                                </button>
                                            </div>
                                        </template>
                                    </div>

                                    <div
                                        v-if="!sortingMode"
                                        class="flex flex-wrap gap-2 lg:justify-end"
                                    >
                                        <Link
                                            :href="
                                                session.hasObservation
                                                    ? `/sessions/${session.id}/edit`
                                                    : `/sessions/${session.id}/edit?step=notes`
                                            "
                                            class="journal-utility-link"
                                        >
                                            Edit session
                                        </Link>
                                        <Link
                                            :href="`/sessions/${session.id}`"
                                            class="journal-utility-link"
                                        >
                                            Open session
                                        </Link>
                                    </div>
                                </div>
                            </article>

                            <article
                                v-if="!visibleSessions.length"
                                class="rounded-[1.2rem] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-4 py-6 text-sm leading-7 text-[color:var(--journal-muted)]"
                            >
                                {{
                                    activeFolder
                                        ? 'This folder is empty. Turn on sorting mode and drop logged sessions into it.'
                                        : 'No sessions yet. Add your first paddle or import your history.'
                                }}
                            </article>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
