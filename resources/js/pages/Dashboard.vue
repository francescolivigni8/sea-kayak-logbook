<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, watch, ref } from 'vue';
import { GripVertical, RotateCcw } from 'lucide-vue-next';
import { usePageHeaderActions } from '@/composables/usePageHeaderActions';
import { useUnitPreferences } from '@/composables/useUnitPreferences';
import { formatDistanceKm } from '@/lib/units';
import HeadlineMetricCards from '@/components/dashboard/HeadlineMetricCards.vue';
import SeaStatePanels from '@/components/dashboard/SeaStatePanels.vue';
import RouteAtlasMap from '@/components/maps/RouteAtlasMap.vue';

interface ProfileSummary {
    name: string;
    slug: string;
    bio: string;
    homeWater: string;
    timezone: string;
}

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

interface SeaState {
    beaufortBands: Array<{ label: string; count: number }>;
    averageBeaufort: number | null;
    swellBands: Array<{ label: string; count: number }>;
    averageSwellHeightM: number | null;
    swellSessionCount: number;
    tideStates: Array<{ label: string; count: number }>;
    conditionMatrix: Array<{
        label: string;
        values: Array<{ key: string; count: number }>;
    }>;
    rescueTotals: Array<{ label: string; count: number }>;
    temperatureAverages: {
        air: number | null;
        sea: number | null;
    };
}

interface MapData {
    defaultView: {
        lat: number;
        lng: number;
        zoom: number;
    };
    routes: Array<{
        id: number | string;
        label: string;
        color: string;
        path?: string | null;
        year?: number | null;
        years?: number[];
        isExpedition?: boolean;
        category?: string | null;
        points: Array<{ lat: number; lng: number }>;
    }>;
    pins: Array<{
        id: number | string;
        label: string;
        color: string;
        path?: string | null;
        year?: number | null;
        years?: number[];
        isExpedition?: boolean;
        category?: string | null;
        count?: number;
        lat: number;
        lng: number;
    }>;
}

interface ExpeditionSummary {
    distanceKm: number;
    daysOut: number;
    tripCount: number;
    missingMapPointCount: number;
}

interface SessionLink {
    id: number | string;
    label: string;
    path: string;
}

interface FlashPageProps {
    flash?: {
        success?: string;
    };
}

type DashboardCardId =
    | 'metric-distance'
    | 'metric-duration'
    | 'metric-air-temperature'
    | 'metric-sea-temperature'
    | 'metric-average-speed'
    | 'sea-beaufort-distribution'
    | 'sea-wind-counts'
    | 'sea-rescue-events'
    | 'sea-profile'
    | 'sea-environmental-conditions'
    | 'sea-distance-by-month'
    | 'sea-timeframe-comparison'
    | 'route-map'
    | 'expedition-distance'
    | 'expedition-days'
    | 'expedition-trips'
    | 'expedition-map'
    | 'expedition-sessions';

interface DashboardPreferences {
    order: DashboardCardId[];
    hidden: DashboardCardId[];
}

const props = defineProps<{
    profile: ProfileSummary;
    headline: HeadlineStats;
    yearSnapshots: SnapshotCard[];
    monthlyDistance: MonthlyDistanceRow[];
    seaState: SeaState;
    mapData: MapData;
    expeditionSummary: ExpeditionSummary;
    expeditionPlaces: Array<unknown>;
    expeditionMapData: MapData;
    expeditionSessionLinks: SessionLink[];
    dashboardPreferences: DashboardPreferences;
}>();

const page = usePage();
const { unitPreferences } = useUnitPreferences();
const { setPageHeaderActions, clearPageHeaderActions } = usePageHeaderActions();
const successMessage = computed(
    () => (page.props as FlashPageProps).flash?.success,
);
const isEditingLayout = ref(false);
const isSavingLayout = ref(false);
const draggingExpeditionCardId = ref<DashboardCardId | null>(null);
const dropTargetExpeditionCardId = ref<DashboardCardId | null>(null);
const defaultCardOrder: DashboardCardId[] = [
    'metric-distance',
    'metric-duration',
    'metric-air-temperature',
    'metric-sea-temperature',
    'metric-average-speed',
    'sea-beaufort-distribution',
    'sea-wind-counts',
    'sea-rescue-events',
    'sea-profile',
    'sea-environmental-conditions',
    'sea-distance-by-month',
    'sea-timeframe-comparison',
    'route-map',
    'expedition-distance',
    'expedition-days',
    'expedition-trips',
    'expedition-map',
    'expedition-sessions',
];

const expeditionSummaryCatalog = [
    {
        id: 'expedition-distance' as DashboardCardId,
        label: 'Total expedition distance',
        detail: 'Tagged sessions, still counted in full totals',
        value: computed(() =>
            formatDistanceKm(
                props.expeditionSummary.distanceKm,
                unitPreferences.value,
            ),
        ),
    },
    {
        id: 'expedition-days' as DashboardCardId,
        label: 'Total expedition days',
        detail: 'Logged days out',
        value: computed(() => String(props.expeditionSummary.daysOut)),
    },
    {
        id: 'expedition-trips' as DashboardCardId,
        label: 'Total multiday trips',
        detail: 'Expedition-tagged sessions',
        value: computed(() => String(props.expeditionSummary.tripCount)),
    },
] as const;

const expeditionCardIds: DashboardCardId[] = [
    'expedition-distance',
    'expedition-days',
    'expedition-trips',
    'expedition-map',
    'expedition-sessions',
];

function sanitizeCardOrder(order: DashboardCardId[]) {
    const seen = new Set<DashboardCardId>();
    const normalized: DashboardCardId[] = [];

    for (const id of order) {
        if (!defaultCardOrder.includes(id) || seen.has(id)) {
            continue;
        }

        normalized.push(id);
        seen.add(id);
    }

    for (const id of defaultCardOrder) {
        if (!seen.has(id)) {
            normalized.push(id);
        }
    }

    return normalized;
}

function sanitizeHiddenCards(hidden: DashboardCardId[]) {
    return hidden.filter(
        (id, index) =>
            defaultCardOrder.includes(id) && hidden.indexOf(id) === index,
    );
}

const cardOrder = ref<DashboardCardId[]>(
    sanitizeCardOrder(props.dashboardPreferences.order),
);
const hiddenCards = ref<DashboardCardId[]>(
    sanitizeHiddenCards(props.dashboardPreferences.hidden),
);

watch(
    () => props.dashboardPreferences,
    (preferences) => {
        cardOrder.value = sanitizeCardOrder(preferences.order);
        hiddenCards.value = sanitizeHiddenCards(preferences.hidden);
    },
    { deep: true },
);

const expeditionSummaryCards = computed(() =>
    expeditionSummaryCatalog
        .filter((card) => !hiddenCards.value.includes(card.id))
        .sort(
            (left, right) =>
                cardOrder.value.indexOf(left.id) - cardOrder.value.indexOf(right.id),
        )
        .map((card) => ({
            id: card.id,
            label: card.label,
            detail: card.detail,
            value: card.value.value,
        })),
);
const hiddenExpeditionSummaryCards = computed(() =>
    expeditionSummaryCatalog.filter((card) => hiddenCards.value.includes(card.id)),
);

const expeditionSessionChips = computed(() =>
    props.expeditionSessionLinks.slice(0, 8),
);
const expeditionMapWarning = computed(() => {
    const count = props.expeditionSummary.missingMapPointCount;

    if (!count) {
        return null;
    }

    return count === 1
        ? '1 expedition session is still missing a track or saved coordinates, so it cannot appear on the world map yet.'
        : `${count} expedition sessions are still missing a track or saved coordinates, so they cannot appear on the world map yet.`;
});

const dashboardLayoutIsDirty = computed(() => {
    const currentOrder = cardOrder.value.join('|');
    const originalOrder = sanitizeCardOrder(props.dashboardPreferences.order).join('|');
    const currentHidden = [...hiddenCards.value].sort().join('|');
    const originalHidden = sanitizeHiddenCards(props.dashboardPreferences.hidden)
        .sort()
        .join('|');

    return currentOrder !== originalOrder || currentHidden !== originalHidden;
});

const routeMapHidden = computed(() => hiddenCards.value.includes('route-map'));
const expeditionMapHidden = computed(() =>
    hiddenCards.value.includes('expedition-map'),
);
const expeditionSessionsHidden = computed(() =>
    hiddenCards.value.includes('expedition-sessions'),
);

function toggleCardVisibility(cardId: DashboardCardId) {
    if (hiddenCards.value.includes(cardId)) {
        hiddenCards.value = hiddenCards.value.filter((id) => id !== cardId);
        return;
    }

    hiddenCards.value = [...hiddenCards.value, cardId];
}

function handleToggleCard(cardId: string) {
    toggleCardVisibility(cardId as DashboardCardId);
}

function resetDashboardLayout() {
    cardOrder.value = [...defaultCardOrder];
    hiddenCards.value = [];
}

function toggleLayoutEditor() {
    isEditingLayout.value = !isEditingLayout.value;

    if (!isEditingLayout.value) {
        clearExpeditionDragState();
    }
}

function moveCardBefore(cardId: DashboardCardId, targetCardId: DashboardCardId) {
    if (cardId === targetCardId) {
        return;
    }

    const next = [...cardOrder.value];
    const fromIndex = next.indexOf(cardId);
    const targetIndex = next.indexOf(targetCardId);

    if (fromIndex === -1 || targetIndex === -1) {
        return;
    }

    next.splice(fromIndex, 1);
    const insertIndex = next.indexOf(targetCardId);
    next.splice(insertIndex, 0, cardId);
    cardOrder.value = next;
}

function handleGroupedCardMove(payload: {
    cardId: string;
    targetCardId: string;
}) {
    moveCardBefore(
        payload.cardId as DashboardCardId,
        payload.targetCardId as DashboardCardId,
    );
}

function clearExpeditionDragState() {
    draggingExpeditionCardId.value = null;
    dropTargetExpeditionCardId.value = null;
}

function handleExpeditionCardDragStart(cardId: DashboardCardId) {
    if (!isEditingLayout.value) {
        return;
    }

    draggingExpeditionCardId.value = cardId;
    dropTargetExpeditionCardId.value = cardId;
}

function handleExpeditionCardDragOver(cardId: DashboardCardId) {
    if (!isEditingLayout.value || draggingExpeditionCardId.value === null) {
        return;
    }

    dropTargetExpeditionCardId.value = cardId;
}

function handleExpeditionCardDrop(cardId: DashboardCardId) {
    if (!isEditingLayout.value || draggingExpeditionCardId.value === null) {
        return;
    }

    moveCardBefore(draggingExpeditionCardId.value, cardId);
    clearExpeditionDragState();
}

function expeditionCardShellClasses(cardId: DashboardCardId) {
    if (!isEditingLayout.value) {
        return '';
    }

    return [
        'rounded-[28px] border border-dashed border-[rgba(103,114,255,0.22)] bg-[rgba(255,255,255,0.32)] p-2',
        draggingExpeditionCardId.value === cardId ? 'opacity-60' : '',
        dropTargetExpeditionCardId.value === cardId
            ? 'ring-2 ring-[rgba(103,114,255,0.28)] ring-offset-2 ring-offset-transparent'
            : '',
    ].join(' ');
}

function saveDashboardLayout() {
    if (!dashboardLayoutIsDirty.value) {
        return;
    }

    isSavingLayout.value = true;

    router.put(
        '/dashboard/preferences',
        {
            order: cardOrder.value,
            hidden: hiddenCards.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onSuccess: () => {
                isEditingLayout.value = false;
            },
            onFinish: () => {
                isSavingLayout.value = false;
            },
        },
    );
}

watch(
    isEditingLayout,
    (editing) => {
        setPageHeaderActions([
            {
                id: 'dashboard-edit-layout',
                label: editing ? 'Done editing' : 'Edit layout',
                active: editing,
                onClick: toggleLayoutEditor,
            },
        ]);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    clearPageHeaderActions();
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-4 sm:gap-5">
        <section v-if="successMessage" class="journal-banner">
            {{ successMessage }}
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <button
                    v-if="isEditingLayout"
                    type="button"
                    class="journal-chip inline-flex items-center gap-1 transition hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)] disabled:opacity-45"
                    :disabled="isSavingLayout"
                    @click="resetDashboardLayout"
                >
                    <RotateCcw class="h-3.5 w-3.5" />
                    Reset
                </button>
                <button
                    v-if="isEditingLayout"
                    type="button"
                    class="inline-flex items-center justify-center rounded-full bg-[color:var(--journal-primary)] px-4 py-2.5 text-sm font-semibold text-white transition hover:-translate-y-px disabled:opacity-45"
                    :disabled="!dashboardLayoutIsDirty || isSavingLayout"
                    @click="saveDashboardLayout"
                >
                    {{ isSavingLayout ? 'Saving…' : 'Save layout' }}
                </button>
            </div>
        </div>

        <HeadlineMetricCards
            :headline="headline"
            :sea-state="seaState"
            :monthly-distance="monthlyDistance"
            :editable="isEditingLayout"
            :card-order="cardOrder"
            :hidden-card-ids="hiddenCards"
            context="private"
            @toggle-card="handleToggleCard"
            @move-card-before="handleGroupedCardMove"
        />

        <SeaStatePanels
            :sea-state="seaState"
            :year-snapshots="yearSnapshots"
            :monthly-distance="monthlyDistance"
            :editable="isEditingLayout"
            :card-order="cardOrder"
            :hidden-card-ids="hiddenCards"
            compare-chip="Distance"
            @toggle-card="handleToggleCard"
            @move-card-before="handleGroupedCardMove"
        />

        <div v-if="isEditingLayout && routeMapHidden" class="flex flex-wrap gap-2">
            <button
                type="button"
                class="journal-chip transition hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                @click="toggleCardVisibility('route-map')"
            >
                Show Route map
            </button>
        </div>

        <section
            v-if="!routeMapHidden"
            class="journal-panel px-4 py-4 sm:px-5 sm:py-5 md:px-6"
        >
            <div
                v-if="isEditingLayout"
                class="mb-3 flex items-center justify-between gap-2 rounded-full border border-[rgba(103,114,255,0.12)] bg-white/88 px-2.5 py-1.5"
            >
                <div class="text-xs font-medium text-[color:var(--journal-muted)]">
                    Route map
                </div>
                <button
                    type="button"
                    class="journal-chip !px-2.5 !py-1 text-[10px]"
                    @click="toggleCardVisibility('route-map')"
                >
                    Hide
                </button>
            </div>

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="journal-kicker">Map</p>
                    <h3 class="mt-2 text-[1.55rem] leading-none sm:text-[1.8rem]">
                        Route map
                    </h3>
                </div>
                <span class="journal-chip">{{ mapData.routes.length }} routes</span>
            </div>

            <div class="mt-6">
                <RouteAtlasMap
                    :routes="mapData.routes"
                    :pins="mapData.pins"
                    :default-view="mapData.defaultView"
                    :storage-key="`${profile.slug}-route-atlas`"
                    :show-filters="false"
                    height-class="h-[320px] sm:h-[420px] lg:h-[560px]"
                />
            </div>
        </section>

        <section class="journal-panel px-4 py-4 sm:px-5 sm:py-5 md:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="mt-2 text-[1.55rem] leading-none sm:text-[1.8rem]">
                        Expeditions and multiday
                    </h3>
                </div>
            </div>

            <div
                v-if="isEditingLayout && hiddenExpeditionSummaryCards.length"
                class="mt-5 flex flex-wrap gap-2"
            >
                <button
                    v-for="card in hiddenExpeditionSummaryCards"
                    :key="card.id"
                    type="button"
                    class="journal-chip transition hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                    @click="toggleCardVisibility(card.id)"
                >
                    Show {{ card.label }}
                </button>
                <button
                    v-if="expeditionMapHidden"
                    type="button"
                    class="journal-chip transition hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                    @click="toggleCardVisibility('expedition-map')"
                >
                    Show Expedition map
                </button>
                <button
                    v-if="expeditionSessionsHidden"
                    type="button"
                    class="journal-chip transition hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                    @click="toggleCardVisibility('expedition-sessions')"
                >
                    Show Session chips
                </button>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 md:mt-6 lg:grid-cols-3">
                <article
                    v-for="card in expeditionSummaryCards"
                    :key="card.id"
                    :class="[
                        'journal-surface-shell rounded-[24px] px-4 py-4',
                        expeditionCardShellClasses(card.id),
                    ]"
                    :draggable="isEditingLayout"
                    @dragstart="handleExpeditionCardDragStart(card.id)"
                    @dragover.prevent="handleExpeditionCardDragOver(card.id)"
                    @drop.prevent="handleExpeditionCardDrop(card.id)"
                    @dragend="clearExpeditionDragState"
                >
                    <div
                        v-if="isEditingLayout"
                        class="mb-3 flex items-center justify-between gap-2 rounded-full border border-[rgba(103,114,255,0.12)] bg-white/88 px-2.5 py-1.5"
                    >
                        <div class="inline-flex items-center gap-1.5 text-xs font-medium text-[color:var(--journal-muted)]">
                            <GripVertical class="h-3.5 w-3.5 text-[color:var(--journal-faint)]" />
                            Move
                        </div>
                        <button
                            type="button"
                            class="journal-chip !px-2.5 !py-1 text-[10px]"
                            @click="toggleCardVisibility(card.id)"
                        >
                            Hide
                        </button>
                    </div>

                    <p class="journal-kicker">{{ card.label }}</p>
                    <p class="mt-3 text-3xl font-semibold text-[color:var(--journal-text)]">
                        {{ card.value }}
                    </p>
                    <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                        {{ card.detail }}
                    </p>
                </article>
            </div>

            <section
                v-if="expeditionMapWarning"
                class="journal-banner journal-banner--danger mt-6"
            >
                {{ expeditionMapWarning }}
            </section>

            <div v-if="!expeditionMapHidden" class="mt-6">
                <div
                    v-if="isEditingLayout"
                    class="mb-3 flex items-center justify-between gap-2 rounded-full border border-[rgba(103,114,255,0.12)] bg-white/88 px-2.5 py-1.5"
                >
                    <div class="text-xs font-medium text-[color:var(--journal-muted)]">
                        Expedition map
                    </div>
                    <button
                        type="button"
                        class="journal-chip !px-2.5 !py-1 text-[10px]"
                        @click="toggleCardVisibility('expedition-map')"
                    >
                        Hide
                    </button>
                </div>

                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h4
                            class="mt-2 text-[1.3rem] leading-none text-[color:var(--journal-text)] sm:text-[1.45rem]"
                        >
                            I paddled here
                        </h4>
                    </div>
                    <span class="text-sm font-medium text-[color:var(--journal-muted)]">
                        {{ expeditionMapData.pins.length }} places
                    </span>
                </div>

                <RouteAtlasMap
                    :routes="expeditionMapData.routes"
                    :pins="expeditionMapData.pins"
                    :default-view="expeditionMapData.defaultView"
                    :storage-key="`${profile.slug}-expedition-footprint`"
                    pin-presentation="expedition"
                    :auto-fit-to-geometry="false"
                    :show-legend="false"
                    :show-filters="false"
                    :show-kind-filter="false"
                    :show-geometry-filter="false"
                    empty-message="No paddled locations logged yet."
                    height-class="h-[280px] sm:h-[360px] lg:h-[440px]"
                />
            </div>

            <div
                v-if="!expeditionSessionsHidden && expeditionSessionChips.length"
                class="mt-5"
            >
                <div
                    v-if="isEditingLayout"
                    class="mb-3 flex items-center justify-between gap-2 rounded-full border border-[rgba(103,114,255,0.12)] bg-white/88 px-2.5 py-1.5"
                >
                    <div class="text-xs font-medium text-[color:var(--journal-muted)]">
                        Session chips
                    </div>
                    <button
                        type="button"
                        class="journal-chip !px-2.5 !py-1 text-[10px]"
                        @click="toggleCardVisibility('expedition-sessions')"
                    >
                        Hide
                    </button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="session in expeditionSessionChips"
                        :key="session.id"
                        :href="session.path"
                        class="journal-chip transition hover:-translate-y-0.5 hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)]"
                    >
                        {{ session.label }}
                    </Link>
                </div>
            </div>
        </section>
    </div>
</template>
