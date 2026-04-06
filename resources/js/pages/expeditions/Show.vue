<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';
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
                href: '#',
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
        label: 'Distance here',
        value: `${props.place.distanceKm.toFixed(1)} km`,
        detail: 'Expedition distance logged at this place',
    },
    {
        label: 'Days out',
        value: props.place.daysOut.toString(),
        detail: 'Total multiday days recorded here',
    },
    {
        label: 'Trips',
        value: props.place.tripCount.toString(),
        detail: props.place.latestDate ? `Latest expedition ${props.place.latestDate}` : 'No latest date logged',
    },
    {
        label: 'Tracked routes',
        value: props.mapData.routes.length.toString(),
        detail: 'Expedition sessions here with route track data',
    },
]);
</script>

<template>
    <Head :title="`${place.label} · Expedition place`" />

    <div class="space-y-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-4">
                    <p class="journal-kicker">Expedition place</p>
                    <Heading
                        :title="place.label"
                        :description="`Grouped expedition view for ${profile.name}. Tracks stay session-specific while this page gathers multiday field notes, photos, and repeat visits in one place.`"
                    />

                    <div class="flex flex-wrap gap-2">
                        <span class="journal-chip journal-chip--primary">
                            {{ profile.homeWater }}
                        </span>
                        <span class="journal-chip">
                            {{ place.tripCount }} trips
                        </span>
                        <span
                            v-if="place.latestDate"
                            class="journal-chip"
                        >
                            {{ place.latestDate }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Link v-if="profile.isPublic" :href="place.publicPath" class="journal-utility-link">Open public view</Link>
                    <Link href="/dashboard" class="journal-utility-link">Back to dashboard</Link>
                    <Link href="/sessions/create" class="journal-primary-link">Add session</Link>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in cards"
                :key="card.label"
                class="journal-metric-card"
                :style="{
                    background:
                        card.label === 'Distance here'
                            ? 'linear-gradient(135deg, rgba(103,114,255,0.14), rgba(255,255,255,0.9))'
                            : card.label === 'Days out'
                              ? 'linear-gradient(135deg, rgba(122,215,208,0.18), rgba(255,255,255,0.9))'
                              : card.label === 'Trips'
                                ? 'linear-gradient(135deg, rgba(255,156,107,0.16), rgba(255,255,255,0.9))'
                                : 'linear-gradient(135deg, rgba(148,141,255,0.16), rgba(255,255,255,0.9))',
                }"
            >
                <p class="journal-kicker">{{ card.label }}</p>
                <p class="mt-4 text-3xl font-semibold text-[color:var(--journal-text)]">{{ card.value }}</p>
                <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">{{ card.detail }}</p>
            </article>
        </section>

        <section class="journal-card px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="journal-kicker">Expedition atlas</p>
                    <h2 class="mt-2 text-[1.7rem] leading-none text-[color:var(--journal-text)]">Tracks and launch points</h2>
                </div>
                <span class="journal-chip">
                    Year filter and pinned view stay local to this place
                </span>
            </div>

            <div class="mt-6">
                <RouteAtlasMap
                    :routes="mapData.routes"
                    :pins="mapData.pins"
                    :default-view="mapData.defaultView"
                    :storage-key="`${profile.slug}-${place.slug}-place-atlas`"
                    :show-kind-filter="false"
                    height-class="h-[520px]"
                    empty-message="No mapped geometry at this expedition place yet."
                />
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
            <article class="journal-card px-5 py-5 md:px-6">
                <div>
                    <p class="journal-kicker">Expedition sessions</p>
                    <h2 class="mt-2 text-[1.7rem] leading-none text-[color:var(--journal-text)]">Trip log</h2>
                </div>

                <div class="mt-6 grid gap-3">
                    <article
                        v-for="session in sessions"
                        :key="session.id"
                        class="journal-soft-card"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-3">
                                <div class="flex flex-wrap gap-2 text-xs font-medium">
                                    <span class="journal-chip">
                                        {{ session.routeCategoryLabel }}
                                    </span>
                                    <span class="journal-chip">
                                        {{ session.daysOut }} days
                                    </span>
                                    <span
                                        v-if="session.beaufort !== null"
                                        class="journal-chip"
                                    >
                                        F{{ session.beaufort }}
                                    </span>
                                </div>

                                <div>
                                    <h3 class="text-lg font-semibold text-[color:var(--journal-text)]">
                                        {{ session.title }}
                                    </h3>
                                    <p class="mt-1 text-sm text-[color:var(--journal-muted)]">
                                        {{ session.date ?? 'No date' }}
                                        <span v-if="session.launchName">· {{ session.launchName }}</span>
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                                    <span class="journal-chip">
                                        {{ session.distanceKm.toFixed(1) }} km
                                    </span>
                                    <span class="journal-chip">
                                        {{ session.durationMinutes }} min
                                    </span>
                                </div>

                                <p v-if="session.notes" class="text-sm leading-6 text-[color:var(--journal-muted)]">
                                    {{ session.notes }}
                                </p>
                            </div>

                            <Link v-if="session.path" :href="session.path" class="journal-utility-link">Open</Link>
                        </div>
                    </article>
                </div>
            </article>

            <article class="journal-card px-5 py-5 md:px-6">
                <div>
                    <p class="journal-kicker">Expedition gallery</p>
                    <h2 class="mt-2 text-[1.7rem] leading-none text-[color:var(--journal-text)]">Photos and notes</h2>
                </div>

                <div v-if="photos.length" class="mt-6 grid gap-4">
                    <article
                        v-for="photo in photos"
                        :key="photo.id"
                        class="overflow-hidden rounded-[1.25rem] border border-[color:var(--journal-line)] bg-white/74"
                    >
                        <img :src="photo.url" :alt="photo.name ?? photo.title" class="h-56 w-full object-cover" />
                        <div class="p-4">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-base font-semibold text-[color:var(--journal-text)]">
                                    {{ photo.title }}
                                </h3>
                                <span class="text-xs font-medium text-[color:var(--journal-muted)]">
                                    {{ photo.date ?? 'No date' }}
                                </span>
                            </div>
                            <p v-if="photo.notes" class="mt-3 text-sm leading-6 text-[color:var(--journal-muted)]">
                                {{ photo.notes }}
                            </p>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-6 rounded-[1.25rem] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-4 py-10 text-sm text-[color:var(--journal-muted)]"
                >
                    No expedition photos here yet. Add session photos during logging to build the place gallery.
                </div>
            </article>
        </section>
    </div>
</template>
