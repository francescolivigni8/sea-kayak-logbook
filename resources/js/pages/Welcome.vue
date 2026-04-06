<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const highlights = [
    'Private profiles with first-party Laravel auth',
    'Structured session logging for sea conditions, rescues, and expeditions',
    'A rebuild path for Garmin imports, GPX storage, and richer diary screens',
];

const dashboardCards = [
    {
        label: 'Track',
        value: 'Sessions',
        detail: 'Distance, duration, launch point, tags, and route files.',
        tone: 'from-cyan-100 to-sky-100',
    },
    {
        label: 'Reflect',
        value: 'Notes',
        detail: 'Observations, expedition learnings, and next-trip improvements.',
        tone: 'from-violet-100 to-indigo-100',
    },
    {
        label: 'Show',
        value: 'Experience',
        detail: 'Charts, exposure history, expedition totals, and public-ready stats.',
        tone: 'from-amber-100 to-orange-100',
    },
];
</script>

<template>
    <Head title="Sea Kayak Logbook" />

    <div
        class="min-h-screen bg-[radial-gradient(circle_at_top,rgba(207,250,254,0.6),transparent_30%),linear-gradient(180deg,#fbfdff_0%,#f6f7fb_48%,#fbfdff_100%)] px-6 py-8 text-slate-900 md:px-8"
    >
        <div class="mx-auto flex max-w-7xl flex-col gap-8">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-orange-400">
                        Laravel + Vue rebuild
                    </p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight md:text-5xl">
                        Sea Kayak Logbook
                    </h1>
                </div>

                <nav class="flex items-center gap-3">
                    <Link
                        :href="$page.props.auth.user ? dashboard() : login()"
                        class="rounded-full border border-slate-200 bg-white/90 px-5 py-2 text-sm font-medium shadow-sm transition hover:border-slate-300"
                    >
                        {{ $page.props.auth.user ? 'Open dashboard' : 'Log in' }}
                    </Link>
                    <Link
                        v-if="!$page.props.auth.user && canRegister"
                        :href="register()"
                        class="rounded-full bg-slate-900 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800"
                    >
                        Create workspace
                    </Link>
                </nav>
            </header>

            <section
                class="grid gap-6 overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/85 p-6 shadow-[0_20px_60px_-35px_rgba(15,23,42,0.35)] md:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)] md:p-8"
            >
                <div class="space-y-5">
                    <p class="max-w-2xl text-lg leading-8 text-slate-600 md:text-xl">
                        A dedicated expedition journal for sea kayaking: real accounts, real profiles, structured sessions, and a dashboard that can grow from private training log into a public experience showcase.
                    </p>

                    <ul class="grid gap-3 text-sm leading-6 text-slate-600">
                        <li
                            v-for="highlight in highlights"
                            :key="highlight"
                            class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3"
                        >
                            {{ highlight }}
                        </li>
                    </ul>
                </div>

                <div class="grid gap-4">
                    <article
                        v-for="card in dashboardCards"
                        :key="card.label"
                        class="rounded-[1.6rem] border border-slate-200/80 bg-white p-5 shadow-sm"
                    >
                        <span
                            class="inline-flex rounded-full bg-gradient-to-r px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-600"
                            :class="card.tone"
                        >
                            {{ card.label }}
                        </span>
                        <h2 class="mt-4 text-2xl font-semibold text-slate-900">
                            {{ card.value }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            {{ card.detail }}
                        </p>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>
