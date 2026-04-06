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

const forceMax = computed(() =>
    Math.max(...props.seaState.beaufortBands.map((item) => item.count), 1),
);

const tideMax = computed(() =>
    Math.max(...props.seaState.tideStates.map((item) => item.count), 1),
);

const conditionMax = computed(() =>
    Math.max(
        ...props.seaState.conditionMatrix.flatMap((row) => row.values.map((value) => value.count)),
        1,
    ),
);

const severityToneClasses: Record<string, string> = {
    low: 'bg-emerald-200 text-emerald-700',
    moderate: 'bg-sky-200 text-sky-700',
    high: 'bg-amber-200 text-amber-700',
    extreme: 'bg-rose-200 text-rose-700',
};
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)_minmax(320px,1fr)]">
        <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                Wind force
            </p>
            <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                Beaufort exposure
            </h2>

            <div class="mt-6 grid gap-3">
                <div
                    v-for="band in seaState.beaufortBands"
                    :key="band.label"
                    class="grid grid-cols-[40px_minmax(0,1fr)_44px] items-center gap-3"
                >
                    <span class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                        {{ band.label }}
                    </span>
                    <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-violet-400 via-indigo-400 to-sky-400"
                            :style="{ width: `${Math.max((band.count / forceMax) * 100, band.count > 0 ? 10 : 0)}%` }"
                        />
                    </div>
                    <span class="text-right text-sm font-medium text-slate-500">
                        {{ band.count }}
                    </span>
                </div>
            </div>
        </article>

        <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                Sea timing
            </p>
            <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                Tide states
            </h2>

            <div class="mt-6 grid gap-3">
                <div
                    v-for="state in seaState.tideStates"
                    :key="state.label"
                    class="grid grid-cols-[64px_minmax(0,1fr)_36px] items-center gap-3"
                >
                    <span class="text-sm text-slate-500">{{ state.label }}</span>
                    <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-teal-400"
                            :style="{ width: `${Math.max((state.count / tideMax) * 100, state.count > 0 ? 10 : 0)}%` }"
                        />
                    </div>
                    <span class="text-right text-sm font-medium text-slate-500">{{ state.count }}</span>
                </div>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-2">
                <article class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Average air</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">
                        {{ seaState.temperatureAverages.air !== null ? `${seaState.temperatureAverages.air.toFixed(1)} C` : '—' }}
                    </p>
                </article>
                <article class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Average sea</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">
                        {{ seaState.temperatureAverages.sea !== null ? `${seaState.temperatureAverages.sea.toFixed(1)} C` : '—' }}
                    </p>
                </article>
            </div>
        </article>

        <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                Checklist
            </p>
            <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                Conditions and rescues
            </h2>

            <div class="mt-6 grid gap-3">
                <article
                    v-for="row in seaState.conditionMatrix"
                    :key="row.label"
                    class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-slate-900">
                            {{ row.label }}
                        </h3>
                    </div>

                    <div class="mt-3 grid grid-cols-4 gap-2">
                        <div
                            v-for="value in row.values"
                            :key="value.key"
                            class="rounded-2xl px-3 py-3 text-center"
                            :class="severityToneClasses[value.key] ?? 'bg-slate-200 text-slate-700'"
                            :style="{ opacity: 0.35 + (value.count / conditionMax) * 0.65 }"
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
                    class="rounded-[1.15rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                        {{ item.label }}
                    </p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">
                        {{ item.count }}
                    </p>
                </article>
            </div>
        </article>
    </section>
</template>
