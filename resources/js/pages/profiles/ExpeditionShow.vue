<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';
import { Button } from '@/components/ui/button';

interface ProfileSummary {
    name: string;
    slug: string;
    homeWater: string;
    timezone: string;
    isPublic: boolean;
    publicPath: string;
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

interface PlacePhoto {
    id: number;
    title: string;
    date: string | null;
    url: string;
    name: string | null;
    notes: string | null;
}

interface PlaceSession {
    id: number;
    title: string;
    date: string | null;
    distanceKm: number;
    durationMinutes: number;
    daysOut: number;
    launchName: string | null;
    routeCategoryLabel: string;
    beaufort: number | null;
    photoUrl: string | null;
    notes: string | null;
    path: string | null;
}

const props = defineProps<{
    profile: ProfileSummary;
    place: ExpeditionPlace;
    mapData: MapData;
    photos: PlacePhoto[];
    sessions: PlaceSession[];
}>();

const cards = computed(() => [
    {
        label: 'Public distance',
        value: `${props.place.distanceKm.toFixed(1)} km`,
        detail: 'Shared expedition distance at this place',
    },
    {
        label: 'Public days out',
        value: props.place.daysOut.toString(),
        detail: 'Shared expedition days recorded here',
    },
    {
        label: 'Public trips',
        value: props.place.tripCount.toString(),
        detail: props.place.latestDate
            ? `Latest public trip ${props.place.latestDate}`
            : 'No latest date logged',
    },
    {
        label: 'Mapped tracks',
        value: props.mapData.routes.length.toString(),
        detail: 'Public expedition sessions here with route track data',
    },
]);
</script>

<template>
    <Head :title="`${place.label} · Public expedition place`" />

    <div
        class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-cyan-50 px-4 py-6 md:px-6 md:py-10"
    >
        <div class="mx-auto flex max-w-7xl flex-col gap-6">
            <section
                class="rounded-[2rem] border border-slate-200/80 bg-white/95 p-6 shadow-sm md:p-8"
            >
                <div
                    class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between"
                >
                    <div class="space-y-4">
                        <p
                            class="text-xs font-semibold tracking-[0.32em] text-orange-400 uppercase"
                        >
                            Public expedition place
                        </p>
                        <Heading
                            :title="place.label"
                            :description="`A shareable expedition-place view from ${profile.name}, rolling up public multiday paddles, route traces, and field notes.`"
                        />

                        <div
                            class="flex flex-wrap gap-3 text-sm text-slate-500"
                        >
                            <span
                                class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2"
                            >
                                {{ profile.homeWater }}
                            </span>
                            <span
                                class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2"
                            >
                                {{ place.tripCount }} trips
                            </span>
                            <span
                                v-if="place.latestDate"
                                class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2"
                            >
                                {{ place.latestDate }}
                            </span>
                        </div>
                    </div>

                    <Button as-child variant="outline">
                        <Link :href="profile.publicPath"
                            >Back to public profile</Link
                        >
                    </Button>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="card in cards"
                    :key="card.label"
                    class="rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 shadow-sm"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.24em] text-orange-400 uppercase"
                    >
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

            <section
                class="rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 shadow-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.28em] text-orange-400 uppercase"
                        >
                            Public expedition atlas
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                            Shared tracks and launch points
                        </h2>
                    </div>
                    <span
                        class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500"
                    >
                        Public expedition traces only
                    </span>
                </div>

                <div class="mt-6">
                    <RouteAtlasMap
                        :routes="mapData.routes"
                        :pins="mapData.pins"
                        :default-view="mapData.defaultView"
                        :storage-key="`${profile.slug}-${place.slug}-public-place-atlas`"
                        :show-kind-filter="false"
                        height-class="h-[520px]"
                        empty-message="No public mapped geometry at this expedition place yet."
                    />
                </div>
            </section>

            <section
                class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]"
            >
                <article
                    class="rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 shadow-sm"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.28em] text-orange-400 uppercase"
                        >
                            Public expedition sessions
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                            Trip log
                        </h2>
                    </div>

                    <div class="mt-6 grid gap-3">
                        <article
                            v-for="session in sessions"
                            :key="session.id"
                            class="rounded-[1.25rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                        >
                            <div
                                class="flex flex-wrap gap-2 text-xs font-medium"
                            >
                                <span
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600"
                                >
                                    {{ session.routeCategoryLabel }}
                                </span>
                                <span
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600"
                                >
                                    {{ session.daysOut }} days
                                </span>
                                <span
                                    v-if="session.beaufort !== null"
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600"
                                >
                                    F{{ session.beaufort }}
                                </span>
                            </div>

                            <div class="mt-3">
                                <h3
                                    class="text-lg font-semibold text-slate-900"
                                >
                                    {{ session.title }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ session.date ?? 'No date' }}
                                    <span v-if="session.launchName"
                                        >· {{ session.launchName }}</span
                                    >
                                </p>
                            </div>

                            <div
                                class="mt-3 flex flex-wrap gap-2 text-xs font-medium text-slate-600"
                            >
                                <span
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1"
                                >
                                    {{ session.distanceKm.toFixed(1) }} km
                                </span>
                                <span
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1"
                                >
                                    {{ session.durationMinutes }} min
                                </span>
                            </div>

                            <p
                                v-if="session.notes"
                                class="mt-3 text-sm leading-6 text-slate-500"
                            >
                                {{ session.notes }}
                            </p>
                        </article>
                    </div>
                </article>

                <article
                    class="rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 shadow-sm"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.28em] text-orange-400 uppercase"
                        >
                            Public expedition gallery
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                            Photos and field notes
                        </h2>
                    </div>

                    <div v-if="photos.length" class="mt-6 grid gap-4">
                        <article
                            v-for="photo in photos"
                            :key="photo.id"
                            class="overflow-hidden rounded-[1.25rem] border border-slate-200 bg-slate-50/80"
                        >
                            <img
                                :src="photo.url"
                                :alt="photo.name ?? photo.title"
                                class="h-56 w-full object-cover"
                            />
                            <div class="p-4">
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <h3
                                        class="text-base font-semibold text-slate-900"
                                    >
                                        {{ photo.title }}
                                    </h3>
                                    <span
                                        class="text-xs font-medium text-slate-500"
                                    >
                                        {{ photo.date ?? 'No date' }}
                                    </span>
                                </div>
                                <p
                                    v-if="photo.notes"
                                    class="mt-3 text-sm leading-6 text-slate-500"
                                >
                                    {{ photo.notes }}
                                </p>
                            </div>
                        </article>
                    </div>

                    <div
                        v-else
                        class="mt-6 rounded-[1.25rem] border border-dashed border-slate-300 bg-slate-50/80 px-4 py-10 text-sm text-slate-500"
                    >
                        No public expedition photos here yet.
                    </div>
                </article>
            </section>
        </div>
    </div>
</template>
