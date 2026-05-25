<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import HeadlineMetricCards from '@/components/dashboard/HeadlineMetricCards.vue';
import SeaStatePanels from '@/components/dashboard/SeaStatePanels.vue';
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';
import { useUnitPreferences } from '@/composables/useUnitPreferences';
import { formatDistanceKm } from '@/lib/units';

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
    averageSpeedKnots: number | null;
    averageSpeedSamples: number;
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
    averageBeaufort: number | null;
    swellBands: Array<{ label: string; count: number }>;
    averageSwellHeightM: number | null;
    swellSessionCount: number;
    tideStates: Array<{ label: string; count: number }>;
    conditionMatrix: Array<{
        label: string;
        values: Array<{ key: string; count: number }>;
    }>;
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
        path?: string | null;
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
    missingMapPointCount: number;
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
const { unitPreferences } = useUnitPreferences();
const formatDistance = (distanceKm: number) =>
    formatDistanceKm(distanceKm, unitPreferences.value);

const expeditionCards = computed(() => [
    {
        label: 'Expedition distance',
        value: formatDistanceKm(
            props.expeditionSummary.distanceKm,
            unitPreferences.value,
        ),
        detail: 'Public expedition distance',
    },
    {
        label: 'Days out',
        value: String(props.expeditionSummary.daysOut),
        detail: 'Public multiday days logged',
    },
    {
        label: 'Multiday trips',
        value: String(props.expeditionSummary.tripCount),
        detail: 'Public expedition sessions',
    },
]);

const featuredPlaces = computed(() => props.expeditionPlaces.slice(0, 3));
const expeditionMapWarning = computed(() => {
    const count = props.expeditionSummary.missingMapPointCount;

    if (!count) {
        return null;
    }

    return count === 1
        ? '1 public expedition session is still missing a track or saved coordinates, so it does not appear on this world map yet.'
        : `${count} public expedition sessions are still missing a track or saved coordinates, so they do not appear on this world map yet.`;
});
</script>

<template>
    <Head :title="`${profile.name} · Sea Kayak Logbook`" />

    <div class="journal-page">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div
                class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
            >
                <div class="space-y-2">
                    <h1
                        class="text-[clamp(2.1rem,4vw,3.35rem)] leading-[0.94]"
                    >
                        {{ profile.name }}’s Sea Kayak Logbook
                    </h1>
                    <p
                        v-if="profile.homeWater"
                        class="text-sm leading-6 text-[color:var(--journal-muted)]"
                    >
                        {{ profile.homeWater }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link href="/login" class="journal-primary-link">
                        Open private dashboard
                    </Link>
                </div>
            </div>
        </section>

        <HeadlineMetricCards
            :headline="headline"
            :sea-state="seaState"
            :monthly-distance="monthlyDistance"
            context="public"
        />

        <SeaStatePanels
            :sea-state="seaState"
            :year-snapshots="yearSnapshots"
            :monthly-distance="monthlyDistance"
            compare-chip="Public only"
        />

        <section class="journal-panel px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="mt-2 text-[1.8rem] leading-none">
                        Public route map
                    </h3>
                </div>
                <span class="journal-chip"
                    >{{ mapData.routes.length }} routes</span
                >
            </div>

            <div class="mt-6">
                <RouteAtlasMap
                    :routes="mapData.routes"
                    :pins="mapData.pins"
                    :default-view="mapData.defaultView"
                    :storage-key="`${profile.slug}-public-route-atlas`"
                    :show-filters="false"
                    :allow-pin-view="false"
                    height-class="h-[320px] sm:h-[420px] lg:h-[520px]"
                />
            </div>
        </section>

        <section class="journal-panel px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="mt-2 text-[1.8rem] leading-none">
                        Expeditions and multiday
                    </h3>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <article
                    v-for="card in expeditionCards"
                    :key="card.label"
                    class="rounded-[24px] border border-[color:var(--journal-line)] bg-white/78 px-4 py-4"
                >
                    <p class="journal-kicker">{{ card.label }}</p>
                    <p
                        class="mt-3 text-3xl font-semibold text-[color:var(--journal-text)]"
                    >
                        {{ card.value }}
                    </p>
                    <p
                        class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                    >
                        {{ card.detail }}
                    </p>
                </article>
            </div>

            <section
                v-if="expeditionMapWarning"
                class="journal-banner journal-banner--danger mt-6"
            >
                {{ expeditionMapWarning }}
            </section>

            <div class="mt-6">
                <div
                    class="mb-4 flex flex-wrap items-start justify-between gap-3"
                >
                    <div>
                        <h4
                            class="mt-2 text-[1.45rem] leading-none text-[color:var(--journal-text)]"
                        >
                            I paddled here
                        </h4>
                    </div>
                    <span
                        class="text-sm font-medium text-[color:var(--journal-muted)]"
                        >{{ expeditionMapData.pins.length }} places</span
                    >
                </div>

                <RouteAtlasMap
                    :routes="expeditionMapData.routes"
                    :pins="expeditionMapData.pins"
                    :default-view="expeditionMapData.defaultView"
                    :storage-key="`${profile.slug}-public-expedition-atlas`"
                    pin-presentation="expedition"
                    :auto-fit-to-geometry="false"
                    :show-legend="false"
                    :show-filters="false"
                    :show-kind-filter="false"
                    :show-geometry-filter="false"
                    empty-message="No public paddled locations yet."
                    height-class="h-[280px] sm:h-[360px] lg:h-[420px]"
                />
            </div>

            <div
                v-if="featuredPlaces.length"
                class="mt-6 grid gap-4 lg:grid-cols-3"
            >
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
                                <h4
                                    class="text-lg font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ place.label }}
                                </h4>
                                <p
                                    class="mt-1 text-sm text-[color:var(--journal-muted)]"
                                >
                                    {{ place.tripCount }} trips ·
                                    {{ place.daysOut }} days
                                </p>
                            </div>

                            <span class="journal-chip"
                                >{{ place.tripCount }} trips</span
                            >
                        </div>

                        <div
                            class="flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]"
                        >
                            <span class="journal-chip">{{
                                formatDistance(place.distanceKm)
                            }}</span>
                            <span
                                v-if="place.latestDate"
                                class="journal-chip"
                                >{{ place.latestDate }}</span
                            >
                        </div>

                        <Link
                            :href="place.path"
                            class="journal-utility-link w-full justify-center"
                        >
                            Open place
                        </Link>
                    </div>
                </article>
            </div>
        </section>

        <section class="journal-panel px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="journal-kicker">Recent public paddles</p>
                    <h3 class="mt-2 text-[1.8rem] leading-none">
                        Latest sessions
                    </h3>
                </div>
            </div>

            <div class="mt-6 grid gap-4 xl:grid-cols-3">
                <article
                    v-for="session in recentSessions"
                    :key="session.id"
                    class="rounded-[24px] border border-[color:var(--journal-line)] bg-white/78 p-4"
                    :style="{
                        background: session.isExpedition
                            ? 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(255,156,107,0.08))'
                            : 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(103,114,255,0.05))',
                    }"
                >
                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                        <span class="journal-chip">{{
                            session.routeCategoryLabel
                        }}</span>
                        <span
                            v-if="session.beaufort !== null"
                            class="journal-chip"
                            >F{{ session.beaufort }}</span
                        >
                        <span v-if="session.hasTrack" class="journal-chip"
                            >Track</span
                        >
                    </div>

                    <div class="mt-4 space-y-2">
                        <h4
                            class="text-xl font-semibold text-[color:var(--journal-text)]"
                        >
                            {{ session.title }}
                        </h4>
                        <p class="text-sm text-[color:var(--journal-muted)]">
                            {{ session.date ?? 'No date' }}
                            <span v-if="session.launchName"
                                >· {{ session.launchName }}</span
                            >
                        </p>
                    </div>

                    <div
                        class="mt-4 flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]"
                    >
                        <span class="journal-chip">{{
                            formatDistance(session.distanceKm)
                        }}</span>
                        <span class="journal-chip"
                            >{{ session.durationMinutes }} min</span
                        >
                        <span
                            v-if="session.isExpedition"
                            class="journal-chip journal-chip--primary"
                            >Expedition</span
                        >
                    </div>

                    <p
                        class="mt-4 text-sm leading-6 text-[color:var(--journal-muted)]"
                    >
                        {{ session.launchName ?? profile.homeWater }} ·
                        {{ session.routeCategoryLabel.toLowerCase() }} day{{
                            session.isExpedition
                                ? ' with expedition tagging'
                                : ''
                        }}.
                    </p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div
                            class="rounded-[18px] border border-[color:var(--journal-line)] bg-white/74 px-3 py-3"
                        >
                            <p
                                class="text-xs font-semibold tracking-[0.2em] text-[color:var(--journal-faint)] uppercase"
                            >
                                Place
                            </p>
                            <p
                                class="mt-2 text-base font-semibold text-[color:var(--journal-text)]"
                            >
                                {{ session.launchName ?? profile.homeWater }}
                            </p>
                        </div>
                        <div
                            class="rounded-[18px] border border-[color:var(--journal-line)] bg-white/74 px-3 py-3"
                        >
                            <p
                                class="text-xs font-semibold tracking-[0.2em] text-[color:var(--journal-faint)] uppercase"
                            >
                                Session
                            </p>
                            <p
                                class="mt-2 text-base font-semibold text-[color:var(--journal-text)]"
                            >
                                {{
                                    session.hasTrack
                                        ? 'Track attached'
                                        : 'Manual entry'
                                }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
