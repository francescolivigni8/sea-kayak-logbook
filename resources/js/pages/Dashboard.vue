<script setup lang="ts">
import HeadlineMetricCards from '@/components/dashboard/HeadlineMetricCards.vue';
import SeaStatePanels from '@/components/dashboard/SeaStatePanels.vue';
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface ProfileSummary {
    name: string;
    slug: string;
    bio: string;
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

const props = defineProps<{
    profile: ProfileSummary;
    headline: HeadlineStats;
    yearSnapshots: SnapshotCard[];
    monthlyDistance: MonthlyDistanceRow[];
    seaState: SeaState;
    mapData: MapData;
    expeditionSummary: ExpeditionSummary;
    expeditionPlaces: Array<unknown>;
    expeditionMapData: MapData;
}>();

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

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

const expeditionSessionChips = computed(() =>
    props.expeditionMapData.pins
        .filter((pin) => Boolean(pin.path))
        .slice(0, 8),
);
const expeditionMapWarning = computed(() => {
    const count = props.expeditionSummary.missingMapPointCount;

    if (!count) {
        return null;
    }

    return count === 1
        ? '1 expedition session is still missing a track or saved coordinates, so it cannot appear on the world map yet.'
        : `${count} expedition sessions are still missing a track or saved coordinates, so they cannot appear on the world map yet.`;
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-5">
        <section v-if="successMessage" class="journal-banner">
            {{ successMessage }}
        </section>

        <HeadlineMetricCards
            :headline="headline"
            :sea-state="seaState"
            :monthly-distance="monthlyDistance"
            context="private"
        />

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
                    height-class="h-[560px]"
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
                    Tag a session as expedition and optionally log the days out in the checklist. Each tagged session drops its own world pin here.
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

            <section v-if="expeditionMapWarning" class="journal-banner journal-banner--danger mt-6">
                {{ expeditionMapWarning }}
            </section>

            <div class="mt-6">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Expeditions</p>
                        <h4 class="mt-2 text-[1.45rem] leading-none text-[color:var(--journal-text)]">I paddled here</h4>
                    </div>
                    <span class="text-sm font-medium text-[color:var(--journal-muted)]">{{ expeditionMapData.pins.length }} pins</span>
                </div>

                <RouteAtlasMap
                    :routes="expeditionMapData.routes"
                    :pins="expeditionMapData.pins"
                    :default-view="expeditionMapData.defaultView"
                    :storage-key="`${profile.slug}-expedition-footprint`"
                    pin-presentation="expedition"
                    :auto-fit-to-geometry="false"
                    :show-legend="false"
                    :show-filters="false"
                    :show-kind-filter="false"
                    :show-geometry-filter="false"
                    empty-message="No expedition locations logged yet."
                    height-class="h-[440px]"
                />
            </div>

            <div v-if="expeditionSessionChips.length" class="mt-5 flex flex-wrap gap-2">
                <Link
                    v-for="pin in expeditionSessionChips"
                    :key="pin.id"
                    :href="pin.path ?? '#'"
                    class="journal-chip transition hover:-translate-y-0.5 hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                >
                    {{ pin.label }}
                </Link>
            </div>
            <p v-if="expeditionSessionChips.length" class="mt-3 text-sm leading-6 text-[color:var(--journal-muted)]">
                Session chips below jump straight into the expedition log entry.
            </p>
        </section>
    </div>
</template>
