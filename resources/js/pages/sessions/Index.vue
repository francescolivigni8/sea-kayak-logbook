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
    hasTrack: boolean;
    hasObservation: boolean;
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
    { label: 'Paddles', value: String(props.stats.sessionCount), detail: 'Logged in the journal' },
    { label: 'Distance', value: `${props.stats.distanceKm.toFixed(1)} km`, detail: 'All-time total' },
    { label: 'Expeditions', value: String(props.stats.expeditionTrips), detail: 'Checklist tagged' },
    { label: 'Days out', value: String(props.stats.expeditionDays), detail: 'Multiday total' },
]);
</script>

<template>
    <Head title="Sessions" />

    <div class="flex flex-col gap-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <p class="journal-kicker">Sessions</p>
                    <div class="space-y-2">
                        <h2 class="text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96]">
                            Session library
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            Every paddle in one quieter place for opening, editing, and scanning the full logbook.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col items-start gap-3 xl:items-end">
                    <p class="text-sm font-medium text-[color:var(--journal-muted)]">
                        {{ props.stats.sessionCount }} paddles recorded.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Link href="/imports/garmin" class="journal-utility-link">
                            Import history
                        </Link>
                        <Link href="/sessions/create" class="journal-primary-link">
                            Add session
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="successMessage" class="journal-banner">
            {{ successMessage }}
        </section>

        <section class="journal-banner journal-banner--soft">
            Recent paddles stay easiest to reach here, while expedition-tagged sessions still count in the same journal totals.
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

        <section class="grid gap-4 md:grid-cols-2">
            <article
                v-for="session in sessions"
                :key="session.id"
                class="journal-card overflow-hidden px-5 py-5 md:px-6"
                :style="{
                    background:
                        session.isExpedition
                            ? 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(255,156,107,0.08))'
                            : session.photoUrl
                              ? 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(122,215,208,0.08))'
                              : 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(103,114,255,0.05))',
                }"
            >
                <div class="flex h-full flex-col gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex flex-wrap gap-2 text-xs font-medium">
                            <span class="journal-kicker">{{ session.date ?? 'No date' }}</span>
                            <span class="journal-chip">{{ session.routeCategoryLabel }}</span>
                            <span v-if="session.beaufort !== null" class="journal-chip">F{{ session.beaufort }}</span>
                        </div>

                        <Link
                            :href="session.hasObservation ? `/sessions/${session.id}/edit` : `/sessions/${session.id}/edit?step=notes`"
                            class="journal-utility-link"
                        >
                            {{ session.hasObservation ? 'Edit' : 'Add observation' }}
                        </Link>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-[1.45rem] leading-none text-[color:var(--journal-text)]">
                            {{ session.title }}
                        </h3>
                        <p class="text-sm leading-6 text-[color:var(--journal-muted)]">
                            {{ session.launchName ?? props.profile.homeWater }}
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="journal-soft-card">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[color:var(--journal-faint)]">Distance</p>
                            <p class="mt-2 text-base font-semibold text-[color:var(--journal-text)]">
                                {{ session.distanceKm.toFixed(1) }} km
                            </p>
                        </div>
                        <div class="journal-soft-card">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[color:var(--journal-faint)]">Time</p>
                            <p class="mt-2 text-base font-semibold text-[color:var(--journal-text)]">
                                {{ session.durationMinutes }} min
                            </p>
                        </div>
                        <div class="journal-soft-card">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[color:var(--journal-faint)]">Track</p>
                            <p class="mt-2 text-base font-semibold text-[color:var(--journal-text)]">
                                {{ session.hasTrack ? 'Attached' : 'None' }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="session.photoUrl"
                        class="overflow-hidden rounded-[22px] border border-[color:var(--journal-line)] bg-white/72"
                    >
                        <img
                            :src="session.photoUrl"
                            alt="Session cover"
                            class="h-40 w-full object-cover"
                        />
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]">
                        <span v-if="session.isExpedition" class="journal-chip journal-chip--primary">Expedition</span>
                        <span v-if="session.isExpedition && session.expeditionDays" class="journal-chip">
                            {{ session.expeditionDays }} days out
                        </span>
                        <span class="journal-chip">{{ session.launchName ?? props.profile.homeWater }}</span>
                    </div>

                    <div class="mt-auto">
                        <Link :href="`/sessions/${session.id}`" class="journal-utility-link w-full justify-center">
                            Open session
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
