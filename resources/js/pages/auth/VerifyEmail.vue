<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email verification" />

    <div class="mx-auto max-w-xl space-y-6 text-center">
        <div class="space-y-3">
            <p class="journal-kicker">Verify email</p>
            <h1
                class="text-[clamp(2rem,6vw,3.5rem)] leading-[0.94] text-[color:var(--journal-text)]"
            >
                Check your inbox
            </h1>
            <p class="journal-copy text-sm md:text-base">
                We sent a verification link to your email address. Verify it
                before opening the journal workspace so public beta accounts
                are tied to real reachable emails.
            </p>
        </div>

        <p v-if="status" class="journal-banner journal-banner--soft">
            {{ status }}
        </p>

        <Form
            action="/email/verification-notification"
            method="post"
            #default="{ processing, recentlySuccessful }"
        >
            <button
                type="submit"
                :disabled="processing"
                class="journal-primary-link disabled:cursor-not-allowed disabled:opacity-70"
            >
                {{ processing ? 'Sending...' : 'Resend verification email' }}
            </button>
            <p
                v-if="recentlySuccessful"
                class="mt-3 text-sm text-[color:var(--journal-muted)]"
            >
                Verification email sent.
            </p>
        </Form>

        <Link
            href="/settings/profile"
            class="inline-flex rounded-full border border-[rgba(103,114,255,0.22)] bg-white/70 px-5 py-2 text-sm font-medium text-[color:var(--journal-text)] transition hover:-translate-y-px hover:bg-white"
        >
            Account settings
        </Link>
    </div>
</template>
