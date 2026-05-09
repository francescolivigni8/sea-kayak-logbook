<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Mic, Square, Volume2 } from 'lucide-vue-next';
import { useUnitPreferences } from '@/composables/useUnitPreferences';
import {
    formatDistanceKm,
    formatSpeedKnots,
    formatTemperatureC,
} from '@/lib/units';

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

type DashboardHeadline = {
    sessionCount: number;
    distanceKm: number;
    durationHours: number;
    averageSpeedKnots: number | null;
    trackSessions: number;
    paddledMonths: number;
};

type DashboardSeaState = {
    averageBeaufort: number | null;
    beaufortBands: Array<{ label: string; count: number }>;
    rescueTotals: Array<{ label: string; count: number }>;
    temperatureAverages: {
        air: number | null;
        sea: number | null;
    };
};

type DashboardMonthlyDistance = {
    label: string;
    distanceKm: number;
};

type SpeechRecognitionAlternativeLike = {
    transcript: string;
};

type SpeechRecognitionResultLike = {
    0: SpeechRecognitionAlternativeLike;
    length: number;
};

type SpeechRecognitionEventLike = {
    results: ArrayLike<SpeechRecognitionResultLike>;
};

type BrowserSpeechRecognition = {
    continuous: boolean;
    interimResults: boolean;
    lang: string;
    maxAlternatives: number;
    onresult: ((event: SpeechRecognitionEventLike) => void) | null;
    onerror: ((event: { error?: string }) => void) | null;
    onend: (() => void) | null;
    start: () => void;
    stop: () => void;
};

type BrowserSpeechRecognitionCtor = new () => BrowserSpeechRecognition;

const props = withDefaults(
    defineProps<{
        currentPath: string;
        feedbackHref?: string | null;
    }>(),
    {
        feedbackHref: null,
    },
);

const page = usePage();
const { unitPreferences } = useUnitPreferences();
const isOpen = ref(false);
const showHint = ref(false);
const prompt = ref('');
const reply = ref<GuideReply | null>(null);
const isListening = ref(false);
const isSpeaking = ref(false);
const lastVoiceError = ref<string | null>(null);
let recognition: BrowserSpeechRecognition | null = null;
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

const dashboardHeadline = computed(
    () => (page.props.headline as DashboardHeadline | undefined) ?? null,
);
const dashboardSeaState = computed(
    () => (page.props.seaState as DashboardSeaState | undefined) ?? null,
);
const dashboardMonthlyDistance = computed(
    () =>
        ((page.props.monthlyDistance as DashboardMonthlyDistance[] | undefined) ??
            []) as DashboardMonthlyDistance[],
);

const speechRecognitionCtor = computed<BrowserSpeechRecognitionCtor | null>(() => {
    if (typeof window === 'undefined') {
        return null;
    }

    const candidate =
        (window as Window & {
            SpeechRecognition?: BrowserSpeechRecognitionCtor;
            webkitSpeechRecognition?: BrowserSpeechRecognitionCtor;
        }).SpeechRecognition ??
        (window as Window & {
            SpeechRecognition?: BrowserSpeechRecognitionCtor;
            webkitSpeechRecognition?: BrowserSpeechRecognitionCtor;
        }).webkitSpeechRecognition;

    return candidate ?? null;
});
const canListen = computed(() => speechRecognitionCtor.value !== null);
const canSpeak = computed(
    () => typeof window !== 'undefined' && 'speechSynthesis' in window,
);

function topBeaufortBand() {
    const bands = dashboardSeaState.value?.beaufortBands ?? [];
    return [...bands].sort((left, right) => right.count - left.count)[0] ?? null;
}

function totalRescueEvents() {
    return (dashboardSeaState.value?.rescueTotals ?? []).reduce(
        (total, item) => total + item.count,
        0,
    );
}

function peakMonth() {
    const rows = dashboardMonthlyDistance.value.filter((item) => item.distanceKm > 0);
    return rows.sort((left, right) => right.distanceKm - left.distanceKm)[0] ?? null;
}

function dashboardCoachReply(): GuideReply {
    const headline = dashboardHeadline.value;
    const seaState = dashboardSeaState.value;

    if (!headline || !seaState) {
        return dashboardReply();
    }

    const distance = formatDistanceKm(headline.distanceKm, unitPreferences.value, 1);
    const speed =
        headline.averageSpeedKnots !== null
            ? formatSpeedKnots(headline.averageSpeedKnots, unitPreferences.value, 1)
            : 'no reliable speed average yet';
    const airTemperature =
        seaState.temperatureAverages.air !== null
            ? formatTemperatureC(
                  seaState.temperatureAverages.air,
                  unitPreferences.value,
                  0,
              )
            : 'no air temperature average yet';
    const seaTemperature =
        seaState.temperatureAverages.sea !== null
            ? formatTemperatureC(
                  seaState.temperatureAverages.sea,
                  unitPreferences.value,
                  0,
              )
            : 'no sea temperature average yet';
    const topBand = topBeaufortBand();
    const strongestPattern = topBand ? `${topBand.label} is the most common logged wind band` : 'wind exposure is still too patchy to call';
    const rescueCount = totalRescueEvents();
    const busiestMonth = peakMonth();

    const suggestions: string[] = [];

    if (headline.paddledMonths < 8) {
        suggestions.push(
            'Consistency first: add one shorter session in the quieter weeks so the log spreads across more months.',
        );
    } else {
        suggestions.push(
            'Consistency looks healthy. Keep one dependable weekly paddle in the diary so the momentum stays easy to maintain.',
        );
    }

    if (headline.averageSpeedKnots !== null && headline.averageSpeedKnots < 3.4) {
        suggestions.push(
            'Cruising speed is still a development area. Add one technique or tempo paddle next week with deliberate forward-stroke work.',
        );
    } else if (headline.averageSpeedKnots !== null) {
        suggestions.push(
            'Your cruising speed looks solid. The next gain is probably efficiency under fatigue or rougher water, not just paddling harder.',
        );
    } else {
        suggestions.push(
            'You need a few more tracked sessions to make speed coaching trustworthy. Keep tracing or importing routes when you can.',
        );
    }

    if ((seaState.averageBeaufort ?? 0) <= 2.5) {
        suggestions.push(
            'Most of the log still leans toward lighter conditions. Plan a coached F3 to low F4 skills day so your rougher-water judgment grows safely.',
        );
    } else {
        suggestions.push(
            'You already have some wind exposure logged. Keep mixing calmer technique paddles with one more committing conditions day, not just one type of outing.',
        );
    }

    if (rescueCount < 3) {
        suggestions.push(
            'Rescue work barely shows in the log. A focused rescue and recovery session would give you one of the highest-signal improvements for the next paddles.',
        );
    }

    if (
        headline.trackSessions > 0 &&
        headline.trackSessions / Math.max(headline.sessionCount, 1) < 0.55
    ) {
        suggestions.push(
            'Route coverage is still thin. Upload or trace more sessions so the speed, map, and progression metrics become more believable.',
        );
    }

    return {
        title: 'Coach’s read on your log',
        body: `You have ${headline.sessionCount} logged sessions covering ${distance}. Average speed is ${speed}. Air is averaging ${airTemperature}, sea ${seaTemperature}, and ${strongestPattern}. ${busiestMonth ? `${busiestMonth.label} is currently the busiest month in the log.` : ''}`.trim(),
        steps: suggestions.slice(0, 4),
        links: [
            buildLink('Open dashboard', '/dashboard'),
            buildLink('Plan next paddle', '/planning'),
            buildLink('Log a session', '/sessions/create'),
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
        defaultReply:
            path.startsWith('/dashboard') && dashboardHeadline.value
                ? dashboardCoachReply()
                : dashboardReply(),
    };
}

const context = computed(() => contextForPath(props.currentPath));
const visibleQuickLinks = computed(() => context.value.quickLinks.slice(0, 2));
const visibleReplySteps = computed(() => reply.value?.steps.slice(0, 2) ?? []);
const visibleReplyLinks = computed(() => reply.value?.links.slice(0, 2) ?? []);

function speakReply(target: GuideReply | null) {
    if (!target || !canSpeak.value || typeof window === 'undefined') {
        return;
    }

    window.speechSynthesis.cancel();

    const utterance = new SpeechSynthesisUtterance(
        [target.title, target.body, ...target.steps].join('. '),
    );
    utterance.lang = 'en-GB';
    utterance.rate = 1;
    utterance.pitch = 1;
    utterance.onend = () => {
        isSpeaking.value = false;
    };
    utterance.onerror = () => {
        isSpeaking.value = false;
    };

    isSpeaking.value = true;
    window.speechSynthesis.speak(utterance);
}

function stopSpeaking() {
    if (!canSpeak.value || typeof window === 'undefined') {
        return;
    }

    window.speechSynthesis.cancel();
    isSpeaking.value = false;
}

function coachMe() {
    const question = 'How am I doing and what should I work on in the next paddles?';
    prompt.value = question;
    ask(question, { speak: true });
}

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
        includesAny(query, [
            'coach',
            'metrics',
            'metric',
            'how am i doing',
            'work on',
            'next paddle',
            'next paddles',
            'analyse',
            'analyze',
            'summary',
        ])
    ) {
        return props.currentPath.startsWith('/dashboard') && dashboardHeadline.value
            ? dashboardCoachReply()
            : genericReply();
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

function ask(question = prompt.value, options: { speak?: boolean } = {}) {
    reply.value = resolveReply(question);
    if (question.trim()) {
        prompt.value = question.trim();
    }
    isOpen.value = true;
    showHint.value = false;
    lastVoiceError.value = null;

    if (options.speak) {
        speakReply(reply.value);
    }
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
    if (isListening.value) {
        recognition?.stop();
    }
    if (isSpeaking.value) {
        stopSpeaking();
    }
}

function startListening() {
    if (!speechRecognitionCtor.value || isListening.value) {
        return;
    }

    lastVoiceError.value = null;
    recognition = new speechRecognitionCtor.value();
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.lang = 'en-GB';
    recognition.maxAlternatives = 1;
    recognition.onresult = (event) => {
        const transcript = event.results?.[0]?.[0]?.transcript?.trim();

        if (transcript) {
            prompt.value = transcript;
            ask(transcript, { speak: true });
        }
    };
    recognition.onerror = (event) => {
        isListening.value = false;
        lastVoiceError.value =
            event.error === 'not-allowed'
                ? 'Microphone permission is blocked.'
                : 'Voice input could not start.';
    };
    recognition.onend = () => {
        isListening.value = false;
    };

    isListening.value = true;
    recognition.start();
}

function stopListening() {
    recognition?.stop();
    isListening.value = false;
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

    if (isListening.value) {
        recognition?.stop();
    }

    if (isSpeaking.value) {
        stopSpeaking();
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
                                    class="h-16 w-16 shrink-0 object-contain drop-shadow-[0_10px_18px_rgba(37,43,82,0.14)]"
                                    width="64"
                                    height="64"
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
                                type="button"
                                class="journal-utility-link min-h-[2rem] px-2.5 py-1.5 text-[0.72rem]"
                                @click="coachMe"
                            >
                                Coach me
                            </button>

                            <button
                                v-if="canListen"
                                type="button"
                                class="journal-utility-link min-h-[2rem] px-2.5 py-1.5 text-[0.72rem]"
                                @click="isListening ? stopListening() : startListening()"
                            >
                                <component
                                    :is="isListening ? Square : Mic"
                                    class="h-3.5 w-3.5"
                                    aria-hidden="true"
                                />
                                {{ isListening ? 'Stop' : 'Listen' }}
                            </button>

                            <button
                                v-if="canSpeak && reply"
                                type="button"
                                class="journal-utility-link min-h-[2rem] px-2.5 py-1.5 text-[0.72rem]"
                                @click="isSpeaking ? stopSpeaking() : speakReply(reply)"
                            >
                                <component
                                    :is="isSpeaking ? Square : Volume2"
                                    class="h-3.5 w-3.5"
                                    aria-hidden="true"
                                />
                                {{ isSpeaking ? 'Stop audio' : 'Read aloud' }}
                            </button>
                        </div>

                        <p
                            v-if="lastVoiceError"
                            class="text-[0.72rem] leading-4 text-[color:var(--journal-sand)]"
                        >
                            {{ lastVoiceError }}
                        </p>

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
                class="group relative z-50 inline-flex h-[5.6rem] w-[5.6rem] items-center justify-center transition hover:-translate-y-0.5"
                :aria-expanded="isOpen"
                aria-label="Open Kraken guide"
                @click="toggleOpen"
            >
                <img
                    :src="krakenGuideArt"
                    alt=""
                    class="relative h-[5.6rem] w-[5.6rem] object-contain drop-shadow-[0_18px_28px_rgba(37,43,82,0.2)]"
                    width="90"
                    height="90"
                />
            </button>
        </div>
    </div>
</template>
