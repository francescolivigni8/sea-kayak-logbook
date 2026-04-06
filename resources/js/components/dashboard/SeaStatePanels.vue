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

const props = defineProps<{
    seaState: SeaStateSummary;
}>();

const forceMax = computed(() => Math.max(...props.seaState.beaufortBands.map((item) => item.count), 1));
const tideMax = computed(() => Math.max(...props.seaState.tideStates.map((item) => item.count), 1));
const conditionMax = computed(() =>
    Math.max(...props.seaState.conditionMatrix.flatMap((row) => row.values.map((value) => value.count)), 1),
);

const severityStyles: Record<string, { bg: string; text: string }> = {
    low: { bg: 'rgba(122, 215, 208, 0.22)', text: '#2f6a66' },
    moderate: { bg: 'rgba(122, 162, 255, 0.2)', text: '#3f569c' },
    high: { bg: 'rgba(255, 156, 107, 0.22)', text: '#9f5d34' },
    extreme: { bg: 'rgba(255, 138, 128, 0.22)', text: '#a3544d' },
};
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(300px,0.9fr)_minmax(0,1fr)]">
        <article class="journal-card px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="journal-kicker">Wind</p>
                    <h3 class="mt-2 text-[1.65rem] leading-none">Beaufort exposure</h3>
                </div>
                <span class="journal-chip">Sessions</span>
            </div>

            <div class="mt-6 grid gap-3">
                <div
                    v-for="band in seaState.beaufortBands"
                    :key="band.label"
                    class="grid grid-cols-[42px_minmax(0,1fr)_36px] items-center gap-3"
                >
                    <span class="text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">
                        {{ band.label }}
                    </span>
                    <div class="h-4 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]">
                        <div
                            class="h-full rounded-full"
                            :style="{
                                width: `${Math.max((band.count / forceMax) * 100, band.count > 0 ? 10 : 0)}%`,
                                background: 'linear-gradient(90deg, #6772ff, #9c80ff 58%, #7aa2ff)',
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
                    <p class="journal-kicker">Sea profile</p>
                    <h3 class="mt-2 text-[1.65rem] leading-none">Tide and temperature</h3>
                </div>
                <span class="journal-chip">Exposure</span>
            </div>

            <div class="mt-6 grid gap-3">
                <div
                    v-for="state in seaState.tideStates"
                    :key="state.label"
                    class="grid grid-cols-[68px_minmax(0,1fr)_36px] items-center gap-3"
                >
                    <span class="text-sm text-[color:var(--journal-muted)]">{{ state.label }}</span>
                    <div class="h-4 overflow-hidden rounded-full bg-[rgba(122,215,208,0.12)]">
                        <div
                            class="h-full rounded-full"
                            :style="{
                                width: `${Math.max((state.count / tideMax) * 100, state.count > 0 ? 10 : 0)}%`,
                                background: 'linear-gradient(90deg, #7ad7d0, #7aa2ff)',
                            }"
                        />
                    </div>
                    <span class="text-right text-sm font-medium text-[color:var(--journal-muted)]">
                        {{ state.count }}
                    </span>
                </div>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-2">
                <article class="rounded-[22px] border border-[color:var(--journal-line)] bg-white/78 px-4 py-4">
                    <p class="journal-kicker">Average air</p>
                    <p class="mt-3 text-2xl font-semibold text-[color:var(--journal-text)]">
                        {{ seaState.temperatureAverages.air !== null ? `${seaState.temperatureAverages.air.toFixed(1)} C` : '—' }}
                    </p>
                </article>
                <article class="rounded-[22px] border border-[color:var(--journal-line)] bg-white/78 px-4 py-4">
                    <p class="journal-kicker">Average sea</p>
                    <p class="mt-3 text-2xl font-semibold text-[color:var(--journal-text)]">
                        {{ seaState.temperatureAverages.sea !== null ? `${seaState.temperatureAverages.sea.toFixed(1)} C` : '—' }}
                    </p>
                </article>
            </div>
        </article>

        <article class="journal-card px-5 py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="journal-kicker">Conditions and rescues</p>
                    <h3 class="mt-2 text-[1.65rem] leading-none">Checklist counts</h3>
                </div>
                <span class="journal-chip">Session checklist</span>
            </div>

            <div class="mt-6 grid gap-3">
                <article
                    v-for="row in seaState.conditionMatrix"
                    :key="row.label"
                    class="rounded-[22px] border border-[color:var(--journal-line)] bg-white/78 px-4 py-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h4 class="text-base font-semibold text-[color:var(--journal-text)]">
                            {{ row.label }}
                        </h4>
                    </div>

                    <div class="mt-3 grid grid-cols-4 gap-2">
                        <div
                            v-for="value in row.values"
                            :key="value.key"
                            class="rounded-[18px] px-3 py-3 text-center"
                            :style="{
                                background: severityStyles[value.key]?.bg ?? 'rgba(103, 114, 255, 0.12)',
                                color: severityStyles[value.key]?.text ?? '#465285',
                                opacity: 0.35 + (value.count / conditionMax) * 0.65,
                            }"
                        >
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em]">
                                {{ value.key }}
                            </p>
                            <p class="mt-2 text-lg font-semibold">
                                {{ value.count }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-3">
                <article
                    v-for="item in seaState.rescueTotals"
                    :key="item.label"
                    class="rounded-[22px] border border-[color:var(--journal-line)] bg-white/78 px-4 py-4"
                >
                    <p class="journal-kicker">{{ item.label }}</p>
                    <p class="mt-3 text-2xl font-semibold text-[color:var(--journal-text)]">
                        {{ item.count }}
                    </p>
                </article>
            </div>
        </article>
    </section>
</template>
