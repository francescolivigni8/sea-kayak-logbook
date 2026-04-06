<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { dashboard } from '@/routes';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sea Kayak Logbook',
                href: dashboard(),
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
}

interface DashboardStats {
    sessionCount: number;
    distanceKm: number;
    durationHours: number;
    expeditionTrips: number;
    publicSessions: number;
}

interface RecentSession {
    id: number;
    title: string;
    date: string | null;
    distanceKm: number;
    beaufort: number | null;
    launchName: string | null;
    isExpedition: boolean;
    isPublic: boolean;
}

const props = defineProps<{
    profile: ProfileSummary;
    stats: DashboardStats;
    recentSessions: RecentSession[];
}>();

const launchCopy = computed(() =>
    props.recentSessions.length
        ? 'Laravel auth, profile ownership, and the first kayak tables are live.'
        : 'The stack is ready for your first real import from Garmin and the old prototype.',
);

const metricCards = computed(() => [
    {
        label: 'Total distance',
        value: `${props.stats.distanceKm.toFixed(1)} km`,
        tone: 'from-cyan-100/70 to-blue-100/80',
        detail: 'All sessions across the active logbook.',
    },
    {
        label: 'Hours paddled',
        value: `${props.stats.durationHours.toFixed(1)} h`,
        tone: 'from-sky-100/80 to-indigo-100/80',
        detail: 'Derived from recorded duration.',
    },
    {
        label: 'Expedition trips',
        value: `${props.stats.expeditionTrips}`,
        tone: 'from-amber-100/80 to-orange-100/80',
        detail: 'Sessions already tagged expedition or multiday.',
    },
    {
        label: 'Public sessions',
        value: `${props.stats.publicSessions}`,
        tone: 'from-emerald-100/80 to-teal-100/80',
        detail: 'Visible on a future public profile.',
    },
]);

const nextSlices = [
    'Import historical Garmin CSV and GPX into Laravel models',
    'Replace the stock dashboard with the chart-first kayak UI',
    'Move session photos and GPX files into Laravel storage',
    'Add dedicated Diary, Observations, and Expedition Notes screens',
];
</script>

<template>
    <Head title="Sea Kayak Logbook" />

    <div class="flex flex-1 flex-col gap-6 rounded-[2rem] p-4 md:p-6">
        <section class="overflow-hidden rounded-[2rem] border border-sidebar-border/70 bg-white/90 shadow-sm">
            <div class="grid gap-6 px-6 py-6 md:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.75fr)] md:px-8">
                <div class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-orange-400">
                        Laravel Pivot
                    </p>
                    <Heading
                        title="Sea Kayak Logbook"
                        description="The app is now running on Laravel + Vue, with first-party auth and a real kayak domain model instead of a single static bundle."
                    />

                    <div class="flex flex-wrap gap-3 text-sm text-slate-500">
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">
                            {{ profile.homeWater }}
                        </span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">
                            {{ profile.timezone }}
                        </span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">
                            {{ profile.isPublic ? 'Public-ready profile' : 'Private profile' }}
                        </span>
                    </div>
                </div>

                <div class="rounded-[1.5rem] border border-slate-200 bg-gradient-to-br from-slate-50 to-indigo-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">
                        Active profile
                    </p>
                    <h3 class="mt-3 text-2xl font-semibold text-slate-900">
                        {{ profile.name }}
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ launchCopy }}
                    </p>

                    <dl class="mt-5 grid gap-3 text-sm text-slate-600">
                        <div class="flex items-center justify-between rounded-2xl bg-white/80 px-4 py-3">
                            <dt>Slug</dt>
                            <dd class="font-medium text-slate-900">
                                {{ profile.slug }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl bg-white/80 px-4 py-3">
                            <dt>Session count</dt>
                            <dd class="font-medium text-slate-900">
                                {{ stats.sessionCount }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in metricCards"
                :key="card.label"
                class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/90 p-5 shadow-sm"
            >
                <div
                    class="inline-flex rounded-full border border-white/70 bg-gradient-to-r px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500"
                    :class="card.tone"
                >
                    {{ card.label }}
                </div>
                <p class="mt-5 text-3xl font-semibold tracking-tight text-slate-900">
                    {{ card.value }}
                </p>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    {{ card.detail }}
                </p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/90 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                            Recent sessions
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                            Latest paddles
                        </h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500">
                        Ready for import
                    </span>
                </div>

                <div v-if="recentSessions.length" class="mt-6 grid gap-3">
                    <article
                        v-for="session in recentSessions"
                        :key="session.id"
                        class="rounded-[1.35rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">
                                    {{ session.title }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ session.date ?? 'No date yet' }}
                                    <span v-if="session.launchName">· {{ session.launchName }}</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs font-medium">
                                <span class="rounded-full bg-white px-3 py-1 text-slate-600">
                                    {{ session.distanceKm.toFixed(1) }} km
                                </span>
                                <span
                                    v-if="session.beaufort !== null"
                                    class="rounded-full bg-white px-3 py-1 text-slate-600"
                                >
                                    F{{ session.beaufort }}
                                </span>
                                <span
                                    class="rounded-full px-3 py-1"
                                    :class="session.isExpedition ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ session.isExpedition ? 'Expedition' : 'Day session' }}
                                </span>
                                <span
                                    class="rounded-full px-3 py-1"
                                    :class="session.isPublic ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                                >
                                    {{ session.isPublic ? 'Public' : 'Private' }}
                                </span>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-6 rounded-[1.35rem] border border-dashed border-slate-300 bg-slate-50/70 px-5 py-10 text-sm leading-7 text-slate-500"
                >
                    No sessions yet. The next step is importing your existing Garmin history and then rebuilding the polished dashboard on top of these Laravel models.
                </div>
            </article>

            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/90 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                    Build slices
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                    What comes next
                </h2>

                <ol class="mt-6 grid gap-3">
                    <li
                        v-for="slice in nextSlices"
                        :key="slice"
                        class="rounded-[1.25rem] border border-slate-200 bg-slate-50/80 px-4 py-4 text-sm leading-6 text-slate-600"
                    >
                        {{ slice }}
                    </li>
                </ol>
            </article>
        </section>
    </div>
</template>
