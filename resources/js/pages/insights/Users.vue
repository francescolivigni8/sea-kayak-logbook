<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

interface StatCard {
    label: string;
    value: number;
    detail: string;
}

interface FunnelStage {
    label: string;
    count: number;
    percent: number;
    detail: string;
}

interface SignupPoint {
    label: string;
    month: string;
    count: number;
}

interface SessionTier {
    label: string;
    count: number;
    percent: number;
}

interface UserRow {
    id: number;
    name: string;
    accountName: string;
    email: string;
    homeWater: string | null;
    kayakClub: string | null;
    setupComplete: boolean;
    sessionCount: number;
    observationCount: number;
    importedSessionCount: number;
    joinedDate: string;
    joinedRelative: string;
    lastSessionDate: string | null;
    lastActivity: string | null;
    flags: string[];
}

defineProps<{
    overviewCards: StatCard[];
    healthCards: StatCard[];
    funnel: FunnelStage[];
    signupTrend: SignupPoint[];
    sessionTiers: SessionTier[];
    recentUsers: UserRow[];
    attentionUsers: UserRow[];
    users: UserRow[];
}>();
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-col gap-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-2">
                    <p class="journal-kicker">Internal</p>
                    <h2 class="text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96]">
                        Users and product insights
                    </h2>
                    <p class="journal-copy max-w-3xl text-sm md:text-base">
                        A private owner view of who joined, who finished setup,
                        who is logging paddles, and where people are dropping
                        out before the journal becomes useful.
                    </p>
                </div>

                <span class="journal-chip">Owner only</span>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="card in overviewCards"
                :key="card.label"
                class="journal-metric-card"
                style="
                    background: linear-gradient(
                        180deg,
                        rgba(255, 255, 255, 0.96),
                        rgba(122, 162, 255, 0.06)
                    );
                "
            >
                <p class="journal-kicker">{{ card.label }}</p>
                <p
                    class="mt-4 text-3xl font-semibold text-[color:var(--journal-text)] md:text-[2.2rem]"
                >
                    {{ card.value }}
                </p>
                <p
                    class="mt-3 text-sm leading-6 text-[color:var(--journal-muted)]"
                >
                    {{ card.detail }}
                </p>
            </article>
        </section>

        <section
            class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]"
        >
            <article class="journal-card px-5 py-5 md:px-6">
                <div class="space-y-2">
                    <p class="journal-kicker">Funnel</p>
                    <h3
                        class="text-[1.7rem] leading-none text-[color:var(--journal-text)]"
                    >
                        Product adoption
                    </h3>
                </div>

                <div class="mt-6 grid gap-4">
                    <article
                        v-for="stage in funnel"
                        :key="stage.label"
                        class="grid gap-2"
                    >
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p
                                    class="text-sm font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ stage.label }}
                                </p>
                                <p
                                    class="text-sm leading-6 text-[color:var(--journal-muted)]"
                                >
                                    {{ stage.detail }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p
                                    class="text-lg font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ stage.count }}
                                </p>
                                <p
                                    class="text-xs font-semibold tracking-[0.18em] text-[color:var(--journal-faint)] uppercase"
                                >
                                    {{ stage.percent }}%
                                </p>
                            </div>
                        </div>

                        <div
                            class="h-3 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max(stage.percent, stage.count > 0 ? 8 : 0)}%`,
                                    background:
                                        'linear-gradient(90deg, #6772ff, #9c80ff 55%, #ff9c6b)',
                                }"
                            />
                        </div>
                    </article>
                </div>
            </article>

            <article class="journal-card px-5 py-5 md:px-6">
                <div class="space-y-2">
                    <p class="journal-kicker">Growth</p>
                    <h3
                        class="text-[1.7rem] leading-none text-[color:var(--journal-text)]"
                    >
                        New paddlers by month
                    </h3>
                </div>

                <div class="mt-6 grid gap-4">
                    <article
                        v-for="point in signupTrend"
                        :key="point.month"
                        class="grid grid-cols-[42px_minmax(0,1fr)_36px] items-center gap-3"
                    >
                        <span
                            class="text-xs font-semibold tracking-[0.24em] text-[color:var(--journal-faint)] uppercase"
                        >
                            {{ point.label }}
                        </span>
                        <div
                            class="h-4 overflow-hidden rounded-full bg-[rgba(122,162,255,0.08)]"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max(point.count * 20, point.count > 0 ? 12 : 0)}%`,
                                    background:
                                        'linear-gradient(90deg, #7aa2ff, #7ad7d0)',
                                }"
                            />
                        </div>
                        <span
                            class="text-right text-sm font-medium text-[color:var(--journal-muted)]"
                        >
                            {{ point.count }}
                        </span>
                    </article>
                </div>
            </article>
        </section>

        <section
            class="grid gap-4 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)_minmax(0,1fr)]"
        >
            <article class="journal-card px-5 py-5 md:px-6">
                <div class="space-y-2">
                    <p class="journal-kicker">Segments</p>
                    <h3
                        class="text-[1.55rem] leading-none text-[color:var(--journal-text)]"
                    >
                        Session tiers
                    </h3>
                </div>

                <div class="mt-6 grid gap-3">
                    <article
                        v-for="tier in sessionTiers"
                        :key="tier.label"
                        class="grid gap-2"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p
                                class="text-sm font-medium text-[color:var(--journal-muted)]"
                            >
                                {{ tier.label }}
                            </p>
                            <p
                                class="text-sm font-semibold text-[color:var(--journal-text)]"
                            >
                                {{ tier.count }}
                            </p>
                        </div>
                        <div
                            class="h-2.5 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max(tier.percent, tier.count > 0 ? 10 : 0)}%`,
                                    background:
                                        'linear-gradient(90deg, #6772ff, #7aa2ff)',
                                }"
                            />
                        </div>
                    </article>
                </div>
            </article>

            <article class="journal-card px-5 py-5 md:px-6">
                <div class="space-y-2">
                    <p class="journal-kicker">Needs attention</p>
                    <h3
                        class="text-[1.55rem] leading-none text-[color:var(--journal-text)]"
                    >
                        Follow-up candidates
                    </h3>
                </div>

                <div v-if="attentionUsers.length" class="mt-6 grid gap-3">
                    <article
                        v-for="user in attentionUsers"
                        :key="user.id"
                        class="rounded-[22px] border border-[color:var(--journal-line)] bg-white/76 px-4 py-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-base font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ user.name }}
                                </p>
                                <p
                                    class="text-sm text-[color:var(--journal-muted)]"
                                >
                                    {{ user.email }}
                                </p>
                            </div>
                            <span
                                class="text-xs font-semibold tracking-[0.16em] text-[color:var(--journal-faint)] uppercase"
                            >
                                {{ user.joinedRelative }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="flag in user.flags"
                                :key="flag"
                                class="journal-chip"
                                >{{ flag }}</span
                            >
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-6 rounded-[20px] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-4 py-5 text-sm leading-6 text-[color:var(--journal-muted)]"
                >
                    No obvious follow-up accounts right now.
                </div>
            </article>

            <article class="journal-card px-5 py-5 md:px-6">
                <div class="space-y-2">
                    <p class="journal-kicker">Signals</p>
                    <h3
                        class="text-[1.55rem] leading-none text-[color:var(--journal-text)]"
                    >
                        Activity health
                    </h3>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <article
                        v-for="card in healthCards"
                        :key="card.label"
                        class="rounded-[22px] border border-[color:var(--journal-line)] bg-white/76 px-4 py-4"
                    >
                        <p class="journal-kicker">{{ card.label }}</p>
                        <p
                            class="mt-3 text-3xl font-semibold text-[color:var(--journal-text)]"
                        >
                            {{ card.value }}
                        </p>
                        <p
                            class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                        >
                            {{ card.detail }}
                        </p>
                    </article>
                </div>
            </article>
        </section>

        <section class="journal-panel px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-2">
                    <p class="journal-kicker">Users</p>
                    <h3
                        class="text-[1.8rem] leading-none text-[color:var(--journal-text)]"
                    >
                        All paddlers
                    </h3>
                    <p class="journal-copy max-w-3xl text-sm md:text-base">
                        Full account-level visibility, with setup, session,
                        observation, and import signals on each paddler.
                    </p>
                </div>
                <span class="journal-chip">{{ users.length }} accounts</span>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="user in users"
                    :key="user.id"
                    class="rounded-[24px] border border-[color:var(--journal-line)] bg-white/78 px-4 py-4"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-lg font-semibold text-[color:var(--journal-text)]"
                            >
                                {{ user.name }}
                            </p>
                            <p
                                class="text-sm text-[color:var(--journal-muted)]"
                            >
                                {{ user.email }}
                            </p>
                        </div>
                        <span
                            class="text-xs font-semibold tracking-[0.16em] text-[color:var(--journal-faint)] uppercase"
                        >
                            {{ user.joinedDate }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div
                            class="rounded-[18px] border border-[color:var(--journal-line)] bg-white/72 px-3 py-3"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.18em] text-[color:var(--journal-faint)] uppercase"
                            >
                                Sessions
                            </p>
                            <p
                                class="mt-2 text-xl font-semibold text-[color:var(--journal-text)]"
                            >
                                {{ user.sessionCount }}
                            </p>
                        </div>
                        <div
                            class="rounded-[18px] border border-[color:var(--journal-line)] bg-white/72 px-3 py-3"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.18em] text-[color:var(--journal-faint)] uppercase"
                            >
                                Observations
                            </p>
                            <p
                                class="mt-2 text-xl font-semibold text-[color:var(--journal-text)]"
                            >
                                {{ user.observationCount }}
                            </p>
                        </div>
                        <div
                            class="rounded-[18px] border border-[color:var(--journal-line)] bg-white/72 px-3 py-3"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.18em] text-[color:var(--journal-faint)] uppercase"
                            >
                                Imports
                            </p>
                            <p
                                class="mt-2 text-xl font-semibold text-[color:var(--journal-text)]"
                            >
                                {{ user.importedSessionCount }}
                            </p>
                        </div>
                        <div
                            class="rounded-[18px] border border-[color:var(--journal-line)] bg-white/72 px-3 py-3"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.18em] text-[color:var(--journal-faint)] uppercase"
                            >
                                Setup
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-[color:var(--journal-text)]"
                            >
                                {{
                                    user.setupComplete
                                        ? 'Complete'
                                        : 'Needs setup'
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-4 grid gap-2 text-sm text-[color:var(--journal-muted)]"
                    >
                        <p>
                            <span
                                class="font-medium text-[color:var(--journal-text)]"
                                >Account:</span
                            >
                            {{ user.accountName }}
                        </p>
                        <p v-if="user.kayakClub">
                            <span
                                class="font-medium text-[color:var(--journal-text)]"
                                >Club:</span
                            >
                            {{ user.kayakClub }}
                        </p>
                        <p v-if="user.homeWater">
                            <span
                                class="font-medium text-[color:var(--journal-text)]"
                                >Home water:</span
                            >
                            {{ user.homeWater }}
                        </p>
                        <p>
                            <span
                                class="font-medium text-[color:var(--journal-text)]"
                                >Joined:</span
                            >
                            {{ user.joinedRelative }}
                        </p>
                        <p>
                            <span
                                class="font-medium text-[color:var(--journal-text)]"
                                >Last activity:</span
                            >
                            {{ user.lastActivity ?? 'None yet' }}
                        </p>
                        <p>
                            <span
                                class="font-medium text-[color:var(--journal-text)]"
                                >Last session:</span
                            >
                            {{ user.lastSessionDate ?? 'No session yet' }}
                        </p>
                    </div>

                    <div
                        v-if="user.flags.length"
                        class="mt-4 flex flex-wrap gap-2"
                    >
                        <span
                            v-for="flag in user.flags"
                            :key="flag"
                            class="journal-chip"
                            >{{ flag }}</span
                        >
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
