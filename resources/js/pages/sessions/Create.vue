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
                title: 'Add session',
                href: '/sessions/create',
            },
        ],
    },
});

defineProps<{
    profile: SessionProfileSummary;
    weatherAutofillAvailable: boolean;
    formDefaults: SessionFormDefaults;
    quickEntryMemory: QuickSessionMemory;
    existingAssets: SessionExistingAssets;
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
            :quick-entry-memory="quickEntryMemory"
            :existing-assets="existingAssets"
        />
    </div>
</template>
