<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import type {
    SessionExistingAssets,
    SessionFormDefaults,
    QuickSessionMemory,
    SessionProfileSummary,
} from '@/types/sessions';
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
    profile: SessionProfileSummary;
    weatherAutofillAvailable: boolean;
    sessionMeta: {
        id: number;
        title: string;
        hasObservation: boolean;
    };
    initialStep: number;
    formDefaults: SessionFormDefaults;
    quickEntryMemory: QuickSessionMemory;
    existingAssets: SessionExistingAssets;
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
            :quick-entry-memory="quickEntryMemory"
            :existing-assets="existingAssets"
            :session-id="sessionMeta.id"
            :initial-step="initialStep"
        />
    </div>
</template>
