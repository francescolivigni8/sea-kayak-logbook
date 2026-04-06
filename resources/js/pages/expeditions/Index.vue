<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sea Kayak Logbook',
                href: dashboard(),
            },
            {
                title: 'Expeditions',
                href: '/expeditions',
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
        label: 'Expedition km',
        value: `${props.expeditionSummary.distanceKm.toFixed(1)} km`,
        detail: 'Distance logged from expedition-tagged sessions',
    },
    {
        label: 'Days out',
        value: props.expeditionSummary.daysOut.toString(),
        detail: 'Total multiday days recorded',
    },
    {
        label: 'Trips',
        value: props.expeditionSummary.tripCount.toString(),
        detail: `${props.expeditionPlaces.length} named expedition places`,
    },
]);
</script>

<template>
    <Head title="Expeditions" />

    <div class="flex flex-1 flex-col gap-6 rounded-[2rem] p-4 md:p-6">
        <section class="rounded-[2rem] border border-sidebar-border/70 bg-white/95 p-6 shadow-sm md:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-orange-400">
                        Expeditions and multiday
                    </p>
                    <Heading
                        title="Expedition atlas"
                        :description="`A dedicated view of where ${profile.name} has paddled on multiday or expedition-tagged sessions.`"
                    />
                </div>

                <div class="flex flex-wrap gap-3">
                    <Button v-if="profile.isPublic" as-child variant="outline">
                        <Link :href="`${profile.publicPath}/expeditions`">Open public expedition atlas</Link>
                    </Button>
                    <Button as-child>
                        <Link href="/sessions/create">Add expedition session</Link>
                    </Button>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <article
                v-for="card in cards"
                :key="card.label"
                class="rounded-[1.5rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm"
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

        <section class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                        I paddled here
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                        Global expedition footprint
                    </h2>
                </div>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500">
                    Tap a pin to open the place page
                </span>
            </div>

            <div class="mt-6">
                <RouteAtlasMap
                    :routes="expeditionMapData.routes"
                    :pins="expeditionMapData.pins"
                    :default-view="expeditionMapData.defaultView"
                    :storage-key="`${profile.slug}-expedition-index`"
                    :show-legend="false"
                    :show-kind-filter="false"
                    :show-geometry-filter="false"
                    height-class="h-[520px]"
                    empty-message="No expedition locations yet."
                />
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="place in expeditionPlaces"
                :key="place.slug"
                class="overflow-hidden rounded-[1.5rem] border border-sidebar-border/70 bg-white/95 shadow-sm"
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
</template>
