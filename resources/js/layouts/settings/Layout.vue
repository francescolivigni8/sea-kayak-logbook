<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Security',
        href: editSecurity(),
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="flex flex-col gap-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <p class="journal-kicker">Settings</p>
                    <div class="space-y-2">
                        <h2 class="text-[clamp(1.9rem,3vw,2.35rem)] leading-[0.96] text-[color:var(--journal-text)]">
                            Account and appearance
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            Keep this area in the same journal language too: identity, password, and appearance without drifting into a utility dashboard.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="`hero-${toUrl(item.href)}`"
                        :href="item.href"
                        :class="['journal-tab', isCurrentOrParentUrl(item.href) ? 'journal-tab--active' : '']"
                    >
                        {{ item.title }}
                    </Link>
                </div>
            </div>
        </section>

        <section class="journal-panel px-5 py-5 md:px-6">
            <div class="flex flex-wrap gap-2" aria-label="Settings">
                <Link
                    v-for="item in sidebarNavItems"
                    :key="toUrl(item.href)"
                    :href="item.href"
                    :class="['journal-tab', isCurrentOrParentUrl(item.href) ? 'journal-tab--active' : '']"
                >
                    {{ item.title }}
                </Link>
            </div>
        </section>

        <div class="max-w-4xl">
            <section class="space-y-5">
                <slot />
            </section>
        </div>
    </div>
</template>
