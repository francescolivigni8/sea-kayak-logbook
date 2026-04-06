<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { logout } from '@/routes';

const page = usePage();
</script>

<template>
    <Head title="Workspace" />

    <main class="min-h-screen bg-slate-50 px-4 py-8 text-slate-900 md:px-8">
        <div class="mx-auto flex max-w-4xl flex-col gap-6">
            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                    Logged in
                </p>
                <h1 class="mt-3 text-4xl font-semibold tracking-tight">
                    Your kayak workspace
                </h1>
                <p class="mt-3 text-sm leading-7 text-slate-600">
                    Auth is working. Use this lightweight workspace as the first stop while we keep polishing the richer app views.
                </p>

                <div class="mt-5 flex flex-wrap gap-3 text-sm text-slate-600">
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">
                        {{ $page.props.auth.user?.name }}
                    </span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">
                        {{ $page.props.auth.user?.email }}
                    </span>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link href="/sessions" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Logbook</p>
                    <h2 class="mt-3 text-2xl font-semibold">Sessions</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Browse, edit, and add paddles.</p>
                </Link>

                <Link href="/dashboard" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Stats</p>
                    <h2 class="mt-3 text-2xl font-semibold">Dashboard</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Open the chart-first metrics view.</p>
                </Link>

                <Link href="/diary" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Calendar</p>
                    <h2 class="mt-3 text-2xl font-semibold">Diary</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Review paddles by day.</p>
                </Link>

                <Link href="/expeditions" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Atlas</p>
                    <h2 class="mt-3 text-2xl font-semibold">Expeditions</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">World pins and multiday history.</p>
                </Link>

                <Link href="/imports/garmin" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Import</p>
                    <h2 class="mt-3 text-2xl font-semibold">Garmin</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Bring in CSV, GPX, and FIT files.</p>
                </Link>

                <Link href="/settings/profile" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Account</p>
                    <h2 class="mt-3 text-2xl font-semibold">Settings</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Profile, password, and security.</p>
                </Link>
            </section>

            <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <Link
                    :href="logout()"
                    method="post"
                    as="button"
                    class="rounded-full border border-slate-300 px-5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Log out
                </Link>
            </section>
        </div>
    </main>
</template>
