<script setup lang="ts">
import SeaStatePanels from '@/components/dashboard/SeaStatePanels.vue';
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

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
    seaState: SeaState;
    mapData: MapData;
    expeditionSummary: ExpeditionSummary;
    expeditionPlaces: ExpeditionPlace[];
    expeditionMapData: MapData;
    recentSessions: RecentSession[];
}>();

const metricCards = computed(() => [
    {
        label: 'Public distance',
        value: `${props.headline.distanceKm.toFixed(1)} km`,
        detail: `${props.headline.sessionCount} public paddles`,
        style: 'linear-gradient(135deg, rgba(103,114,255,0.14), rgba(255,255,255,0.9))',
    },
    {
        label: 'Public hours',
        value: `${props.headline.durationHours.toFixed(1)} h`,
        detail: `${props.headline.paddledMonths} months paddled`,
        style: 'linear-gradient(135deg, rgba(122,215,208,0.18), rgba(255,255,255,0.9))',
    },
    {
        label: 'Longest public outing',
        value: `${props.headline.longestDistanceKm.toFixed(1)} km`,
        detail: `${props.headline.averageDistanceKm.toFixed(1)} km average outing`,
        style: 'linear-gradient(135deg, rgba(255,156,107,0.16), rgba(255,255,255,0.9))',
    },
    {
        label: 'Mapped routes',
        value: String(props.headline.trackSessions),
        detail: 'Public sessions with route data',
        style: 'linear-gradient(135deg, rgba(148,141,255,0.16), rgba(255,255,255,0.9))',
    },
]);

const expeditionCards = computed(() => [
    { label: 'Expedition distance', value: `${props.expeditionSummary.distanceKm.toFixed(1)} km`, detail: 'Public expedition distance' },
    { label: 'Days out', value: String(props.expeditionSummary.daysOut), detail: 'Public multiday days logged' },
    { label: 'Multiday trips', value: String(props.expeditionSummary.tripCount), detail: 'Public expedition sessions' },
]);

const monthlyMax = computed(() => Math.max(...props.monthlyDistance.map((item) => item.distanceKm), 1));
const featuredPlaces = computed(() => props.expeditionPlaces.slice(0, 3));
</script>

<template>
    <Head :title="`${profile.name} · Sea Kayak Logbook`" />

    <div class="journal-page">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <p class="journal-kicker">Public profile</p>
                    <div class="space-y-2">
                        <h1 class="text-[clamp(2.1rem,4vw,3.35rem)] leading-[0.94]">
                            {{ profile.name }}
                        </h1>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            A shareable sea-kayak journal showing only public paddles, mapped routes, expedition places, and logged environmental exposure.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span class="journal-chip journal-chip--primary">{{ profile.homeWater }}</span>
                        <span class="journal-chip">{{ profile.timezone }}</span>
                        <span class="journal-chip">Public logbook</span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link href="/" class="journal-utility-link">
                        Home
                    </Link>
                    <Link href="/login" class="journal-primary-link">
                        Open private dashboard
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in metricCards"
                :key="card.label"
                class="journal-metric-card"
                :style="{ background: card.style }"
            >
                <p class="journal-kicker">{{ card.label }}</p>
                <p class="mt-4 text-3xl font-semibold text-[color:var(--journal-text)] md:text-[2.2rem]">
                    {{ card.value }}
                </p>
                <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                    {{ card.detail }}
                </p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
            <article class="journal-card px-5 py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Consistency</p>
                        <h3 class="mt-2 text-[1.7rem] leading-none">Distance by month</h3>
                    </div>
                    <span class="journal-chip">Rolling 12 months</span>
                </div>

                <div class="mt-6 grid gap-4">
                    <div
                        v-for="item in monthlyDistance"
                        :key="item.key"
                        class="grid grid-cols-[42px_minmax(0,1fr)_72px] items-center gap-3"
                    >
                        <span class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">
                            {{ item.label }}
                        </span>
                        <div class="h-4 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]">
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max((item.distanceKm / monthlyMax) * 100, item.distanceKm > 0 ? 8 : 0)}%`,
                                    background: 'linear-gradient(90deg, #6772ff, #9c80ff 48%, #ff9c6b)',
                                }"
                            />
                        </div>
                        <span class="text-right text-sm font-medium text-[color:var(--journal-muted)]">
                            {{ item.distanceKm ? `${item.distanceKm.toFixed(1)} km` : '–' }}
                        </span>
                    </div>
                </div>
            </article>

            <article class="journal-card px-5 py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Compare</p>
                        <h3 class="mt-2 text-[1.7rem] leading-none">All time / year / 12m</h3>
                    </div>
                    <span class="journal-chip">Public only</span>
                </div>

                <div class="mt-6 grid gap-3">
                    <article
                        v-for="snapshot in yearSnapshots"
                        :key="snapshot.label"
                        class="rounded-[22px] border border-[color:var(--journal-line)] bg-white/78 px-4 py-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">
                                    {{ snapshot.label }}
                                </p>
                                <p class="mt-2 text-2xl font-semibold text-[color:var(--journal-text)]">
                                    {{ snapshot.value.toFixed(1) }}
                                    <span class="text-base text-[color:var(--journal-muted)]">{{ snapshot.unit }}</span>
                                </p>
                            </div>
                            <p class="max-w-[140px] text-right text-xs leading-5 text-[color:var(--journal-muted)]">
                                {{ snapshot.detail }}
                            </p>
                        </div>
                    </article>
                </div>
            </article>
        </section>

        <SeaStatePanels :sea-state="seaState" />

        <section class="journal-panel px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="journal-kicker">Map</p>
                    <h3 class="mt-2 text-[1.8rem] leading-none">Public route map</h3>
                </div>
                <span class="journal-chip">Shareable routes only</span>
            </div>

            <div class="mt-6">
                <RouteAtlasMap
                    :routes="mapData.routes"
                    :pins="mapData.pins"
                    :default-view="mapData.defaultView"
                    :storage-key="`${profile.slug}-public-route-atlas`"
                    :show-filters="false"
                    height-class="h-[520px]"
                />
            </div>
        </section>

        <section class="journal-panel px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="journal-kicker">Expeditions and multiday</p>
                    <h3 class="mt-2 text-[1.8rem] leading-none">I paddled here</h3>
                </div>
                <span class="journal-chip">Public expedition places</span>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <article
                    v-for="card in expeditionCards"
                    :key="card.label"
                    class="rounded-[24px] border border-[color:var(--journal-line)] bg-white/78 px-4 py-4"
                >
                    <p class="journal-kicker">{{ card.label }}</p>
                    <p class="mt-3 text-3xl font-semibold text-[color:var(--journal-text)]">
                        {{ card.value }}
                    </p>
                    <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                        {{ card.detail }}
                    </p>
                </article>
            </div>

            <div class="mt-6">
                <RouteAtlasMap
                    :routes="expeditionMapData.routes"
                    :pins="expeditionMapData.pins"
                    :default-view="expeditionMapData.defaultView"
                    :storage-key="`${profile.slug}-public-expedition-atlas`"
                    :show-legend="false"
                    :show-filters="false"
                    :show-kind-filter="false"
                    :show-geometry-filter="false"
                    empty-message="No public expedition locations yet."
                    height-class="h-[420px]"
                />
            </div>

            <div v-if="featuredPlaces.length" class="mt-6 grid gap-4 lg:grid-cols-3">
                <article
                    v-for="place in featuredPlaces"
                    :key="place.slug"
                    class="overflow-hidden rounded-[24px] border border-[color:var(--journal-line)] bg-white/78"
                >
                    <img
                        v-if="place.photoUrl"
                        :src="place.photoUrl"
                        :alt="place.label"
                        class="h-40 w-full object-cover"
                    />

                    <div class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h4 class="text-lg font-semibold text-[color:var(--journal-text)]">
                                    {{ place.label }}
                                </h4>
                                <p class="mt-1 text-sm text-[color:var(--journal-muted)]">
                                    {{ place.tripCount }} trips · {{ place.daysOut }} days
                                </p>
                            </div>

                            <Link :href="place.path" class="journal-utility-link">
                                Open
                            </Link>
                        </div>

                        <div class="flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]">
                            <span class="journal-chip">{{ place.distanceKm.toFixed(1) }} km</span>
                            <span v-if="place.latestDate" class="journal-chip">{{ place.latestDate }}</span>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="journal-panel px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="journal-kicker">Recent public paddles</p>
                    <h3 class="mt-2 text-[1.8rem] leading-none">Latest sessions</h3>
                </div>
            </div>

            <div class="mt-6 grid gap-4 xl:grid-cols-3">
                <article
                    v-for="session in recentSessions"
                    :key="session.id"
                    class="rounded-[24px] border border-[color:var(--journal-line)] bg-white/78 p-4"
                >
                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                        <span class="journal-chip">{{ session.routeCategoryLabel }}</span>
                        <span v-if="session.beaufort !== null" class="journal-chip">F{{ session.beaufort }}</span>
                        <span v-if="session.hasTrack" class="journal-chip">Track</span>
                    </div>

                    <div class="mt-4 space-y-2">
                        <h4 class="text-xl font-semibold text-[color:var(--journal-text)]">
                            {{ session.title }}
                        </h4>
                        <p class="text-sm text-[color:var(--journal-muted)]">
                            {{ session.date ?? 'No date' }}
                            <span v-if="session.launchName">· {{ session.launchName }}</span>
                        </p>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]">
                        <span class="journal-chip">{{ session.distanceKm.toFixed(1) }} km</span>
                        <span class="journal-chip">{{ session.durationMinutes }} min</span>
                        <span v-if="session.isExpedition" class="journal-chip journal-chip--primary">Expedition</span>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
