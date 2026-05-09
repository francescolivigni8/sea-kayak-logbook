<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import HeadlineMetricCards from '@/components/dashboard/HeadlineMetricCards.vue';
import SeaStatePanels from '@/components/dashboard/SeaStatePanels.vue';
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';

const headline = {
    sessionCount: 42,
    distanceKm: 427,
    durationHours: 78,
    longestDistanceKm: 29.4,
    averageDistanceKm: 10.2,
    averageSpeedKnots: 2.8,
    averageSpeedSamples: 24,
    trackSessions: 31,
    paddledMonths: 9,
};

const monthlyDistance = [
    { key: 'jan', label: 'Jan', distanceKm: 55.2 },
    { key: 'feb', label: 'Feb', distanceKm: 64.4 },
    { key: 'mar', label: 'Mar', distanceKm: 97.4 },
    { key: 'apr', label: 'Apr', distanceKm: 21.7 },
    { key: 'may', label: 'May', distanceKm: 0 },
    { key: 'jun', label: 'Jun', distanceKm: 0 },
    { key: 'jul', label: 'Jul', distanceKm: 0 },
    { key: 'aug', label: 'Aug', distanceKm: 15.1 },
    { key: 'sep', label: 'Sep', distanceKm: 23.2 },
    { key: 'oct', label: 'Oct', distanceKm: 7.1 },
    { key: 'nov', label: 'Nov', distanceKm: 0 },
    { key: 'dec', label: 'Dec', distanceKm: 34.0 },
];

const seaState = {
    beaufortBands: [
        { label: 'F2', count: 1 },
        { label: 'F3', count: 11 },
        { label: 'F4', count: 11 },
        { label: 'F5', count: 3 },
        { label: 'F6+', count: 0 },
    ],
    averageBeaufort: 4,
    tideStates: [
        { label: 'Low', count: 3 },
        { label: 'Flood', count: 8 },
        { label: 'High', count: 3 },
        { label: 'Ebb', count: 7 },
        { label: 'Slack', count: 5 },
    ],
    conditionMatrix: [
        {
            label: 'Rain',
            values: [
                { key: 'low', count: 29 },
                { key: 'moderate', count: 1 },
                { key: 'high', count: 0 },
                { key: 'extreme', count: 0 },
            ],
        },
        {
            label: 'Wind',
            values: [
                { key: 'low', count: 17 },
                { key: 'moderate', count: 9 },
                { key: 'high', count: 4 },
                { key: 'extreme', count: 0 },
            ],
        },
        {
            label: 'Temperature',
            values: [
                { key: 'low', count: 4 },
                { key: 'moderate', count: 7 },
                { key: 'high', count: 4 },
                { key: 'extreme', count: 15 },
            ],
        },
        {
            label: 'Forecast',
            values: [
                { key: 'low', count: 3 },
                { key: 'moderate', count: 6 },
                { key: 'high', count: 6 },
                { key: 'extreme', count: 15 },
            ],
        },
    ],
    rescueTotals: [
        { label: 'Successful rolls', count: 23 },
        { label: 'Wet exits (swims)', count: 19 },
        { label: 'Tow rescues', count: 7 },
    ],
    temperatureAverages: {
        air: 10.1,
        sea: 6.3,
    },
};

const yearSnapshots = [
    {
        label: 'All time',
        value: 427,
        unit: 'km',
        detail: '42 sessions recorded',
    },
    {
        label: '2026',
        value: 239,
        unit: 'km',
        detail: '24 sessions this year',
    },
    {
        label: 'Rolling 12 months',
        value: 407,
        unit: 'km',
        detail: 'Live moving window',
    },
    {
        label: 'Expedition total',
        value: 52.3,
        unit: 'km',
        detail: '9 days out',
    },
];

const mapData = {
    defaultView: {
        lat: 64.1466,
        lng: -21.9426,
        zoom: 10,
    },
    routes: [
        {
            id: 'spring-benchmark',
            label: 'Spring pace benchmark',
            color: '#6772ff',
            points: [
                { lat: 64.1566, lng: -22.017 },
                { lat: 64.1632, lng: -21.995 },
                { lat: 64.1711, lng: -21.966 },
                { lat: 64.1639, lng: -21.935 },
                { lat: 64.1514, lng: -21.945 },
                { lat: 64.1493, lng: -21.986 },
                { lat: 64.1566, lng: -22.017 },
            ],
        },
        {
            id: 'harbor-circuit',
            label: 'Harbor evening circuit',
            color: '#7ad7d0',
            points: [
                { lat: 64.1502, lng: -22.029 },
                { lat: 64.1428, lng: -21.998 },
                { lat: 64.1356, lng: -21.972 },
                { lat: 64.1271, lng: -21.989 },
                { lat: 64.1331, lng: -22.021 },
                { lat: 64.1502, lng: -22.029 },
            ],
        },
    ],
    pins: [
        {
            id: 'reykjavik',
            label: 'Reykjavik',
            color: '#6772ff',
            lat: 64.1466,
            lng: -21.9426,
            count: 18,
        },
        {
            id: 'grotta',
            label: 'Grotta',
            color: '#7ad7d0',
            lat: 64.1672,
            lng: -22.0226,
            count: 9,
        },
        {
            id: 'skarfabakki',
            label: 'Skarfabakki',
            color: '#ff9c6b',
            lat: 64.1541,
            lng: -21.8998,
            count: 6,
        },
    ],
};

const expeditionSummaryCards = [
    {
        label: 'Total expedition distance',
        value: '52.3 km',
        detail: '3 expedition-tagged sessions',
    },
    {
        label: 'Total expedition days',
        value: '9',
        detail: 'Logged days out',
    },
    {
        label: 'Total multiday trips',
        value: '3',
        detail: 'Expedition-tagged sessions',
    },
];

const expeditionMapData = {
    defaultView: {
        lat: 30,
        lng: -12,
        zoom: 1,
    },
    routes: [
        {
            id: 'anglesey-loop',
            label: 'Anglesey training block',
            color: '#6772ff',
            isExpedition: true,
            points: [
                { lat: 53.31, lng: -4.69 },
                { lat: 53.33, lng: -4.6 },
                { lat: 53.35, lng: -4.47 },
                { lat: 53.39, lng: -4.39 },
            ],
        },
        {
            id: 'shetland-crossing',
            label: 'Shetland day out',
            color: '#ff9c6b',
            isExpedition: true,
            points: [
                { lat: 60.15, lng: -1.15 },
                { lat: 60.19, lng: -1.05 },
                { lat: 60.24, lng: -0.94 },
            ],
        },
        {
            id: 'westfjords',
            label: 'Westfjords multiday',
            color: '#7ad7d0',
            isExpedition: true,
            points: [
                { lat: 66.06, lng: -23.12 },
                { lat: 66.08, lng: -22.98 },
                { lat: 66.11, lng: -22.81 },
            ],
        },
    ],
    pins: [
        {
            id: 'anglesey',
            label: 'Anglesey',
            color: '#6772ff',
            lat: 53.31,
            lng: -4.63,
            count: 1,
            isExpedition: true,
        },
        {
            id: 'shetland',
            label: 'Shetland',
            color: '#ff9c6b',
            lat: 60.2,
            lng: -1.03,
            count: 1,
            isExpedition: true,
        },
        {
            id: 'westfjords',
            label: 'Westfjords',
            color: '#7ad7d0',
            lat: 66.08,
            lng: -22.94,
            count: 1,
            isExpedition: true,
        },
    ],
};

const expeditionSessionChips = [
    'Anglesey 3-day block',
    'Shetland day out',
    'Westfjords multiday',
];

</script>

<template>
    <Head title="See the dashboard first" />

    <div class="min-h-screen bg-[linear-gradient(180deg,rgba(245,247,255,0.96),rgba(235,239,255,0.86))] px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-5">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-center gap-3">
                    <img
                        src="/brand/ykj-logo-clean.png"
                        alt="Your Kayaking Journal logo"
                        class="size-[4.2rem] rounded-[1.25rem] border border-[rgba(103,114,255,0.16)] object-cover shadow-[0_18px_34px_rgba(37,43,82,0.14)]"
                        width="80"
                        height="80"
                    />
                    <div class="space-y-2">
                        <h1 class="text-[1.9rem] font-semibold leading-[0.94] text-[color:var(--journal-text)] sm:text-[2.7rem]">
                            See the dashboard before you sign up.
                        </h1>
                        <p class="text-sm leading-7 text-[color:var(--journal-muted)] sm:text-[1rem]">
                            Sample data, real layout.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                    <a
                        href="/login"
                        class="journal-chip transition hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                    >
                        Log in
                    </a>
                    <a
                        href="/register"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-[color:var(--journal-primary)] px-4 py-2.5 text-sm font-semibold text-white shadow-[0_18px_28px_rgba(103,114,255,0.22)] transition hover:-translate-y-0.5"
                    >
                        Create account
                        <ArrowRight class="h-4 w-4" />
                    </a>
                </div>
            </header>

            <section class="journal-panel journal-panel--hero overflow-hidden px-5 py-5 sm:px-6 sm:py-6">
                <p class="max-w-3xl text-sm leading-7 text-[color:var(--journal-muted)] sm:text-[1rem]">
                    This is a sample account so you can see the dashboard before creating your own.
                </p>
            </section>

            <div class="space-y-5">
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
                    compare-chip="Distance"
                />

                <section class="journal-panel px-4 py-4 sm:px-5 sm:py-5 md:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="mt-2 text-[1.55rem] leading-none sm:text-[1.8rem]">
                                Route map
                            </h3>
                        </div>
                        <span class="journal-chip">{{ mapData.routes.length }} routes</span>
                    </div>

                    <div class="mt-6">
                        <RouteAtlasMap
                            :routes="mapData.routes"
                            :pins="mapData.pins"
                            :default-view="mapData.defaultView"
                            storage-key="guest-preview-route-atlas"
                            :show-filters="false"
                            :show-legend="false"
                            :show-kind-filter="false"
                            :show-geometry-filter="false"
                            :allow-pin-view="false"
                            height-class="h-[280px] sm:h-[360px] lg:h-[460px]"
                        />
                    </div>
                </section>

                <section class="journal-panel px-4 py-4 sm:px-5 sm:py-5 md:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="mt-2 text-[1.55rem] leading-none sm:text-[1.8rem]">
                                Expeditions and multiday
                            </h3>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 md:mt-6 lg:grid-cols-3">
                        <article
                            v-for="card in expeditionSummaryCards"
                            :key="card.label"
                            class="journal-surface-shell rounded-[24px] px-4 py-4"
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
                                <h4
                                    class="mt-2 text-[1.3rem] leading-none text-[color:var(--journal-text)] sm:text-[1.45rem]"
                                >
                                    I paddled here
                                </h4>
                            </div>
                            <span class="text-sm font-medium text-[color:var(--journal-muted)]">
                                {{ expeditionMapData.pins.length }} places
                            </span>
                        </div>

                        <RouteAtlasMap
                            :routes="expeditionMapData.routes"
                            :pins="expeditionMapData.pins"
                            :default-view="expeditionMapData.defaultView"
                            storage-key="guest-preview-expedition-atlas"
                            pin-presentation="expedition"
                            :auto-fit-to-geometry="false"
                            :show-legend="false"
                            :show-filters="false"
                            :show-kind-filter="false"
                            :show-geometry-filter="false"
                            empty-message="No paddled locations logged yet."
                            height-class="h-[280px] sm:h-[360px] lg:h-[440px]"
                        />
                    </div>

                    <div class="mt-5">
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="session in expeditionSessionChips"
                                :key="session"
                                class="journal-chip"
                            >
                                {{ session }}
                            </span>
                        </div>
                    </div>
                </section>
            </div>

            <section class="journal-panel px-5 py-5 sm:px-6 sm:py-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-[1.4rem] font-semibold leading-tight text-[color:var(--journal-text)] sm:text-[1.8rem]">
                            Create an account to start your own logbook.
                        </h2>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            href="/login"
                            class="journal-chip transition hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                        >
                            Log in
                        </a>
                        <a
                            href="/register"
                            class="inline-flex items-center justify-center gap-2 rounded-full bg-[color:var(--journal-primary)] px-4 py-2.5 text-sm font-semibold text-white shadow-[0_18px_28px_rgba(103,114,255,0.22)] transition hover:-translate-y-0.5"
                        >
                            Create account
                            <ArrowRight class="h-4 w-4" />
                        </a>
                    </div>
                </div>
            </section>

            <footer class="px-1 pb-2 text-center text-[0.75rem] leading-6 text-[color:var(--journal-faint)]">
                <p>© {{ new Date().getFullYear() }} Francesco Li Vigni. Your Kayaking Journal. All rights reserved.</p>
                <div class="mt-2 flex flex-wrap items-center justify-center gap-3">
                    <a class="underline underline-offset-4" href="/privacy">Privacy</a>
                    <a class="underline underline-offset-4" href="/terms">Terms</a>
                    <a class="underline underline-offset-4" href="/contact">Contact</a>
                </div>
            </footer>
        </div>
    </div>
</template>
