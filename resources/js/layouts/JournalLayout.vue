<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';
import { capturePageview, initProductAnalytics } from '@/lib/productAnalytics';

type JournalNavShape = {
    homeWater?: string;
    sessionCount?: number;
    topForce?: number | null;
    thisYearDistanceKm?: number;
};

type OwnerToolsShape = {
    canViewUsers?: boolean;
};

type IntegrationsShape = {
    analytics?: {
        posthog?: {
            enabled?: boolean;
            key?: string | null;
            host?: string | null;
        };
    };
};

const page = usePage();

const currentPath = computed(() => page.url.split('?')[0] || '/dashboard');
const journalNav = computed(
    () => (page.props.journalNav as JournalNavShape | undefined) ?? null,
);
const ownerTools = computed(
    () => (page.props.ownerTools as OwnerToolsShape | undefined) ?? null,
);
const integrations = computed(
    () => (page.props.integrations as IntegrationsShape | undefined) ?? null,
);
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
    {
        label: 'Expedition notes',
        href: '/expedition-notes',
        match: ['/expedition-notes', '/expeditions'],
    },
    {
        label: 'Planning',
        href: '/planning',
        match: ['/planning'],
    },
    {
        label: 'Add session',
        href: '/sessions/create',
        match: ['/sessions/create', '/sessions/', '/imports/garmin'],
    },
] satisfies readonly PrimaryNavItem[];

const utilityLinks = computed(() => {
    return [
        { label: 'Library', href: '/sessions', match: ['/sessions'] },
        {
            label: 'Import',
            href: '/imports/garmin',
            match: ['/imports/garmin'],
        },
        ...(ownerTools.value?.canViewUsers
            ? [
                  {
                      label: 'Users',
                      href: '/insights/users',
                      match: ['/insights'],
                  },
              ]
            : []),
        { label: 'Account', href: '/settings/profile', match: ['/settings'] },
    ];
});

function isActive(item: {
    href: string;
    match: readonly string[];
    placeholder?: boolean;
}) {
    if (item.placeholder) {
        return false;
    }

    if (item.match.includes(currentPath.value)) {
        return true;
    }

    if (item.href === '/sessions/create') {
        return (
            currentPath.value.startsWith('/sessions/create') ||
            (currentPath.value.startsWith('/sessions/') &&
                currentPath.value.endsWith('/edit'))
        );
    }

    if (item.href === '/expedition-notes') {
        return (
            currentPath.value.startsWith('/expedition-notes') ||
            currentPath.value.startsWith('/expeditions')
        );
    }

    return currentPath.value.startsWith(item.href);
}

function isPrimaryCta(item: PrimaryNavItem) {
    return item.href === '/sessions/create';
}

function isPlaceholder(item: PrimaryNavItem) {
    return Boolean(item.placeholder);
}

function isUtilityActive(item: { href: string; match: string[] }) {
    return item.match.some((prefix) => currentPath.value.startsWith(prefix));
}

onMounted(() => {
    initProductAnalytics(integrations.value?.analytics?.posthog);
});

watch(
    () => page.url,
    () => {
        capturePageview();
    },
);
</script>

<template>
    <div class="journal-page">
        <header
            class="journal-panel journal-panel--hero overflow-hidden px-4 py-4 sm:px-5 md:px-6 md:py-5"
        >
            <div class="flex flex-col gap-3 sm:gap-4">
                <div
                    class="grid gap-4 sm:gap-5 xl:grid-cols-[minmax(0,1.05fr)_minmax(340px,0.95fr)] xl:items-start"
                >
                    <div class="flex items-center gap-3 sm:gap-4">
                        <img
                            src="/brand/ykj-logo-clean.png"
                            alt="Your Kayaking Journal logo"
                            class="size-[5.15rem] shrink-0 rounded-[1.35rem] border border-[rgba(103,114,255,0.16)] object-cover shadow-[0_18px_34px_rgba(37,43,82,0.14)] sm:size-[6.15rem]"
                            width="98"
                            height="98"
                        />
                        <div class="space-y-2">
                            <p class="journal-kicker">Sea kayak logbook</p>
                            <h1
                                class="text-[2.1rem] leading-[0.94] text-[color:var(--journal-text)] sm:text-[clamp(2.1rem,4vw,3.35rem)]"
                            >
                                {{ heroTitle }}
                            </h1>
                        </div>
                    </div>

                    <div class="space-y-4 xl:text-right">
                        <div
                            class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap sm:justify-start sm:gap-3 xl:justify-end"
                        >
                            <div class="journal-stat-pill">
                                <span class="journal-stat-pill__label"
                                    >Sessions</span
                                >
                                <span class="journal-stat-pill__value">{{
                                    journalNav?.sessionCount ?? 0
                                }}</span>
                            </div>
                            <div class="journal-stat-pill">
                                <span class="journal-stat-pill__label"
                                    >Top force</span
                                >
                                <span class="journal-stat-pill__value">
                                    {{
                                        journalNav?.topForce !== null &&
                                        journalNav?.topForce !== undefined
                                            ? `F${journalNav.topForce}`
                                            : '—'
                                    }}
                                </span>
                            </div>
                            <div class="journal-stat-pill">
                                <span class="journal-stat-pill__label"
                                    >This year</span
                                >
                                <span class="journal-stat-pill__value">
                                    {{
                                        journalNav?.thisYearDistanceKm !==
                                        undefined
                                            ? journalNav.thisYearDistanceKm.toFixed(
                                                  0,
                                              )
                                            : '0'
                                    }}
                                    km
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
                >
                    <nav
                        class="journal-surface-shell flex items-center gap-2 overflow-x-auto rounded-[1.65rem] p-1.5 [-ms-overflow-style:none] [scrollbar-width:none] sm:inline-flex sm:flex-wrap [&::-webkit-scrollbar]:hidden"
                    >
                        <component
                            v-for="item in primaryNav"
                            :key="item.label"
                            :is="isPlaceholder(item) ? 'span' : Link"
                            :href="isPlaceholder(item) ? undefined : item.href"
                            :class="[
                                'journal-tab',
                                'shrink-0',
                                isActive(item) ? 'journal-tab--active' : '',
                                isPrimaryCta(item) ? 'journal-tab--cta' : '',
                                isPlaceholder(item)
                                    ? 'journal-tab--placeholder'
                                    : '',
                            ]"
                        >
                            {{ item.label }}
                        </component>
                    </nav>

                    <div
                        class="flex items-center gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:pb-0 [&::-webkit-scrollbar]:hidden"
                    >
                        <Link
                            v-for="link in utilityLinks"
                            :key="link.label"
                            :href="link.href"
                            :class="[
                                'journal-utility-link',
                                'shrink-0',
                                isUtilityActive(link)
                                    ? 'journal-utility-link--active'
                                    : '',
                            ]"
                        >
                            {{ link.label }}
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="journal-utility-link shrink-0"
                        >
                            Sign out
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <slot />
    </div>
</template>
