<script setup lang="ts">
import { computed } from 'vue';
import { useUnitPreferences } from '@/composables/useUnitPreferences';
import {
    convertTemperatureC,
    formatDistanceKm,
    formatSpeedKnots,
    formatTemperatureC,
    speedUnitLabel,
    temperatureUnitLabel,
} from '@/lib/units';

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

interface MonthlyDistanceRow {
    key: string;
    label: string;
    distanceKm: number;
}

interface SeaState {
    temperatureAverages: {
        air: number | null;
        sea: number | null;
    };
}

interface SparklineVisual {
    linePath: string;
    areaPath: string;
    lastPointX: number;
    lastPointY: number;
    startLabel: string;
    endLabel: string;
    lineColor: string;
    fillColor: string;
}

interface BandVisual {
    label: string;
    value: string;
    percent: number;
    fill: string;
}

interface ThermometerVisual {
    percent: number;
    minLabel: string;
    maxLabel: string;
    status: string;
    hasValue: boolean;
    fill: string;
}

interface SpeedVisual {
    percent: number;
    hasValue: boolean;
    fill: string;
    status: string;
    minLabel: string;
    maxLabel: string;
}

interface MetricCard {
    label: string;
    value: string;
    detail: string;
    style: string;
    type: 'sparkline' | 'bands' | 'temperature' | 'speed';
    sparkline?: SparklineVisual;
    bands?: BandVisual[];
    thermometer?: ThermometerVisual;
    speed?: SpeedVisual;
}

const props = withDefaults(
    defineProps<{
        headline: HeadlineStats;
        seaState: SeaState;
        monthlyDistance: MonthlyDistanceRow[];
        context?: 'private' | 'public';
    }>(),
    {
        context: 'private',
    },
);
const { unitPreferences } = useUnitPreferences();

function clamp(value: number, min: number, max: number) {
    return Math.min(Math.max(value, min), max);
}

function createSparkline(
    values: number[],
    labels: string[],
    lineColor: string,
    fillColor: string,
): SparklineVisual {
    const width = 176;
    const height = 52;
    const safeValues = values.length ? values : [0];
    const maxValue = Math.max(...safeValues, 1);
    const step =
        safeValues.length > 1 ? width / (safeValues.length - 1) : width;

    const points = safeValues.map((value, index) => {
        const x = safeValues.length > 1 ? index * step : width / 2;
        const normalized = value / maxValue;
        const y = height - normalized * (height - 10) - 5;

        return { x, y };
    });

    const linePath = points
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`,
        )
        .join(' ');

    const areaPath = `${linePath} L ${points[points.length - 1]?.x.toFixed(2) ?? width} ${height} L ${
        points[0]?.x.toFixed(2) ?? 0
    } ${height} Z`;

    return {
        linePath,
        areaPath,
        lastPointX: points[points.length - 1]?.x ?? width / 2,
        lastPointY: points[points.length - 1]?.y ?? height / 2,
        startLabel: labels[0] ?? 'Start',
        endLabel: labels[labels.length - 1] ?? 'Now',
        lineColor,
        fillColor,
    };
}

function describeTemperature(
    value: number | null,
    min: number,
    max: number,
    coldLabel: string,
    warmLabel: string,
) {
    if (value === null) {
        return {
            percent: 0,
            minLabel: `${convertTemperatureC(min, unitPreferences.value).toFixed(0)} ${temperatureUnitLabel(unitPreferences.value.temperature)}`,
            maxLabel: `${convertTemperatureC(max, unitPreferences.value).toFixed(0)} ${temperatureUnitLabel(unitPreferences.value.temperature)}`,
            status: 'No data yet',
            hasValue: false,
            fill: 'linear-gradient(90deg, rgba(122,215,208,0.3), rgba(255,156,107,0.3))',
        };
    }

    const percent = clamp(((value - min) / (max - min)) * 100, 0, 100);
    const pivot = min + (max - min) * 0.52;

    return {
        percent,
        minLabel: `${convertTemperatureC(min, unitPreferences.value).toFixed(0)} ${temperatureUnitLabel(unitPreferences.value.temperature)}`,
        maxLabel: `${convertTemperatureC(max, unitPreferences.value).toFixed(0)} ${temperatureUnitLabel(unitPreferences.value.temperature)}`,
        status: value >= pivot ? warmLabel : coldLabel,
        hasValue: true,
        fill: 'linear-gradient(90deg, rgba(122,215,208,0.88), rgba(122,162,255,0.88) 52%, rgba(255,156,107,0.92))',
    };
}

function describeSpeed(value: number | null) {
    const speedLabel = speedUnitLabel(unitPreferences.value.speed);
    const maxDisplay = unitPreferences.value.speed === 'kt' ? 7 : 7 * 1.852;

    if (value === null) {
        return {
            percent: 0,
            hasValue: false,
            fill: 'linear-gradient(90deg, rgba(122,162,255,0.3), rgba(122,215,208,0.3), rgba(255,156,107,0.3))',
            status: 'No timed sessions',
            minLabel: `0 ${speedLabel}`,
            maxLabel: `${maxDisplay.toFixed(0)} ${speedLabel}`,
        };
    }

    const percent = clamp((value / 7) * 100, 0, 100);

    return {
        percent,
        hasValue: true,
        fill: 'linear-gradient(90deg, #7aa2ff, #6772ff 38%, #7ad7d0 68%, #ff9c6b)',
        status:
            value >= 5.5
                ? 'Fast cruising'
                : value >= 4
                  ? 'Cruising'
                  : 'Easy pace',
        minLabel: `0 ${speedLabel}`,
        maxLabel: `${maxDisplay.toFixed(0)} ${speedLabel}`,
    };
}

const monthlySeries = computed(() =>
    props.monthlyDistance.map((row) => row.distanceKm),
);
const monthlyLabels = computed(() =>
    props.monthlyDistance.map((row) => row.label),
);

const peakMonth = computed(() => {
    const fallback = props.monthlyDistance[0] ?? {
        key: 'none',
        label: 'No month',
        distanceKm: 0,
    };

    return props.monthlyDistance.reduce(
        (best, row) => (row.distanceKm > best.distanceKm ? row : best),
        fallback,
    );
});

const averageSessionMinutes = computed(() =>
    props.headline.sessionCount
        ? (props.headline.durationHours * 60) / props.headline.sessionCount
        : 0,
);

const durationBands = computed<BandVisual[]>(() => [
    {
        label: 'Months',
        value: `${props.headline.paddledMonths}`,
        percent: clamp((props.headline.paddledMonths / 12) * 100, 0, 100),
        fill: 'linear-gradient(90deg, #7ad7d0, #7aa2ff)',
    },
    {
        label: 'Tracked',
        value: `${props.headline.trackSessions}`,
        percent: props.headline.sessionCount
            ? clamp(
                  (props.headline.trackSessions / props.headline.sessionCount) *
                      100,
                  0,
                  100,
              )
            : 0,
        fill: 'linear-gradient(90deg, #6772ff, #9c80ff)',
    },
    {
        label: 'Avg day',
        value: `${Math.round(averageSessionMinutes.value)}m`,
        percent: clamp((averageSessionMinutes.value / 240) * 100, 0, 100),
        fill: 'linear-gradient(90deg, #ff9c6b, #ff8a80)',
    },
]);

const cards = computed<MetricCard[]>(() => [
    {
        label: 'Total distance',
        value: formatDistanceKm(props.headline.distanceKm, unitPreferences.value),
        detail:
            peakMonth.value.distanceKm > 0
                ? `Peak ${peakMonth.value.label} · ${formatDistanceKm(peakMonth.value.distanceKm, unitPreferences.value)}`
                : 'No monthly distance logged yet',
        style: 'linear-gradient(135deg, color-mix(in srgb, var(--journal-sea) 18%, transparent), var(--journal-metric-bg))',
        type: 'sparkline',
        sparkline: createSparkline(
            monthlySeries.value,
            monthlyLabels.value,
            '#6772ff',
            'rgba(103, 114, 255, 0.14)',
        ),
    },
    {
        label: 'Total duration',
        value: `${props.headline.durationHours.toFixed(1)} h`,
        detail:
            props.context === 'public'
                ? `${props.headline.paddledMonths} months active · ${Math.round(averageSessionMinutes.value)}m average day`
                : `${props.headline.trackSessions} tracked sessions · ${Math.round(averageSessionMinutes.value)}m average day`,
        style: 'linear-gradient(135deg, color-mix(in srgb, var(--journal-mint) 20%, transparent), var(--journal-metric-bg))',
        type: 'bands',
        bands: durationBands.value,
    },
    {
        label: 'Average air temperature',
        value:
            props.seaState.temperatureAverages.air !== null
                ? formatTemperatureC(
                      props.seaState.temperatureAverages.air,
                      unitPreferences.value,
                  )
                : '—',
        detail:
            props.context === 'public'
                ? 'Across public air logs'
                : 'Across logged air readings',
        style: 'linear-gradient(135deg, color-mix(in srgb, var(--journal-sand) 18%, transparent), var(--journal-metric-bg))',
        type: 'temperature',
        thermometer: describeTemperature(
            props.seaState.temperatureAverages.air,
            -5,
            25,
            'Cool air',
            'Warmer air',
        ),
    },
    {
        label: 'Average sea temperature',
        value:
            props.seaState.temperatureAverages.sea !== null
                ? formatTemperatureC(
                      props.seaState.temperatureAverages.sea,
                      unitPreferences.value,
                  )
                : '—',
        detail:
            props.context === 'public'
                ? 'Across public sea logs'
                : 'Across logged sea readings',
        style: 'linear-gradient(135deg, color-mix(in srgb, var(--journal-private) 18%, transparent), var(--journal-metric-bg))',
        type: 'temperature',
        thermometer: describeTemperature(
            props.seaState.temperatureAverages.sea,
            0,
            15,
            'Cold sea',
            'Milder sea',
        ),
    },
    {
        label: 'Average speed',
        value:
            props.headline.averageSpeedKnots !== null
                ? formatSpeedKnots(
                      props.headline.averageSpeedKnots,
                      unitPreferences.value,
                  )
                : '—',
        detail:
            props.headline.averageSpeedSamples > 0
                ? `Across ${props.headline.averageSpeedSamples} ${props.headline.averageSpeedSamples === 1 ? 'session' : 'sessions'} with distance + time`
                : 'Add distance and time to calculate knots',
        style: 'linear-gradient(135deg, color-mix(in srgb, var(--journal-sea-strong) 18%, transparent), var(--journal-metric-bg))',
        type: 'speed',
        speed: describeSpeed(props.headline.averageSpeedKnots),
    },
]);
</script>

<template>
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <article
            v-for="card in cards"
            :key="card.label"
            class="journal-metric-card"
            :style="{ background: card.style }"
        >
            <p class="journal-kicker">{{ card.label }}</p>

            <div class="mt-4 flex items-end justify-between gap-3">
                <p
                    class="text-[2rem] font-semibold text-[color:var(--journal-text)] sm:text-3xl md:text-[2.2rem]"
                >
                    {{ card.value }}
                </p>
                <p
                    v-if="
                        card.type === 'temperature' &&
                        card.thermometer?.hasValue
                    "
                    class="journal-surface-shell rounded-full px-2.5 py-1 text-[11px] font-medium tracking-[0.18em] text-[color:var(--journal-faint)] uppercase"
                >
                    {{ card.thermometer.status }}
                </p>
            </div>

            <div
                v-if="card.type === 'sparkline' && card.sparkline"
                class="mt-4"
            >
                <div class="journal-mini-sparkline">
                    <svg
                        viewBox="0 0 176 52"
                        class="h-14 w-full sm:h-16"
                        preserveAspectRatio="none"
                        aria-hidden="true"
                    >
                        <path
                            :d="card.sparkline.areaPath"
                            :fill="card.sparkline.fillColor"
                        />
                        <path
                            :d="card.sparkline.linePath"
                            :stroke="card.sparkline.lineColor"
                            stroke-width="3.2"
                            fill="none"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <circle
                            :cx="card.sparkline.lastPointX"
                            :cy="card.sparkline.lastPointY"
                            r="4.2"
                            :fill="card.sparkline.lineColor"
                            stroke="rgba(255,255,255,0.92)"
                            stroke-width="2"
                        />
                    </svg>
                </div>

                <div
                    class="mt-2 flex items-center justify-between text-[11px] font-semibold tracking-[0.22em] text-[color:var(--journal-faint)] uppercase"
                >
                    <span>{{ card.sparkline.startLabel }}</span>
                    <span>{{ card.sparkline.endLabel }}</span>
                </div>
            </div>

            <div
                v-else-if="card.type === 'bands' && card.bands"
                class="mt-4 grid gap-2.5"
            >
                <div
                    v-for="band in card.bands"
                    :key="band.label"
                    class="grid grid-cols-[54px_minmax(0,1fr)_max-content] items-center gap-3"
                >
                    <span
                        class="text-[11px] font-semibold tracking-[0.18em] text-[color:var(--journal-faint)] uppercase"
                    >
                        {{ band.label }}
                    </span>
                    <div
                        class="journal-meter-track h-2.5 overflow-hidden rounded-full"
                    >
                        <div
                            class="h-full rounded-full"
                            :style="{
                                width: `${Math.max(band.percent, band.percent > 0 ? 10 : 0)}%`,
                                background: band.fill,
                            }"
                        />
                    </div>
                    <span
                        class="text-sm font-medium text-[color:var(--journal-muted)]"
                    >
                        {{ band.value }}
                    </span>
                </div>
            </div>

            <div
                v-else-if="card.type === 'temperature' && card.thermometer"
                class="mt-4 grid gap-2"
            >
                <div
                    class="journal-meter-track relative h-3 overflow-hidden rounded-full"
                >
                    <div
                        class="absolute inset-0 rounded-full"
                        :style="{ background: card.thermometer.fill }"
                    />
                    <div
                        v-if="card.thermometer.hasValue"
                        class="journal-meter-thumb absolute top-1/2 h-4 w-4 -translate-y-1/2 rounded-full"
                        :style="{
                            left: `calc(${card.thermometer.percent}% - 0.5rem)`,
                        }"
                    />
                </div>

                <div
                    class="flex items-center justify-between text-[11px] font-semibold tracking-[0.22em] text-[color:var(--journal-faint)] uppercase"
                >
                    <span>{{ card.thermometer.minLabel }}</span>
                    <span>{{ card.thermometer.maxLabel }}</span>
                </div>
            </div>

            <div
                v-else-if="card.type === 'speed' && card.speed"
                class="mt-4 grid gap-2"
            >
                <div
                    class="flex items-center justify-between text-[11px] font-semibold tracking-[0.2em] text-[color:var(--journal-faint)] uppercase"
                >
                    <span>{{ card.speed.status }}</span>
                    <span v-if="card.speed.hasValue">{{ card.value }}</span>
                </div>

                <div
                    class="journal-meter-track relative h-3 overflow-hidden rounded-full"
                >
                    <div
                        class="absolute inset-0 rounded-full"
                        :style="{ background: card.speed.fill }"
                    />
                    <div
                        v-if="card.speed.hasValue"
                        class="journal-meter-thumb absolute top-1/2 h-4 w-4 -translate-y-1/2 rounded-full"
                        :style="{
                            left: `calc(${card.speed.percent}% - 0.5rem)`,
                        }"
                    />
                </div>

                <div
                    class="flex items-center justify-between text-[11px] font-semibold tracking-[0.22em] text-[color:var(--journal-faint)] uppercase"
                >
                    <span>{{ card.speed.minLabel }}</span>
                    <span>{{ card.speed.maxLabel }}</span>
                </div>
            </div>

            <p class="mt-3 text-sm leading-6 text-[color:var(--journal-muted)]">
                {{ card.detail }}
            </p>
        </article>
    </section>
</template>
