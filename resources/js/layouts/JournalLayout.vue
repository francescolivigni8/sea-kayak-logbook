<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type ProfileShape = {
    name?: string;
    slug?: string;
    homeWater?: string;
    timezone?: string;
    isPublic?: boolean;
    publicPath?: string;
};

const props = defineProps<{
    profile?: ProfileShape;
}>();

const page = usePage();

const currentPath = computed(() => page.url.split('?')[0] || '/dashboard');
const authUser = computed(() => page.props.auth?.user as { name?: string } | undefined);
const activeProfile = computed(() => (props.profile ?? (page.props.profile as ProfileShape | undefined)) ?? null);

const titleText = computed(() => activeProfile.value?.name || authUser.value?.name || 'Sea Kayak Logbook');

const metaPills = computed(() => {
    const pills = [];

    if (activeProfile.value?.homeWater) {
        pills.push({ label: activeProfile.value.homeWater, primary: true });
    }

    if (activeProfile.value?.timezone) {
        pills.push({ label: activeProfile.value.timezone, primary: false });
    }

    pills.push({ label: 'Laravel journal', primary: false });

    return pills;
});

const primaryNav = [
    { label: 'Dashboard', href: '/dashboard', match: ['/dashboard'] },
    { label: 'Diary', href: '/diary', match: ['/diary', '/sessions', '/expeditions'] },
    { label: 'Add session', href: '/sessions/create', match: ['/sessions/create', '/sessions/', '/imports/garmin'] },
];

const secondaryLinks = computed(() => {
    const links = [
        { label: 'All sessions', href: '/sessions' },
        { label: 'Garmin import', href: '/imports/garmin' },
        { label: 'Settings', href: '/settings/profile' },
    ];

    if (activeProfile.value?.isPublic && activeProfile.value.publicPath) {
        links.unshift({
            label: 'Public profile',
            href: activeProfile.value.publicPath,
        });
    }

    return links;
});

const screenLinks = computed(() => {
    const links = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Diary', href: '/diary' },
        { label: 'Sessions', href: '/sessions' },
        { label: 'Expeditions', href: '/expeditions' },
        { label: 'Add session', href: '/sessions/create' },
        { label: 'Garmin import', href: '/imports/garmin' },
        { label: 'Settings', href: '/settings/profile' },
    ];

    if (activeProfile.value?.isPublic && activeProfile.value.publicPath) {
        links.splice(3, 0, {
            label: 'Public profile',
            href: activeProfile.value.publicPath,
        });
    }

    return links;
});

const showBackButton = computed(() => currentPath.value !== '/dashboard');

const backFallback = computed(() => {
    if (currentPath.value.startsWith('/sessions/create') || currentPath.value.endsWith('/edit') || /^\/sessions\/\d+/.test(currentPath.value)) {
        return '/sessions';
    }

    if (currentPath.value.startsWith('/imports/')) {
        return '/sessions';
    }

    if (currentPath.value.startsWith('/settings/')) {
        return '/dashboard';
    }

    if (currentPath.value.startsWith('/expeditions/') && currentPath.value !== '/expeditions') {
        return '/expeditions';
    }

    if (currentPath.value.startsWith('/diary')) {
        return '/dashboard';
    }

    return '/dashboard';
});

function isActive(item: { match: string[] }) {
    if (item.match.includes(currentPath.value)) {
        return true;
    }

    if (item.href === '/sessions/create') {
        return currentPath.value.startsWith('/sessions/create') || currentPath.value.startsWith('/sessions/') && currentPath.value.endsWith('/edit');
    }

    if (item.href === '/diary') {
        return currentPath.value.startsWith('/diary') || currentPath.value.startsWith('/sessions') && !currentPath.value.endsWith('/create') && !currentPath.value.endsWith('/edit') || currentPath.value.startsWith('/expeditions');
    }

    return currentPath.value.startsWith(item.href);
}

function isScreenLinkActive(item: { href: string }) {
    if (item.href === '/dashboard') {
        return currentPath.value === '/dashboard';
    }

    if (item.href === '/diary') {
        return currentPath.value.startsWith('/diary');
    }

    if (item.href === '/sessions') {
        return currentPath.value === '/sessions' || /^\/sessions\/\d+$/.test(currentPath.value);
    }

    if (item.href === '/sessions/create') {
        return currentPath.value === '/sessions/create' || currentPath.value.endsWith('/edit');
    }

    if (item.href === '/expeditions') {
        return currentPath.value.startsWith('/expeditions');
    }

    if (item.href === '/imports/garmin') {
        return currentPath.value.startsWith('/imports/');
    }

    if (item.href === '/settings/profile') {
        return currentPath.value.startsWith('/settings/');
    }

    if (activeProfile.value?.publicPath && item.href === activeProfile.value.publicPath) {
        return currentPath.value.startsWith(activeProfile.value.publicPath);
    }

    return currentPath.value === item.href;
}

function goBack() {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();
        return;
    }

    router.visit(backFallback.value);
}
</script>

<template>
    <div class="journal-page">
        <header class="journal-panel overflow-hidden px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="space-y-3">
                        <p class="journal-kicker">Sea kayak logbook</p>
                        <div class="space-y-2">
                            <h1 class="text-[clamp(2.1rem,4vw,3.35rem)] leading-[0.94] text-[color:var(--journal-text)]">
                                {{ titleText }}
                            </h1>
                            <p class="journal-copy max-w-3xl text-sm md:text-base">
                                A lighter journal view for logging paddles, browsing the diary, and keeping the expedition story readable.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="pill in metaPills"
                                :key="pill.label"
                                :class="['journal-chip', pill.primary ? 'journal-chip--primary' : '']"
                            >
                                {{ pill.label }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                        <Link
                            v-for="link in secondaryLinks"
                            :key="link.label"
                            :href="link.href"
                            class="journal-utility-link"
                        >
                            {{ link.label }}
                        </Link>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="journal-utility-link"
                        >
                            Sign out
                        </Link>
                    </div>
                </div>

                <nav class="inline-flex flex-wrap items-center gap-2 rounded-full border border-[color:var(--journal-line)] bg-white/74 p-1.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.8)]">
                    <Link
                        v-for="item in primaryNav"
                        :key="item.label"
                        :href="item.href"
                        :class="['journal-tab', isActive(item) ? 'journal-tab--active' : '']"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="showBackButton"
                            type="button"
                            class="journal-utility-link"
                            @click="goBack"
                        >
                            Back
                        </button>

                        <span class="journal-chip">
                            Quick routes
                        </span>
                    </div>

                    <nav class="flex flex-wrap items-center gap-2" aria-label="Quick navigation">
                        <Link
                            v-for="item in screenLinks"
                            :key="item.label"
                            :href="item.href"
                            :class="['journal-tab', isScreenLinkActive(item) ? 'journal-tab--active' : '']"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>
            </div>
        </header>

        <slot />
    </div>
</template>
