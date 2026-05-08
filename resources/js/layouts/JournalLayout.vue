<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';
import { usePageHeaderActions } from '@/composables/usePageHeaderActions';
import { useUnitPreferences } from '@/composables/useUnitPreferences';
import { formatDistanceKm } from '@/lib/units';
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

type AuthShape = {
    user?: {
        id: number | string;
    } | null;
};

type LegalShape = {
    productName?: string;
    copyrightOwner?: string;
};

const page = usePage();
const { unitPreferences } = useUnitPreferences();
const { pageHeaderActions } = usePageHeaderActions();

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
const legal = computed(
    () => (page.props.legal as LegalShape | undefined) ?? null,
);
const auth = computed(() => (page.props.auth as AuthShape | undefined) ?? null);
const heroTitle = 'Your kayaking journal';
const footerYear = new Date().getFullYear();
const footerProductName = computed(
    () => legal.value?.productName || 'Your Kayaking Journal',
);
const footerCopyrightOwner = computed(
    () => legal.value?.copyrightOwner || 'Francesco Li Vigni',
);
const footerCopyright = computed(
    () =>
        `© ${footerYear} ${footerCopyrightOwner.value}. ${footerProductName.value}. All rights reserved.`,
);
const feedbackHref = computed(() =>
    auth.value?.user
        ? `/settings/profile?from=${encodeURIComponent(currentPath.value)}#feedback`
        : null,
);
const isSessionEditorPath = computed(() => {
    return (
        currentPath.value === '/sessions/create' ||
        (currentPath.value.startsWith('/sessions/') &&
            currentPath.value.endsWith('/edit'))
    );
});

type PrimaryNavItem = {
    label: string;
    href: string;
    match: readonly string[];
    placeholder?: boolean;
};

const primaryNav = [
    { label: 'Dashboard', href: '/dashboard', match: ['/dashboard'] },
    { label: 'Diary', href: '/diary', match: ['/diary'] },
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
const trailingUtilityLink = computed(
    () => utilityLinks.value[utilityLinks.value.length - 1] ?? null,
);
const leadingUtilityLinks = computed(() =>
    utilityLinks.value.slice(0, Math.max(utilityLinks.value.length - 1, 0)),
);

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
            :class="
                isSessionEditorPath
                    ? 'px-3 py-3 sm:px-5 md:px-6 md:py-5'
                    : 'px-4 py-4 sm:px-5 md:px-6 md:py-5'
            "
            class="journal-panel journal-panel--hero overflow-hidden"
        >
            <div class="flex flex-col gap-3 sm:gap-4">
                <div
                    class="grid gap-4 sm:gap-5 xl:grid-cols-[minmax(0,1.05fr)_minmax(340px,0.95fr)] xl:items-start"
                >
                    <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:gap-4">
                        <img
                            src="/brand/ykj-logo-clean.png"
                            alt="Your Kayaking Journal logo"
                            :class="
                                isSessionEditorPath
                                    ? 'size-[3.4rem] sm:size-[6.15rem]'
                                    : 'size-[4.4rem] sm:size-[6.15rem]'
                            "
                            class="shrink-0 rounded-[1.2rem] border border-[rgba(103,114,255,0.16)] object-cover shadow-[0_18px_34px_rgba(37,43,82,0.14)]"
                            width="98"
                            height="98"
                        />
                        <div class="space-y-2">
                            <p class="journal-kicker">Sea kayak logbook</p>
                            <h1
                                :class="
                                    isSessionEditorPath
                                        ? 'text-[1.45rem] sm:text-[clamp(2.1rem,4vw,3.35rem)]'
                                        : 'text-[1.85rem] sm:text-[clamp(2.1rem,4vw,3.35rem)]'
                                "
                                class="leading-[0.94] text-[color:var(--journal-text)]"
                            >
                                {{ heroTitle }}
                            </h1>
                        </div>
                    </div>

                    <div
                        :class="isSessionEditorPath ? 'hidden sm:block xl:text-right' : 'space-y-4 xl:text-right'"
                    >
                        <div
                            class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-start sm:gap-3 xl:justify-end"
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
                            <div class="journal-stat-pill col-span-2 sm:col-span-1">
                                <span class="journal-stat-pill__label"
                                    >This year</span
                                >
                                <span class="journal-stat-pill__value">
                                    {{
                                        journalNav?.thisYearDistanceKm !==
                                        undefined
                                            ? formatDistanceKm(
                                                  journalNav.thisYearDistanceKm,
                                                  unitPreferences,
                                                  0,
                                              )
                                            : formatDistanceKm(
                                                  0,
                                                  unitPreferences,
                                                  0,
                                              )
                                    }}
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
                        :class="
                            isSessionEditorPath
                                ? 'hidden sm:flex sm:flex-wrap sm:items-center sm:gap-2 sm:pb-0'
                                : 'flex items-center gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:pb-0 [&::-webkit-scrollbar]:hidden'
                        "
                    >
                        <Link
                            v-for="link in leadingUtilityLinks"
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
                        <button
                            v-for="action in pageHeaderActions"
                            :key="action.id"
                            type="button"
                            :class="[
                                'journal-utility-link',
                                'shrink-0',
                                action.active
                                    ? 'journal-utility-link--active'
                                    : '',
                            ]"
                            @click="action.onClick"
                        >
                            {{ action.label }}
                        </button>
                        <Link
                            v-if="trailingUtilityLink"
                            :href="trailingUtilityLink.href"
                            :class="[
                                'journal-utility-link',
                                'shrink-0',
                                isUtilityActive(trailingUtilityLink)
                                    ? 'journal-utility-link--active'
                                    : '',
                            ]"
                        >
                            {{ trailingUtilityLink.label }}
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

        <footer
            class="px-1 pb-2 text-center text-[0.75rem] leading-6 text-[color:var(--journal-faint)]"
        >
            <p>{{ footerCopyright }}</p>
            <p class="mt-1">
                Use of the app does not transfer ownership of the brand,
                original design, or protected content.
            </p>
            <div class="mt-2 flex flex-wrap items-center justify-center gap-3">
                <Link class="underline underline-offset-4" href="/privacy">
                    Privacy
                </Link>
                <Link class="underline underline-offset-4" href="/terms">
                    Terms
                </Link>
                <Link class="underline underline-offset-4" href="/contact">
                    Contact
                </Link>
                <Link
                    v-if="feedbackHref"
                    class="underline underline-offset-4"
                    :href="feedbackHref"
                >
                    Feedback
                </Link>
            </div>
        </footer>
    </div>
</template>
