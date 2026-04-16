<script setup lang="ts">
import L from 'leaflet';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{
    lat: string;
    lng: string;
    zoom: string;
    latName?: string;
    lngName?: string;
    zoomName?: string;
    saved?: boolean;
    errors?: {
        lat?: string;
        lng?: string;
        zoom?: string;
    };
}>(), {
    latName: 'default_map_lat',
    lngName: 'default_map_lng',
    zoomName: 'default_map_zoom',
    saved: false,
    errors: () => ({}),
});

const emit = defineEmits<{
    'update:lat': [value: string];
    'update:lng': [value: string];
    'update:zoom': [value: string];
}>();

const fallbackView = {
    lat: 64.167,
    lng: -21.821,
    zoom: 10,
};

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let marker: L.Marker | null = null;

const normalizedLat = computed(() => parseCoordinate(props.lat, fallbackView.lat, -90, 90).toFixed(6));
const normalizedLng = computed(() => parseCoordinate(props.lng, fallbackView.lng, -180, 180).toFixed(6));
const normalizedZoom = computed(() => String(parseZoom(props.zoom)));

function parseCoordinate(value: string, fallback: number, min: number, max: number): number {
    const parsed = Number(value);

    if (!Number.isFinite(parsed)) {
        return fallback;
    }

    return Math.min(max, Math.max(min, parsed));
}

function parseZoom(value: string): number {
    const parsed = Number(value);

    if (!Number.isFinite(parsed)) {
        return fallbackView.zoom;
    }

    return Math.min(16, Math.max(2, Math.round(parsed)));
}

function currentView() {
    return {
        lat: Number(normalizedLat.value),
        lng: Number(normalizedLng.value),
        zoom: Number(normalizedZoom.value),
    };
}

function baseTileLayer() {
    return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: 'Map data © OpenStreetMap contributors',
    });
}

function mapPinIcon() {
    return L.divIcon({
        className: 'journal-map-pin-wrapper',
        html: `
            <div class="journal-map-pin journal-map-pin--minimal" style="--pin-color: var(--journal-sea)">
                <span class="journal-map-pin__core">
                    <span class="journal-map-pin__dot"></span>
                </span>
            </div>
        `,
        iconSize: [28, 38],
        iconAnchor: [14, 34],
    });
}

function emitPoint(lat: number, lng: number) {
    emit('update:lat', lat.toFixed(6));
    emit('update:lng', lng.toFixed(6));
}

function moveMarker(lat: number, lng: number) {
    if (!map) {
        return;
    }

    if (!marker) {
        marker = L.marker([lat, lng], {
            draggable: true,
            icon: mapPinIcon(),
        })
            .bindTooltip('Default map start')
            .addTo(map);

        marker.on('dragend', () => {
            if (!marker) {
                return;
            }

            const point = marker.getLatLng();
            emitPoint(point.lat, point.lng);
        });

        return;
    }

    marker.setLatLng([lat, lng]);
}

function placePin(lat: number, lng: number) {
    emitPoint(lat, lng);
    moveMarker(lat, lng);
}

function placeAtMapCenter() {
    if (!map) {
        return;
    }

    const center = map.getCenter();
    placePin(center.lat, center.lng);
}

function syncMapPointToValues() {
    if (!map) {
        return;
    }

    const view = currentView();
    moveMarker(view.lat, view.lng);

    const center = map.getCenter();
    const centerChanged = Math.abs(center.lat - view.lat) > 0.00001 || Math.abs(center.lng - view.lng) > 0.00001;

    if (centerChanged) {
        map.setView([view.lat, view.lng], map.getZoom());
    }
}

function syncMapZoomToValue() {
    if (!map) {
        return;
    }

    const zoom = Number(normalizedZoom.value);

    if (map.getZoom() !== zoom) {
        map.setZoom(zoom);
    }
}

async function initializeMap() {
    await nextTick();

    if (!mapElement.value || map) {
        return;
    }

    const view = currentView();

    map = L.map(mapElement.value, {
        center: [view.lat, view.lng],
        zoom: view.zoom,
        zoomControl: true,
        scrollWheelZoom: false,
    });

    baseTileLayer().addTo(map);
    moveMarker(view.lat, view.lng);

    map.on('click', (event: L.LeafletMouseEvent) => {
        placePin(event.latlng.lat, event.latlng.lng);
    });

    map.on('zoomend', () => {
        if (!map) {
            return;
        }

        emit('update:zoom', String(parseZoom(String(map.getZoom()))));
    });

    window.setTimeout(() => map?.invalidateSize(), 120);
}

watch(
    () => [props.lat, props.lng],
    () => syncMapPointToValues(),
);

watch(
    () => props.zoom,
    () => syncMapZoomToValue(),
);

onMounted(() => {
    initializeMap();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    marker = null;
});
</script>

<template>
    <div class="space-y-4">
        <input type="hidden" :name="latName" :value="normalizedLat" />
        <input type="hidden" :name="lngName" :value="normalizedLng" />
        <input type="hidden" :name="zoomName" :value="normalizedZoom" />

        <div class="overflow-hidden rounded-[20px] border border-[color:var(--journal-line)] bg-white/78 shadow-[inset_0_1px_0_rgba(255,255,255,0.72)]">
            <div ref="mapElement" class="h-[280px] sm:h-[340px]" />
        </div>

        <div class="grid gap-3 md:grid-cols-[1fr_1fr_0.55fr]">
            <div>
                <span class="journal-field-label">Latitude</span>
                <div class="journal-input mt-2 bg-white/62 font-mono text-sm">{{ normalizedLat }}</div>
                <p v-if="errors.lat" class="mt-2 text-sm text-rose-600">{{ errors.lat }}</p>
            </div>

            <div>
                <span class="journal-field-label">Longitude</span>
                <div class="journal-input mt-2 bg-white/62 font-mono text-sm">{{ normalizedLng }}</div>
                <p v-if="errors.lng" class="mt-2 text-sm text-rose-600">{{ errors.lng }}</p>
            </div>

            <div>
                <span class="journal-field-label">Zoom</span>
                <div class="journal-input mt-2 bg-white/62 font-mono text-sm">{{ normalizedZoom }}</div>
                <p v-if="errors.zoom" class="mt-2 text-sm text-rose-600">{{ errors.zoom }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button class="journal-utility-link" type="button" @click="placeAtMapCenter">
                Move pin to map centre
            </button>
            <button class="journal-primary-link" type="submit">
                Save map view
            </button>
            <span v-if="saved" class="journal-banner journal-banner--soft px-3 py-2 text-sm">
                Map view saved.
            </span>
            <span class="journal-chip">Click or drag the pin</span>
            <span class="journal-chip">Zoom is saved too</span>
        </div>
    </div>
</template>
