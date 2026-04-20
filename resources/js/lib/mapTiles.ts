import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export type MapStyleKey = 'chart' | 'clean' | 'activity';

export interface MapTileStyle {
    label: string;
    url: string;
    attribution: string;
    max_zoom?: number;
}

type SharedIntegrations = {
    maps?: {
        provider?: string;
        styles?: Partial<Record<MapStyleKey, MapTileStyle>>;
    };
};

const fallbackStyles: Record<MapStyleKey, MapTileStyle> = {
    chart: {
        label: 'Chart',
        url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
        attribution:
            'Map data © OpenStreetMap contributors, SRTM | Map style © OpenTopoMap',
        max_zoom: 17,
    },
    clean: {
        label: 'Clean',
        url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
        attribution:
            'Map data © OpenStreetMap contributors | Map style © CARTO',
        max_zoom: 20,
    },
    activity: {
        label: 'Activity',
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: 'Map data © OpenStreetMap contributors',
        max_zoom: 19,
    },
};

export function useMapTileStyles() {
    const page = usePage();

    return computed(() => {
        const integrations = page.props.integrations as
            | SharedIntegrations
            | undefined;
        const sharedStyles = integrations?.maps?.styles ?? {};

        return {
            chart: normalizeStyle(sharedStyles.chart, fallbackStyles.chart),
            clean: normalizeStyle(sharedStyles.clean, fallbackStyles.clean),
            activity: normalizeStyle(
                sharedStyles.activity,
                fallbackStyles.activity,
            ),
        } satisfies Record<MapStyleKey, MapTileStyle>;
    });
}

function normalizeStyle(
    style: MapTileStyle | undefined,
    fallback: MapTileStyle,
): MapTileStyle {
    return {
        label: style?.label || fallback.label,
        url: style?.url || fallback.url,
        attribution: style?.attribution || fallback.attribution,
        max_zoom: style?.max_zoom || fallback.max_zoom,
    };
}
