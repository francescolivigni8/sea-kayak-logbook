<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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
    { label: 'Sessions', value: String(props.stats.sessionCount), detail: 'Saved paddles in the journal' },
    { label: 'Distance', value: `${props.stats.distanceKm.toFixed(1)} km`, detail: 'Total recorded distance' },
    { label: 'Expedition trips', value: String(props.stats.expeditionTrips), detail: 'Tagged multiday paddles' },
    { label: 'Days out', value: String(props.stats.expeditionDays), detail: 'Total expedition days logged' },
]);
</script>

<template>
    <Head title="Sessions" />

    <div class="flex flex-col gap-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <p class="journal-kicker">Session library</p>
                    <div class="space-y-2">
                        <h2 class="text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96]">
                            All paddles
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            A clean list for opening, editing, and reviewing the sessions already inside your journal.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link href="/imports/garmin" class="journal-utility-link">
                        Garmin import
                    </Link>
                    <Link href="/sessions/create" class="journal-primary-link">
                        Add session
                    </Link>
                </div>
            </div>
        </section>

        <section v-if="successMessage" class="journal-banner">
            {{ successMessage }}
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in statCards"
                :key="card.label"
                class="journal-metric-card"
                style="background: rgba(255, 255, 255, 0.86)"
            >
                <p class="journal-kicker">{{ card.label }}</p>
                <p class="mt-4 text-3xl font-semibold text-[color:var(--journal-text)]">
                    {{ card.value }}
                </p>
                <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                    {{ card.detail }}
                </p>
            </article>
        </section>

        <section class="grid gap-4">
            <article
                v-for="session in sessions"
                :key="session.id"
                class="journal-panel overflow-hidden px-5 py-5 md:px-6"
            >
                <div class="grid gap-4 lg:grid-cols-[150px_minmax(0,1fr)_auto] lg:items-center">
                    <div class="overflow-hidden rounded-[22px] border border-[color:var(--journal-line)] bg-white/70">
                        <img
                            v-if="session.photoUrl"
                            :src="session.photoUrl"
                            alt="Session cover"
                            class="h-32 w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-32 items-center justify-center text-xs font-medium uppercase tracking-[0.24em] text-[color:var(--journal-faint)]"
                        >
                            No photo
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex flex-wrap gap-2 text-xs font-medium">
                            <span class="journal-chip">{{ session.routeCategoryLabel }}</span>
                            <span v-if="session.isExpedition" class="journal-chip journal-chip--primary">Expedition</span>
                            <span class="journal-chip">{{ session.isPublic ? 'Public' : 'Private' }}</span>
                            <span v-if="session.hasTrack" class="journal-chip">Track attached</span>
                        </div>

                        <div>
                            <h3 class="text-[1.45rem] leading-none text-[color:var(--journal-text)]">
                                {{ session.title }}
                            </h3>
                            <p class="mt-2 text-sm text-[color:var(--journal-muted)]">
                                {{ session.date ?? 'No date' }}
                                <span v-if="session.launchName">· {{ session.launchName }}</span>
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]">
                            <span class="journal-chip">{{ session.distanceKm.toFixed(1) }} km</span>
                            <span class="journal-chip">{{ session.durationMinutes }} min</span>
                            <span v-if="session.beaufort !== null" class="journal-chip">F{{ session.beaufort }}</span>
                            <span v-if="session.isExpedition && session.expeditionDays" class="journal-chip">
                                {{ session.expeditionDays }} days out
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                        <Link :href="`/sessions/${session.id}`" class="journal-utility-link">
                            Open
                        </Link>
                        <Link :href="`/sessions/${session.id}/edit`" class="journal-utility-link">
                            Edit
                        </Link>
                    </div>
                </div>
            </article>

            <article
                v-if="!sessions.length"
                class="rounded-[1.75rem] border border-dashed border-[color:var(--journal-line)] bg-white/78 px-5 py-10 text-sm leading-7 text-[color:var(--journal-muted)]"
            >
                No sessions yet. Start by adding your first paddle or importing Garmin history.
            </article>
        </section>
    </div>
</template>
