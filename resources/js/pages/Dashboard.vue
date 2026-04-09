<script setup lang="ts">
import SeaStatePanels from '@/components/dashboard/SeaStatePanels.vue';
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
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

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const metricCards = computed(() => [
    {
        label: 'Total distance',
        value: `${props.headline.distanceKm.toFixed(1)} km`,
        detail: `${props.headline.sessionCount} paddles logged`,
        style: 'linear-gradient(135deg, rgba(103,114,255,0.14), rgba(255,255,255,0.9))',
    },
    {
        label: 'Total duration',
        value: `${props.headline.durationHours.toFixed(1)} h`,
        detail: `${props.headline.averageDistanceKm.toFixed(1)} km average session`,
        style: 'linear-gradient(135deg, rgba(122,215,208,0.18), rgba(255,255,255,0.9))',
    },
    {
        label: 'Average air temperature',
        value: props.seaState.temperatureAverages.air !== null ? `${props.seaState.temperatureAverages.air.toFixed(1)} C` : '—',
        detail: 'Across sessions with air temperature logged',
        style: 'linear-gradient(135deg, rgba(255,156,107,0.16), rgba(255,255,255,0.9))',
    },
    {
        label: 'Average sea temperature',
        value: props.seaState.temperatureAverages.sea !== null ? `${props.seaState.temperatureAverages.sea.toFixed(1)} C` : '—',
        detail: 'Across sessions with sea temperature logged',
        style: 'linear-gradient(135deg, rgba(148,141,255,0.16), rgba(255,255,255,0.9))',
    },
]);

const expeditionCards = computed(() => [
    {
        label: 'Total expedition km',
        value: `${props.expeditionSummary.distanceKm.toFixed(1)} km`,
        detail: 'Tagged sessions, still counted in full totals',
    },
    {
        label: 'Total expedition days',
        value: String(props.expeditionSummary.daysOut),
        detail: 'Logged days out',
    },
    {
        label: 'Total multiday trips',
        value: String(props.expeditionSummary.tripCount),
        detail: 'Expedition-tagged sessions',
    },
]);

const featuredPlaces = computed(() => props.expeditionPlaces.slice(0, 3));
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-5">
        <section v-if="successMessage" class="journal-banner">
            {{ successMessage }}
        </section>

        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <p class="journal-kicker">Dashboard</p>
                    <div class="space-y-2">
                        <h2 class="text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96]">
                            All sessions
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            Distance, exposure, rescue, routes, and expedition footprint at a glance.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col items-start gap-3 xl:items-end">
                    <p class="text-sm font-medium text-[color:var(--journal-muted)]">
                        {{ headline.sessionCount }} paddles across {{ headline.paddledMonths }} active months.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Link v-if="profile.isPublic" :href="profile.publicPath" class="journal-utility-link">
                            Public profile
                        </Link>
                        <Link href="/sessions" class="journal-utility-link">
                            Library
                        </Link>
                        <Link href="/sessions/create" class="journal-primary-link">
                            Add session
                        </Link>
                    </div>
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

        <SeaStatePanels
            :sea-state="seaState"
            :year-snapshots="yearSnapshots"
            :monthly-distance="monthlyDistance"
            compare-chip="Distance"
        />

        <section class="journal-panel px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="journal-kicker">Map</p>
                    <h3 class="mt-2 text-[1.8rem] leading-none">Route map</h3>
                </div>
                <span class="journal-chip">{{ mapData.routes.length }} routes</span>
            </div>

            <div class="mt-6">
                <RouteAtlasMap
                    :routes="mapData.routes"
                    :pins="mapData.pins"
                    :default-view="mapData.defaultView"
                    :storage-key="`${profile.slug}-route-atlas`"
                    :show-filters="false"
                    height-class="h-[520px]"
                />
            </div>
        </section>

        <section class="journal-panel px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="journal-kicker">Expeditions</p>
                    <h3 class="mt-2 text-[1.8rem] leading-none">Expeditions and multiday</h3>
                </div>
                <span class="journal-chip">Checklist tagged</span>
            </div>

            <div class="mt-6 rounded-[24px] border border-[color:var(--journal-line)] bg-white/78 px-5 py-5">
                <p class="text-base font-semibold text-[color:var(--journal-text)]">
                    Longer journeys, kept separate and still counted in the full logbook totals.
                </p>
                <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                    Tag a session as expedition in the checklist and optionally log the days out.
                </p>
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
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Expeditions</p>
                        <h4 class="mt-2 text-[1.45rem] leading-none text-[color:var(--journal-text)]">I paddled here</h4>
                    </div>
                    <span class="text-sm font-medium text-[color:var(--journal-muted)]">{{ expeditionPlaces.length }} places</span>
                </div>

                <RouteAtlasMap
                    :routes="expeditionMapData.routes"
                    :pins="expeditionMapData.pins"
                    :default-view="expeditionMapData.defaultView"
                    :storage-key="`${profile.slug}-expedition-footprint`"
                    pin-presentation="pin"
                    :show-legend="false"
                    :show-filters="false"
                    :show-kind-filter="false"
                    :show-geometry-filter="false"
                    :allow-pin-view="false"
                    empty-message="No expedition locations logged yet."
                    height-class="h-[440px]"
                />
            </div>

            <div v-if="featuredPlaces.length" class="mt-6 grid gap-4 lg:grid-cols-3">
                <article
                    v-for="place in featuredPlaces"
                    :key="place.slug"
                    class="overflow-hidden rounded-[24px] border border-[color:var(--journal-line)] bg-white/78"
                    :style="{
                        background: place.photoUrl
                            ? 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(122,215,208,0.08))'
                            : 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(103,114,255,0.05))',
                    }"
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

                            <span class="journal-chip">{{ place.tripCount }} trips</span>
                        </div>

                        <div class="flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]">
                            <span class="journal-chip">{{ place.distanceKm.toFixed(1) }} km</span>
                            <span v-if="place.latestDate" class="journal-chip">{{ place.latestDate }}</span>
                        </div>

                        <Link :href="place.path" class="journal-utility-link w-full justify-center">
                            Open place
                        </Link>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
