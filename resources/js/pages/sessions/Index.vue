<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface ProfileSummary {
    name: string;
    homeWater: string;
}

interface SessionStats {
    plannedCount: number;
    sessionCount: number;
    collectionCount: number;
    distanceKm: number;
    expeditionTrips: number;
    expeditionDays: number;
}

interface SessionCategoryPill {
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
    categories: SessionCategoryPill[];
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
}

interface CategoryGroup {
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
    categoryGroups: CategoryGroup[];
}>();

const page = usePage();
const successMessage = computed(
    () => (page.props as FlashPageProps).flash?.success,
);
const showPlannedSessions = ref(true);
const showLoggedSessions = ref(true);
const showCollections = ref(true);
const activeCategoryId = ref<number | null>(null);
const activeCategory = computed(
    () =>
        props.categoryGroups.find(
            (category) => category.id === activeCategoryId.value,
        ) ?? null,
);
const visibleSessions = computed(() => {
    if (activeCategoryId.value === null) {
        return props.sessions;
    }

    return props.sessions.filter((session) =>
        session.categories.some(
            (category) => category.id === activeCategoryId.value,
        ),
    );
});

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
        label: 'Collections',
        value: String(props.stats.collectionCount),
        detail: 'Folders for grouped paddles',
    },
    {
        label: 'Distance',
        value: `${props.stats.distanceKm.toFixed(1)} km`,
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
                style="background: rgba(255, 255, 255, 0.86)"
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
                            <p class="journal-kicker">Collections</p>
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
                                activeCategoryId === null
                                    ? 'border-[color:var(--journal-line-strong)] text-[color:var(--journal-text)]'
                                    : '',
                            ]"
                            @click="activeCategoryId = null"
                        >
                            All logged
                        </button>
                        <Link
                            href="/sessions/create"
                            class="journal-primary-link"
                        >
                            Add to collection
                        </Link>
                        <button
                            type="button"
                            class="journal-utility-link"
                            :aria-expanded="showCollections"
                            aria-controls="library-session-collections"
                            @click="showCollections = !showCollections"
                        >
                            {{ showCollections ? 'Collapse' : 'Expand' }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="showCollections"
                id="library-session-collections"
                class="grid gap-4 border-t border-[rgba(255,156,107,0.2)] px-5 py-5 md:grid-cols-2 xl:grid-cols-3 md:px-6"
            >
                <article
                    v-for="category in categoryGroups"
                    :key="category.id"
                    :class="[
                        'journal-card px-5 py-5 text-left transition hover:-translate-y-0.5 hover:border-[color:var(--journal-line-strong)]',
                        activeCategoryId === category.id
                            ? 'border-[color:var(--journal-line-strong)] shadow-[0_20px_70px_rgba(103,114,255,0.16)]'
                            : '',
                    ]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="journal-kicker">
                                {{ category.latestDate ?? 'No date' }}
                            </p>
                            <h4
                                class="mt-2 text-[1.35rem] leading-none text-[color:var(--journal-text)]"
                            >
                                {{ category.name }}
                            </h4>
                        </div>
                        <span class="journal-chip"
                            >{{ category.sessionCount }}
                            {{
                                category.sessionCount === 1
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
                                {{ category.distanceKm.toFixed(1) }} km
                            </p>
                        </div>
                        <div class="journal-soft-card">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-[color:var(--journal-faint)] uppercase"
                            >
                                Filter
                            </p>
                            <button
                                type="button"
                                class="mt-2 text-base font-semibold text-[color:var(--journal-text)]"
                                @click="activeCategoryId = category.id"
                            >
                                Show folder
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link
                            v-for="session in category.sessions"
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
                    v-if="!categoryGroups.length"
                    class="rounded-[1.75rem] border border-dashed border-[color:var(--journal-line)] bg-white/78 px-5 py-10 text-sm leading-7 text-[color:var(--journal-muted)]"
                >
                    No collections yet. Add names such as “Anglesey 2026” or
                    “Club paddles” in the session form and they will appear
                    here.
                </article>
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
                            rgba(255, 255, 255, 0.95),
                            rgba(122, 215, 208, 0.12)
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
                                    {{ plan.distanceKm.toFixed(1) }} km
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
                            <span class="journal-chip"
                                >{{ plan.speedKnots.toFixed(1) }} kt</span
                            >
                            <span class="journal-chip"
                                >{{ plan.pointCount }} points</span
                            >
                            <span v-if="plan.notes" class="journal-chip"
                                >Notes</span
                            >
                        </div>

                        <div class="mt-auto">
                            <Link
                                :href="`/planning/${plan.id}/edit`"
                                class="journal-utility-link w-full justify-center"
                            >
                                Open planned session
                            </Link>
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
                                {{
                                    activeCategory
                                        ? activeCategory.name
                                        : 'Logged sessions'
                                }}
                            </h3>
                            <p
                                class="mt-2 max-w-2xl text-sm leading-6 text-[color:var(--journal-muted)]"
                            >
                                <template v-if="activeCategory">
                                    Filtered to one collection. These are still
                                    normal logged sessions and keep feeding the
                                    same dashboard totals.
                                </template>
                                <template v-else>
                                    Completed paddles. These are the sessions
                                    that feed dashboard totals, diary days,
                                    observations, maps, and expedition stats.
                                </template>
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
                class="grid gap-4 border-t border-[rgba(103,114,255,0.18)] px-5 py-5 md:grid-cols-2 md:px-6"
            >
                <article
                    v-for="session in visibleSessions"
                    :key="session.id"
                    class="journal-card overflow-hidden px-5 py-5 md:px-6"
                    :style="{
                        background: session.isExpedition
                            ? 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(255,156,107,0.08))'
                            : session.photoUrl
                              ? 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(122,215,208,0.08))'
                              : 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(103,114,255,0.05))',
                    }"
                >
                    <div class="flex h-full flex-col gap-4">
                        <div class="flex items-start justify-between gap-3">
                            <div
                                class="flex flex-wrap gap-2 text-xs font-medium"
                            >
                                <span class="journal-kicker">{{
                                    session.date ?? 'No date'
                                }}</span>
                                <span class="journal-chip">{{
                                    session.routeCategoryLabel
                                }}</span>
                                <span
                                    v-if="session.beaufort !== null"
                                    class="journal-chip"
                                    >F{{ session.beaufort }}</span
                                >
                            </div>

                            <Link
                                :href="
                                    session.hasObservation
                                        ? `/sessions/${session.id}/edit`
                                        : `/sessions/${session.id}/edit?step=notes`
                                "
                                class="journal-utility-link"
                            >
                                {{
                                    session.hasObservation
                                        ? 'Edit'
                                        : 'Add observation'
                                }}
                            </Link>
                        </div>

                        <div class="space-y-2">
                            <h3
                                class="text-[1.45rem] leading-none text-[color:var(--journal-text)]"
                            >
                                {{ session.title }}
                            </h3>
                            <p
                                class="text-sm leading-6 text-[color:var(--journal-muted)]"
                            >
                                {{
                                    session.launchName ??
                                    props.profile.homeWater
                                }}
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
                                    {{ session.distanceKm.toFixed(1) }} km
                                </p>
                            </div>
                            <div class="journal-soft-card">
                                <p
                                    class="text-xs font-semibold tracking-[0.2em] text-[color:var(--journal-faint)] uppercase"
                                >
                                    Time
                                </p>
                                <p
                                    class="mt-2 text-base font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ session.durationMinutes }} min
                                </p>
                            </div>
                            <div class="journal-soft-card">
                                <p
                                    class="text-xs font-semibold tracking-[0.2em] text-[color:var(--journal-faint)] uppercase"
                                >
                                    Track
                                </p>
                                <p
                                    class="mt-2 text-base font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ session.hasTrack ? 'Attached' : 'None' }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="session.photoUrl"
                            class="overflow-hidden rounded-[22px] border border-[color:var(--journal-line)] bg-white/72"
                        >
                            <img
                                :src="session.photoUrl"
                                alt="Session cover"
                                class="h-40 w-full object-cover"
                            />
                        </div>

                        <div
                            class="flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]"
                        >
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
                            <span class="journal-chip">{{
                                session.launchName ?? props.profile.homeWater
                            }}</span>
                            <button
                                v-for="category in session.categories"
                                :key="category.id"
                                type="button"
                                class="journal-chip transition hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                                @click="activeCategoryId = category.id"
                            >
                                {{ category.name }}
                            </button>
                        </div>

                        <div class="mt-auto">
                            <Link
                                :href="`/sessions/${session.id}`"
                                class="journal-utility-link w-full justify-center"
                            >
                                Open session
                            </Link>
                        </div>
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
