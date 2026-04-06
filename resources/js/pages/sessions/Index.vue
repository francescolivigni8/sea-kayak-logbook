<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
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
        ],
    },
});

interface ProfileSummary {
    name: string;
    homeWater: string;
}

interface SessionStats {
    sessionCount: number;
    distanceKm: number;
    expeditionTrips: number;
    expeditionDays: number;
}

interface SessionListItem {
    id: number;
    title: string;
    date: string | null;
    launchName: string | null;
    distanceKm: number;
    durationMinutes: number;
    beaufort: number | null;
    routeCategoryLabel: string;
    isExpedition: boolean;
    expeditionDays: number | null;
    isPublic: boolean;
    hasTrack: boolean;
    photoUrl: string | null;
}

const props = defineProps<{
    profile: ProfileSummary;
    stats: SessionStats;
    sessions: SessionListItem[];
}>();

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const statCards = computed(() => [
    {
        label: 'Sessions',
        value: props.stats.sessionCount.toString(),
        detail: 'Saved paddles in this logbook.',
    },
    {
        label: 'Distance',
        value: `${props.stats.distanceKm.toFixed(1)} km`,
        detail: 'All recorded distance so far.',
    },
    {
        label: 'Expedition trips',
        value: props.stats.expeditionTrips.toString(),
        detail: 'Tagged expedition or multiday sessions.',
    },
    {
        label: 'Expedition days',
        value: props.stats.expeditionDays.toString(),
        detail: 'Total multiday days logged.',
    },
]);
</script>

<template>
    <Head title="Sessions" />

    <div class="flex flex-1 flex-col gap-6 rounded-[2rem] p-4 md:p-6">
        <section class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/90 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Sessions"
                    :description="`All paddles for ${profile.name}, ready for editing, uploads, and future dashboard aggregation.`"
                />

                <div class="flex items-center gap-3">
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-500">
                        {{ profile.homeWater }}
                    </span>
                    <Button as-child variant="outline">
                        <Link href="/imports/garmin">Import Garmin</Link>
                    </Button>
                    <Button as-child>
                        <Link href="/sessions/create">Add session</Link>
                    </Button>
                </div>
            </div>
        </section>

        <section
            v-if="successMessage"
            class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
        >
            {{ successMessage }}
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in statCards"
                :key="card.label"
                class="rounded-[1.5rem] border border-sidebar-border/70 bg-white/90 p-5 shadow-sm"
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

        <section class="grid gap-4">
            <article
                v-for="session in sessions"
                :key="session.id"
                class="overflow-hidden rounded-[1.75rem] border border-sidebar-border/70 bg-white/90 shadow-sm"
            >
                <div class="grid gap-4 p-5 lg:grid-cols-[140px_minmax(0,1fr)_auto] lg:items-center">
                    <div class="overflow-hidden rounded-[1.25rem] border border-slate-200 bg-slate-50">
                        <img
                            v-if="session.photoUrl"
                            :src="session.photoUrl"
                            alt="Session cover"
                            class="h-28 w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-28 items-center justify-center text-xs font-medium uppercase tracking-[0.24em] text-slate-400"
                        >
                            No photo
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold text-slate-900">
                                {{ session.title }}
                            </h2>
                            <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-700">
                                {{ session.routeCategoryLabel }}
                            </span>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-medium"
                                :class="session.isPublic ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                            >
                                {{ session.isPublic ? 'Public' : 'Private' }}
                            </span>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-medium"
                                :class="session.isExpedition ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ session.isExpedition ? 'Expedition' : 'Day session' }}
                            </span>
                        </div>

                        <p class="text-sm text-slate-500">
                            {{ session.date ?? 'No date' }}
                            <span v-if="session.launchName">· {{ session.launchName }}</span>
                        </p>

                        <div class="flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                                {{ session.distanceKm.toFixed(1) }} km
                            </span>
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                                {{ session.durationMinutes }} min
                            </span>
                            <span
                                v-if="session.beaufort !== null"
                                class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1"
                            >
                                F{{ session.beaufort }}
                            </span>
                            <span
                                v-if="session.isExpedition && session.expeditionDays"
                                class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1"
                            >
                                {{ session.expeditionDays }} days out
                            </span>
                            <span
                                v-if="session.hasTrack"
                                class="rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-cyan-700"
                            >
                                Track attached
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-start gap-2 lg:justify-end">
                        <Button as-child variant="outline">
                            <Link :href="`/sessions/${session.id}`">Open</Link>
                        </Button>
                        <Button as-child variant="outline">
                            <Link :href="`/sessions/${session.id}/edit`">Edit</Link>
                        </Button>
                    </div>
                </div>
            </article>

            <article
                v-if="!sessions.length"
                class="rounded-[1.75rem] border border-dashed border-slate-300 bg-white/90 px-5 py-10 text-sm leading-7 text-slate-500 shadow-sm"
            >
                No sessions yet. Start by adding your first paddle, then we can plug Garmin imports and the chart-first dashboard into real Laravel data.
            </article>
        </section>
    </div>
</template>
