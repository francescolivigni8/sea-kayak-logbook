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
                title: 'Sessions',
                href: '/sessions',
            },
            {
                title: 'Session detail',
                href: '#',
            },
        ],
    },
});

interface ProfileSummary {
    name: string;
    homeWater: string;
}

interface RoutePoint {
    x: number;
    y: number;
    lat: number;
    lng: number;
    minute: number;
    distanceKm: number;
    speedKmh: number;
}

interface ConditionRating {
    label: string;
    value: string;
}

interface SessionDetail {
    id: number;
    title: string;
    date: string | null;
    startTimeLocal: string | null;
    timezone: string;
    areaName: string | null;
    launchName: string | null;
    launchLat: number | null;
    launchLng: number | null;
    landingName: string | null;
    landingLat: number | null;
    landingLng: number | null;
    routeCategoryLabel: string;
    bodyOfWater: string | null;
    distanceKm: number;
    durationMinutes: number;
    movingMinutes: number | null;
    averageSpeedKmh: number | null;
    beaufort: number | null;
    windAvgMs: number | null;
    windGustMs: number | null;
    tideState: string | null;
    currentKnots: number | null;
    waveHeightM: number | null;
    swellHeightM: number | null;
    swellPeriodS: number | null;
    airTempC: number | null;
    seaTempC: number | null;
    visibilityCode: string | null;
    weatherSummary: string | null;
    routeSummary: string | null;
    notesPublic: string | null;
    notesPrivate: string | null;
    expeditionNotes: string | null;
    whatWentWell: string | null;
    improveNext: string | null;
    skills: string[];
    routeTags: string[];
    partners: string[];
    successfulRollsCount: number;
    wetExitsCount: number;
    towRescuesCount: number;
    confidenceScore: number | null;
    fatigueScore: number | null;
    decisionScore: number | null;
    conditionsLogged: boolean;
    developmentLogged: boolean;
    isExpedition: boolean;
    expeditionDays: number | null;
    isPublic: boolean;
    photoUrl: string | null;
    photoName: string | null;
    gpxUrl: string | null;
    gpxName: string | null;
    fitUrl: string | null;
    fitName: string | null;
    routeProfile: RoutePoint[];
    conditionRatings: ConditionRating[];
}

const props = defineProps<{
    profile: ProfileSummary;
    session: SessionDetail;
}>();

const heroChips = computed(() => [
    props.session.routeCategoryLabel,
    props.session.isExpedition ? 'Expedition' : 'Day session',
    props.session.bodyOfWater ? props.session.bodyOfWater.toUpperCase() : null,
    props.session.isPublic ? 'Public' : 'Private',
].filter(Boolean) as string[]);

const statCards = computed(() => [
    {
        label: 'Distance',
        value: `${props.session.distanceKm.toFixed(1)} km`,
    },
    {
        label: 'Duration',
        value: `${props.session.durationMinutes} min`,
    },
    {
        label: 'Average speed',
        value: props.session.averageSpeedKmh !== null ? `${props.session.averageSpeedKmh.toFixed(1)} km/h` : '—',
    },
    {
        label: 'Moving time',
        value: props.session.movingMinutes !== null ? `${props.session.movingMinutes} min` : '—',
    },
]);

const speedChart = computed(() => {
    const points = props.session.routeProfile;

    if (!points.length) {
        return '';
    }

    const maxSpeed = Math.max(...points.map((point) => point.speedKmh || 0), 1);

    return points
        .map((point, index) => {
            const x = 18 + (index / Math.max(points.length - 1, 1)) * 304;
            const y = 104 - (((point.speedKmh || 0) / maxSpeed) * 74);

            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
});

const conditionFacts = computed(() =>
    [
        props.session.windAvgMs !== null ? `Wind ${props.session.windAvgMs.toFixed(1)} m/s` : null,
        props.session.windGustMs !== null ? `Gusts ${props.session.windGustMs.toFixed(1)} m/s` : null,
        props.session.beaufort !== null ? `F${props.session.beaufort}` : null,
        props.session.tideState ? `Tide ${props.session.tideState}` : null,
        props.session.currentKnots !== null ? `Current ${props.session.currentKnots.toFixed(1)} kt` : null,
        props.session.waveHeightM !== null ? `Wave ${props.session.waveHeightM.toFixed(1)} m` : null,
        props.session.swellHeightM !== null ? `Swell ${props.session.swellHeightM.toFixed(1)} m` : null,
        props.session.swellPeriodS !== null ? `Period ${props.session.swellPeriodS.toFixed(0)} s` : null,
        props.session.airTempC !== null ? `Air ${props.session.airTempC.toFixed(1)} C` : null,
        props.session.seaTempC !== null ? `Sea ${props.session.seaTempC.toFixed(1)} C` : null,
        props.session.visibilityCode ? `Visibility ${props.session.visibilityCode}` : null,
    ].filter(Boolean) as string[],
);

const reflectionCards = computed(() =>
    [
        { label: 'What went well', value: props.session.whatWentWell },
        { label: 'Improve next', value: props.session.improveNext },
        { label: 'Public notes', value: props.session.notesPublic },
        { label: 'Private notes', value: props.session.notesPrivate },
        { label: 'Expedition notes', value: props.session.expeditionNotes },
    ].filter((item) => item.value),
);

const scoreCards = computed(() =>
    [
        { label: 'Confidence', value: props.session.confidenceScore },
        { label: 'Fatigue', value: props.session.fatigueScore },
        { label: 'Decision', value: props.session.decisionScore },
    ].filter((item) => item.value !== null),
);

const routeMapData = computed(() => {
    const routePoints = props.session.routeProfile
        .filter((point) => point.lat !== undefined && point.lng !== undefined)
        .map((point) => ({
            lat: point.lat,
            lng: point.lng,
        }));

    const routes = routePoints.length > 1 ? [
        {
            id: props.session.id,
            label: props.session.title,
            color: '#4f46e5',
            points: routePoints,
        },
    ] : [];

    const pins = [];

    if (!routes.length && props.session.launchLat !== null && props.session.launchLng !== null) {
        pins.push({
            id: 'launch',
            label: props.session.launchName ?? 'Launch',
            color: '#10b981',
            lat: props.session.launchLat,
            lng: props.session.launchLng,
        });
    }

    if (!routes.length && props.session.landingLat !== null && props.session.landingLng !== null) {
        pins.push({
            id: 'landing',
            label: props.session.landingName ?? 'Landing',
            color: '#f97316',
            lat: props.session.landingLat,
            lng: props.session.landingLng,
        });
    }

    return {
        defaultView: {
            lat: props.session.launchLat ?? 64.1466,
            lng: props.session.launchLng ?? -21.9426,
            zoom: 11,
        },
        routes,
        pins,
    };
});
</script>

<template>
    <Head :title="session.title" />

    <div class="flex flex-1 flex-col gap-6 rounded-[2rem] p-4 md:p-6">
        <section class="rounded-[2rem] border border-sidebar-border/70 bg-white/95 p-6 shadow-sm md:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-orange-400">
                        Session detail
                    </p>
                    <Heading
                        :title="session.title"
                        :description="`${session.date ?? 'No date'}${session.launchName ? ` · ${session.launchName}` : ''}${session.startTimeLocal ? ` · ${session.startTimeLocal} ${session.timezone}` : ''}`"
                    />

                    <div class="flex flex-wrap gap-3 text-sm text-slate-500">
                        <span
                            v-for="chip in heroChips"
                            :key="chip"
                            class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2"
                        >
                            {{ chip }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Button as-child variant="outline">
                        <Link href="/sessions">Back to sessions</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="`/sessions/${session.id}/edit`">Edit session</Link>
                    </Button>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in statCards"
                :key="card.label"
                class="rounded-[1.5rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">
                    {{ card.label }}
                </p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">
                    {{ card.value }}
                </p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                            Route
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                            Route map
                        </h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500">
                        {{ session.routeProfile.length ? `${session.routeProfile.length} sampled points` : 'No GPX profile yet' }}
                    </span>
                </div>

                <div class="mt-6">
                    <RouteAtlasMap
                        :routes="routeMapData.routes"
                        :pins="routeMapData.pins"
                        :default-view="routeMapData.defaultView"
                        height-class="h-[520px]"
                    />
                </div>

                <p v-if="session.routeSummary" class="mt-4 text-sm leading-6 text-slate-500">
                    {{ session.routeSummary }}
                </p>
            </article>

            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                            Route pace
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                            Speed curve
                        </h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500">
                        Garmin-derived
                    </span>
                </div>

                <div
                    v-if="session.routeProfile.length"
                    class="mt-6 overflow-hidden rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-4"
                >
                    <svg viewBox="0 0 340 120" class="w-full">
                        <path
                            d="M 18 104 H 322"
                            stroke="rgba(100, 116, 139, 0.15)"
                            stroke-width="1"
                        />
                        <path
                            d="M 18 66 H 322"
                            stroke="rgba(100, 116, 139, 0.1)"
                            stroke-width="1"
                            stroke-dasharray="4 6"
                        />
                        <polyline
                            :points="speedChart"
                            fill="none"
                            stroke="rgba(244, 114, 182, 0.9)"
                            stroke-width="4"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>

                <div v-else class="mt-6 rounded-[1.35rem] border border-dashed border-slate-300 bg-slate-50/80 px-5 py-10 text-sm text-slate-500">
                    Speed curve appears when a GPX route is attached.
                </div>

                <div class="mt-4 flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                    <span
                        v-for="tag in session.routeTags"
                        :key="tag"
                        class="rounded-full border border-slate-200 bg-white px-3 py-1"
                    >
                        {{ tag }}
                    </span>
                </div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,0.95fr)_minmax(320px,1.05fr)]">
            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                    Conditions
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                    Sea and weather
                </h2>

                <div class="mt-6 flex flex-wrap gap-2 text-sm text-slate-600">
                    <span
                        v-for="fact in conditionFacts"
                        :key="fact"
                        class="rounded-full border border-slate-200 bg-slate-50 px-3 py-2"
                    >
                        {{ fact }}
                    </span>
                    <span
                        v-if="!conditionFacts.length"
                        class="rounded-full border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-slate-400"
                    >
                        No sea-state details logged yet
                    </span>
                </div>

                <div v-if="session.conditionRatings.length" class="mt-6 grid gap-3 md:grid-cols-2">
                    <article
                        v-for="rating in session.conditionRatings"
                        :key="rating.label"
                        class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                            {{ rating.label }}
                        </p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">
                            {{ rating.value }}
                        </p>
                    </article>
                </div>

                <p v-if="session.weatherSummary" class="mt-6 text-sm leading-6 text-slate-500">
                    {{ session.weatherSummary }}
                </p>
            </article>

            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                    Development
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                    Rescue, skills, and reflections
                </h2>

                <div class="mt-6 grid gap-3 md:grid-cols-3">
                    <article class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Successful rolls</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ session.successfulRollsCount }}</p>
                    </article>
                    <article class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Wet exits</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ session.wetExitsCount }}</p>
                    </article>
                    <article class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Tow rescues</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ session.towRescuesCount }}</p>
                    </article>
                </div>

                <div v-if="session.skills.length" class="mt-6 flex flex-wrap gap-2 text-sm text-slate-600">
                    <span
                        v-for="skill in session.skills"
                        :key="skill"
                        class="rounded-full border border-slate-200 bg-slate-50 px-3 py-2"
                    >
                        {{ skill }}
                    </span>
                </div>

                <div v-if="scoreCards.length" class="mt-6 grid gap-3 md:grid-cols-3">
                    <article
                        v-for="score in scoreCards"
                        :key="score.label"
                        class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ score.label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ score.value }}/5</p>
                    </article>
                </div>

                <div v-if="reflectionCards.length" class="mt-6 grid gap-3">
                    <article
                        v-for="reflection in reflectionCards"
                        :key="reflection.label"
                        class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                            {{ reflection.label }}
                        </p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ reflection.value }}
                        </p>
                    </article>
                </div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(320px,1.1fr)]">
            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                    Journey
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                    Core session facts
                </h2>

                <div class="mt-6 grid gap-3">
                    <article class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Launch / landing</p>
                        <p class="mt-2 text-base font-semibold text-slate-900">
                            {{ session.launchName ?? 'Unknown launch' }}<span v-if="session.landingName"> → {{ session.landingName }}</span>
                        </p>
                    </article>
                    <article class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Area</p>
                        <p class="mt-2 text-base font-semibold text-slate-900">
                            {{ session.areaName ?? profile.homeWater }}
                        </p>
                    </article>
                    <article
                        v-if="session.isExpedition"
                        class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Days out</p>
                        <p class="mt-2 text-base font-semibold text-slate-900">
                            {{ session.expeditionDays ?? '—' }}
                        </p>
                    </article>
                    <article
                        v-if="session.partners.length"
                        class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Partners</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ session.partners.join(', ') }}
                        </p>
                    </article>
                </div>
            </article>

            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                    Files
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                    Attachments
                </h2>

                <div class="mt-6 grid gap-4">
                    <div
                        v-if="session.photoUrl"
                        class="overflow-hidden rounded-[1.35rem] border border-slate-200 bg-slate-50"
                    >
                        <img :src="session.photoUrl" :alt="session.photoName ?? session.title" class="h-56 w-full object-cover" />
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <article class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">GPX</p>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <p class="text-sm text-slate-600">{{ session.gpxName ?? 'No GPX attached' }}</p>
                                <Button v-if="session.gpxUrl" as-child variant="outline">
                                    <a :href="session.gpxUrl" target="_blank" rel="noreferrer">Open</a>
                                </Button>
                            </div>
                        </article>
                        <article class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">FIT</p>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <p class="text-sm text-slate-600">{{ session.fitName ?? 'No FIT attached' }}</p>
                                <Button v-if="session.fitUrl" as-child variant="outline">
                                    <a :href="session.fitUrl" target="_blank" rel="noreferrer">Open</a>
                                </Button>
                            </div>
                        </article>
                    </div>
                </div>
            </article>
        </section>
    </div>
</template>
