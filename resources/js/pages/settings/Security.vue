<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Label } from '@/components/ui/label';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
        ],
    },
});

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <Head title="Security settings" />

    <h1 class="sr-only">Security settings</h1>

    <SettingsLayout>
        <div class="space-y-6">
            <section class="journal-panel px-5 py-5 md:px-6">
                <div class="space-y-2">
                    <p class="journal-kicker">Security</p>
                    <h2 class="text-[1.85rem] leading-[0.98] text-[color:var(--journal-text)]">
                        Password
                    </h2>
                    <p class="journal-copy max-w-2xl text-sm md:text-base">
                        Keep sign-in straightforward here with a strong password and a clean recovery path.
                    </p>
                </div>

                <Form
                    v-bind="SecurityController.update.form()"
                    :options="{
                        preserveScroll: true,
                    }"
                    reset-on-success
                    :reset-on-error="[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]"
                    class="mt-6 space-y-5"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-5 md:grid-cols-3">
                        <article class="journal-soft-card">
                            <Label class="journal-field-label" for="current_password">Current password</Label>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                class="journal-input mt-1 block w-full"
                                autocomplete="current-password"
                                placeholder="Current password"
                            />
                            <InputError class="mt-2" :message="errors.current_password" />
                        </article>

                        <article class="journal-soft-card">
                            <Label class="journal-field-label" for="password">New password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                class="journal-input mt-1 block w-full"
                                autocomplete="new-password"
                                placeholder="New password"
                            />
                            <InputError class="mt-2" :message="errors.password" />
                        </article>

                        <article class="journal-soft-card">
                            <Label class="journal-field-label" for="password_confirmation">Confirm password</Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                class="journal-input mt-1 block w-full"
                                autocomplete="new-password"
                                placeholder="Confirm password"
                            />
                            <InputError class="mt-2" :message="errors.password_confirmation" />
                        </article>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            :disabled="processing"
                            class="journal-primary-link disabled:cursor-not-allowed disabled:opacity-70"
                            data-test="update-password-button"
                        >
                            {{ processing ? 'Saving...' : 'Save password' }}
                        </button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="journal-banner journal-banner--soft"
                            >
                                Password updated.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </section>

            <section v-if="canManageTwoFactor" class="journal-panel px-5 py-5 md:px-6">
                <div class="space-y-2">
                    <p class="journal-kicker">Security</p>
                    <h2 class="text-[1.85rem] leading-[0.98] text-[color:var(--journal-text)]">
                        Two-factor authentication
                    </h2>
                    <p class="journal-copy max-w-2xl text-sm md:text-base">
                        Optional extra security when you want a second step at sign-in without complicating the normal journal flow.
                    </p>
                </div>

                <div
                    v-if="!twoFactorEnabled"
                    class="mt-6 flex flex-col items-start gap-4"
                >
                    <div class="journal-banner journal-banner--soft max-w-3xl">
                        Enable a time-based code from your authenticator app if you want a second lock on the account.
                    </div>

                    <div>
                        <button
                            v-if="hasSetupData"
                            type="button"
                            class="journal-primary-link"
                            @click="showSetupModal = true"
                        >
                            <ShieldCheck class="h-4 w-4" />
                            Continue setup
                        </button>
                        <Form
                            v-else
                            v-bind="enable.form()"
                            @success="showSetupModal = true"
                            #default="{ processing }"
                        >
                            <button type="submit" :disabled="processing" class="journal-primary-link disabled:cursor-not-allowed disabled:opacity-70">
                                {{ processing ? 'Enabling...' : 'Enable 2FA' }}
                            </button>
                        </Form>
                    </div>
                </div>

                <div v-else class="mt-6 flex flex-col items-start gap-4">
                    <div class="journal-banner journal-banner--soft max-w-3xl">
                        Two-factor is active. You will be asked for an authenticator code after entering your password.
                    </div>

                    <Form v-bind="disable.form()" #default="{ processing }">
                        <button
                            type="submit"
                            :disabled="processing"
                            class="inline-flex items-center justify-center rounded-full border border-[rgba(255,138,128,0.42)] bg-[rgba(255,255,255,0.9)] px-4 py-3 text-sm font-semibold text-[#9c4841] transition hover:-translate-y-px hover:bg-[rgba(255,241,240,0.95)] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ processing ? 'Disabling...' : 'Disable 2FA' }}
                        </button>
                    </Form>

                    <div class="w-full rounded-[24px] border border-[color:var(--journal-line)] bg-white/72 p-5">
                        <TwoFactorRecoveryCodes />
                    </div>
                </div>

                <TwoFactorSetupModal
                    v-model:isOpen="showSetupModal"
                    :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled"
                />
            </section>
        </div>
    </SettingsLayout>
</template>
