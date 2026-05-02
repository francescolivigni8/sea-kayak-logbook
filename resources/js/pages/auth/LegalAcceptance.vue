<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Accept the latest terms',
        description:
            'Before reopening the journal, confirm the current Terms and Privacy Policy.',
    },
});

type LegalShape = {
    productName?: string;
    copyrightOwner?: string;
};

defineProps<{
    termsVersion: string;
    privacyVersion: string;
    setupRequired: boolean;
}>();

const page = usePage();
const legal = (page.props.legal as LegalShape | undefined) ?? null;
const productName = legal?.productName || 'Your Kayaking Journal';
const copyrightOwner = legal?.copyrightOwner || 'Francesco Li Vigni';
</script>

<template>
    <Head title="Accept the latest terms" />

    <Form
        action="/legal/acceptance"
        method="patch"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="space-y-4 rounded-[1.6rem] border border-[color:var(--journal-line)] bg-white/78 p-5 shadow-[0_22px_40px_rgba(37,43,82,0.08)]">
            <p class="journal-kicker">Legal update</p>
            <h1 class="text-2xl font-semibold text-[color:var(--journal-text)]">
                Please confirm the current policies
            </h1>
            <p class="text-sm leading-7 text-[color:var(--journal-muted)]">
                We now record acceptance of the current Terms and Privacy
                Policy so the journal has a clear legal trail for account
                access and future policy updates.
            </p>
            <p
                v-if="setupRequired"
                class="rounded-[1.1rem] border border-[rgba(103,114,255,0.14)] bg-[rgba(103,114,255,0.08)] px-4 py-3 text-sm leading-6 text-[color:var(--journal-text)]"
            >
                Once this is confirmed, we’ll continue to your profile setup.
            </p>
        </div>

        <div class="grid gap-3">
            <label
                for="accept_terms"
                class="flex items-start gap-3 rounded-[1.15rem] border border-[color:var(--journal-line)] bg-white/72 px-4 py-3 text-sm leading-6 text-[color:var(--journal-muted)]"
            >
                <input
                    id="accept_terms"
                    type="checkbox"
                    name="accept_terms"
                    value="1"
                    required
                    class="mt-1 size-4 rounded border-[color:var(--journal-line)]"
                />
                <span>
                    I have read and accept the
                    <Link
                        href="/terms"
                        target="_blank"
                        class="font-semibold text-[color:var(--journal-text)] underline underline-offset-4"
                    >
                        Terms
                    </Link>
                    <span class="text-[color:var(--journal-faint)]">
                        (version {{ termsVersion }})
                    </span>
                    .
                </span>
            </label>
            <InputError :message="errors.accept_terms" />
        </div>

        <div class="grid gap-3">
            <label
                for="accept_privacy"
                class="flex items-start gap-3 rounded-[1.15rem] border border-[color:var(--journal-line)] bg-white/72 px-4 py-3 text-sm leading-6 text-[color:var(--journal-muted)]"
            >
                <input
                    id="accept_privacy"
                    type="checkbox"
                    name="accept_privacy"
                    value="1"
                    required
                    class="mt-1 size-4 rounded border-[color:var(--journal-line)]"
                />
                <span>
                    I have read and accept the
                    <Link
                        href="/privacy"
                        target="_blank"
                        class="font-semibold text-[color:var(--journal-text)] underline underline-offset-4"
                    >
                        Privacy Policy
                    </Link>
                    <span class="text-[color:var(--journal-faint)]">
                        (version {{ privacyVersion }})
                    </span>
                    .
                </span>
            </label>
            <InputError :message="errors.accept_privacy" />
        </div>

        <div class="rounded-[1.15rem] border border-[rgba(37,43,82,0.08)] bg-[rgba(255,255,255,0.65)] px-4 py-3 text-xs leading-6 text-[color:var(--journal-faint)]">
            © {{ new Date().getFullYear() }} {{ copyrightOwner }}.
            {{ productName }}. All rights reserved. Use of the service does not
            transfer ownership of the brand, original design, or protected
            materials.
        </div>

        <Button class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            Accept and continue
        </Button>
    </Form>
</template>
