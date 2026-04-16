<script setup lang="ts">
import { dashboard } from '@/routes';
import { Head } from '@inertiajs/vue3';
import SessionForm from './Partials/SessionForm.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sea Kayak Logbook',
                href: dashboard(),
            },
            {
                title: 'Sessions',
                href: '/sessions',
            },
            {
                title: 'Edit session',
                href: '/sessions',
            },
        ],
    },
});

defineProps<{
    profile: {
        name: string;
        homeWater: string;
        timezone: string;
        defaultMapView?: {
            lat: number;
            lng: number;
            zoom: number;
        };
    };
    weatherAutofillAvailable: boolean;
    sessionMeta: {
        id: number;
        title: string;
        hasObservation: boolean;
    };
    initialStep: number;
    formDefaults: Record<string, string | boolean>;
    existingAssets: {
        gpxName: string | null;
        fitName: string | null;
        photoName: string | null;
        photoUrl: string | null;
    };
}>();
</script>

<template>
    <Head title="Edit session" />

    <div class="flex flex-col gap-5">
        <SessionForm
            mode="edit"
            :profile="profile"
            :weather-autofill-available="weatherAutofillAvailable"
            :form-defaults="formDefaults"
            :existing-assets="existingAssets"
            :session-id="sessionMeta.id"
            :initial-step="initialStep"
        />
    </div>
</template>
