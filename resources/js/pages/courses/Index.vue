<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

interface HeadlineStats {
    sessionCount: number;
    distanceKm: number;
    durationHours: number;
    longestDistanceKm: number;
    averageDistanceKm: number;
    averageSpeedKnots: number | null;
    averageSpeedSamples: number;
    trackSessions: number;
    paddledMonths: number;
}

interface SummaryItem {
    label: string;
    count: number;
}

interface ExpeditionSummary {
    distanceKm: number;
    daysOut: number;
    tripCount: number;
}

interface EvidenceSession {
    id: number;
    date: string | null;
    title: string;
    routeCategoryLabel: string;
    distanceKm: number;
    durationMinutes: number;
    beaufort: number | null;
    isExpedition: boolean;
    launchName: string | null;
    path: string;
    summary: string | null;
    evidenceTags: string[];
}

interface NoteExcerpt {
    id: number;
    date: string | null;
    title: string;
    source: string;
    excerpt: string | null;
    path: string;
}

interface ReportData {
    generatedAt: string;
    purpose: string;
    profile: {
        name: string;
        paddlerName: string;
        homeWater: string;
        timezone: string;
        kayakClub: string | null;
        kayaksOwned: string[];
        paddlesOwned: string[];
    };
    headline: HeadlineStats;
    expeditionSummary: ExpeditionSummary;
    observationCount: number;
    skillsSummary: SummaryItem[];
    folderSummary: SummaryItem[];
    evidenceSessions: EvidenceSession[];
    noteExcerpts: NoteExcerpt[];
    sessionLog: Array<unknown>;
}

const props = defineProps<{
    report: ReportData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Courses',
                href: '/courses',
            },
        ],
    },
});

const topCards = [
    {
        label: 'Sessions logged',
        value: String(props.report.headline.sessionCount),
        detail: 'Complete journal history included in the appendix',
    },
    {
        label: 'Distance paddled',
        value: `${props.report.headline.distanceKm.toFixed(1)} km`,
        detail: `${props.report.headline.durationHours.toFixed(1)} h total duration`,
    },
    {
        label: 'Tracked sessions',
        value: String(props.report.headline.trackSessions),
        detail: 'GPX, FIT, or route geometry attached',
    },
    {
        label: 'Expedition sessions',
        value: String(props.report.expeditionSummary.tripCount),
        detail: `${props.report.expeditionSummary.daysOut} logged days out`,
    },
];
</script>

<template>
    <Head title="Courses" />

    <div class="flex flex-col gap-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div
                class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between"
            >
                <div class="max-w-3xl space-y-4">
                    <p class="journal-kicker">Courses</p>
                    <div class="space-y-2">
                        <h1
                            class="text-[clamp(2rem,4.1vw,3rem)] leading-[0.94] text-[color:var(--journal-text)]"
                        >
                            Course application report
                        </h1>
                        <p class="journal-copy text-sm md:text-base">
                            Build a formal, PDF-ready summary of your paddling
                            history for advanced course applications. The report
                            pulls from the full journal, highlights stronger
                            evidence sessions, and appends the complete session
                            log.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span class="journal-chip">{{
                            report.profile.paddlerName
                        }}</span>
                        <span class="journal-chip">{{
                            report.profile.homeWater
                        }}</span>
                        <span
                            v-if="report.profile.kayakClub"
                            class="journal-chip"
                        >
                            {{ report.profile.kayakClub }}
                        </span>
                        <span class="journal-chip">
                            Generated {{ report.generatedAt }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link href="/dashboard" class="journal-utility-link">
                        Dashboard
                    </Link>
                    <a
                        href="/courses/report"
                        target="_blank"
                        rel="noopener"
                        class="journal-primary-link"
                    >
                        Open PDF report
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in topCards"
                :key="card.label"
                class="journal-metric-card"
            >
                <p class="journal-kicker">{{ card.label }}</p>
                <p
                    class="mt-4 text-3xl font-semibold text-[color:var(--journal-text)]"
                >
                    {{ card.value }}
                </p>
                <p class="mt-2 text-sm text-[color:var(--journal-muted)]">
                    {{ card.detail }}
                </p>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            <article class="journal-panel px-5 py-5 md:px-6">
                <p class="journal-kicker">Included</p>
                <h2
                    class="mt-2 text-[1.75rem] leading-[0.96] text-[color:var(--journal-text)]"
                >
                    What the report contains
                </h2>

                <div class="mt-6 grid gap-3 md:grid-cols-2">
                    <article class="journal-soft-card">
                        <h3 class="text-lg font-semibold text-[color:var(--journal-text)]">
                            Experience summary
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                            Totals, pace, months paddled, distance windows, and
                            exposure overview built from the full journal.
                        </p>
                    </article>

                    <article class="journal-soft-card">
                        <h3 class="text-lg font-semibold text-[color:var(--journal-text)]">
                            Conditions and exposure
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                            Beaufort spread, tides, rescue totals, temperatures,
                            and how much of the journal has track and conditions
                            data logged.
                        </p>
                    </article>

                    <article class="journal-soft-card">
                        <h3 class="text-lg font-semibold text-[color:var(--journal-text)]">
                            Evidence sessions
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                            The strongest or most complete sessions are surfaced
                            automatically with notes, expedition flags, and
                            attached track data.
                        </p>
                    </article>

                    <article class="journal-soft-card">
                        <h3 class="text-lg font-semibold text-[color:var(--journal-text)]">
                            Full session appendix
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                            Every logged paddle is listed in a compact appendix
                            so the report still feels complete, not hand-picked.
                        </p>
                    </article>
                </div>
            </article>

            <article class="journal-panel px-5 py-5 md:px-6">
                <p class="journal-kicker">Profile</p>
                <h2
                    class="mt-2 text-[1.75rem] leading-[0.96] text-[color:var(--journal-text)]"
                >
                    Candidate details
                </h2>

                <div class="mt-6 space-y-3">
                    <article class="journal-soft-card">
                        <p class="journal-field-label">Home water</p>
                        <p class="mt-2 text-base font-semibold text-[color:var(--journal-text)]">
                            {{ report.profile.homeWater }}
                        </p>
                    </article>

                    <article
                        v-if="report.profile.kayaksOwned.length"
                        class="journal-soft-card"
                    >
                        <p class="journal-field-label">Kayaks owned</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="kayak in report.profile.kayaksOwned"
                                :key="kayak"
                                class="journal-chip"
                            >
                                {{ kayak }}
                            </span>
                        </div>
                    </article>

                    <article
                        v-if="report.profile.paddlesOwned.length"
                        class="journal-soft-card"
                    >
                        <p class="journal-field-label">Paddles owned</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="paddle in report.profile.paddlesOwned"
                                :key="paddle"
                                class="journal-chip"
                            >
                                {{ paddle }}
                            </span>
                        </div>
                    </article>
                </div>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <article class="journal-panel px-5 py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Evidence</p>
                        <h2
                            class="mt-2 text-[1.75rem] leading-[0.96] text-[color:var(--journal-text)]"
                        >
                            Strong evidence sessions
                        </h2>
                    </div>
                    <span class="journal-chip">{{
                        report.evidenceSessions.length
                    }}
                        selected</span
                    >
                </div>

                <div class="mt-6 grid gap-3">
                    <Link
                        v-for="session in report.evidenceSessions.slice(0, 4)"
                        :key="session.id"
                        :href="session.path"
                        class="journal-soft-card transition hover:-translate-y-0.5"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div class="space-y-2">
                                <p class="journal-kicker">
                                    {{ session.date ?? 'Session' }}
                                </p>
                                <h3
                                    class="text-xl font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ session.title }}
                                </h3>
                                <p
                                    v-if="session.summary"
                                    class="text-sm leading-6 text-[color:var(--journal-muted)]"
                                >
                                    {{ session.summary }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span class="journal-chip">{{
                                    session.routeCategoryLabel
                                }}</span>
                                <span class="journal-chip"
                                    >{{ session.distanceKm.toFixed(1) }} km</span
                                >
                                <span
                                    v-if="session.beaufort !== null"
                                    class="journal-chip"
                                    >F{{ session.beaufort }}</span
                                >
                            </div>
                        </div>
                    </Link>
                </div>
            </article>

            <article class="journal-panel px-5 py-5 md:px-6">
                <p class="journal-kicker">Logged focus</p>
                <h2
                    class="mt-2 text-[1.75rem] leading-[0.96] text-[color:var(--journal-text)]"
                >
                    Skills, folders, and notes
                </h2>

                <div class="mt-6 grid gap-4">
                    <article class="journal-soft-card">
                        <p class="journal-field-label">Skills most logged</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="skill in report.skillsSummary.slice(0, 8)"
                                :key="skill.label"
                                class="journal-chip"
                            >
                                {{ skill.label }} · {{ skill.count }}
                            </span>
                        </div>
                    </article>

                    <article class="journal-soft-card">
                        <p class="journal-field-label">Folders in use</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="folder in report.folderSummary.slice(0, 8)"
                                :key="folder.label"
                                class="journal-chip"
                            >
                                {{ folder.label }} · {{ folder.count }}
                            </span>
                        </div>
                    </article>

                    <article class="journal-soft-card">
                        <p class="journal-field-label">Observations included</p>
                        <p class="mt-2 text-3xl font-semibold text-[color:var(--journal-text)]">
                            {{ report.observationCount }}
                        </p>
                        <p
                            class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                        >
                            Observation and expedition note entries are pulled
                            into the report to show reflection as well as raw
                            mileage.
                        </p>
                    </article>
                </div>
            </article>
        </section>
    </div>
</template>
