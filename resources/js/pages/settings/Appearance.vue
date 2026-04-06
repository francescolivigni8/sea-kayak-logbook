<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <SettingsLayout>
        <div class="space-y-6">
            <section class="journal-panel px-5 py-5 md:px-6">
                <Heading
                    variant="small"
                    title="Appearance"
                    description="Keep the reading surface comfortable. The journal style stays light by default, and you can still switch when you want a different working mode."
                />

                <div class="mt-6 space-y-4">
                    <div class="journal-banner journal-banner--soft max-w-3xl">
                        This only changes how the private workspace feels for you. Public profile pages keep their own presentation.
                    </div>

                    <AppearanceTabs />
                </div>
            </section>
        </div>
    </SettingsLayout>
</template>
