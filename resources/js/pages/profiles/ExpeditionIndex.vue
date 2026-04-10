<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';
import { Button } from '@/components/ui/button';
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
    publicPath: string;
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
        label: 'Public expedition km',
        value: `${props.expeditionSummary.distanceKm.toFixed(1)} km`,
        detail: 'Shared expedition distance',
    },
    {
        label: 'Public days out',
        value: props.expeditionSummary.daysOut.toString(),
        detail: 'Shared multiday days',
    },
    {
        label: 'Public trips',
        value: props.expeditionSummary.tripCount.toString(),
        detail: `${props.expeditionPlaces.length} public expedition places`,
    },
]);

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
    <Head :title="`${profile.name} · Expedition atlas`" />

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-cyan-50 px-4 py-6 md:px-6 md:py-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-6">
            <section class="rounded-[2rem] border border-slate-200/80 bg-white/95 p-6 shadow-sm md:p-8">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                    <div class="space-y-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-orange-400">
                            Public expedition atlas
                        </p>
                        <Heading
                            :title="`${profile.name} · I paddled here`"
                            :description="`A world view of public expedition and multiday paddles from ${profile.name}.`"
                        />
                    </div>

                    <Button as-child variant="outline">
                        <Link :href="profile.publicPath">Back to public profile</Link>
                    </Button>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="card in cards"
                    :key="card.label"
                    class="rounded-[1.5rem] border border-slate-200/80 bg-white/95 p-5 shadow-sm"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">
                        {{ card.label }}
                    </p>
                    <p class="mt-4 text-3xl font-semibold text-slate-900">
                        {{ card.value }}
                    </p>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        {{ card.detail }}
                    </p>
                </article>
            </section>

            <section class="rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                            I paddled here
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                            Global public expedition footprint
                        </h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500">
                        {{ expeditionMapData.pins.length }} public expedition pins
                    </span>
                </div>

                <div class="mt-6">
                    <RouteAtlasMap
                        :routes="expeditionMapData.routes"
                        :pins="expeditionMapData.pins"
                        :default-view="expeditionMapData.defaultView"
                        :storage-key="`${profile.slug}-public-expedition-index`"
                        pin-presentation="expedition"
                        :auto-fit-to-geometry="false"
                        :show-legend="false"
                        :show-filters="false"
                        :show-kind-filter="false"
                        :show-geometry-filter="false"
                        height-class="h-[520px]"
                        empty-message="No public expedition locations yet."
                    />
                </div>

                <section v-if="expeditionMapWarning" class="mt-5 rounded-[1.35rem] border border-rose-200 bg-rose-50/90 px-4 py-4 text-sm leading-6 text-rose-700">
                    {{ expeditionMapWarning }}
                </section>

                <p v-if="expeditionPlaces.length" class="mt-4 text-sm leading-6 text-slate-500">
                    The map shows one pin per public expedition session. The cards below group repeated places together.
                </p>
            </section>

            <section class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="place in expeditionPlaces"
                    :key="place.slug"
                    class="overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-white/95 shadow-sm"
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
                                <h3 class="text-lg font-semibold text-slate-900">
                                    {{ place.label }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ place.tripCount }} trips · {{ place.daysOut }} days out
                                </p>
                            </div>
                            <Button as-child variant="outline" size="sm">
                                <Link :href="place.path">Open</Link>
                            </Button>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                                {{ place.distanceKm.toFixed(1) }} km
                            </span>
                            <span
                                v-if="place.latestDate"
                                class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1"
                            >
                                {{ place.latestDate }}
                            </span>
                        </div>
                    </div>
                </article>
            </section>
        </div>
    </div>
</template>
