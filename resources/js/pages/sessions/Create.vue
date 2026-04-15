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
                title: 'Add session',
                href: '/sessions/create',
            },
        ],
    },
});

defineProps<{
    profile: {
        name: string;
        homeWater: string;
        timezone: string;
    };
    weatherAutofillAvailable: boolean;
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
    <Head title="Add session" />

    <div class="flex flex-col gap-5">
        <SessionForm
            mode="create"
            :profile="profile"
            :weather-autofill-available="weatherAutofillAvailable"
            :form-defaults="formDefaults"
            :existing-assets="existingAssets"
        />
    </div>
</template>
