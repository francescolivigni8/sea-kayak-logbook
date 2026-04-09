<script setup lang="ts">
import { computed } from 'vue';

interface ForceBand {
    label: string;
    count: number;
}

interface TideState {
    label: string;
    count: number;
}

interface ConditionValue {
    key: string;
    count: number;
}

interface ConditionRow {
    label: string;
    values: ConditionValue[];
}

interface RescueTotal {
    label: string;
    count: number;
}

interface SnapshotCard {
    label: string;
    value: number;
    unit: string;
    detail: string;
}

interface MonthlyDistanceRow {
    key: string;
    label: string;
    distanceKm: number;
}

interface SeaStateSummary {
    beaufortBands: ForceBand[];
    tideStates: TideState[];
    conditionMatrix: ConditionRow[];
    rescueTotals: RescueTotal[];
    temperatureAverages: {
        air: number | null;
        sea: number | null;
    };
}

const props = withDefaults(defineProps<{
    seaState: SeaStateSummary;
    yearSnapshots: SnapshotCard[];
    monthlyDistance: MonthlyDistanceRow[];
    compareChip?: string;
}>(), {
    compareChip: 'Distance',
});

const forcePalette: Record<string, string> = {
    F0: '#d7dbff',
    F1: '#b8d8ff',
    F2: '#8be1c7',
    F3: '#73b8ff',
    F4: '#6293ff',
    F5: '#f0c35c',
    'F6+': '#ff9c6b',
};

const severityStyles: Record<string, { bg: string; text: string }> = {
    low: { bg: 'rgba(122, 215, 208, 0.18)', text: '#2f6a66' },
    moderate: { bg: 'rgba(122, 162, 255, 0.18)', text: '#3f569c' },
    high: { bg: 'rgba(255, 156, 107, 0.18)', text: '#9f5d34' },
    extreme: { bg: 'rgba(255, 138, 128, 0.18)', text: '#a3544d' },
};

const monthlyMax = computed(() => Math.max(...props.monthlyDistance.map((item) => item.distanceKm), 1));
const rescueMax = computed(() => Math.max(...props.seaState.rescueTotals.map((item) => item.count), 1));
const tideTotal = computed(() => props.seaState.tideStates.reduce((total, item) => total + item.count, 0));

const loggedForceCount = computed(() => props.seaState.beaufortBands.reduce((total, item) => total + item.count, 0));

const displayedForceBands = computed(() => {
    const preferredOrder = ['F2', 'F3', 'F4', 'F5', 'F6+'];
    const lookup = new Map(props.seaState.beaufortBands.map((item) => [item.label, item]));

    return preferredOrder.map((label) => lookup.get(label) ?? { label, count: 0 });
});

const forceBarMax = computed(() => Math.max(...displayedForceBands.value.map((item) => item.count), 1));

const forceBreakdown = computed(() => {
    const source = props.seaState.beaufortBands.filter((item) => item.count > 0);
    const total = loggedForceCount.value;

    return source.map((item) => ({
        ...item,
        percent: total ? Math.round((item.count / total) * 100) : 0,
        color: forcePalette[item.label] ?? '#6772ff',
    }));
});

const forceDonutStyle = computed(() => {
    if (!forceBreakdown.value.length || !loggedForceCount.value) {
        return {
            background: 'conic-gradient(rgba(103, 114, 255, 0.14) 0deg 360deg)',
        };
    }

    let progress = 0;
    const stops: string[] = [];

    forceBreakdown.value.forEach((item) => {
        const sweep = (item.count / loggedForceCount.value) * 360;
        stops.push(`${item.color} ${progress}deg ${progress + sweep}deg`);
        progress += sweep;
    });

    return {
        background: `conic-gradient(${stops.join(', ')})`,
    };
});

const conditionSummaries = computed(() =>
    props.seaState.conditionMatrix.map((row) => {
        const total = row.values.reduce((sum, value) => sum + value.count, 0);
        const dominant = row.values.reduce((winner, value) => (value.count > winner.count ? value : winner), row.values[0]);

        return {
            label: row.label,
            total,
            dominant: total > 0 ? dominant.key : 'not set',
            dominantStyle: total > 0 ? (severityStyles[dominant.key] ?? severityStyles.low) : null,
        };
    }),
);

const hasConditionData = computed(() => conditionSummaries.value.some((item) => item.total > 0));

function tidePercent(count: number) {
    if (!tideTotal.value) {
        return 0;
    }

    return Math.round((count / tideTotal.value) * 100);
}
</script>

<template>
    <div class="space-y-4">
        <section class="grid gap-4 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,0.95fr)_minmax(0,1fr)_minmax(0,1fr)]">
            <article class="journal-card px-5 py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Wind</p>
                        <h3 class="mt-2 text-[1.7rem] leading-none">Beaufort distribution</h3>
                    </div>
                    <span class="journal-chip">{{ loggedForceCount }} logged</span>
                </div>

                <div class="mt-6 flex items-center gap-6">
                    <div class="relative grid h-32 w-32 place-items-center rounded-full" :style="forceDonutStyle">
                        <div class="grid h-[78px] w-[78px] place-items-center rounded-full bg-white/92 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.95)]">
                            <div>
                                <p class="text-2xl font-semibold text-[color:var(--journal-text)]">{{ loggedForceCount }}</p>
                                <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-[color:var(--journal-faint)]">
                                    logged
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid flex-1 gap-3">
                        <div
                            v-for="band in forceBreakdown"
                            :key="band.label"
                            class="grid grid-cols-[36px_minmax(0,1fr)_34px] items-center gap-3"
                        >
                            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-[color:var(--journal-faint)]">
                                {{ band.label }}
                            </span>
                            <div class="h-2.5 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]">
                                <div
                                    class="h-full rounded-full"
                                    :style="{ width: `${band.percent}%`, background: band.color }"
                                />
                            </div>
                            <span class="text-right text-xs font-medium text-[color:var(--journal-muted)]">
                                {{ band.percent }}%
                            </span>
                        </div>
                    </div>
                </div>
            </article>

            <article class="journal-card px-5 py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Wind counts</p>
                        <h3 class="mt-2 text-[1.7rem] leading-none">F2-F6</h3>
                    </div>
                    <span class="journal-chip">Counts</span>
                </div>

                <div class="mt-6 grid gap-3">
                    <div
                        v-for="band in displayedForceBands"
                        :key="band.label"
                        class="grid grid-cols-[42px_minmax(0,1fr)_24px] items-center gap-3"
                    >
                        <span class="text-sm font-medium text-[color:var(--journal-muted)]">
                            {{ band.label }}
                        </span>
                        <div class="h-3 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]">
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max((band.count / forceBarMax) * 100, band.count > 0 ? 10 : 0)}%`,
                                    background: 'linear-gradient(90deg, #6772ff, #9c80ff 52%, #ff9c6b)',
                                }"
                            />
                        </div>
                        <span class="text-right text-sm font-medium text-[color:var(--journal-muted)]">
                            {{ band.count }}
                        </span>
                    </div>
                </div>
            </article>

            <article class="journal-card px-5 py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Compare</p>
                        <h3 class="mt-2 text-[1.7rem] leading-none">All time / year / 12m</h3>
                    </div>
                    <span class="journal-chip">{{ compareChip }}</span>
                </div>

                <div class="mt-6 grid gap-3">
                    <article
                        v-for="snapshot in yearSnapshots"
                        :key="snapshot.label"
                        class="rounded-[22px] border border-[color:var(--journal-line)] bg-white/78 px-4 py-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">
                                    {{ snapshot.label }}
                                </p>
                                <p class="mt-2 text-2xl font-semibold text-[color:var(--journal-text)]">
                                    {{ snapshot.value.toFixed(1) }}
                                    <span class="text-base text-[color:var(--journal-muted)]">{{ snapshot.unit }}</span>
                                </p>
                            </div>
                            <p class="max-w-[140px] text-right text-xs leading-5 text-[color:var(--journal-muted)]">
                                {{ snapshot.detail }}
                            </p>
                        </div>
                    </article>
                </div>
            </article>

            <article class="journal-card px-5 py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Tide + current</p>
                        <h3 class="mt-2 text-[1.7rem] leading-none">Sea profile</h3>
                    </div>
                    <span class="journal-chip">Exposure</span>
                </div>

                <div class="mt-6 grid gap-3">
                    <div
                        v-for="state in seaState.tideStates"
                        :key="state.label"
                        class="grid grid-cols-[54px_minmax(0,1fr)_36px] items-center gap-3"
                    >
                        <span class="text-sm font-medium text-[color:var(--journal-muted)]">{{ state.label }}</span>
                        <div class="h-2.5 overflow-hidden rounded-full bg-[rgba(122,215,208,0.12)]">
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max(tidePercent(state.count), state.count > 0 ? 10 : 0)}%`,
                                    background: 'linear-gradient(90deg, #7ad7d0, #7aa2ff)',
                                }"
                            />
                        </div>
                        <span class="text-right text-xs font-medium text-[color:var(--journal-muted)]">
                            {{ tidePercent(state.count) }}%
                        </span>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,0.78fr)_minmax(0,1.45fr)_minmax(0,0.9fr)]">
            <article class="journal-card px-5 py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Conditions</p>
                        <h3 class="mt-2 text-[1.7rem] leading-none">Environmental conditions</h3>
                    </div>
                    <span class="journal-chip">Session checklist</span>
                </div>

                <div v-if="hasConditionData" class="mt-6 grid gap-3">
                    <article
                        v-for="item in conditionSummaries"
                        :key="item.label"
                        class="rounded-[20px] border border-[color:var(--journal-line)] bg-white/78 px-4 py-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-base font-semibold text-[color:var(--journal-text)]">
                                {{ item.label }}
                            </h4>
                            <span
                                class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                                :style="item.dominantStyle ? { background: item.dominantStyle.bg, color: item.dominantStyle.text } : { background: 'rgba(103,114,255,0.1)', color: 'var(--journal-muted)' }"
                            >
                                {{ item.dominant }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-[color:var(--journal-muted)]">
                            {{ item.total }} sessions
                        </p>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-6 rounded-[20px] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-4 py-5 text-sm leading-6 text-[color:var(--journal-muted)]"
                >
                    No checklist condition ratings saved yet.
                </div>
            </article>

            <article class="journal-card px-5 py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Consistency</p>
                        <h3 class="mt-2 text-[1.7rem] leading-none">Distance by month</h3>
                    </div>
                    <span class="journal-chip">Year view</span>
                </div>

                <div class="mt-6 grid gap-4">
                    <div
                        v-for="item in monthlyDistance"
                        :key="item.key"
                        class="grid grid-cols-[42px_minmax(0,1fr)_72px] items-center gap-3"
                    >
                        <span class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">
                            {{ item.label }}
                        </span>
                        <div class="h-4 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]">
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max((item.distanceKm / monthlyMax) * 100, item.distanceKm > 0 ? 8 : 0)}%`,
                                    background: 'linear-gradient(90deg, #6772ff, #9c80ff 48%, #ff9c6b)',
                                }"
                            />
                        </div>
                        <span class="text-right text-sm font-medium text-[color:var(--journal-muted)]">
                            {{ item.distanceKm ? `${item.distanceKm.toFixed(1)} km` : '–' }}
                        </span>
                    </div>
                </div>
            </article>

            <article class="journal-card px-5 py-5 md:px-6" style="background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(255,156,107,0.06));">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Development</p>
                        <h3 class="mt-2 text-[1.7rem] leading-none">Rescue events</h3>
                    </div>
                    <span class="journal-chip">Session totals</span>
                </div>

                <div class="mt-6 grid gap-4">
                    <article
                        v-for="item in seaState.rescueTotals"
                        :key="item.label"
                        class="grid gap-2"
                    >
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <p class="font-medium text-[color:var(--journal-muted)]">{{ item.label }}</p>
                            <p class="font-semibold text-[color:var(--journal-text)]">{{ item.count }}</p>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]">
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max((item.count / rescueMax) * 100, item.count > 0 ? 12 : 0)}%`,
                                    background: item.label.toLowerCase().includes('successful') ? 'linear-gradient(90deg, #6772ff, #7aa2ff)' : item.label.toLowerCase().includes('wet') ? 'linear-gradient(90deg, #ff8a80, #ff9c6b)' : 'linear-gradient(90deg, #7aa2ff, #6772ff)',
                                }"
                            />
                        </div>
                    </article>
                </div>
            </article>
        </section>
    </div>
</template>
