<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

interface StatCard {
    label: string;
    value: number;
    detail: string;
}

interface KindBreakdown {
    kind: string;
    label: string;
    count: number;
}

interface FeedbackRow {
    id: number;
    kind: string;
    subject: string;
    message: string;
    pageContext: string | null;
    submittedFromPath: string | null;
    status: string;
    createdAt: string | null;
    createdRelative: string | null;
    user: {
        name: string | null;
        email: string | null;
    };
    profile: {
        name: string | null;
        slug: string | null;
    };
}

defineProps<{
    overviewCards: StatCard[];
    kindBreakdown: KindBreakdown[];
    reports: FeedbackRow[];
}>();

function kindLabel(kind: string) {
    return kind.charAt(0).toUpperCase() + kind.slice(1);
}

function kindChipClass(kind: string) {
    if (kind === 'issue') {
        return 'border-[rgba(255,138,128,0.26)] bg-[rgba(255,138,128,0.12)] text-[#9c4841]';
    }

    if (kind === 'idea') {
        return 'border-[rgba(255,156,107,0.24)] bg-[rgba(255,156,107,0.14)] text-[#9a5a1f]';
    }

    if (kind === 'question') {
        return 'border-[rgba(122,162,255,0.22)] bg-[rgba(122,162,255,0.12)] text-[#4159a5]';
    }

    return 'border-[rgba(122,215,208,0.24)] bg-[rgba(122,215,208,0.14)] text-[#1d6e69]';
}
</script>

<template>
    <Head title="Feedback" />

    <div class="flex flex-col gap-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-2">
                    <p class="journal-kicker">Internal</p>
                    <h2 class="text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96]">
                        Tester feedback inbox
                    </h2>
                    <p class="journal-copy max-w-3xl text-sm md:text-base">
                        Read the issues, questions, and rough tester notes sent
                        from the in-app feedback form without leaving the
                        journal.
                    </p>
                </div>

                <span class="journal-chip">Owner only</span>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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
            class="grid gap-4 xl:grid-cols-[minmax(0,0.72fr)_minmax(0,1.28fr)]"
        >
            <article class="journal-card px-5 py-5 md:px-6">
                <div class="space-y-2">
                    <p class="journal-kicker">Breakdown</p>
                    <h3
                        class="text-[1.55rem] leading-none text-[color:var(--journal-text)]"
                    >
                        Report types
                    </h3>
                </div>

                <div class="mt-6 grid gap-3">
                    <article
                        v-for="row in kindBreakdown"
                        :key="row.kind"
                        class="grid gap-2"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-[color:var(--journal-muted)]">
                                {{ row.label }}
                            </p>
                            <p class="text-sm font-semibold text-[color:var(--journal-text)]">
                                {{ row.count }}
                            </p>
                        </div>
                        <div
                            class="h-2.5 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max(row.count * 16, row.count > 0 ? 10 : 0)}%`,
                                    background:
                                        row.kind === 'issue'
                                            ? 'linear-gradient(90deg, #ff8a80, #ff9c6b)'
                                            : row.kind === 'idea'
                                              ? 'linear-gradient(90deg, #ff9c6b, #ffd174)'
                                              : row.kind === 'question'
                                                ? 'linear-gradient(90deg, #7aa2ff, #6772ff)'
                                                : 'linear-gradient(90deg, #7ad7d0, #7aa2ff)',
                                }"
                            />
                        </div>
                    </article>
                </div>
            </article>

            <article class="journal-card px-5 py-5 md:px-6">
                <div class="space-y-2">
                    <p class="journal-kicker">Inbox</p>
                    <h3
                        class="text-[1.55rem] leading-none text-[color:var(--journal-text)]"
                    >
                        Latest reports
                    </h3>
                </div>

                <div v-if="reports.length" class="mt-6 grid gap-3">
                    <article
                        v-for="report in reports"
                        :key="report.id"
                        class="rounded-[22px] border border-[color:var(--journal-line)] bg-white/76 px-4 py-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-full border px-2.5 py-1 text-[10px] font-semibold tracking-[0.16em] uppercase"
                                        :class="kindChipClass(report.kind)"
                                    >
                                        {{ kindLabel(report.kind) }}
                                    </span>
                                    <span
                                        class="text-[10px] font-semibold tracking-[0.16em] text-[color:var(--journal-faint)] uppercase"
                                    >
                                        {{ report.createdRelative || 'Just now' }}
                                    </span>
                                </div>
                                <h4 class="text-base font-semibold text-[color:var(--journal-text)]">
                                    {{ report.subject }}
                                </h4>
                                <p class="text-sm leading-6 text-[color:var(--journal-muted)]">
                                    {{ report.message }}
                                </p>
                            </div>

                            <div class="min-w-[180px] space-y-1 text-right text-xs leading-5 text-[color:var(--journal-muted)]">
                                <p v-if="report.user.name">
                                    {{ report.user.name }}
                                </p>
                                <p v-if="report.user.email">
                                    {{ report.user.email }}
                                </p>
                                <p v-if="report.pageContext">
                                    {{ report.pageContext }}
                                </p>
                                <p v-else-if="report.submittedFromPath">
                                    {{ report.submittedFromPath }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-6 rounded-[20px] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-4 py-5 text-sm leading-6 text-[color:var(--journal-muted)]"
                >
                    No reports yet. Once testers use the feedback form, they
                    will appear here.
                </div>
            </article>
        </section>
    </div>
</template>
