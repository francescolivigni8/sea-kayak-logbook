<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type GuideLink = {
    label: string;
    href: string;
};

type GuideReply = {
    title: string;
    body: string;
    steps: string[];
    links: GuideLink[];
};

type GuideContext = {
    title: string;
    summary: string;
    suggestions: string[];
    quickLinks: GuideLink[];
    defaultReply: GuideReply;
};

const props = withDefaults(
    defineProps<{
        currentPath: string;
        feedbackHref?: string | null;
    }>(),
    {
        feedbackHref: null,
    },
);

const isOpen = ref(false);
const showHint = ref(false);
const prompt = ref('');
const reply = ref<GuideReply | null>(null);
let hintTimer: number | null = null;
const krakenGuideArt = '/brand/kraken-guide-chat.png';

function buildLink(label: string, href: string): GuideLink {
    return { label, href };
}

function supportLink(): GuideLink {
    return props.feedbackHref
        ? buildLink('Send feedback', props.feedbackHref)
        : buildLink('Open account', '/settings/profile');
}

function dashboardReply(): GuideReply {
    return {
        title: 'Use the dashboard as a launch point',
        body: 'Move or hide cards with Edit layout, then jump into the next paddle from Add session, Planning, or the Library.',
        steps: [
            'Use Edit layout in the header if you want to rearrange the cards.',
            'Open Library when you want an older logged session.',
            'Use Add session or Planning when you are starting something new.',
        ],
        links: [
            buildLink('Dashboard', '/dashboard'),
            buildLink('Add session', '/sessions/create'),
            buildLink('Library', '/sessions'),
        ],
    };
}

function planningReply(): GuideReply {
    return {
        title: 'Build the route first, then save it',
        body: 'Click the map to drop points. Tap the first point again to close the loop. Use live weather as planning guidance, not the final call.',
        steps: [
            'Use Clear course if the line gets messy.',
            'Toggle live weather only when you want the overlay on top of the base map.',
            'Save the route when it looks right so it lands in Planned sessions.',
        ],
        links: [
            buildLink('Planning', '/planning'),
            buildLink('Library', '/sessions'),
            supportLink(),
        ],
    };
}

function sessionReply(): GuideReply {
    return {
        title: 'Quick is for speed, Extended is for detail',
        body: 'Quick gives you the essential log. Extended is where you add weather, notes, files, and a fuller route story.',
        steps: [
            'Stay on Quick if you only want title, date, place, distance, and duration.',
            'Switch to Extended when you want richer notes or files.',
            'If you trace the route on the map, the form uses that line for distance and the first and last points.',
        ],
        links: [
            buildLink('Add session', '/sessions/create'),
            buildLink('Library', '/sessions'),
            supportLink(),
        ],
    };
}

function libraryReply(): GuideReply {
    return {
        title: 'Library is your working archive',
        body: 'Use folders to group sessions, open older logs, and come back later to edit details without crowding the first save.',
        steps: [
            'Open a folder to see the sessions inside it.',
            'Use Edit session when you want to add notes or files later.',
            'Use Export GPX when you need a route outside the app.',
        ],
        links: [
            buildLink('Library', '/sessions'),
            buildLink('Add session', '/sessions/create'),
            supportLink(),
        ],
    };
}

function importReply(): GuideReply {
    return {
        title: 'Import Garmin in stages',
        body: 'CSV builds or updates sessions. GPX and FIT help repair route coverage when the CSV alone is not enough.',
        steps: [
            'Upload the Garmin CSV first for bulk history.',
            'Add GPX or FIT files if you want stronger route matching.',
            'If totals look wrong, flag activity types such as Running, Whitewater, or SUP.',
        ],
        links: [
            buildLink('Import', '/imports/garmin'),
            buildLink('Library', '/sessions'),
            supportLink(),
        ],
    };
}

function accountReply(): GuideReply {
    return {
        title: 'Account is where the app fits you better',
        body: 'This is where you change units, default map area, exports, and the support form.',
        steps: [
            'Set your preferred units once and the rest of the app follows.',
            'Choose the map area you want to see first when you open planners and pickers.',
            'Use backup export if you want a local copy of your data.',
        ],
        links: [
            buildLink('Account', '/settings/profile'),
            supportLink(),
        ],
    };
}

function feedbackReply(): GuideReply {
    return {
        title: 'Good bug reports are short and specific',
        body: 'Tell us the page, the click, and what you expected. That makes troubleshooting much faster.',
        steps: [
            'Include the page where it happened.',
            'Say what you clicked right before it broke.',
            'If refresh fixes it, mention that too.',
        ],
        links: [supportLink()],
    };
}

function genericReply(): GuideReply {
    return {
        title: 'Ask Kraken in plain words',
        body: 'Try a short question like “How do I import Garmin?” or “Where do I change units?” and I will point you to the right place.',
        steps: [
            'Use the quick links if you already know the page you need.',
            'Use feedback if something looks broken rather than confusing.',
        ],
        links: [
            buildLink('Dashboard', '/dashboard'),
            buildLink('Library', '/sessions'),
            buildLink('Account', '/settings/profile'),
        ],
    };
}

function contextForPath(path: string): GuideContext {
    if (path.startsWith('/planning')) {
        return {
            title: 'Planning',
            summary: 'Need help with the route line, weather overlay, or saving the plan?',
            suggestions: [
                'How do I close the route loop?',
                'How do I clear the course?',
                'How does the weather board work?',
            ],
            quickLinks: [
                buildLink('Planning', '/planning'),
                buildLink('Library', '/sessions'),
                buildLink('Add session', '/sessions/create'),
            ],
            defaultReply: planningReply(),
        };
    }

    if (
        path === '/sessions/create' ||
        (path.startsWith('/sessions/') && path.endsWith('/edit'))
    ) {
        return {
            title: 'Add session',
            summary: 'Need help choosing Quick or Extended, tracing a route, or getting distance right?',
            suggestions: [
                'What is Quick vs Extended?',
                'How does the route trace set distance?',
                'Where do I add notes and files?',
            ],
            quickLinks: [
                buildLink('Add session', '/sessions/create'),
                buildLink('Library', '/sessions'),
                buildLink('Planning', '/planning'),
            ],
            defaultReply: sessionReply(),
        };
    }

    if (path.startsWith('/sessions')) {
        return {
            title: 'Library',
            summary: 'Need help finding old sessions, using folders, or exporting routes?',
            suggestions: [
                'How do folders work?',
                'Where do I edit a session later?',
                'Can I export a GPX?',
            ],
            quickLinks: [
                buildLink('Library', '/sessions'),
                buildLink('Add session', '/sessions/create'),
                buildLink('Import', '/imports/garmin'),
            ],
            defaultReply: libraryReply(),
        };
    }

    if (path.startsWith('/imports/garmin')) {
        return {
            title: 'Garmin import',
            summary: 'Need help with CSV, GPX, FIT, or mismatched totals?',
            suggestions: [
                'How do I import Garmin history?',
                'What if GPX and CSV do not match?',
                'Why are some activities missing?',
            ],
            quickLinks: [
                buildLink('Import', '/imports/garmin'),
                buildLink('Library', '/sessions'),
                buildLink('Feedback', props.feedbackHref ?? '/settings/profile#feedback'),
            ],
            defaultReply: importReply(),
        };
    }

    if (path.startsWith('/settings')) {
        return {
            title: 'Account',
            summary: 'Need help with units, profile setup, exports, or feedback?',
            suggestions: [
                'Where do I change units?',
                'How do I set my default map area?',
                'How do I export my data?',
            ],
            quickLinks: [
                buildLink('Account', '/settings/profile'),
                supportLink(),
                buildLink('Dashboard', '/dashboard'),
            ],
            defaultReply: accountReply(),
        };
    }

    if (path.startsWith('/diary')) {
        return {
            title: 'Diary',
            summary: 'Need help reading the calendar or jumping from a date back into a session?',
            suggestions: [
                'How do I open a session from the diary?',
                'Where do I log a new paddle?',
                'How do I edit an older session?',
            ],
            quickLinks: [
                buildLink('Diary', '/diary'),
                buildLink('Add session', '/sessions/create'),
                buildLink('Library', '/sessions'),
            ],
            defaultReply: {
                title: 'Use Diary for the calendar view',
                body: 'Diary is the quickest way to see where the paddles landed in the month, then jump back into the full session when you need detail.',
                steps: [
                    'Click a day with a session to open the underlying log.',
                    'Use Add session if the paddle is missing.',
                    'Use Library when you want broader browsing than the calendar gives you.',
                ],
                links: [
                    buildLink('Diary', '/diary'),
                    buildLink('Add session', '/sessions/create'),
                    buildLink('Library', '/sessions'),
                ],
            },
        };
    }

    if (path.startsWith('/insights/feedback')) {
        return {
            title: 'Feedback inbox',
            summary: 'Need help reviewing reports or tracing friction back to the page where it happened?',
            suggestions: [
                'How should I read bug reports?',
                'Where do users submit feedback?',
                'How do I jump back into the app from a report?',
            ],
            quickLinks: [
                buildLink('Feedback', '/insights/feedback'),
                buildLink('Users', '/insights/users'),
                buildLink('Dashboard', '/dashboard'),
            ],
            defaultReply: feedbackReply(),
        };
    }

    if (path.startsWith('/insights/users')) {
        return {
            title: 'Users',
            summary: 'Need help checking testers, account state, or who may be hitting friction?',
            suggestions: [
                'Where do people send bug reports?',
                'How do I open the feedback inbox?',
                'Where do I change account settings?',
            ],
            quickLinks: [
                buildLink('Users', '/insights/users'),
                buildLink('Feedback', '/insights/feedback'),
                buildLink('Account', '/settings/profile'),
            ],
            defaultReply: {
                title: 'Use Users for the account-level picture',
                body: 'This page is best for checking who is in the app, then pairing it with feedback reports when you want the story behind the friction.',
                steps: [
                    'Use Feedback when you need the actual report details.',
                    'Use Account for settings and support links.',
                    'Go back to Dashboard if you are done with admin work.',
                ],
                links: [
                    buildLink('Users', '/insights/users'),
                    buildLink('Feedback', '/insights/feedback'),
                    buildLink('Dashboard', '/dashboard'),
                ],
            },
        };
    }

    return {
        title: 'Dashboard',
        summary: 'Need help finding the right page, rearranging cards, or starting the next log?',
        suggestions: [
            'How do I rearrange dashboard cards?',
            'Where do I add a session?',
            'How do I find an old session?',
        ],
        quickLinks: [
            buildLink('Dashboard', '/dashboard'),
            buildLink('Add session', '/sessions/create'),
            buildLink('Planning', '/planning'),
            buildLink('Library', '/sessions'),
        ],
        defaultReply: dashboardReply(),
    };
}

const context = computed(() => contextForPath(props.currentPath));
const visibleSuggestions = computed(() => context.value.suggestions.slice(0, 2));
const visibleQuickLinks = computed(() => context.value.quickLinks.slice(0, 2));
const visibleReplySteps = computed(() => reply.value?.steps.slice(0, 2) ?? []);
const visibleReplyLinks = computed(() => reply.value?.links.slice(0, 2) ?? []);

function normalize(text: string) {
    return text
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function includesAny(query: string, keywords: string[]) {
    return keywords.some((keyword) => query.includes(keyword));
}

function resolveReply(question: string): GuideReply {
    const query = normalize(question);

    if (!query) {
        return context.value.defaultReply;
    }

    if (
        includesAny(query, ['import', 'garmin', 'csv', 'gpx', 'fit', 'missing totals'])
    ) {
        return importReply();
    }

    if (
        includesAny(query, [
            'planning',
            'weather',
            'wind',
            'tide',
            'current',
            'route',
            'course',
            'pin',
            'loop',
        ])
    ) {
        return planningReply();
    }

    if (
        includesAny(query, [
            'quick',
            'extended',
            'session',
            'log',
            'trace',
            'distance',
            'place',
            'launch',
            'landing',
        ])
    ) {
        return sessionReply();
    }

    if (
        includesAny(query, [
            'library',
            'folder',
            'folders',
            'find',
            'old session',
            'gpx export',
            'archive',
        ])
    ) {
        return libraryReply();
    }

    if (
        includesAny(query, [
            'dashboard',
            'layout',
            'cards',
            'hide card',
            'move card',
            'rearrange',
        ])
    ) {
        return dashboardReply();
    }

    if (
        includesAny(query, [
            'account',
            'profile',
            'unit',
            'units',
            'settings',
            'backup',
            'export',
            'privacy',
        ])
    ) {
        return accountReply();
    }

    if (
        includesAny(query, [
            'feedback',
            'bug',
            'issue',
            'broken',
            'stuck',
            'report',
            'refresh',
        ])
    ) {
        return feedbackReply();
    }

    return genericReply();
}

function ask(question = prompt.value) {
    reply.value = resolveReply(question);
    if (question.trim()) {
        prompt.value = question.trim();
    }
    isOpen.value = true;
    showHint.value = false;
}

function toggleOpen() {
    isOpen.value = !isOpen.value;
    if (isOpen.value && !reply.value) {
        reply.value = context.value.defaultReply;
    }
    if (isOpen.value) {
        showHint.value = false;
    }
}

function closeGuide() {
    isOpen.value = false;
}

watch(
    () => props.currentPath,
    () => {
        prompt.value = '';
        reply.value = context.value.defaultReply;
    },
);

onMounted(() => {
    reply.value = context.value.defaultReply;

    try {
        if (!window.localStorage.getItem('ykj-kraken-guide-hint-seen')) {
            showHint.value = true;
            window.localStorage.setItem('ykj-kraken-guide-hint-seen', '1');
            hintTimer = window.setTimeout(() => {
                showHint.value = false;
            }, 4200);
        }
    } catch {
        showHint.value = true;
        hintTimer = window.setTimeout(() => {
            showHint.value = false;
        }, 4200);
    }
});

onBeforeUnmount(() => {
    if (hintTimer !== null) {
        window.clearTimeout(hintTimer);
    }
});
</script>

<template>
    <div
        class="fixed inset-x-0 bottom-0 z-50 flex justify-end px-3 pb-3 sm:px-5 sm:pb-5"
    >
        <div class="flex flex-col items-end gap-3">
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-2 opacity-0"
            >
                <button
                    v-if="showHint && !isOpen"
                    type="button"
                    class="journal-chip bg-white/92 px-3 py-2 text-[0.74rem] text-[color:var(--journal-text)] shadow-[0_14px_24px_rgba(37,43,82,0.1)]"
                    @click="toggleOpen"
                >
                    Ask Kraken
                </button>
            </transition>

            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-4 scale-[0.98] opacity-0"
                enter-to-class="translate-y-0 scale-100 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 scale-100 opacity-100"
                leave-to-class="translate-y-4 scale-[0.98] opacity-0"
            >
                <section
                    v-if="isOpen"
                    class="journal-card z-[60] w-[min(20.5rem,calc(100vw-1rem))] overflow-hidden border border-[color:var(--journal-line)] bg-[color:var(--journal-panel-solid)] shadow-[0_24px_44px_rgba(37,43,82,0.16)]"
                    @click.stop
                >
                    <div class="border-b border-[color:var(--journal-line)] px-3.5 py-3.5 sm:px-4">
                        <div class="flex items-start justify-between gap-2.5">
                            <div class="flex min-w-0 items-start gap-2.5">
                                <img
                                    :src="krakenGuideArt"
                                    alt=""
                                    class="h-12 w-12 shrink-0 rounded-[1rem] border border-[rgba(103,114,255,0.16)] bg-[rgba(246,248,255,0.96)] object-contain p-1 shadow-[0_8px_18px_rgba(37,43,82,0.1)]"
                                    width="48"
                                    height="48"
                                />
                                <div class="min-w-0 space-y-0.5">
                                    <p class="text-[0.92rem] font-semibold text-[color:var(--journal-text)]">
                                        Ask Kraken
                                    </p>
                                    <p class="text-[0.78rem] leading-5 text-[color:var(--journal-muted)]">
                                        {{ context.title }} help
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="inline-flex size-8 shrink-0 items-center justify-center rounded-full border border-[color:var(--journal-line)] bg-white/86 text-[1rem] leading-none text-[color:var(--journal-muted)] transition hover:text-[color:var(--journal-text)]"
                                aria-label="Close guide"
                                @click="closeGuide"
                            >
                                ×
                            </button>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <Link
                                v-for="link in visibleQuickLinks"
                                :key="link.href"
                                :href="link.href"
                                class="journal-utility-link min-h-[2rem] px-2.5 py-1.5 text-[0.72rem]"
                            >
                                {{ link.label }}
                            </Link>
                        </div>
                    </div>

                    <div class="space-y-3 px-3.5 py-3.5 sm:px-4">
                        <form class="space-y-2" @submit.prevent="ask()">
                            <div class="flex gap-2">
                                <input
                                    id="kraken-guide-prompt"
                                    v-model="prompt"
                                    type="text"
                                    class="journal-input min-h-[42px] flex-1 bg-white text-sm"
                                    placeholder="Ask a quick question"
                                    @keydown.enter.prevent="ask()"
                                />
                                <button
                                    type="button"
                                    class="journal-primary-link min-h-[42px] shrink-0 px-3.5 text-[0.82rem]"
                                    @click.stop="ask()"
                                >
                                    Ask
                                </button>
                            </div>
                        </form>

                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="suggestion in visibleSuggestions"
                                :key="suggestion"
                                type="button"
                                class="journal-chip px-2.5 py-1.5 text-left text-[0.7rem] leading-4"
                                @click="ask(suggestion)"
                            >
                                {{ suggestion }}
                            </button>
                        </div>

                        <div
                            v-if="reply"
                            class="rounded-[1.2rem] border border-[color:var(--journal-line)] bg-[rgba(248,249,255,0.86)] p-3"
                        >
                            <p class="text-[0.9rem] font-semibold leading-5 text-[color:var(--journal-text)]">
                                {{ reply.title }}
                            </p>
                            <p class="mt-1.5 text-[0.8rem] leading-5 text-[color:var(--journal-muted)]">
                                {{ reply.body }}
                            </p>

                            <ol class="mt-2.5 space-y-1.5 text-[0.8rem] leading-5 text-[color:var(--journal-text)]">
                                <li
                                    v-for="(step, index) in visibleReplySteps"
                                    :key="`${reply.title}-${index}`"
                                    class="flex gap-2"
                                >
                                    <span class="mt-[0.05rem] font-semibold text-[color:var(--journal-sand)]">
                                        {{ index + 1 }}.
                                    </span>
                                    <span>{{ step }}</span>
                                </li>
                            </ol>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <Link
                                    v-for="(link, index) in visibleReplyLinks"
                                    :key="`${reply.title}-${link.href}`"
                                    :href="link.href"
                                    :class="
                                        index === 0
                                            ? 'journal-primary-link px-3.5 py-2 text-[0.78rem]'
                                            : 'journal-utility-link px-2.5 py-2 text-[0.72rem]'
                                    "
                                >
                                    {{ link.label }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>
            </transition>

            <button
                type="button"
                class="group relative z-50 inline-flex h-[4.4rem] w-[4.4rem] items-center justify-center overflow-hidden rounded-[1.5rem] border border-[rgba(103,114,255,0.24)] bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(244,246,255,0.96))] shadow-[0_18px_30px_rgba(37,43,82,0.16)] transition hover:-translate-y-0.5 hover:shadow-[0_22px_36px_rgba(37,43,82,0.18)]"
                :aria-expanded="isOpen"
                aria-label="Open Kraken guide"
                @click="toggleOpen"
            >
                <span
                    class="absolute inset-0 bg-[radial-gradient(circle_at_35%_30%,rgba(122,215,208,0.18),transparent_52%),radial-gradient(circle_at_75%_18%,rgba(255,211,92,0.16),transparent_34%)]"
                ></span>
                <img
                    :src="krakenGuideArt"
                    alt=""
                    class="relative h-[3.9rem] w-[3.9rem] rounded-[1.15rem] object-contain p-0.5"
                    width="62"
                    height="62"
                />
            </button>
        </div>
    </div>
</template>
