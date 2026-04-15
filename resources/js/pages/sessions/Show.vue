<script setup lang="ts">
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';
import { dashboard } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
    photoUrl: string | null;
    photoName: string | null;
    gpxUrl: string | null;
    gpxName: string | null;
    fitUrl: string | null;
    fitName: string | null;
    routeProfile: RoutePoint[];
    conditionRatings: ConditionRating[];
}

type DetailTab = 'map' | 'charts' | 'stats';

const props = defineProps<{
    profile: ProfileSummary;
    session: SessionDetail;
}>();

const activeTab = ref<DetailTab>('stats');

const sessionSubtitle = computed(() =>
    [
        props.session.date,
        props.session.launchName ?? props.session.areaName,
        props.session.startTimeLocal ? `${props.session.startTimeLocal} ${props.session.timezone}` : null,
    ].filter(Boolean).join(' · '),
);

const heroChips = computed(() => [
    props.session.routeCategoryLabel,
    props.session.launchName,
    props.session.bodyOfWater,
    props.session.beaufort !== null ? `F${props.session.beaufort}` : null,
    props.session.isExpedition ? 'Expedition' : null,
    props.session.routeProfile.length ? 'Track attached' : null,
].filter(Boolean) as string[]);

const statCards = computed(() => [
    {
        label: 'Distance',
        value: `${props.session.distanceKm.toFixed(1)} km`,
        detail: 'Journey',
        style: 'linear-gradient(135deg, rgba(103,114,255,0.14), rgba(255,255,255,0.9))',
    },
    {
        label: 'Time',
        value: `${props.session.durationMinutes} min`,
        detail: 'Total duration',
        style: 'linear-gradient(135deg, rgba(122,215,208,0.18), rgba(255,255,255,0.9))',
    },
    {
        label: 'Avg speed',
        value: props.session.averageSpeedKmh !== null ? `${props.session.averageSpeedKmh.toFixed(1)} km/h` : '—',
        detail: 'Moving pace on the water',
        style: 'linear-gradient(135deg, rgba(255,156,107,0.16), rgba(255,255,255,0.9))',
    },
    {
        label: 'Wind',
        value: props.session.beaufort !== null
            ? `F${props.session.beaufort}${props.session.windAvgMs !== null ? ` / ${props.session.windAvgMs.toFixed(1)} m/s` : ''}`
            : props.session.windAvgMs !== null ? `${props.session.windAvgMs.toFixed(1)} m/s` : '—',
        detail: 'Sea state snapshot',
        style: 'linear-gradient(135deg, rgba(148,141,255,0.16), rgba(255,255,255,0.9))',
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
        { label: 'Observations', value: props.session.notesPublic },
        { label: 'Session notes', value: props.session.notesPrivate },
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

const journeyFacts = computed(() =>
    [
        { label: 'Route', value: `${props.session.launchName ?? 'Unknown launch'}${props.session.landingName ? ` → ${props.session.landingName}` : ''}` },
        { label: 'Category', value: props.session.routeCategoryLabel },
        { label: 'Water', value: props.session.bodyOfWater ?? 'Sea' },
        { label: 'Area', value: props.session.areaName ?? props.profile.homeWater },
        { label: 'Moving time', value: props.session.movingMinutes !== null ? `${props.session.movingMinutes} min` : '—' },
        { label: 'Days out', value: props.session.isExpedition ? String(props.session.expeditionDays ?? '—') : 'Day session' },
    ],
);

const seaFacts = computed(() =>
    [
        { label: 'Wind', value: props.session.windAvgMs !== null ? `${props.session.windAvgMs.toFixed(1)} m/s${props.session.beaufort !== null ? ` / F${props.session.beaufort}` : ''}` : '—' },
        { label: 'Tide', value: props.session.tideState ?? '—' },
        { label: 'Current', value: props.session.currentKnots !== null ? `${props.session.currentKnots.toFixed(1)} kt` : '—' },
        { label: 'Swell', value: props.session.swellHeightM !== null ? `${props.session.swellHeightM.toFixed(1)} m${props.session.swellPeriodS !== null ? ` @ ${props.session.swellPeriodS.toFixed(0)} s` : ''}` : '—' },
        { label: 'Temps', value: `${props.session.airTempC !== null ? `${props.session.airTempC.toFixed(1)} C air` : '—'} / ${props.session.seaTempC !== null ? `${props.session.seaTempC.toFixed(1)} C sea` : '—'}` },
        { label: 'Summary', value: props.session.weatherSummary ?? 'No conditions summary logged yet.' },
    ],
);

const developmentFacts = computed(() =>
    [
        { label: 'Development', value: `${props.session.successfulRollsCount} successful rolls, ${props.session.wetExitsCount} wet exits (swims), ${props.session.towRescuesCount} tow rescues` },
        { label: 'Skills', value: props.session.skills.length ? props.session.skills.join(', ') : 'No skills logged yet.' },
        { label: 'Partners', value: props.session.partners.length ? props.session.partners.join(', ') : 'Solo session' },
        { label: 'Scores', value: scoreCards.value.length ? scoreCards.value.map((score) => `${score.label} ${score.value}/5`).join(' · ') : 'No scores logged yet.' },
    ],
);

const attachmentCards = computed(() =>
    [
        { label: 'GPX', name: props.session.gpxName, url: props.session.gpxUrl },
        { label: 'FIT', name: props.session.fitName, url: props.session.fitUrl },
    ],
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

    <div class="space-y-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-4">
                    <p class="journal-kicker">{{ session.date ?? 'Session detail' }}</p>
                    <div class="space-y-2">
                        <h1 class="text-[clamp(2rem,3.8vw,3rem)] leading-[0.94] text-[color:var(--journal-text)]">
                            {{ session.title }}
                        </h1>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            {{ sessionSubtitle || 'A selected paddle day from the journal.' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span v-for="chip in heroChips" :key="chip" class="journal-chip">
                            {{ chip }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link href="/sessions" class="journal-utility-link">Library</Link>
                    <Link
                        :href="session.notesPublic ? `/sessions/${session.id}/edit` : `/sessions/${session.id}/edit?step=notes`"
                        class="journal-primary-link"
                    >
                        {{ session.notesPublic ? 'Edit session' : 'Add observation' }}
                    </Link>
                </div>
            </div>
        </section>

        <section class="journal-banner journal-banner--soft">
            This page stays session-first: summary on top, then route, charts, and notes without overloading the screen.
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in statCards"
                :key="card.label"
                class="journal-metric-card"
                :style="{ background: card.style }"
            >
                <p class="journal-kicker">{{ card.label }}</p>
                <p class="mt-4 text-3xl font-semibold text-[color:var(--journal-text)]">{{ card.value }}</p>
                <p class="mt-2 text-sm text-[color:var(--journal-muted)]">{{ card.detail }}</p>
            </article>
        </section>

        <section class="journal-panel px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <nav class="inline-flex flex-wrap items-center gap-2 rounded-full border border-[color:var(--journal-line)] bg-white/74 p-1.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.8)]">
                    <button
                        type="button"
                        :class="['journal-tab', activeTab === 'map' ? 'journal-tab--active' : '']"
                        @click="activeTab = 'map'"
                    >
                        Map
                    </button>
                    <button
                        type="button"
                        :class="['journal-tab', activeTab === 'charts' ? 'journal-tab--active' : '']"
                        @click="activeTab = 'charts'"
                    >
                        Charts
                    </button>
                    <button
                        type="button"
                        :class="['journal-tab', activeTab === 'stats' ? 'journal-tab--active' : '']"
                        @click="activeTab = 'stats'"
                    >
                        Stats
                    </button>
                </nav>

                <span class="text-sm font-medium text-[color:var(--journal-muted)]">
                    {{ activeTab === 'map' ? 'Route view' : activeTab === 'charts' ? 'Track profile' : 'Session facts' }}
                </span>
            </div>

            <div v-if="activeTab === 'map'" class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]">
                <article class="journal-card px-4 py-4 md:px-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="journal-kicker">Route</p>
                            <h2 class="mt-2 text-[1.65rem] leading-none text-[color:var(--journal-text)]">Route map</h2>
                        </div>
                        <span class="journal-chip">
                            {{ session.routeProfile.length ? `${session.routeProfile.length} sampled points` : 'No GPX profile yet' }}
                        </span>
                    </div>

                    <div class="mt-6">
                        <RouteAtlasMap
                            :routes="routeMapData.routes"
                            :pins="routeMapData.pins"
                            :default-view="routeMapData.defaultView"
                            :show-legend="false"
                            :show-filters="false"
                            :allow-pin-view="false"
                            height-class="h-[560px]"
                        />
                    </div>
                </article>

                <div class="grid gap-4">
                    <article class="journal-card px-5 py-5">
                        <p class="journal-kicker">Route notes</p>
                        <p class="mt-4 text-sm leading-6 text-[color:var(--journal-muted)]">
                            {{ session.routeSummary ?? 'No route summary written yet.' }}
                        </p>
                    </article>

                    <article
                        v-if="session.photoUrl"
                        class="overflow-hidden rounded-[26px] border border-[color:var(--journal-line)] bg-white/78"
                    >
                        <img :src="session.photoUrl" :alt="session.photoName ?? session.title" class="h-64 w-full object-cover" />
                    </article>

                    <article class="journal-card px-5 py-5">
                        <p class="journal-kicker">Route tags</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span v-for="tag in session.routeTags" :key="tag" class="journal-chip">
                                {{ tag }}
                            </span>
                            <span
                                v-if="!session.routeTags.length"
                                class="rounded-full border border-dashed border-[color:var(--journal-line)] bg-white/74 px-3 py-2 text-[color:var(--journal-faint)]"
                            >
                                No route tags yet
                            </span>
                        </div>
                    </article>
                </div>
            </div>

            <div v-else-if="activeTab === 'charts'" class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1.05fr)_minmax(320px,0.95fr)]">
                <article class="journal-card px-5 py-5 md:px-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="journal-kicker">Track profile</p>
                            <h2 class="mt-2 text-[1.65rem] leading-none text-[color:var(--journal-text)]">Speed curve</h2>
                        </div>
                        <span class="journal-chip">Garmin-derived</span>
                    </div>

                    <div
                        v-if="session.routeProfile.length"
                        class="journal-soft-card mt-6 overflow-hidden"
                    >
                        <svg viewBox="0 0 340 120" class="w-full">
                            <path d="M 18 104 H 322" stroke="rgba(100, 116, 139, 0.15)" stroke-width="1" />
                            <path d="M 18 66 H 322" stroke="rgba(100, 116, 139, 0.1)" stroke-width="1" stroke-dasharray="4 6" />
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

                    <div
                        v-else
                        class="mt-6 rounded-[1.35rem] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-5 py-10 text-sm text-[color:var(--journal-muted)]"
                    >
                        Speed curve appears when a GPX or FIT track is attached.
                    </div>
                </article>

                <article class="journal-card px-5 py-5 md:px-6">
                    <p class="journal-kicker">Conditions</p>
                    <h2 class="mt-2 text-[1.65rem] leading-none text-[color:var(--journal-text)]">Sea and weather</h2>

                    <div class="mt-6 flex flex-wrap gap-2">
                        <span v-for="fact in conditionFacts" :key="fact" class="journal-chip">
                            {{ fact }}
                        </span>
                        <span
                            v-if="!conditionFacts.length"
                            class="rounded-full border border-dashed border-[color:var(--journal-line)] bg-white/74 px-3 py-2 text-[color:var(--journal-faint)]"
                        >
                            No sea-state details logged yet
                        </span>
                    </div>

                    <div v-if="session.conditionRatings.length" class="mt-6 grid gap-3 md:grid-cols-2">
                        <article
                            v-for="rating in session.conditionRatings"
                            :key="rating.label"
                            class="journal-soft-card"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">
                                {{ rating.label }}
                            </p>
                            <p class="mt-2 text-lg font-semibold text-[color:var(--journal-text)]">
                                {{ rating.value }}
                            </p>
                        </article>
                    </div>

                    <p v-if="session.weatherSummary" class="mt-6 text-sm leading-6 text-[color:var(--journal-muted)]">
                        {{ session.weatherSummary }}
                    </p>
                </article>
            </div>

            <div v-else class="mt-6 grid gap-4 xl:grid-cols-3">
                <article class="journal-card px-5 py-5 md:px-6">
                    <p class="journal-kicker">Journey</p>
                    <div class="mt-4 grid gap-3">
                        <article v-for="item in journeyFacts" :key="item.label" class="journal-soft-card">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">{{ item.label }}</p>
                            <p class="mt-2 text-sm font-semibold leading-6 text-[color:var(--journal-text)]">{{ item.value }}</p>
                        </article>
                    </div>
                </article>

                <article class="journal-card px-5 py-5 md:px-6">
                    <p class="journal-kicker">Sea</p>
                    <div class="mt-4 grid gap-3">
                        <article v-for="item in seaFacts" :key="item.label" class="journal-soft-card">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">{{ item.label }}</p>
                            <p class="mt-2 text-sm font-semibold leading-6 text-[color:var(--journal-text)]">{{ item.value }}</p>
                        </article>
                    </div>
                </article>

                <article class="journal-card px-5 py-5 md:px-6">
                    <p class="journal-kicker">Development</p>
                    <div class="mt-4 grid gap-3">
                        <article v-for="item in developmentFacts" :key="item.label" class="journal-soft-card">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">{{ item.label }}</p>
                            <p class="mt-2 text-sm font-semibold leading-6 text-[color:var(--journal-text)]">{{ item.value }}</p>
                        </article>
                    </div>
                </article>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.88fr)]">
            <article class="journal-card px-5 py-5 md:px-6">
                <p class="journal-kicker">Notes</p>
                <h2 class="mt-2 text-[1.7rem] leading-none text-[color:var(--journal-text)]">Observations and reflections</h2>

                <div v-if="reflectionCards.length" class="mt-6 grid gap-3">
                    <article
                        v-for="reflection in reflectionCards"
                        :key="reflection.label"
                        class="journal-soft-card"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">
                            {{ reflection.label }}
                        </p>
                        <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                            {{ reflection.value }}
                        </p>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-6 rounded-[1.35rem] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-5 py-8 text-sm text-[color:var(--journal-muted)]"
                >
                    No notes added to this session yet.
                </div>
            </article>

            <article class="journal-card px-5 py-5 md:px-6">
                <p class="journal-kicker">Files</p>
                <h2 class="mt-2 text-[1.7rem] leading-none text-[color:var(--journal-text)]">Attachments</h2>

                <div class="mt-6 grid gap-3">
                    <article
                        v-for="item in attachmentCards"
                        :key="item.label"
                        class="journal-soft-card"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">{{ item.label }}</p>
                                <p class="mt-2 text-sm text-[color:var(--journal-muted)]">{{ item.name ?? `No ${item.label} attached` }}</p>
                            </div>
                            <a v-if="item.url" :href="item.url" target="_blank" rel="noreferrer" class="journal-utility-link">Open</a>
                        </div>
                    </article>
                </div>
            </article>
        </section>
    </div>
</template>
