<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type JournalNavShape = {
    homeWater?: string;
    sessionCount?: number;
    topForce?: number | null;
    thisYearDistanceKm?: number;
};

const page = usePage();

const currentPath = computed(() => page.url.split('?')[0] || '/dashboard');
const journalNav = computed(() => (page.props.journalNav as JournalNavShape | undefined) ?? null);
const heroTitle = 'Your kayaking journal';

type PrimaryNavItem = {
    label: string;
    href: string;
    match: readonly string[];
    placeholder?: boolean;
};

const primaryNav = [
    { label: 'Dashboard', href: '/dashboard', match: ['/dashboard'] },
    { label: 'Diary', href: '/diary', match: ['/diary'] },
    { label: 'Observations', href: '/observations', match: ['/observations'] },
    { label: 'Expedition notes', href: '/expedition-notes', match: ['/expedition-notes', '/expeditions'] },
    { label: 'Courses', href: '/courses', match: ['/courses'], placeholder: true },
    { label: 'Add session', href: '/sessions/create', match: ['/sessions/create', '/sessions/', '/imports/garmin'] },
] satisfies readonly PrimaryNavItem[];

const utilityLinks = computed(() => {
    return [
        { label: 'Library', href: '/sessions', match: ['/sessions'] },
        { label: 'Import', href: '/imports/garmin', match: ['/imports/garmin'] },
        { label: 'Account', href: '/settings/profile', match: ['/settings'] },
    ];
});

const showBackButton = computed(() => currentPath.value !== '/dashboard');

const backFallback = computed(() => {
    if (
        currentPath.value.startsWith('/sessions/create') ||
        currentPath.value.endsWith('/edit') ||
        /^\/sessions\/\d+$/.test(currentPath.value)
    ) {
        return '/sessions';
    }

    if (currentPath.value.startsWith('/imports/')) {
        return '/sessions';
    }

    if (currentPath.value.startsWith('/settings/')) {
        return '/dashboard';
    }

    if (currentPath.value.startsWith('/expeditions/') && currentPath.value !== '/expeditions') {
        return '/expedition-notes';
    }

    if (
        currentPath.value.startsWith('/diary') ||
        currentPath.value.startsWith('/observations') ||
        currentPath.value.startsWith('/expedition-notes')
    ) {
        return '/dashboard';
    }

    return '/dashboard';
});

function isActive(item: { href: string; match: readonly string[]; placeholder?: boolean }) {
    if (item.placeholder) {
        return false;
    }

    if (item.match.includes(currentPath.value)) {
        return true;
    }

    if (item.href === '/sessions/create') {
        return currentPath.value.startsWith('/sessions/create') || (currentPath.value.startsWith('/sessions/') && currentPath.value.endsWith('/edit'));
    }

    if (item.href === '/expedition-notes') {
        return currentPath.value.startsWith('/expedition-notes') || currentPath.value.startsWith('/expeditions');
    }

    return currentPath.value.startsWith(item.href);
}

function goBack() {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();
        return;
    }

    router.visit(backFallback.value);
}

function isPrimaryCta(item: { href: string }) {
    return item.href === '/sessions/create';
}

function isPlaceholder(item: { placeholder?: boolean }) {
    return Boolean(item.placeholder);
}

function isUtilityActive(item: { href: string; match: string[] }) {
    return item.match.some((prefix) => currentPath.value.startsWith(prefix));
}
</script>

<template>
    <div class="journal-page">
        <header class="journal-panel journal-panel--hero overflow-hidden px-5 py-4 md:px-6 md:py-5">
            <div class="flex flex-col gap-4">
                <div class="grid gap-5 xl:grid-cols-[minmax(0,1.05fr)_minmax(340px,0.95fr)] xl:items-start">
                    <div class="space-y-2">
                        <p class="journal-kicker">Sea kayak logbook</p>
                        <h1 class="text-[clamp(2.1rem,4vw,3.35rem)] leading-[0.94] text-[color:var(--journal-text)]">
                            {{ heroTitle }}
                        </h1>
                    </div>

                    <div class="space-y-4 xl:text-right">
                        <div class="flex flex-wrap justify-start gap-3 xl:justify-end">
                            <div class="journal-stat-pill">
                                <span class="journal-stat-pill__label">Sessions</span>
                                <span class="journal-stat-pill__value">{{ journalNav?.sessionCount ?? 0 }}</span>
                            </div>
                            <div class="journal-stat-pill">
                                <span class="journal-stat-pill__label">Top force</span>
                                <span class="journal-stat-pill__value">
                                    {{ journalNav?.topForce !== null && journalNav?.topForce !== undefined ? `F${journalNav.topForce}` : '—' }}
                                </span>
                            </div>
                            <div class="journal-stat-pill">
                                <span class="journal-stat-pill__label">This year</span>
                                <span class="journal-stat-pill__value">
                                    {{ journalNav?.thisYearDistanceKm !== undefined ? journalNav.thisYearDistanceKm.toFixed(0) : '0' }} km
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <nav class="inline-flex flex-wrap items-center gap-2 rounded-full border border-[color:var(--journal-line)] bg-white/74 p-1.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.8)]">
                        <component
                            v-for="item in primaryNav"
                            :key="item.label"
                            :is="isPlaceholder(item) ? 'span' : Link"
                            :href="isPlaceholder(item) ? undefined : item.href"
                            :class="[
                                'journal-tab',
                                isActive(item) ? 'journal-tab--active' : '',
                                isPrimaryCta(item) ? 'journal-tab--cta' : '',
                                isPlaceholder(item) ? 'journal-tab--placeholder' : '',
                            ]"
                        >
                            {{ item.label }}
                        </component>
                    </nav>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="showBackButton"
                            type="button"
                            class="journal-utility-link"
                            @click="goBack"
                        >
                            Back
                        </button>

                        <Link
                            v-for="link in utilityLinks"
                            :key="link.label"
                            :href="link.href"
                            :class="[
                                'journal-utility-link',
                                isUtilityActive(link) ? 'journal-utility-link--active' : '',
                            ]"
                        >
                            {{ link.label }}
                        </Link>

                        <Link href="/logout" method="post" as="button" class="journal-utility-link">
                            Sign out
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <slot />
    </div>
</template>
