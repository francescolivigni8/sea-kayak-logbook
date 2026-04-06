<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Email verification" />

    <div class="space-y-6 text-center">
        <p class="text-sm leading-7 text-slate-600">
            Email verification is currently disabled in this environment while live auth and deployment are being stabilized.
        </p>

        <Link
            href="/workspace"
            class="inline-flex rounded-full border border-slate-300 px-5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
        >
            Continue to workspace
        </Link>
    </div>
</template>
