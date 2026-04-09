<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
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
                <div class="space-y-2">
                    <p class="journal-kicker">Appearance</p>
                    <h2 class="text-[1.85rem] leading-[0.98] text-[color:var(--journal-text)]">
                        Reading surface
                    </h2>
                    <p class="journal-copy max-w-2xl text-sm md:text-base">
                        Keep the reading surface comfortable. The journal style stays light by default, and you can still switch when you want a different working mode.
                    </p>
                </div>

                <div class="mt-6 space-y-4">
                    <div class="journal-banner journal-banner--soft max-w-3xl">
                        This only changes how your private workspace feels while you work in the journal.
                    </div>

                    <AppearanceTabs />
                </div>
            </section>
        </div>
    </SettingsLayout>
</template>
