<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowRight,
    Compass,
    FileText,
    Map,
    ShieldCheck,
} from 'lucide-vue-next';
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

const featureChips = [
    'Quick or detailed session logging',
    'Planning with weather and tide context',
    'Printable reports and exports',
    'Private journal by default',
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
                        <p class="journal-kicker">Sea kayak logbook</p>
                        <h1 class="text-[1.9rem] font-semibold leading-[0.94] text-[color:var(--journal-text)] sm:text-[2.7rem]">
                            See the dashboard before you sign up.
                        </h1>
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
                <div class="grid gap-5 lg:grid-cols-[minmax(0,0.92fr)_minmax(320px,0.8fr)] lg:items-start">
                    <div class="space-y-4">
                        <div class="inline-flex items-center gap-2 rounded-full border border-[rgba(103,114,255,0.12)] bg-white/78 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.26em] text-[color:var(--journal-primary)]">
                            <Compass class="h-3.5 w-3.5" />
                            Sample dashboard preview
                        </div>

                        <div class="space-y-3">
                            <h2 class="text-[1.35rem] font-semibold leading-tight text-[color:var(--journal-text)] sm:text-[1.7rem]">
                                See what the journal looks like with real sessions in it.
                            </h2>
                            <p class="max-w-3xl text-sm leading-7 text-[color:var(--journal-muted)] sm:text-[1rem]">
                                This is a sample account with logged paddles, a route map, and the kind of
                                stats the dashboard builds up over time. It gives you a quick feel for the
                                app before you decide to sign up.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="chip in featureChips"
                                :key="chip"
                                class="journal-chip"
                            >
                                {{ chip }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <article class="journal-surface-shell rounded-[24px] px-4 py-4">
                            <div class="inline-flex size-10 items-center justify-center rounded-2xl bg-[rgba(103,114,255,0.12)] text-[color:var(--journal-primary)]">
                                <Map class="h-5 w-5" />
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-[color:var(--journal-text)]">
                                Routes and places
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                                Route maps, launch areas, and logged distance that make the journal feel
                                closer to sea kayaking than a generic workout app.
                            </p>
                        </article>

                        <article class="journal-surface-shell rounded-[24px] px-4 py-4">
                            <div class="inline-flex size-10 items-center justify-center rounded-2xl bg-[rgba(122,215,208,0.16)] text-[#2f6a66]">
                                <FileText class="h-5 w-5" />
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-[color:var(--journal-text)]">
                                Useful reports
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                                Turn your logbook into something you can actually use later for course
                                applications, trip review, and keeping a clearer record of progress.
                            </p>
                        </article>

                        <article class="journal-surface-shell rounded-[24px] px-4 py-4 sm:col-span-2">
                            <div class="inline-flex size-10 items-center justify-center rounded-2xl bg-[rgba(255,156,107,0.16)] text-[#9f5d34]">
                                <ShieldCheck class="h-5 w-5" />
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-[color:var(--journal-text)]">
                                Private by default
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                                This preview uses sample data. Your own journal stays private unless you
                                choose to export or share something later.
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="rounded-[30px] border border-dashed border-[rgba(103,114,255,0.16)] bg-white/55 px-4 py-3 text-sm text-[color:var(--journal-muted)] shadow-[0_18px_42px_rgba(94,109,255,0.08)]">
                The dashboard below uses sample data so first-time visitors can understand the product before
                creating an account.
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
                            <p class="journal-kicker">Map</p>
                            <h3 class="mt-2 text-[1.55rem] leading-none sm:text-[1.8rem]">
                                Route preview
                            </h3>
                        </div>
                        <span class="journal-chip">3 places tracked</span>
                    </div>

                    <div class="mt-5">
                        <RouteAtlasMap
                            :routes="mapData.routes"
                            :pins="mapData.pins"
                            :default-view="mapData.defaultView"
                            :show-filters="false"
                            :show-legend="false"
                            :show-kind-filter="false"
                            :show-geometry-filter="false"
                            :allow-pin-view="false"
                            height-class="h-[280px] sm:h-[360px] lg:h-[460px]"
                        />
                    </div>
                </section>
            </div>

            <section class="journal-panel px-5 py-5 sm:px-6 sm:py-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-2">
                        <p class="journal-kicker">Ready to start</p>
                        <h2 class="text-[1.4rem] font-semibold leading-tight text-[color:var(--journal-text)] sm:text-[1.8rem]">
                            Create an account and start logging for real.
                        </h2>
                        <p class="max-w-2xl text-sm leading-7 text-[color:var(--journal-muted)] sm:text-[1rem]">
                            Save your own sessions, plan days out, import Garmin history, and build the
                            journal from there.
                        </p>
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
