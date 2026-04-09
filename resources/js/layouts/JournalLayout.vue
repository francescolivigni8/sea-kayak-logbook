<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type ProfileShape = {
    name?: string;
    slug?: string;
    bio?: string;
    homeWater?: string;
    timezone?: string;
    isPublic?: boolean;
    publicPath?: string;
};

type JournalNavShape = {
    homeWater?: string;
    sessionCount?: number;
    topForce?: number | null;
    thisYearDistanceKm?: number;
    statusSummary?: string;
    statusItems?: {
        key: string;
        label: string;
        href: string;
        count: number;
        detail: string;
        tone: string;
        active: boolean;
    }[];
};

const props = defineProps<{
    profile?: ProfileShape;
}>();

const page = usePage();

const currentPath = computed(() => page.url.split('?')[0] || '/dashboard');
const authUser = computed(() => page.props.auth?.user as { name?: string } | undefined);
const activeProfile = computed(() => (props.profile ?? (page.props.profile as ProfileShape | undefined)) ?? null);
const journalNav = computed(() => (page.props.journalNav as JournalNavShape | undefined) ?? null);
const isDashboard = computed(() => currentPath.value === '/dashboard');

const titleText = computed(() => activeProfile.value?.name || authUser.value?.name || 'Sea Kayak Logbook');
const homeWaterText = computed(() => journalNav.value?.homeWater || activeProfile.value?.homeWater || 'Home water');
const heroTitle = computed(() => (isDashboard.value ? 'Your kayaking journal' : titleText.value));
const heroCopy = computed(() => {
    if (isDashboard.value) {
        return '';
    }

    return 'Distance, notes, sea state, expeditions, and route memory in one place.';
});
const journalStatusItems = computed(() => journalNav.value?.statusItems ?? []);
const journalStatusSummary = computed(() => journalNav.value?.statusSummary || 'Catch-up points across sessions and expeditions.');

const metaPills = computed(() => {
    const pills = [];

    if (journalNav.value?.homeWater || activeProfile.value?.homeWater) {
        pills.push({ label: journalNav.value?.homeWater || activeProfile.value?.homeWater || '', primary: true });
    }

    if (activeProfile.value?.timezone) {
        pills.push({ label: activeProfile.value.timezone, primary: false });
    }

    pills.push({ label: 'GPX / FIT', primary: false });

    return pills;
});

const primaryNav = [
    { label: 'Dashboard', href: '/dashboard', match: ['/dashboard'] },
    { label: 'Diary', href: '/diary', match: ['/diary'] },
    { label: 'Observations', href: '/observations', match: ['/observations'] },
    { label: 'Expedition notes', href: '/expedition-notes', match: ['/expedition-notes', '/expeditions'] },
    { label: 'Add session', href: '/sessions/create', match: ['/sessions/create', '/sessions/', '/imports/garmin'] },
] as const;

const utilityLinks = computed(() => {
    return [
        { label: 'Library', href: '/sessions' },
        { label: 'Import', href: '/imports/garmin' },
        { label: 'Account', href: '/settings/profile' },
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

function isActive(item: { href: string; match: readonly string[] }) {
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
</script>

<template>
    <div class="journal-page">
        <header :class="['journal-panel overflow-hidden px-5 py-5 md:px-6 md:py-6', isDashboard ? 'journal-panel--hero' : '']">
            <div class="flex flex-col gap-5">
                <div class="grid gap-5 xl:grid-cols-[minmax(0,1.05fr)_minmax(340px,0.95fr)] xl:items-start">
                    <div class="space-y-3">
                        <p class="journal-kicker">Sea kayak logbook</p>
                        <div class="space-y-2">
                            <h1 class="text-[clamp(2.1rem,4vw,3.35rem)] leading-[0.94] text-[color:var(--journal-text)]">
                                {{ heroTitle }}
                            </h1>
                            <p v-if="heroCopy" class="journal-copy max-w-2xl text-sm md:text-base">
                                {{ heroCopy }}
                            </p>
                        </div>

                        <article v-if="isDashboard && journalStatusItems.length" class="journal-status-strip">
                            <div class="space-y-1">
                                <p class="journal-kicker">Journal status</p>
                                <p class="journal-copy max-w-2xl text-sm md:text-[0.95rem]">
                                    {{ journalStatusSummary }}
                                </p>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <Link
                                    v-for="item in journalStatusItems"
                                    :key="item.key"
                                    :href="item.href"
                                    :class="[
                                        'journal-status-card',
                                        item.active ? `journal-status-card--${item.tone}` : 'journal-status-card--quiet',
                                    ]"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <span class="journal-status-card__eyebrow">
                                                {{ item.active ? 'Needs attention' : 'Up to date' }}
                                            </span>
                                            <strong class="journal-status-card__label">{{ item.label }}</strong>
                                        </div>
                                        <span class="journal-status-card__count">
                                            {{ item.active ? item.count : 'OK' }}
                                        </span>
                                    </div>
                                    <p class="journal-status-card__detail">
                                        {{ item.detail }}
                                    </p>
                                </Link>
                            </div>
                        </article>

                        <div v-if="!isDashboard" class="flex flex-wrap gap-2">
                            <span
                                v-for="pill in metaPills"
                                :key="pill.label"
                                :class="['journal-chip', pill.primary ? 'journal-chip--primary' : '']"
                            >
                                {{ pill.label }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-4 xl:text-right">
                        <div class="flex flex-wrap justify-start gap-2 xl:justify-end">
                            <Link
                                v-for="link in utilityLinks.slice(0, 1)"
                                :key="link.label"
                                :href="link.href"
                                class="journal-utility-link"
                            >
                                {{ link.label }}
                            </Link>

                            <Link href="/logout" method="post" as="button" class="journal-utility-link">
                                Sign out
                            </Link>
                        </div>

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
                        <Link
                            v-for="item in primaryNav"
                            :key="item.label"
                            :href="item.href"
                            :class="[
                                'journal-tab',
                                isActive(item) ? 'journal-tab--active' : '',
                                isPrimaryCta(item) ? 'journal-tab--cta' : '',
                            ]"
                        >
                            {{ item.label }}
                        </Link>
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
                            class="journal-utility-link"
                        >
                            {{ link.label }}
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <slot />
    </div>
</template>
