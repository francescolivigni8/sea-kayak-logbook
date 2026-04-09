<script setup lang="ts">
import L from 'leaflet';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type MapTarget = 'launch' | 'landing';

interface DefaultView {
    lat: number;
    lng: number;
    zoom: number;
}

const props = withDefaults(defineProps<{
    launchLat?: number | null;
    launchLng?: number | null;
    landingLat?: number | null;
    landingLng?: number | null;
    defaultView?: DefaultView;
}>(), {
    launchLat: null,
    launchLng: null,
    landingLat: null,
    landingLng: null,
    defaultView: () => ({
        lat: 64.1466,
        lng: -21.9426,
        zoom: 10,
    }),
});

const emit = defineEmits<{
    'update:launchLat': [value: string];
    'update:launchLng': [value: string];
    'update:landingLat': [value: string];
    'update:landingLng': [value: string];
}>();

const mapElement = ref<HTMLElement | null>(null);
const activeTarget = ref<MapTarget>('launch');

let map: L.Map | null = null;
let markerLayer: L.LayerGroup | null = null;
let lineLayer: L.Polyline | null = null;
let baseLayer: L.TileLayer | null = null;

const launchPoint = computed(() => {
    if (props.launchLat === null || props.launchLng === null) {
        return null;
    }

    return {
        lat: props.launchLat,
        lng: props.launchLng,
    };
});

const landingPoint = computed(() => {
    if (props.landingLat === null || props.landingLng === null) {
        return null;
    }

    return {
        lat: props.landingLat,
        lng: props.landingLng,
    };
});

function buildBaseLayer() {
    return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: 'Map data © OpenStreetMap contributors',
    });
}

function fitToPoints() {
    if (!map) {
        return;
    }

    const points = [launchPoint.value, landingPoint.value].filter((point): point is { lat: number; lng: number } => point !== null);

    if (!points.length) {
        map.setView([props.defaultView.lat, props.defaultView.lng], props.defaultView.zoom);
        return;
    }

    if (points.length === 1) {
        map.setView([points[0].lat, points[0].lng], 12);
        return;
    }

    const bounds = L.latLngBounds(points.map((point) => [point.lat, point.lng] as [number, number]));
    map.fitBounds(bounds.pad(0.18));
}

function renderMarkers() {
    if (!map || !markerLayer) {
        return;
    }

    markerLayer.clearLayers();

    if (lineLayer) {
        map.removeLayer(lineLayer);
        lineLayer = null;
    }

    if (launchPoint.value) {
        L.circleMarker([launchPoint.value.lat, launchPoint.value.lng], {
            radius: 8,
            color: '#ffffff',
            weight: 2,
            fillColor: '#10b981',
            fillOpacity: 1,
        })
            .bindTooltip('Launch')
            .addTo(markerLayer);
    }

    if (landingPoint.value) {
        L.circleMarker([landingPoint.value.lat, landingPoint.value.lng], {
            radius: 8,
            color: '#ffffff',
            weight: 2,
            fillColor: '#f97316',
            fillOpacity: 1,
        })
            .bindTooltip('Landing')
            .addTo(markerLayer);
    }

    if (launchPoint.value && landingPoint.value) {
        lineLayer = L.polyline(
            [
                [launchPoint.value.lat, launchPoint.value.lng],
                [landingPoint.value.lat, landingPoint.value.lng],
            ],
            {
                color: '#6772ff',
                weight: 3,
                opacity: 0.9,
                dashArray: '8 8',
            },
        ).addTo(map);
    }

    fitToPoints();
}

function updateTargetPoint(lat: number, lng: number) {
    const formattedLat = lat.toFixed(6);
    const formattedLng = lng.toFixed(6);

    if (activeTarget.value === 'launch') {
        emit('update:launchLat', formattedLat);
        emit('update:launchLng', formattedLng);
        return;
    }

    emit('update:landingLat', formattedLat);
    emit('update:landingLng', formattedLng);
}

async function initializeMap() {
    await nextTick();

    if (!mapElement.value || map) {
        return;
    }

    map = L.map(mapElement.value, {
        zoomControl: true,
        scrollWheelZoom: false,
    });

    markerLayer = L.layerGroup().addTo(map);
    baseLayer = buildBaseLayer();
    baseLayer.addTo(map);

    map.on('click', (event: L.LeafletMouseEvent) => {
        updateTargetPoint(event.latlng.lat, event.latlng.lng);
    });

    fitToPoints();
    renderMarkers();
}

watch(
    () => [props.launchLat, props.launchLng, props.landingLat, props.landingLng],
    () => {
        renderMarkers();
    },
    { deep: true },
);

onMounted(() => {
    initializeMap();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <section class="rounded-[24px] border border-[color:var(--journal-line)] bg-white/72 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div class="space-y-2">
                <p class="journal-kicker">Geolocation</p>
                <h4 class="text-[1.35rem] leading-none text-[color:var(--journal-text)]">Place the session</h4>
                <p class="text-sm leading-6 text-[color:var(--journal-muted)]">
                    Click the map to set the
                    <strong class="text-[color:var(--journal-text)]">{{ activeTarget }}</strong>
                    point. A launch pin alone is enough to show the session on the atlas.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="journal-utility-link"
                    :class="activeTarget === 'launch' ? 'journal-chip--primary' : ''"
                    @click="activeTarget = 'launch'"
                >
                    Place launch
                </button>
                <button
                    type="button"
                    class="journal-utility-link"
                    :class="activeTarget === 'landing' ? 'journal-chip--primary' : ''"
                    @click="activeTarget = 'landing'"
                >
                    Place landing
                </button>
            </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-[20px] border border-[color:var(--journal-line)] bg-white/78 shadow-[inset_0_1px_0_rgba(255,255,255,0.72)]">
            <div ref="mapElement" class="h-[320px]" />
        </div>

        <div class="mt-4 flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]">
            <span class="journal-chip">Launch = green</span>
            <span class="journal-chip">Landing = orange</span>
            <span class="journal-chip">Click map to update current target</span>
        </div>
    </section>
</template>
