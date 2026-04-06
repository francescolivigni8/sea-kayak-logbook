<script setup lang="ts">
import SeaStatePanels from '@/components/dashboard/SeaStatePanels.vue';
import Heading from '@/components/Heading.vue';
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sea Kayak Logbook',
                href: dashboard(),
            },
        ],
    },
});

interface ProfileSummary {
    name: string;
    slug: string;
    homeWater: string;
    timezone: string;
    isPublic: boolean;
    publicPath: string;
}

interface HeadlineStats {
    sessionCount: number;
    distanceKm: number;
    durationHours: number;
    longestDistanceKm: number;
    averageDistanceKm: number;
    trackSessions: number;
    paddledMonths: number;
}

interface SnapshotCard {
    label: string;
    value: number;
    unit: string;
    detail: string;
}

interface MonthlyDistanceRow {
    key: string;
    label: string;
    distanceKm: number;
}

interface RouteMixRow {
    key: string;
    label: string;
    sessionCount: number;
    distanceKm: number;
    share: number;
    tone: string;
}

interface CoverageRow {
    label: string;
    count: number;
    percent: number;
    tone: string;
}

interface SeaState {
    beaufortBands: Array<{ label: string; count: number }>;
    tideStates: Array<{ label: string; count: number }>;
    conditionMatrix: Array<{ label: string; values: Array<{ key: string; count: number }> }>;
    rescueTotals: Array<{ label: string; count: number }>;
    temperatureAverages: {
        air: number | null;
        sea: number | null;
    };
}

interface MapData {
    defaultView: {
        lat: number;
        lng: number;
        zoom: number;
    };
    routes: Array<{
        id: number | string;
        label: string;
        color: string;
        year?: number | null;
        years?: number[];
        isExpedition?: boolean;
        category?: string | null;
        points: Array<{ lat: number; lng: number }>;
    }>;
    pins: Array<{
        id: number | string;
        label: string;
        color: string;
        path?: string | null;
        year?: number | null;
        years?: number[];
        isExpedition?: boolean;
        category?: string | null;
        count?: number;
        lat: number;
        lng: number;
    }>;
}

interface ExpeditionSummary {
    distanceKm: number;
    daysOut: number;
    tripCount: number;
}

interface ExpeditionPlace {
    slug: string;
    label: string;
    tripCount: number;
    distanceKm: number;
    daysOut: number;
    latestDate: string | null;
    photoUrl: string | null;
    path: string;
    publicPath: string;
}

interface RecentSession {
    id: number;
    title: string;
    date: string | null;
    distanceKm: number;
    durationMinutes: number;
    routeCategoryLabel: string;
    launchName: string | null;
    beaufort: number | null;
    isPublic: boolean;
    hasTrack: boolean;
    isExpedition: boolean;
}

const props = defineProps<{
    profile: ProfileSummary;
    headline: HeadlineStats;
    yearSnapshots: SnapshotCard[];
    monthlyDistance: MonthlyDistanceRow[];
    routeMix: RouteMixRow[];
    dataCoverage: CoverageRow[];
    seaState: SeaState;
    mapData: MapData;
    expeditionSummary: ExpeditionSummary;
    expeditionPlaces: ExpeditionPlace[];
    expeditionMapData: MapData;
    recentSessions: RecentSession[];
}>();

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const metricCards = computed(() => [
    {
        label: 'Total distance',
        value: `${props.headline.distanceKm.toFixed(1)} km`,
        detail: `${props.headline.sessionCount} sessions in this logbook`,
        accent: 'from-cyan-200 via-sky-100 to-white',
    },
    {
        label: 'Time on water',
        value: `${props.headline.durationHours.toFixed(1)} h`,
        detail: `${props.headline.paddledMonths} active months so far`,
        accent: 'from-violet-200 via-indigo-100 to-white',
    },
    {
        label: 'Longest outing',
        value: `${props.headline.longestDistanceKm.toFixed(1)} km`,
        detail: `${props.headline.averageDistanceKm.toFixed(1)} km average outing`,
        accent: 'from-amber-200 via-orange-100 to-white',
    },
    {
        label: 'Tracked routes',
        value: props.headline.trackSessions.toString(),
        detail: 'Sessions with route or FIT track data',
        accent: 'from-emerald-200 via-teal-100 to-white',
    },
]);

const monthlyMax = computed(() =>
    Math.max(...props.monthlyDistance.map((item) => item.distanceKm), 1),
);

const routeToneClasses: Record<string, string> = {
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
    amber: 'bg-amber-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    indigo: 'bg-indigo-500',
    slate: 'bg-slate-500',
};

const coverageToneClasses: Record<string, string> = {
    cyan: 'bg-cyan-500',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    violet: 'bg-violet-500',
};

const coverageCopy = computed(() =>
    props.dataCoverage.some((item) => item.count > 0)
        ? 'The Garmin import already filled the history. Sea-state notes and development fields can now be layered on top.'
        : 'The logbook is ready, but none of the deeper context fields have been logged yet.',
);

const expeditionCards = computed(() => [
    {
        label: 'Expedition distance',
        value: `${props.expeditionSummary.distanceKm.toFixed(1)} km`,
        detail: 'Counted inside the global distance totals as well',
    },
    {
        label: 'Days out',
        value: props.expeditionSummary.daysOut.toString(),
        detail: 'Total expedition and multiday days logged',
    },
    {
        label: 'Multiday trips',
        value: props.expeditionSummary.tripCount.toString(),
        detail: 'Sessions tagged as expedition',
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 rounded-[2rem] p-4 md:p-6">
        <section class="rounded-[2rem] border border-sidebar-border/70 bg-white/95 p-6 shadow-sm md:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-orange-400">
                        Expedition workspace
                    </p>
                    <Heading
                        title="Sea Kayak Logbook"
                        description="Your imported history is now live in Laravel. This dashboard is reading real paddles, route categories, track coverage, and year-on-year distance straight from your account."
                    />

                    <div class="flex flex-wrap gap-3 text-sm text-slate-500">
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">
                            {{ profile.name }}
                        </span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">
                            {{ profile.homeWater }}
                        </span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">
                            {{ profile.timezone }}
                        </span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">
                            {{ profile.isPublic ? 'Public-ready profile' : 'Private profile' }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Button v-if="profile.isPublic" as-child variant="outline">
                        <Link :href="profile.publicPath">Open public profile</Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link href="/imports/garmin">Garmin import</Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link href="/sessions">View sessions</Link>
                    </Button>
                    <Button as-child>
                        <Link href="/sessions/create">Add session</Link>
                    </Button>
                </div>
            </div>
        </section>

        <section
            v-if="successMessage"
            class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
        >
            {{ successMessage }}
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in metricCards"
                :key="card.label"
                class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm"
            >
                <div
                    class="inline-flex rounded-full border border-white/80 bg-gradient-to-r px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-600"
                    :class="card.accent"
                >
                    {{ card.label }}
                </div>
                <p class="mt-5 text-3xl font-semibold tracking-tight text-slate-900">
                    {{ card.value }}
                </p>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    {{ card.detail }}
                </p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.85fr)]">
            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                            Consistency
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                            Distance by month
                        </h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500">
                        Last 12 months
                    </span>
                </div>

                <div class="mt-6 grid gap-4">
                    <div
                        v-for="item in monthlyDistance"
                        :key="item.key"
                        class="grid grid-cols-[44px_minmax(0,1fr)_66px] items-center gap-3"
                    >
                        <span class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">
                            {{ item.label }}
                        </span>
                        <div class="h-4 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-sky-400 via-violet-400 to-orange-300"
                                :style="{ width: `${Math.max((item.distanceKm / monthlyMax) * 100, item.distanceKm > 0 ? 8 : 0)}%` }"
                            />
                        </div>
                        <span class="text-right text-sm font-medium text-slate-500">
                            {{ item.distanceKm ? `${item.distanceKm.toFixed(1)} km` : '–' }}
                        </span>
                    </div>
                </div>
            </article>

            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                    Year view
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                    Distance snapshots
                </h2>

                <div class="mt-6 grid gap-3">
                    <article
                        v-for="snapshot in yearSnapshots"
                        :key="snapshot.label"
                        class="rounded-[1.25rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                                    {{ snapshot.label }}
                                </p>
                                <p class="mt-2 text-2xl font-semibold text-slate-900">
                                    {{ snapshot.value.toFixed(1) }}
                                    <span class="text-base text-slate-500">{{ snapshot.unit }}</span>
                                </p>
                            </div>
                            <p class="max-w-[150px] text-right text-xs leading-5 text-slate-500">
                                {{ snapshot.detail }}
                            </p>
                        </div>
                    </article>
                </div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.95fr)]">
            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                            Session mix
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                            Route categories
                        </h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500">
                        Distance share
                    </span>
                </div>

                <div class="mt-6 grid gap-4">
                    <article
                        v-for="item in routeMix"
                        :key="item.key"
                        class="rounded-[1.25rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">
                                    {{ item.label }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ item.sessionCount }} sessions · {{ item.distanceKm.toFixed(1) }} km
                                </p>
                            </div>
                            <span class="text-sm font-semibold text-slate-600">
                                {{ item.share.toFixed(1) }}%
                            </span>
                        </div>
                        <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full"
                                :class="routeToneClasses[item.tone] ?? routeToneClasses.slate"
                                :style="{ width: `${Math.max(item.share, item.share > 0 ? 8 : 0)}%` }"
                            />
                        </div>
                    </article>
                </div>
            </article>

            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                    Coverage
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                    Logbook readiness
                </h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">
                    {{ coverageCopy }}
                </p>

                <div class="mt-6 grid gap-4">
                    <article
                        v-for="item in dataCoverage"
                        :key="item.label"
                        class="rounded-[1.25rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-base font-semibold text-slate-900">
                                {{ item.label }}
                            </h3>
                            <span class="text-sm font-semibold text-slate-600">
                                {{ item.count }} / {{ headline.sessionCount }}
                            </span>
                        </div>
                        <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full"
                                :class="coverageToneClasses[item.tone] ?? coverageToneClasses.cyan"
                                :style="{ width: `${Math.max(item.percent, item.count > 0 ? 8 : 0)}%` }"
                            />
                        </div>
                    </article>
                </div>
            </article>
        </section>

        <SeaStatePanels :sea-state="seaState" />

        <section class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                        Route atlas
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                        Mapped sessions
                    </h2>
                </div>

                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500">
                    Route-backed sessions and launch pins
                </span>
            </div>

            <div class="mt-6">
                <RouteAtlasMap
                    :routes="mapData.routes"
                    :pins="mapData.pins"
                    :default-view="mapData.defaultView"
                    :storage-key="`${profile.slug}-route-atlas`"
                    height-class="h-[500px]"
                />
            </div>
        </section>

        <section class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                        Expeditions and multiday
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                        Expedition footprint
                    </h2>
                </div>

                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500">
                    Expedition tracks stay in the main atlas. This map shows where they happened.
                </span>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <article
                    v-for="card in expeditionCards"
                    :key="card.label"
                    class="rounded-[1.25rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                        {{ card.label }}
                    </p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">
                        {{ card.value }}
                    </p>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        {{ card.detail }}
                    </p>
                </article>
            </div>

            <div class="mt-6">
                <RouteAtlasMap
                    :routes="expeditionMapData.routes"
                    :pins="expeditionMapData.pins"
                    :default-view="expeditionMapData.defaultView"
                    :storage-key="`${profile.slug}-expedition-footprint`"
                    :show-legend="false"
                    :show-kind-filter="false"
                    :show-geometry-filter="false"
                    empty-message="No expedition locations yet. Tag a session as expedition to start building the world footprint."
                    height-class="h-[460px]"
                />
            </div>

            <div class="mt-4 flex justify-end">
                <Button as-child variant="outline">
                    <Link href="/expeditions">View all expedition places</Link>
                </Button>
            </div>

            <div v-if="expeditionPlaces.length" class="mt-6 grid gap-3 lg:grid-cols-3">
                <article
                    v-for="place in expeditionPlaces.slice(0, 6)"
                    :key="place.slug"
                    class="overflow-hidden rounded-[1.25rem] border border-slate-200 bg-slate-50/80"
                >
                    <img
                        v-if="place.photoUrl"
                        :src="place.photoUrl"
                        :alt="place.label"
                        class="h-36 w-full object-cover"
                    />
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">
                                    {{ place.label }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ place.tripCount }} trips · {{ place.daysOut }} days
                                </p>
                            </div>
                            <Button as-child variant="outline" size="sm">
                                <Link :href="place.path">Open</Link>
                            </Button>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1">
                                {{ place.distanceKm.toFixed(1) }} km
                            </span>
                            <span
                                v-if="place.latestDate"
                                class="rounded-full border border-slate-200 bg-white px-3 py-1"
                            >
                                {{ place.latestDate }}
                            </span>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                        Recent sessions
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                        Latest paddles
                    </h2>
                </div>

                <Button as-child variant="outline">
                    <Link href="/sessions">All sessions</Link>
                </Button>
            </div>

            <div class="mt-6 grid gap-3 lg:grid-cols-2">
                <article
                    v-for="session in recentSessions"
                    :key="session.id"
                    class="rounded-[1.35rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div class="flex flex-wrap gap-2 text-xs font-medium">
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600">
                                    {{ session.routeCategoryLabel }}
                                </span>
                                <span
                                    class="rounded-full px-3 py-1"
                                    :class="session.isPublic ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                                >
                                    {{ session.isPublic ? 'Public' : 'Private' }}
                                </span>
                                <span
                                    v-if="session.hasTrack"
                                    class="rounded-full bg-cyan-100 px-3 py-1 text-cyan-700"
                                >
                                    Track
                                </span>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">
                                    {{ session.title }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ session.date ?? 'No date' }}
                                    <span v-if="session.launchName">· {{ session.launchName }}</span>
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">
                                    {{ session.distanceKm.toFixed(1) }} km
                                </span>
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">
                                    {{ session.durationMinutes }} min
                                </span>
                                <span
                                    v-if="session.beaufort !== null"
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1"
                                >
                                    F{{ session.beaufort }}
                                </span>
                            </div>
                        </div>

                        <Button as-child variant="outline">
                            <Link :href="`/sessions/${session.id}`">Open</Link>
                        </Button>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
