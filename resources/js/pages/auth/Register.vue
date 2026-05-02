<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineOptions({
    layout: {
        title: 'Create your kayak workspace',
        description:
            'Start a personal sea-kayak logbook with your own profile, dashboard, and expedition history.',
    },
});

type LegalShape = {
    productName?: string;
    copyrightOwner?: string;
};

const page = usePage();
const legal = (page.props.legal as LegalShape | undefined) ?? null;
const productName = legal?.productName || 'Your Kayaking Journal';
const copyrightOwner = legal?.copyrightOwner || 'Francesco Li Vigni';
</script>

<template>
    <Head title="Create your kayak workspace" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Full name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="grid gap-2">
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
                        <a
                            href="/terms"
                            target="_blank"
                            rel="noopener"
                            class="font-semibold text-[color:var(--journal-text)] underline underline-offset-4"
                        >
                            Terms
                        </a>.
                    </span>
                </label>
                <InputError :message="errors.accept_terms" />
            </div>

            <div class="grid gap-2">
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
                        <a
                            href="/privacy"
                            target="_blank"
                            rel="noopener"
                            class="font-semibold text-[color:var(--journal-text)] underline underline-offset-4"
                        >
                            Privacy Policy
                        </a>.
                    </span>
                </label>
                <InputError :message="errors.accept_privacy" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
                >Log in</TextLink
            >
        </div>

        <div class="text-center text-xs leading-6 text-muted-foreground">
            <p>
                © {{ new Date().getFullYear() }} {{ copyrightOwner }}.
                {{ productName }}. All rights reserved.
            </p>
            <p>
                Use of the app does not transfer ownership of the brand,
                original design, or protected content.
            </p>
        </div>
    </Form>
</template>
