import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        chunkSizeWarningLimit: 1200,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/@maptiler/weather/')) {
                        return 'maptiler-weather-core';
                    }

                    if (id.includes('node_modules/three/examples/')) {
                        return 'maptiler-three-extras';
                    }

                    if (
                        id.includes('node_modules/three/src/renderers/') ||
                        id.includes('node_modules/three/src/materials/') ||
                        id.includes('node_modules/three/src/textures/') ||
                        id.includes('node_modules/three/src/lights/')
                    ) {
                        return 'maptiler-three-render';
                    }

                    if (
                        id.includes('node_modules/three/src/core/') ||
                        id.includes('node_modules/three/src/math/') ||
                        id.includes('node_modules/three/src/geometries/') ||
                        id.includes('node_modules/three/src/objects/')
                    ) {
                        return 'maptiler-three-core';
                    }

                    if (
                        id.includes('node_modules/three/src/') ||
                        id.includes('node_modules/three/build/')
                    ) {
                        return 'maptiler-three-scene';
                    }

                    if (id.includes('node_modules/ol/')) {
                        return 'maptiler-weather-geo';
                    }

                    if (
                        id.includes(
                            'node_modules/@maplibre/maplibre-gl-style-spec/',
                        )
                    ) {
                        return 'maptiler-maplibre-style-spec';
                    }

                    if (
                        id.includes('node_modules/maplibre-gl/src/style/') ||
                        id.includes('node_modules/maplibre-gl/src/symbol/')
                    ) {
                        return 'maptiler-maplibre-style';
                    }

                    if (
                        id.includes('node_modules/maplibre-gl/src/render/') ||
                        id.includes('node_modules/maplibre-gl/src/gl/') ||
                        id.includes('node_modules/maplibre-gl/src/shaders/')
                    ) {
                        return 'maptiler-maplibre-render';
                    }

                    if (
                        id.includes('node_modules/maplibre-gl/src/source/') ||
                        id.includes('node_modules/maplibre-gl/src/data/') ||
                        id.includes('node_modules/maplibre-gl/src/tile/')
                    ) {
                        return 'maptiler-maplibre-data';
                    }

                    if (id.includes('node_modules/maplibre-gl/')) {
                        return 'maptiler-maplibre-core';
                    }

                    if (id.includes('node_modules/@maptiler/sdk/')) {
                        return 'maptiler-sdk';
                    }
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
});
