<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref, toRefs } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import DefaultMapViewPicker from '@/components/maps/DefaultMapViewPicker.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Label } from '@/components/ui/label';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { update as updateProfile } from '@/routes/profile';
import { disable, enable } from '@/routes/two-factor';
import { update as updatePassword } from '@/routes/user-password';

type Props = {
    status?: string;
    requiresSetup: boolean;
    setupMode: boolean;
    security: {
        canManageTwoFactor: boolean;
        requiresConfirmation: boolean;
        twoFactorEnabled: boolean;
    };
    profile: {
        name: string;
        email: string;
        homeWater: string;
        settings: {
            paddlerName: string;
            kayakClub: string;
            kayaksOwnedText: string;
            paddlesOwnedText: string;
            defaultMapLat: string;
            defaultMapLng: string;
            defaultMapZoom: string;
        };
    };
};

const props = defineProps<Props>();
const { profile, security, setupMode, status } = toRefs(props);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Account settings',
                href: '/settings/profile',
            },
        ],
    },
});

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref(false);
const defaultMapLat = ref(profile.value.settings.defaultMapLat);
const defaultMapLng = ref(profile.value.settings.defaultMapLng);
const defaultMapZoom = ref(profile.value.settings.defaultMapZoom);

onUnmounted(() => clearTwoFactorAuthData());

function setDefaultMapPreset(lat: string, lng: string, zoom: string) {
    defaultMapLat.value = lat;
    defaultMapLng.value = lng;
    defaultMapZoom.value = zoom;
}
</script>

<template>
    <Head title="Account settings" />

    <SettingsLayout>
        <div class="space-y-5">
            <section v-if="setupMode" class="journal-panel px-5 py-5 md:px-6">
                <div class="space-y-3">
                    <p class="journal-kicker">First log-in</p>
                    <h1
                        class="text-[clamp(2rem,4vw,2.8rem)] leading-[0.94] text-[color:var(--journal-text)]"
                    >
                        Finish your paddler profile
                    </h1>
                    <p class="journal-copy max-w-3xl text-sm md:text-base">
                        Before we drop you into the journal, let’s set the
                        paddler name, club, and the gear you actually own.
                    </p>
                    <div class="journal-banner journal-banner--soft max-w-3xl">
                        Save this page once and the setup is done. You can
                        always come back later and adjust the details.
                    </div>
                </div>
            </section>

            <section v-if="status" class="journal-banner journal-banner--soft">
                {{ status }}
            </section>

            <div
                class="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]"
            >
                <section id="profile" class="journal-panel px-5 py-5 md:px-6">
                    <div class="space-y-2">
                        <p class="journal-kicker">Profile</p>
                        <h2
                            class="text-[1.85rem] leading-[0.98] text-[color:var(--journal-text)]"
                        >
                            Paddler details
                        </h2>
                        <p class="journal-copy max-w-2xl text-sm md:text-base">
                            Keep this part practical: account identity, club,
                            and the kayaks and paddles you want available
                            throughout the journal.
                        </p>
                    </div>

                    <Form
                        v-bind="updateProfile.form()"
                        :options="{ preserveScroll: true }"
                        class="mt-6 space-y-5"
                        v-slot="{ errors, processing, recentlySuccessful }"
                    >
                        <input
                            v-if="setupMode"
                            type="hidden"
                            name="finish_setup"
                            value="1"
                        />

                        <div class="grid gap-5 md:grid-cols-2">
                            <article class="journal-soft-card">
                                <Label class="journal-field-label" for="name"
                                    >Account name</Label
                                >
                                <input
                                    id="name"
                                    class="journal-input"
                                    name="name"
                                    required
                                    autocomplete="name"
                                    :value="profile.name"
                                    placeholder="Full name"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="errors.name"
                                />
                            </article>

                            <article class="journal-soft-card">
                                <Label class="journal-field-label" for="email"
                                    >Email address</Label
                                >
                                <input
                                    id="email"
                                    type="email"
                                    class="journal-input"
                                    name="email"
                                    required
                                    autocomplete="username"
                                    :value="profile.email"
                                    placeholder="email@example.com"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="errors.email"
                                />
                            </article>

                            <article class="journal-soft-card">
                                <Label
                                    class="journal-field-label"
                                    for="paddler_name"
                                    >Paddler name</Label
                                >
                                <input
                                    id="paddler_name"
                                    class="journal-input"
                                    name="paddler_name"
                                    :value="
                                        profile.settings.paddlerName ||
                                        profile.name
                                    "
                                    placeholder="Francesco Li Vigni"
                                />
                                <p
                                    class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                                >
                                    This is the name shown around the journal
                                    itself.
                                </p>
                                <InputError
                                    class="mt-2"
                                    :message="errors.paddler_name"
                                />
                            </article>

                            <article class="journal-soft-card">
                                <Label
                                    class="journal-field-label"
                                    for="kayak_club"
                                    >Kayak club affiliated</Label
                                >
                                <input
                                    id="kayak_club"
                                    class="journal-input"
                                    name="kayak_club"
                                    :value="profile.settings.kayakClub"
                                    placeholder="Manual text entry for now"
                                />
                                <p
                                    class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                                >
                                    Keep it flexible. This is just free text for
                                    now.
                                </p>
                                <InputError
                                    class="mt-2"
                                    :message="errors.kayak_club"
                                />
                            </article>

                            <article class="journal-soft-card">
                                <Label
                                    class="journal-field-label"
                                    for="kayaks_owned_text"
                                    >Kayaks owned</Label
                                >
                                <textarea
                                    id="kayaks_owned_text"
                                    class="journal-textarea min-h-[120px]"
                                    name="kayaks_owned_text"
                                    :value="profile.settings.kayaksOwnedText"
                                    placeholder="Valley Etain 17-7, P&H Scorpio MV"
                                />
                                <p
                                    class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                                >
                                    Add model names separated by commas. These
                                    become suggestions during session logging.
                                </p>
                                <InputError
                                    class="mt-2"
                                    :message="errors.kayaks_owned_text"
                                />
                            </article>

                            <article class="journal-soft-card">
                                <Label
                                    class="journal-field-label"
                                    for="paddles_owned_text"
                                    >Paddles owned</Label
                                >
                                <textarea
                                    id="paddles_owned_text"
                                    class="journal-textarea min-h-[120px]"
                                    name="paddles_owned_text"
                                    :value="profile.settings.paddlesOwnedText"
                                    placeholder="Werner Cyprus, Gearlab Kalleq"
                                />
                                <p
                                    class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                                >
                                    Add the actual paddle models you rotate
                                    between.
                                </p>
                                <InputError
                                    class="mt-2"
                                    :message="errors.paddles_owned_text"
                                />
                            </article>

                            <article class="journal-soft-card md:col-span-2">
                                <Label class="journal-field-label"
                                    >Default session map view</Label
                                >
                                <p
                                    class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                                >
                                    The Place session map opens here when a new
                                    session has no pin yet. Click the map or
                                    drag the pin to choose the default starting
                                    area.
                                </p>

                                <DefaultMapViewPicker
                                    class="mt-4"
                                    v-model:lat="defaultMapLat"
                                    v-model:lng="defaultMapLng"
                                    v-model:zoom="defaultMapZoom"
                                    :saved="recentlySuccessful"
                                    :errors="{
                                        lat: errors.default_map_lat,
                                        lng: errors.default_map_lng,
                                        zoom: errors.default_map_zoom,
                                    }"
                                />

                                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                    <button
                                        class="journal-chip"
                                        type="button"
                                        @click="
                                            setDefaultMapPreset(
                                                '64.167000',
                                                '-21.821000',
                                                '10',
                                            )
                                        "
                                    >
                                        Faxafloi
                                    </button>
                                    <button
                                        class="journal-chip"
                                        type="button"
                                        @click="
                                            setDefaultMapPreset(
                                                '64.146600',
                                                '-21.942600',
                                                '11',
                                            )
                                        "
                                    >
                                        Reykjavik
                                    </button>
                                </div>
                            </article>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                class="journal-primary-link"
                                type="submit"
                                :disabled="processing"
                                data-test="update-profile-button"
                            >
                                {{
                                    processing
                                        ? 'Saving...'
                                        : setupMode
                                          ? 'Finish setup'
                                          : 'Save profile'
                                }}
                            </button>
                            <p
                                v-if="recentlySuccessful"
                                class="journal-banner journal-banner--soft"
                            >
                                {{
                                    setupMode
                                        ? 'Profile setup saved.'
                                        : 'Profile saved.'
                                }}
                            </p>
                        </div>
                    </Form>
                </section>

                <div class="space-y-5">
                    <section
                        id="security"
                        class="journal-panel px-5 py-5 md:px-6"
                    >
                        <div class="space-y-2">
                            <p class="journal-kicker">Security</p>
                            <h2
                                class="text-[1.65rem] leading-[0.98] text-[color:var(--journal-text)]"
                            >
                                Password
                            </h2>
                            <p class="journal-copy text-sm md:text-base">
                                Keep sign-in strong without making this page
                                feel like an admin console.
                            </p>
                        </div>

                        <Form
                            v-bind="updatePassword.form()"
                            :options="{ preserveScroll: true }"
                            reset-on-success
                            :reset-on-error="[
                                'password',
                                'password_confirmation',
                                'current_password',
                            ]"
                            class="mt-6 space-y-4"
                            v-slot="{ errors, processing, recentlySuccessful }"
                        >
                            <article class="journal-soft-card">
                                <Label
                                    class="journal-field-label"
                                    for="current_password"
                                    >Current password</Label
                                >
                                <PasswordInput
                                    id="current_password"
                                    name="current_password"
                                    class="journal-input mt-1 block w-full"
                                    autocomplete="current-password"
                                    placeholder="Current password"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="errors.current_password"
                                />
                            </article>

                            <article class="journal-soft-card">
                                <Label
                                    class="journal-field-label"
                                    for="password"
                                    >New password</Label
                                >
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    class="journal-input mt-1 block w-full"
                                    autocomplete="new-password"
                                    placeholder="New password"
                                />
                                <p
                                    class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                                >
                                    Use at least 12 characters with uppercase,
                                    lowercase, a number, and a symbol.
                                </p>
                                <InputError
                                    class="mt-2"
                                    :message="errors.password"
                                />
                            </article>

                            <article class="journal-soft-card">
                                <Label
                                    class="journal-field-label"
                                    for="password_confirmation"
                                    >Confirm password</Label
                                >
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    class="journal-input mt-1 block w-full"
                                    autocomplete="new-password"
                                    placeholder="Confirm new password"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="errors.password_confirmation"
                                />
                            </article>

                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    type="submit"
                                    :disabled="processing"
                                    class="journal-primary-link disabled:cursor-not-allowed disabled:opacity-70"
                                    data-test="update-password-button"
                                >
                                    {{
                                        processing
                                            ? 'Saving...'
                                            : 'Save password'
                                    }}
                                </button>

                                <p
                                    v-if="recentlySuccessful"
                                    class="journal-banner journal-banner--soft"
                                >
                                    Password updated.
                                </p>
                            </div>
                        </Form>
                    </section>

                    <section
                        id="appearance"
                        class="journal-panel px-5 py-5 md:px-6"
                    >
                        <div class="space-y-2">
                            <p class="journal-kicker">Appearance</p>
                            <h2
                                class="text-[1.65rem] leading-[0.98] text-[color:var(--journal-text)]"
                            >
                                Themes
                            </h2>
                            <p class="journal-copy text-sm md:text-base">
                                Choose the journal surface you want to work in.
                                These themes now change the full workspace, not
                                just a token switch.
                            </p>
                        </div>

                        <div class="mt-6">
                            <AppearanceTabs />
                        </div>
                    </section>
                </div>
            </div>

            <section
                v-if="security.canManageTwoFactor"
                class="journal-panel px-5 py-5 md:px-6"
            >
                <div class="space-y-2">
                    <p class="journal-kicker">Security</p>
                    <h2
                        class="text-[1.75rem] leading-[0.98] text-[color:var(--journal-text)]"
                    >
                        Two-factor authentication
                    </h2>
                    <p class="journal-copy max-w-2xl text-sm md:text-base">
                        Optional extra security when you want a second step at
                        sign-in without making the normal journal flow heavier.
                    </p>
                </div>

                <div
                    v-if="!security.twoFactorEnabled"
                    class="mt-6 flex flex-col items-start gap-4"
                >
                    <div class="journal-banner journal-banner--soft max-w-3xl">
                        Enable an authenticator code if you want a second lock
                        on the account.
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
                            <button
                                type="submit"
                                :disabled="processing"
                                class="journal-primary-link disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                {{ processing ? 'Enabling...' : 'Enable 2FA' }}
                            </button>
                        </Form>
                    </div>
                </div>

                <div v-else class="mt-6 flex flex-col items-start gap-4">
                    <div class="journal-banner journal-banner--soft max-w-3xl">
                        Two-factor is active. You will be asked for an
                        authenticator code after entering your password.
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

                    <div class="journal-soft-card w-full p-5">
                        <TwoFactorRecoveryCodes />
                    </div>
                </div>

                <TwoFactorSetupModal
                    v-model:isOpen="showSetupModal"
                    :requiresConfirmation="security.requiresConfirmation"
                    :twoFactorEnabled="security.twoFactorEnabled"
                />
            </section>

            <DeleteUser />
        </div>
    </SettingsLayout>
</template>
