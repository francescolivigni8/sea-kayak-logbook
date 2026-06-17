<script setup lang="ts">
import L from 'leaflet';
import { Maximize2, Minimize2 } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { useMapTileStyles } from '@/lib/mapTiles';
import type { MapStyleKey } from '@/lib/mapTiles';

interface LatLngPoint {
    lat: number;
    lng: number;
}

interface AtlasRouteItem {
    id: number | string;
    label: string;
    color: string;
    points: LatLngPoint[];
    path?: string | null;
    year?: number | null;
    years?: number[];
    isExpedition?: boolean;
    category?: string | null;
}

interface AtlasPinItem {
    id: number | string;
    label: string;
    color: string;
    lat: number;
    lng: number;
    path?: string | null;
    count?: number;
    countsByYear?: Record<string, number>;
    year?: number | null;
    years?: number[];
    isExpedition?: boolean;
    category?: string | null;
}

interface DefaultView {
    lat: number;
    lng: number;
    zoom: number;
}

type MapStyle = MapStyleKey;
type SessionKind = 'all' | 'day' | 'expedition';
type GeometryKind = 'all' | 'routes' | 'pins';
type PinPresentation = 'dot' | 'pin' | 'expedition';

const props = withDefaults(
    defineProps<{
        routes?: AtlasRouteItem[];
        pins?: AtlasPinItem[];
        defaultView?: DefaultView;
        heightClass?: string;
        showLegend?: boolean;
        showFilters?: boolean;
        showGeometryFilter?: boolean;
        showKindFilter?: boolean;
        allowPinView?: boolean;
        storageKey?: string | null;
        emptyMessage?: string;
        pinPresentation?: PinPresentation;
        autoFitToGeometry?: boolean;
        allowFullscreen?: boolean;
    }>(),
    {
        routes: () => [],
        pins: () => [],
        defaultView: () => ({
            lat: 64.1466,
            lng: -21.9426,
            zoom: 10,
        }),
        heightClass: 'h-[420px]',
        showLegend: true,
        showFilters: true,
        showGeometryFilter: true,
        showKindFilter: true,
        allowPinView: true,
        storageKey: null,
        emptyMessage:
            'No mapped geometry yet. Attach GPX files or add launch coordinates to start building the route atlas.',
        pinPresentation: 'dot',
        autoFitToGeometry: true,
        allowFullscreen: true,
    },
);

const mapShell = ref<HTMLElement | null>(null);
const mapElement = ref<HTMLElement | null>(null);
const selectedStyle = ref<MapStyle>('chart');
const selectedYear = ref<string>('all');
const selectedKind = ref<SessionKind>('all');
const selectedGeometry = ref<GeometryKind>('all');
const pinnedView = ref<DefaultView | null>(null);
const pinFeedback = ref<'idle' | 'saved'>('idle');
const isFullscreen = ref(false);
const mapTileStyles = useMapTileStyles();

let map: L.Map | null = null;
let routeLayerGroup: L.LayerGroup | null = null;
let pinLayerGroup: L.LayerGroup | null = null;
let currentBaseLayer: L.TileLayer | null = null;
let pinFeedbackTimeout: number | null = null;
let initialViewportApplied = false;
let mapResizeObserver: ResizeObserver | null = null;
let previousBodyOverflow: string | null = null;

const styleOptions = computed(() => mapTileStyles.value);

const storageKeys = computed(() => {
    if (!props.storageKey) {
        return null;
    }

    return {
        style: `${props.storageKey}:style`,
        year: `${props.storageKey}:year`,
        kind: `${props.storageKey}:kind`,
        geometry: `${props.storageKey}:geometry`,
        pinnedView: `${props.storageKey}:pinned-view`,
    };
});

const yearOptions = computed(() => {
    const years = new Set<number>();

    props.routes.forEach((route) => {
        if (route.year) {
            years.add(route.year);
        }

        route.years?.forEach((year) => years.add(year));
    });

    props.pins.forEach((pin) => {
        if (pin.year) {
            years.add(pin.year);
        }

        pin.years?.forEach((year) => years.add(year));
    });

    return Array.from(years).sort((left, right) => right - left);
});

function itemMatchesYear(
    item: { year?: number | null; years?: number[] },
    selected: string,
): boolean {
    if (selected === 'all') {
        return true;
    }

    const targetYear = Number(selected);

    if (!Number.isFinite(targetYear)) {
        return true;
    }

    if (item.year === targetYear) {
        return true;
    }

    return Array.isArray(item.years) ? item.years.includes(targetYear) : false;
}

function itemMatchesKind(
    item: { isExpedition?: boolean },
    selected: SessionKind,
): boolean {
    if (selected === 'all') {
        return true;
    }

    if (selected === 'expedition') {
        return Boolean(item.isExpedition);
    }

    return !item.isExpedition;
}

function pinCountForSelection(pin: AtlasPinItem): number {
    if (selectedYear.value === 'all') {
        return pin.count ?? 1;
    }

    const byYear = pin.countsByYear ?? {};

    if (byYear[selectedYear.value] !== undefined) {
        return byYear[selectedYear.value];
    }

    return pin.year === Number(selectedYear.value) ? (pin.count ?? 1) : 0;
}

const filteredRoutes = computed(() => {
    const geometry = props.showFilters ? selectedGeometry.value : 'all';
    const year = props.showFilters ? selectedYear.value : 'all';
    const kind =
        props.showFilters && props.showKindFilter ? selectedKind.value : 'all';

    if (geometry === 'pins') {
        return [];
    }

    return props.routes.filter(
        (route) =>
            itemMatchesYear(route, year) && itemMatchesKind(route, kind),
    );
});

const filteredPins = computed(() => {
    const geometry = props.showFilters ? selectedGeometry.value : 'all';
    const year = props.showFilters ? selectedYear.value : 'all';
    const kind =
        props.showFilters && props.showKindFilter ? selectedKind.value : 'all';

    if (geometry === 'routes') {
        return [];
    }

    return props.pins.filter(
        (pin) => itemMatchesYear(pin, year) && itemMatchesKind(pin, kind),
    );
});

const hasGeometry = computed(
    () => filteredRoutes.value.length > 0 || filteredPins.value.length > 0,
);

const legendRoutes = computed(() => filteredRoutes.value.slice(0, 10));
const mapShellStyle = computed(() =>
    isFullscreen.value
        ? {
              position: 'fixed',
              inset: '0',
              zIndex: '99999',
              width: '100vw',
              height: '100dvh',
              backgroundColor: '#ffffff',
          }
        : undefined,
);

function createTileLayer(style: MapStyle) {
    const config = styleOptions.value[style];

    return L.tileLayer(config.url, {
        maxZoom: config.max_zoom ?? 18,
        attribution: config.attribution,
    });
}

function persistState(
    key: 'style' | 'year' | 'kind' | 'geometry',
    value: string,
) {
    if (!storageKeys.value || typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(storageKeys.value[key], value);
}

function readPersistedState() {
    if (!storageKeys.value || typeof window === 'undefined') {
        return;
    }

    const persistedStyle = window.localStorage.getItem(
        storageKeys.value.style,
    ) as MapStyle | null;
    const persistedYear = window.localStorage.getItem(storageKeys.value.year);
    const persistedKind = window.localStorage.getItem(
        storageKeys.value.kind,
    ) as SessionKind | null;
    const persistedGeometry = window.localStorage.getItem(
        storageKeys.value.geometry,
    ) as GeometryKind | null;
    const persistedView = window.localStorage.getItem(
        storageKeys.value.pinnedView,
    );

    if (persistedStyle && persistedStyle in styleOptions.value) {
        selectedStyle.value = persistedStyle;
    }

    if (props.showFilters && persistedYear) {
        selectedYear.value = persistedYear;
    }

    if (
        props.showFilters &&
        props.showKindFilter &&
        persistedKind &&
        ['all', 'day', 'expedition'].includes(persistedKind)
    ) {
        selectedKind.value = persistedKind;
    }

    if (
        props.showFilters &&
        props.showGeometryFilter &&
        persistedGeometry &&
        ['all', 'routes', 'pins'].includes(persistedGeometry)
    ) {
        selectedGeometry.value = persistedGeometry;
    }

    if (persistedView) {
        try {
            const parsed = JSON.parse(persistedView) as DefaultView;

            if (
                Number.isFinite(parsed.lat) &&
                Number.isFinite(parsed.lng) &&
                Number.isFinite(parsed.zoom)
            ) {
                pinnedView.value = parsed;
            }
        } catch {
            pinnedView.value = null;
        }
    }
}

function switchStyle(style: MapStyle) {
    selectedStyle.value = style;

    if (!map) {
        return;
    }

    if (currentBaseLayer) {
        map.removeLayer(currentBaseLayer);
    }

    currentBaseLayer = createTileLayer(style);
    currentBaseLayer.addTo(map);
}

function resetGeometry() {
    routeLayerGroup?.clearLayers();
    pinLayerGroup?.clearLayers();
}

function applyFallbackView() {
    if (!map) {
        return;
    }

    const fallback = pinnedView.value ?? props.defaultView;
    map.setView([fallback.lat, fallback.lng], fallback.zoom);
}

function fitMapToGeometry() {
    if (!map) {
        return;
    }

    if (!props.autoFitToGeometry) {
        if (!initialViewportApplied) {
            initialViewportApplied = true;
            applyFallbackView();
        }

        return;
    }

    if (pinnedView.value && !initialViewportApplied) {
        initialViewportApplied = true;
        map.setView(
            [pinnedView.value.lat, pinnedView.value.lng],
            pinnedView.value.zoom,
        );

        return;
    }

    const bounds = L.latLngBounds([]);

    filteredRoutes.value.forEach((route) => {
        route.points.forEach((point) => bounds.extend([point.lat, point.lng]));
    });

    filteredPins.value.forEach((pin) => {
        bounds.extend([pin.lat, pin.lng]);
    });

    if (bounds.isValid()) {
        initialViewportApplied = true;
        map.fitBounds(bounds.pad(0.18));

        return;
    }

    initialViewportApplied = true;
    applyFallbackView();
}

function renderGeometry() {
    if (!map || !routeLayerGroup || !pinLayerGroup) {
        return;
    }

    const routeLayers = routeLayerGroup;
    const pinLayers = pinLayerGroup;

    resetGeometry();

    filteredRoutes.value.forEach((route) => {
        if (!route.points.length) {
            return;
        }

        const latLngs = route.points.map(
            (point) => [point.lat, point.lng] as [number, number],
        );
        const baseWeight = route.isExpedition ? 5 : 4;
        const hoverWeight = route.isExpedition ? 8 : 7;
        const baseOpacity = 0.9;
        const hoverOpacity = 1;

        const polyline = L.polyline(latLngs, {
            className: route.path
                ? 'journal-map-route journal-map-route--interactive'
                : 'journal-map-route',
            color: route.color,
            weight: baseWeight,
            opacity: baseOpacity,
        })
            .bindTooltip(route.label)
            .addTo(routeLayers);

        polyline.on('mouseover', () => {
            polyline.setStyle({
                weight: hoverWeight,
                opacity: hoverOpacity,
            });
            polyline.bringToFront();
        });

        polyline.on('mouseout', () => {
            polyline.setStyle({
                weight: baseWeight,
                opacity: baseOpacity,
            });
        });

        if (route.path && typeof window !== 'undefined') {
            polyline.on('click', () => {
                window.location.assign(route.path as string);
            });
        }

        const startPoint = route.points[0];
        const endPoint = route.points[route.points.length - 1];

        L.circleMarker([startPoint.lat, startPoint.lng], {
            radius: 5,
            color: '#ffffff',
            weight: 2,
            fillColor: '#10b981',
            fillOpacity: 1,
        }).addTo(routeLayers);

        L.circleMarker([endPoint.lat, endPoint.lng], {
            radius: 5,
            color: '#ffffff',
            weight: 2,
            fillColor: '#f97316',
            fillOpacity: 1,
        }).addTo(routeLayers);
    });

    filteredPins.value.forEach((pin) => {
        const count = Math.max(pinCountForSelection(pin), 1);
        const marker =
            props.pinPresentation === 'pin' ||
            props.pinPresentation === 'expedition'
                ? L.marker([pin.lat, pin.lng], {
                      icon: L.divIcon({
                          className: 'journal-map-pin-wrapper',
                          html: `
                        <div class="journal-map-pin journal-map-pin--minimal ${props.pinPresentation === 'expedition' ? 'journal-map-pin--expedition' : ''}" style="--pin-color: ${pin.color}">
                            <span class="journal-map-pin__core">
                                ${
                                    count > 1
                                        ? `<span class="journal-map-pin__count">${count}</span>`
                                        : props.pinPresentation === 'expedition'
                                          ? '<span class="journal-map-pin__glyph">✦</span>'
                                          : '<span class="journal-map-pin__dot"></span>'
                                }
                            </span>
                        </div>
                    `,
                          iconSize: [26, 34],
                          iconAnchor: [13, 33],
                          tooltipAnchor: [0, -24],
                      }),
                  })
                : L.circleMarker([pin.lat, pin.lng], {
                      radius: Math.min(7 + Math.max(count - 1, 0), 14),
                      color: '#ffffff',
                      weight: 2,
                      fillColor: pin.color,
                      fillOpacity: 0.95,
                  });

        const label = count > 1 ? `${pin.label} · ${count} trips` : pin.label;
        marker.bindTooltip(label).addTo(pinLayers);

        if (pin.path && typeof window !== 'undefined') {
            marker.on('click', () => {
                window.location.assign(pin.path as string);
            });
        }
    });

    fitMapToGeometry();
}

function pinCurrentView() {
    if (!map || !storageKeys.value || typeof window === 'undefined') {
        return;
    }

    const center = map.getCenter();
    const payload = {
        lat: Number(center.lat.toFixed(6)),
        lng: Number(center.lng.toFixed(6)),
        zoom: map.getZoom(),
    };

    pinnedView.value = payload;
    window.localStorage.setItem(
        storageKeys.value.pinnedView,
        JSON.stringify(payload),
    );
    pinFeedback.value = 'saved';

    if (pinFeedbackTimeout) {
        window.clearTimeout(pinFeedbackTimeout);
    }

    pinFeedbackTimeout = window.setTimeout(() => {
        pinFeedback.value = 'idle';
    }, 1600);
}

function goToPinnedView() {
    if (!map || !pinnedView.value) {
        return;
    }

    map.setView(
        [pinnedView.value.lat, pinnedView.value.lng],
        pinnedView.value.zoom,
    );
}

function refreshMapSize() {
    if (typeof window === 'undefined') {
        return;
    }

    window.setTimeout(() => map?.invalidateSize(), 80);
    window.setTimeout(() => map?.invalidateSize(), 280);
    window.setTimeout(() => map?.invalidateSize(), 600);
}

async function toggleFullscreen() {
    if (!props.allowFullscreen || typeof document === 'undefined') {
        return;
    }

    isFullscreen.value = !isFullscreen.value;

    if (isFullscreen.value) {
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = previousBodyOverflow ?? '';
        previousBodyOverflow = null;
    }

    await nextTick();
    refreshMapSize();
}

async function exitFullscreenMap() {
    if (!isFullscreen.value || typeof document === 'undefined') {
        return;
    }

    isFullscreen.value = false;
    document.body.style.overflow = previousBodyOverflow ?? '';
    previousBodyOverflow = null;

    await nextTick();
    refreshMapSize();
}

function handleFullscreenKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        exitFullscreenMap();
    }
}

function openPath(path?: string | null) {
    if (!path || typeof window === 'undefined') {
        return;
    }

    window.location.assign(path);
}

async function initializeMap() {
    await nextTick();

    if (!mapElement.value || map) {
        return;
    }

    readPersistedState();

    map = L.map(mapElement.value, {
        zoomControl: true,
        scrollWheelZoom: false,
    });

    routeLayerGroup = L.layerGroup().addTo(map);
    pinLayerGroup = L.layerGroup().addTo(map);

    const startingView = pinnedView.value ?? props.defaultView;
    map.setView([startingView.lat, startingView.lng], startingView.zoom);

    switchStyle(selectedStyle.value);
    renderGeometry();
}

watch(selectedStyle, (value) => {
    persistState('style', value);
});

watch(selectedYear, (value) => {
    persistState('year', value);
});

watch(selectedKind, (value) => {
    persistState('kind', value);
});

watch(selectedGeometry, (value) => {
    persistState('geometry', value);
});

watch(
    yearOptions,
    (years) => {
        if (
            selectedYear.value !== 'all' &&
            !years.includes(Number(selectedYear.value))
        ) {
            selectedYear.value = 'all';
        }
    },
    { immediate: true },
);

watch(
    () => [filteredRoutes.value, filteredPins.value],
    () => {
        renderGeometry();
    },
    { deep: true },
);

watch(
    () => props.defaultView,
    () => {
        if (!map || hasGeometry.value) {
            return;
        }

        applyFallbackView();
    },
    { deep: true },
);

onMounted(() => {
    initializeMap();
    document.addEventListener('keydown', handleFullscreenKeydown);

    if (typeof ResizeObserver !== 'undefined' && mapShell.value) {
        mapResizeObserver = new ResizeObserver(() => refreshMapSize());
        mapResizeObserver.observe(mapShell.value);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined' && pinFeedbackTimeout) {
        window.clearTimeout(pinFeedbackTimeout);
    }

    document.removeEventListener('keydown', handleFullscreenKeydown);
    if (isFullscreen.value && typeof document !== 'undefined') {
        document.body.style.overflow = previousBodyOverflow ?? '';
    }
    mapResizeObserver?.disconnect();
    mapResizeObserver = null;
    map?.remove();
    map = null;
});
</script>

<template>
    <div class="space-y-3 sm:space-y-4">
        <div
            class="flex flex-col gap-2.5 lg:flex-row lg:items-end lg:justify-between"
        >
            <div
                class="flex items-center gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:pb-0 [&::-webkit-scrollbar]:hidden"
            >
                <button
                    v-for="(style, key) in styleOptions"
                    :key="key"
                    type="button"
                    class="shrink-0 rounded-full border px-2.5 py-1.5 text-[11px] font-medium transition sm:px-3 sm:text-xs"
                    :class="
                        selectedStyle === key
                            ? 'border-[color:var(--journal-line-strong)] bg-[rgba(103,114,255,0.14)] text-[color:var(--journal-text)]'
                            : 'border-[color:var(--journal-line)] bg-white/85 text-[color:var(--journal-muted)] hover:border-[color:var(--journal-line-strong)]'
                    "
                    @click="switchStyle(key)"
                >
                    {{ style.label }}
                </button>

                <button
                    v-if="allowPinView"
                    type="button"
                    class="shrink-0 rounded-full border px-2.5 py-1.5 text-[11px] font-medium transition sm:px-3 sm:text-xs"
                    :class="
                        pinFeedback === 'saved'
                            ? 'border-[rgba(137,223,171,0.5)] bg-[rgba(241,255,245,0.9)] text-[#256a48]'
                            : 'border-[color:var(--journal-line)] bg-white/85 text-[color:var(--journal-muted)] hover:border-[color:var(--journal-line-strong)]'
                    "
                    @click="pinCurrentView"
                >
                    {{
                        pinFeedback === 'saved'
                            ? 'Pinned view saved'
                            : 'Pin current view'
                    }}
                </button>

                <button
                    v-if="allowPinView"
                    type="button"
                    class="shrink-0 rounded-full border border-[color:var(--journal-line)] bg-white/85 px-2.5 py-1.5 text-[11px] font-medium text-[color:var(--journal-muted)] transition hover:border-[color:var(--journal-line-strong)] disabled:cursor-not-allowed disabled:opacity-50 sm:px-3 sm:text-xs"
                    :disabled="!pinnedView"
                    @click="goToPinnedView"
                >
                    Go to default
                </button>
            </div>

            <div
                v-if="showFilters"
                class="flex items-center gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:pb-0 [&::-webkit-scrollbar]:hidden"
            >
                <select
                    v-model="selectedYear"
                    class="shrink-0 rounded-full border border-[color:var(--journal-line)] bg-white/85 px-2.5 py-1.5 text-[11px] font-medium text-[color:var(--journal-muted)] sm:px-3 sm:text-xs"
                >
                    <option value="all">All years</option>
                    <option
                        v-for="year in yearOptions"
                        :key="year"
                        :value="String(year)"
                    >
                        {{ year }}
                    </option>
                </select>

                <select
                    v-if="showKindFilter"
                    v-model="selectedKind"
                    class="shrink-0 rounded-full border border-[color:var(--journal-line)] bg-white/85 px-2.5 py-1.5 text-[11px] font-medium text-[color:var(--journal-muted)] sm:px-3 sm:text-xs"
                >
                    <option value="all">All sessions</option>
                    <option value="day">Day sessions</option>
                    <option value="expedition">Expeditions</option>
                </select>

                <select
                    v-if="showGeometryFilter"
                    v-model="selectedGeometry"
                    class="shrink-0 rounded-full border border-[color:var(--journal-line)] bg-white/85 px-2.5 py-1.5 text-[11px] font-medium text-[color:var(--journal-muted)] sm:px-3 sm:text-xs"
                >
                    <option value="all">Routes and pins</option>
                    <option value="routes">Routes only</option>
                    <option value="pins">Pins only</option>
                </select>
            </div>
        </div>

        <div
            ref="mapShell"
            class="overflow-hidden rounded-[1.35rem] border border-[color:var(--journal-line)] bg-white/78 shadow-[inset_0_1px_0_rgba(255,255,255,0.72)] sm:rounded-[1.7rem]"
            :class="
                isFullscreen
                    ? 'rounded-none border-0 bg-white shadow-none'
                    : 'relative'
            "
            :style="mapShellStyle"
        >
            <div
                ref="mapElement"
                :class="isFullscreen ? 'h-full w-full' : props.heightClass"
            />
            <button
                v-if="allowFullscreen"
                type="button"
                class="absolute top-3 right-3 z-[500] inline-flex size-10 items-center justify-center rounded-full border border-[color:var(--journal-line)] bg-white/92 text-[color:var(--journal-text)] shadow-[0_10px_30px_rgba(15,23,42,0.16)] backdrop-blur transition hover:-translate-y-0.5 hover:border-[color:var(--journal-line-strong)]"
                :aria-label="isFullscreen ? 'Exit fullscreen map' : 'Open fullscreen map'"
                :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
                @click="toggleFullscreen"
            >
                <Minimize2 v-if="isFullscreen" class="size-4" />
                <Maximize2 v-else class="size-4" />
            </button>
        </div>

        <div
            v-if="allowPinView && (pinnedView || pinFeedback === 'saved')"
            class="flex flex-wrap items-center justify-end gap-2 text-xs text-[color:var(--journal-muted)]"
        >
            <span>{{
                pinFeedback === 'saved'
                    ? 'Pinned view saved.'
                    : 'Pinned view ready.'
            }}</span>
        </div>

        <div
            v-if="!hasGeometry"
            class="rounded-[1.15rem] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-4 py-4 text-sm text-[color:var(--journal-muted)]"
        >
            {{ emptyMessage }}
        </div>

        <div
            v-if="props.showLegend && legendRoutes.length"
            class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:pb-0 [&::-webkit-scrollbar]:hidden"
        >
            <button
                v-for="route in legendRoutes"
                :key="route.id"
                type="button"
                class="inline-flex shrink-0 items-center gap-2 rounded-full border border-[color:var(--journal-line)] bg-white/85 px-3 py-1 text-xs text-[color:var(--journal-muted)] transition hover:-translate-y-0.5 hover:border-[color:var(--journal-line-strong)] hover:text-[color:var(--journal-text)] disabled:cursor-default disabled:hover:translate-y-0 disabled:hover:border-[color:var(--journal-line)] disabled:hover:text-[color:var(--journal-muted)]"
                :disabled="!route.path"
                @click="openPath(route.path)"
            >
                <span
                    class="h-2.5 w-2.5 rounded-full"
                    :style="{ backgroundColor: route.color }"
                />
                {{ route.label }}
            </button>
        </div>
    </div>
</template>
