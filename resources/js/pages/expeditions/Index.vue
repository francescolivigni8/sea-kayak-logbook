<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';

interface ProfileSummary {
    name: string;
    slug: string;
    homeWater: string;
    timezone: string;
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

interface MapData {
    defaultView: { lat: number; lng: number; zoom: number };
    routes: Array<never>;
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

const props = defineProps<{
    profile: ProfileSummary;
    expeditionSummary: ExpeditionSummary;
    expeditionPlaces: ExpeditionPlace[];
    expeditionMapData: MapData;
}>();

const cards = computed(() => [
    {
        label: 'Total expedition km',
        value: `${props.expeditionSummary.distanceKm.toFixed(1)} km`,
        detail: 'Tagged in the checklist',
    },
    {
        label: 'Total expedition days',
        value: props.expeditionSummary.daysOut.toString(),
        detail: 'Logged days out',
    },
    {
        label: 'Total multiday trips',
        value: props.expeditionSummary.tripCount.toString(),
        detail: `${props.expeditionPlaces.length} named places`,
    },
]);

const expeditionMapWarning = computed(() => {
    const count = props.expeditionSummary.missingMapPointCount;

    if (!count) {
        return null;
    }

    return count === 1
        ? '1 expedition session is still missing a track or saved coordinates, so it cannot appear on this world map yet.'
        : `${count} expedition sessions are still missing a track or saved coordinates, so they cannot appear on this world map yet.`;
});
</script>

<template>
    <Head title="Expeditions" />

    <div class="space-y-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div
                class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
            >
                <div class="space-y-3">
                    <p class="journal-kicker">Expeditions</p>
                    <div class="space-y-2">
                        <h2
                            class="text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96]"
                        >
                            Expeditions and multiday
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            Longer journeys, kept separate and still counted in
                            the full logbook totals.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="journal-chip journal-chip--primary"
                        >Checklist tagged</span
                    >
                    <Link href="/sessions/create" class="journal-primary-link"
                        >Add expedition session</Link
                    >
                </div>
            </div>
        </section>

        <section class="journal-banner journal-banner--soft">
            Tag a session as expedition in the checklist and optionally log the
            days out to build this area automatically.
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <article
                v-for="card in cards"
                :key="card.label"
                class="journal-metric-card"
                :style="{
                    background:
                        card.label === 'Total expedition km'
                            ? 'linear-gradient(135deg, rgba(103,114,255,0.14), rgba(255,255,255,0.9))'
                            : card.label === 'Total expedition days'
                              ? 'linear-gradient(135deg, rgba(122,215,208,0.18), rgba(255,255,255,0.9))'
                              : 'linear-gradient(135deg, rgba(255,156,107,0.16), rgba(255,255,255,0.9))',
                }"
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

        <section class="journal-card px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="journal-kicker">I paddled here</p>
                    <h2
                        class="mt-2 text-[1.7rem] leading-none text-[color:var(--journal-text)]"
                        >
                        Global paddling footprint
                    </h2>
                </div>
                <span
                    class="text-sm font-medium text-[color:var(--journal-muted)]"
                    >{{ expeditionMapData.pins.length }} places</span
                >
            </div>

            <div class="mt-6">
                <RouteAtlasMap
                    :routes="expeditionMapData.routes"
                    :pins="expeditionMapData.pins"
                    :default-view="expeditionMapData.defaultView"
                    :storage-key="`${profile.slug}-expedition-index`"
                    pin-presentation="expedition"
                    :auto-fit-to-geometry="false"
                    :show-legend="false"
                    :show-filters="false"
                    :show-kind-filter="false"
                    :show-geometry-filter="false"
                    height-class="h-[520px]"
                    empty-message="No paddled locations yet."
                />
            </div>

            <section
                v-if="expeditionMapWarning"
                class="journal-banner journal-banner--danger mt-5"
            >
                {{ expeditionMapWarning }}
            </section>

            <p
                v-if="expeditionPlaces.length"
                class="mt-4 text-sm leading-6 text-[color:var(--journal-muted)]"
            >
                The map groups all logged paddled locations into one pin per
                place. The cards below remain expedition-tagged places.
            </p>
        </section>

        <section class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="place in expeditionPlaces"
                :key="place.slug"
                class="journal-card overflow-hidden"
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
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3
                                class="text-lg font-semibold text-[color:var(--journal-text)]"
                            >
                                {{ place.label }}
                            </h3>
                            <p
                                class="mt-1 text-sm text-[color:var(--journal-muted)]"
                            >
                                {{ place.tripCount }} trips ·
                                {{ place.daysOut }} days out
                            </p>
                        </div>
                        <span class="journal-chip"
                            >{{ place.tripCount }} trips</span
                        >
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="journal-chip">
                            {{ place.distanceKm.toFixed(1) }} km
                        </span>
                        <span v-if="place.latestDate" class="journal-chip">
                            {{ place.latestDate }}
                        </span>
                    </div>

                    <div class="mt-5">
                        <Link
                            :href="place.path"
                            class="journal-utility-link w-full justify-center"
                            >Open place</Link
                        >
                    </div>
                </div>
            </article>
        </section>
    </div>
</template>
