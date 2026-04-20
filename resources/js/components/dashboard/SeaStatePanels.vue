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
    averageBeaufort: number | null;
    tideStates: TideState[];
    conditionMatrix: ConditionRow[];
    rescueTotals: RescueTotal[];
    temperatureAverages: {
        air: number | null;
        sea: number | null;
    };
}

const props = withDefaults(
    defineProps<{
        seaState: SeaStateSummary;
        yearSnapshots: SnapshotCard[];
        monthlyDistance: MonthlyDistanceRow[];
        compareChip?: string;
    }>(),
    {
        compareChip: 'Distance',
    },
);

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
const severityRgb: Record<string, string> = {
    low: '122, 215, 208',
    moderate: '122, 162, 255',
    high: '255, 156, 107',
    extreme: '255, 138, 128',
};
const severityOrder = ['low', 'moderate', 'high', 'extreme'];
const severityLabels: Record<string, string> = {
    low: 'Low',
    moderate: 'Moderate',
    high: 'High',
    extreme: 'Extreme',
};
const comparisonGradients = [
    'linear-gradient(90deg, #6772ff, #9c80ff 52%, #ff9c6b)',
    'linear-gradient(90deg, #7aa2ff, #6772ff)',
    'linear-gradient(90deg, #7ad7d0, #7aa2ff)',
];

const monthlyMax = computed(() =>
    Math.max(...props.monthlyDistance.map((item) => item.distanceKm), 1),
);
const rescueMax = computed(() =>
    Math.max(...props.seaState.rescueTotals.map((item) => item.count), 1),
);
const tideTotal = computed(() =>
    props.seaState.tideStates.reduce((total, item) => total + item.count, 0),
);
const comparisonMax = computed(() =>
    Math.max(...props.yearSnapshots.map((item) => item.value), 1),
);
const conditionCellMax = computed(() =>
    Math.max(
        ...props.seaState.conditionMatrix.flatMap((row) =>
            row.values.map((value) => value.count),
        ),
        1,
    ),
);

const loggedForceCount = computed(() =>
    props.seaState.beaufortBands.reduce((total, item) => total + item.count, 0),
);

const displayedForceBands = computed(() => {
    const preferredOrder = ['F2', 'F3', 'F4', 'F5', 'F6+'];
    const lookup = new Map(
        props.seaState.beaufortBands.map((item) => [item.label, item]),
    );

    return preferredOrder.map(
        (label) => lookup.get(label) ?? { label, count: 0 },
    );
});

const forceBarMax = computed(() =>
    Math.max(...displayedForceBands.value.map((item) => item.count), 1),
);

const forceBreakdown = computed(() => {
    const source = props.seaState.beaufortBands.filter(
        (item) => item.count > 0,
    );
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

const averageBeaufortLabel = computed(() => {
    if (props.seaState.averageBeaufort === null) {
        return '—';
    }

    return Number.isInteger(props.seaState.averageBeaufort)
        ? `F${props.seaState.averageBeaufort.toFixed(0)}`
        : `F${props.seaState.averageBeaufort.toFixed(1)}`;
});

const conditionHeatmapRows = computed(() =>
    props.seaState.conditionMatrix.map((row) => {
        const total = row.values.reduce((sum, value) => sum + value.count, 0);
        const values = severityOrder.map((severity) => {
            const current = row.values.find(
                (value) => value.key === severity,
            ) ?? { key: severity, count: 0 };
            const alpha =
                current.count > 0
                    ? 0.14 + (current.count / conditionCellMax.value) * 0.56
                    : 0.05;

            return {
                ...current,
                label: severity,
                style: {
                    background: `rgba(${severityRgb[severity] ?? '103, 114, 255'}, ${alpha.toFixed(2)})`,
                    color:
                        current.count > 0
                            ? (severityStyles[severity]?.text ??
                              'var(--journal-text)')
                            : 'var(--journal-faint)',
                    borderColor: `rgba(${severityRgb[severity] ?? '103, 114, 255'}, ${Math.max(alpha - 0.08, 0.08).toFixed(2)})`,
                },
            };
        });

        return {
            label: row.label,
            total,
            values,
        };
    }),
);

const hasConditionData = computed(() =>
    conditionHeatmapRows.value.some((item) => item.total > 0),
);

const comparisonSnapshots = computed(() =>
    props.yearSnapshots.map((snapshot, index) => ({
        ...snapshot,
        percent: Math.max(
            (snapshot.value / comparisonMax.value) * 100,
            snapshot.value > 0 ? 10 : 0,
        ),
        gradient: comparisonGradients[index % comparisonGradients.length],
    })),
);

function tidePercent(count: number) {
    if (!tideTotal.value) {
        return 0;
    }

    return Math.round((count / tideTotal.value) * 100);
}
</script>

<template>
    <div class="space-y-4">
        <section
            class="grid gap-3 lg:grid-cols-2 xl:grid-cols-[minmax(0,0.82fr)_minmax(0,0.8fr)_minmax(0,0.84fr)_minmax(0,0.92fr)]"
        >
            <article class="journal-card h-full px-4 py-4 md:px-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Wind</p>
                        <h3
                            class="mt-2 text-[1.3rem] leading-none sm:text-[1.45rem]"
                        >
                            Beaufort distribution
                        </h3>
                    </div>
                    <span class="journal-chip"
                        >{{ loggedForceCount }} logged</span
                    >
                </div>

                <div
                    class="mt-4 flex flex-col gap-4 sm:mt-5 sm:flex-row sm:items-center"
                >
                    <div
                        class="relative grid h-20 w-20 place-items-center rounded-full sm:h-24 sm:w-24"
                        :style="forceDonutStyle"
                    >
                        <div
                            class="grid h-[52px] w-[52px] place-items-center rounded-full bg-white/92 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.95)] sm:h-[58px] sm:w-[58px]"
                        >
                            <div>
                                <p
                                    class="text-[18px] font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ averageBeaufortLabel }}
                                </p>
                                <p
                                    class="text-[7px] leading-none font-medium tracking-[0.1em] text-[color:var(--journal-faint)] uppercase"
                                >
                                    average
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid flex-1 gap-2">
                        <div
                            v-for="band in forceBreakdown"
                            :key="band.label"
                            class="grid grid-cols-[34px_minmax(0,1fr)_30px] items-center gap-2"
                        >
                            <span
                                class="text-[11px] font-semibold tracking-[0.18em] text-[color:var(--journal-faint)] uppercase"
                            >
                                {{ band.label }}
                            </span>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :style="{
                                        width: `${band.percent}%`,
                                        background: band.color,
                                    }"
                                />
                            </div>
                            <span
                                class="text-right text-[11px] font-medium text-[color:var(--journal-muted)]"
                            >
                                {{ band.percent }}%
                            </span>
                        </div>
                    </div>
                </div>
            </article>

            <article class="journal-card h-full px-4 py-4 md:px-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Wind counts</p>
                        <h3
                            class="mt-2 text-[1.3rem] leading-none sm:text-[1.45rem]"
                        >
                            F2-F6
                        </h3>
                    </div>
                    <span class="journal-chip">Counts</span>
                </div>

                <div class="mt-5 grid gap-2.5">
                    <div
                        v-for="band in displayedForceBands"
                        :key="band.label"
                        class="grid grid-cols-[36px_minmax(0,1fr)_22px] items-center gap-2"
                    >
                        <span
                            class="text-[13px] font-medium text-[color:var(--journal-muted)]"
                        >
                            {{ band.label }}
                        </span>
                        <div
                            class="h-2.5 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max((band.count / forceBarMax) * 100, band.count > 0 ? 10 : 0)}%`,
                                    background:
                                        'linear-gradient(90deg, #6772ff, #9c80ff 52%, #ff9c6b)',
                                }"
                            />
                        </div>
                        <span
                            class="text-right text-[13px] font-medium text-[color:var(--journal-muted)]"
                        >
                            {{ band.count }}
                        </span>
                    </div>
                </div>
            </article>

            <article
                class="journal-card h-full px-4 py-4 md:px-5"
                style="
                    background: linear-gradient(
                        180deg,
                        var(--journal-card-top),
                        color-mix(
                            in srgb,
                            var(--journal-sand) 8%,
                            var(--journal-panel-soft)
                        )
                    );
                "
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Development</p>
                        <h3
                            class="mt-2 text-[1.3rem] leading-none sm:text-[1.45rem]"
                        >
                            Rescue events
                        </h3>
                    </div>
                    <span class="journal-chip">Session totals</span>
                </div>

                <div class="mt-5 grid gap-3">
                    <article
                        v-for="item in seaState.rescueTotals"
                        :key="item.label"
                        class="grid gap-2"
                    >
                        <div
                            class="flex items-center justify-between gap-3 text-[13px]"
                        >
                            <p
                                class="font-medium text-[color:var(--journal-muted)]"
                            >
                                {{ item.label }}
                            </p>
                            <p
                                class="font-semibold text-[color:var(--journal-text)]"
                            >
                                {{ item.count }}
                            </p>
                        </div>
                        <div
                            class="h-2.5 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max((item.count / rescueMax) * 100, item.count > 0 ? 12 : 0)}%`,
                                    background: item.label
                                        .toLowerCase()
                                        .includes('successful')
                                        ? 'linear-gradient(90deg, #6772ff, #7aa2ff)'
                                        : item.label
                                                .toLowerCase()
                                                .includes('wet')
                                          ? 'linear-gradient(90deg, #ff8a80, #ff9c6b)'
                                          : 'linear-gradient(90deg, #7aa2ff, #6772ff)',
                                }"
                            />
                        </div>
                    </article>
                </div>
            </article>

            <article class="journal-card h-full px-4 py-4 md:px-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Tide + current</p>
                        <h3
                            class="mt-2 text-[1.3rem] leading-none sm:text-[1.45rem]"
                        >
                            Sea profile
                        </h3>
                    </div>
                    <span class="journal-chip">Exposure</span>
                </div>

                <div class="mt-5 grid gap-2.5">
                    <div
                        v-for="state in seaState.tideStates"
                        :key="state.label"
                        class="grid grid-cols-[48px_minmax(0,1fr)_34px] items-center gap-2"
                    >
                        <span
                            class="text-[13px] font-medium text-[color:var(--journal-muted)]"
                            >{{ state.label }}</span
                        >
                        <div
                            class="h-2.5 overflow-hidden rounded-full bg-[rgba(122,215,208,0.12)]"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max(tidePercent(state.count), state.count > 0 ? 10 : 0)}%`,
                                    background:
                                        'linear-gradient(90deg, #7ad7d0, #7aa2ff)',
                                }"
                            />
                        </div>
                        <span
                            class="text-right text-[11px] font-medium text-[color:var(--journal-muted)]"
                        >
                            {{ tidePercent(state.count) }}%
                        </span>
                    </div>
                </div>
            </article>
        </section>

        <section
            class="grid gap-3 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,1.26fr)_minmax(0,0.95fr)]"
        >
            <article class="journal-card px-4 py-4 sm:px-5 sm:py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Conditions</p>
                        <h3
                            class="mt-2 text-[1.5rem] leading-none sm:text-[1.7rem]"
                        >
                            Environmental conditions
                        </h3>
                    </div>
                    <span class="journal-chip">Session checklist</span>
                </div>

                <div
                    v-if="hasConditionData"
                    class="mt-6 overflow-hidden rounded-[22px] border border-[color:var(--journal-line)] bg-white/78"
                >
                    <div class="overflow-x-auto">
                        <div
                            class="grid min-w-[430px] grid-cols-[minmax(128px,1.05fr)_repeat(4,minmax(62px,1fr))]"
                        >
                            <div
                                class="border-b border-[color:var(--journal-line)] px-4 py-3"
                            />
                            <div
                                v-for="severity in severityOrder"
                                :key="severity"
                                class="border-b border-l border-[color:var(--journal-line)] px-2 py-3 text-center text-[10px] leading-tight font-semibold text-[color:var(--journal-faint)]"
                            >
                                {{ severityLabels[severity] }}
                            </div>

                            <template
                                v-for="item in conditionHeatmapRows"
                                :key="item.label"
                            >
                                <div
                                    class="border-b border-[color:var(--journal-line)] px-4 py-3"
                                >
                                    <p
                                        class="text-sm leading-5 font-semibold text-[color:var(--journal-text)]"
                                    >
                                        {{ item.label }}
                                    </p>
                                    <p
                                        class="mt-1 text-[11px] tracking-[0.12em] text-[color:var(--journal-faint)] uppercase"
                                    >
                                        {{ item.total }} logged
                                    </p>
                                </div>

                                <div
                                    v-for="value in item.values"
                                    :key="`${item.label}-${value.key}`"
                                    class="border-b border-l border-[color:var(--journal-line)] p-2"
                                >
                                    <div
                                        class="grid min-h-[62px] place-items-center rounded-[16px] border text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.55)]"
                                        :style="value.style"
                                    >
                                        <span class="text-lg font-semibold">{{
                                            value.count || '—'
                                        }}</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div
                        class="border-t border-[color:var(--journal-line)] px-4 py-3 text-sm leading-6 text-[color:var(--journal-muted)]"
                    >
                        Darker cells mean those conditions recur more often in
                        your saved checklist ratings.
                    </div>
                </div>

                <div
                    v-else
                    class="mt-6 rounded-[20px] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-4 py-5 text-sm leading-6 text-[color:var(--journal-muted)]"
                >
                    No checklist condition ratings saved yet.
                </div>
            </article>

            <article class="journal-card px-4 py-4 sm:px-5 sm:py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Consistency</p>
                        <h3
                            class="mt-2 text-[1.5rem] leading-none sm:text-[1.7rem]"
                        >
                            Distance by month
                        </h3>
                    </div>
                    <span class="journal-chip">Year view</span>
                </div>

                <div class="mt-6 grid gap-4">
                    <div
                        v-for="item in monthlyDistance"
                        :key="item.key"
                        class="grid grid-cols-[42px_minmax(0,1fr)_72px] items-center gap-3"
                    >
                        <span
                            class="text-xs font-semibold tracking-[0.24em] text-[color:var(--journal-faint)] uppercase"
                        >
                            {{ item.label }}
                        </span>
                        <div
                            class="h-4 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.max((item.distanceKm / monthlyMax) * 100, item.distanceKm > 0 ? 8 : 0)}%`,
                                    background:
                                        'linear-gradient(90deg, #6772ff, #9c80ff 48%, #ff9c6b)',
                                }"
                            />
                        </div>
                        <span
                            class="text-right text-sm font-medium text-[color:var(--journal-muted)]"
                        >
                            {{
                                item.distanceKm
                                    ? `${item.distanceKm.toFixed(1)} km`
                                    : '–'
                            }}
                        </span>
                    </div>
                </div>
            </article>

            <article class="journal-card px-4 py-4 sm:px-5 sm:py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Distance windows</p>
                        <h3
                            class="mt-2 text-[1.5rem] leading-none sm:text-[1.7rem]"
                        >
                            Timeframe comparison
                        </h3>
                    </div>
                    <span class="journal-chip">{{ compareChip }}</span>
                </div>

                <div class="mt-6 grid gap-4">
                    <article
                        v-for="snapshot in comparisonSnapshots"
                        :key="snapshot.label"
                        class="rounded-[22px] border border-[color:var(--journal-line)] bg-white/78 px-4 py-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold tracking-[0.24em] text-[color:var(--journal-faint)] uppercase"
                                >
                                    {{ snapshot.label }}
                                </p>
                                <p
                                    class="mt-2 text-2xl font-semibold text-[color:var(--journal-text)]"
                                >
                                    {{ snapshot.value.toFixed(1) }}
                                    <span
                                        class="text-base text-[color:var(--journal-muted)]"
                                        >{{ snapshot.unit }}</span
                                    >
                                </p>
                            </div>
                            <p
                                class="max-w-[140px] text-right text-xs leading-5 text-[color:var(--journal-muted)]"
                            >
                                {{ snapshot.detail }}
                            </p>
                        </div>

                        <div class="mt-4 grid gap-2">
                            <div
                                class="flex items-center justify-between text-[11px] font-semibold tracking-[0.2em] text-[color:var(--journal-faint)] uppercase"
                            >
                                <span>0 {{ snapshot.unit }}</span>
                                <span>{{ snapshot.label }}</span>
                            </div>
                            <div
                                class="h-4 overflow-hidden rounded-full bg-[rgba(103,114,255,0.08)]"
                            >
                                <div
                                    class="relative h-full rounded-full"
                                    :style="{
                                        width: `${snapshot.percent}%`,
                                        background: snapshot.gradient,
                                    }"
                                >
                                    <span
                                        class="absolute top-1/2 right-1 h-3 w-3 -translate-y-1/2 rounded-full border border-white/90 bg-white/92 shadow-[0_6px_16px_rgba(37,43,82,0.14)]"
                                    />
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </article>
        </section>
    </div>
</template>
