<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Download, FileText, ShieldCheck } from 'lucide-vue-next';
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
import type {
    CurrentUnit,
    DistanceUnit,
    SpeedUnit,
    TemperatureUnit,
    WindUnit,
} from '@/lib/units';
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
        reportUrl: string;
        backupUrl: string;
        exportUrl: string;
        settings: {
            paddlerName: string;
            kayakClub: string;
            kayaksOwnedText: string;
            paddlesOwnedText: string;
            unitPreferences: {
                distance: DistanceUnit;
                speed: SpeedUnit;
                wind: WindUnit;
                current: CurrentUnit;
                temperature: TemperatureUnit;
            };
            defaultMapLat: string;
            defaultMapLng: string;
            defaultMapZoom: string;
            hasCustomDefaultMapView: boolean;
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
const distanceUnit = ref(profile.value.settings.unitPreferences.distance);
const speedUnit = ref(profile.value.settings.unitPreferences.speed);
const windUnit = ref(profile.value.settings.unitPreferences.wind);
const currentUnit = ref(profile.value.settings.unitPreferences.current);
const temperatureUnit = ref(profile.value.settings.unitPreferences.temperature);
const defaultMapLat = ref(profile.value.settings.defaultMapLat);
const defaultMapLng = ref(profile.value.settings.defaultMapLng);
const defaultMapZoom = ref(profile.value.settings.defaultMapZoom);

const distanceUnitOptions: Array<{ value: DistanceUnit; label: string }> = [
    { value: 'km', label: 'Kilometres (km)' },
    { value: 'nm', label: 'Nautical miles (nm)' },
];
const speedUnitOptions: Array<{ value: SpeedUnit; label: string }> = [
    { value: 'kmh', label: 'Kilometres per hour (km/h)' },
    { value: 'kt', label: 'Knots (kt)' },
];
const windUnitOptions: Array<{ value: WindUnit; label: string }> = [
    { value: 'ms', label: 'Metres per second (m/s)' },
    { value: 'kmh', label: 'Kilometres per hour (km/h)' },
    { value: 'kt', label: 'Knots (kt)' },
];
const currentUnitOptions: Array<{ value: CurrentUnit; label: string }> = [
    { value: 'kt', label: 'Knots (kt)' },
    { value: 'kmh', label: 'Kilometres per hour (km/h)' },
    { value: 'ms', label: 'Metres per second (m/s)' },
];
const temperatureUnitOptions: Array<{
    value: TemperatureUnit;
    label: string;
}> = [
    { value: 'c', label: 'Celsius (C)' },
    { value: 'f', label: 'Fahrenheit (F)' },
];

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
                                    >Units used around the journal</Label
                                >
                                <p
                                    class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                                >
                                    Set each measurement separately instead of
                                    locking the app into one preset. These
                                    preferences are used anywhere the journal
                                    shows distance, speed, wind, current, and
                                    temperature.
                                </p>

                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    <label class="journal-soft-card">
                                        <span class="journal-field-label"
                                            >Distance</span
                                        >
                                        <select
                                            v-model="distanceUnit"
                                            name="distance_unit"
                                            class="journal-input mt-3"
                                        >
                                            <option
                                                v-for="option in distanceUnitOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <InputError
                                            class="mt-2"
                                            :message="errors.distance_unit"
                                        />
                                    </label>

                                    <label class="journal-soft-card">
                                        <span class="journal-field-label"
                                            >Paddling speed</span
                                        >
                                        <select
                                            v-model="speedUnit"
                                            name="speed_unit"
                                            class="journal-input mt-3"
                                        >
                                            <option
                                                v-for="option in speedUnitOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <InputError
                                            class="mt-2"
                                            :message="errors.speed_unit"
                                        />
                                    </label>

                                    <label class="journal-soft-card">
                                        <span class="journal-field-label"
                                            >Wind</span
                                        >
                                        <select
                                            v-model="windUnit"
                                            name="wind_unit"
                                            class="journal-input mt-3"
                                        >
                                            <option
                                                v-for="option in windUnitOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <InputError
                                            class="mt-2"
                                            :message="errors.wind_unit"
                                        />
                                    </label>

                                    <label class="journal-soft-card">
                                        <span class="journal-field-label"
                                            >Current</span
                                        >
                                        <select
                                            v-model="currentUnit"
                                            name="current_unit"
                                            class="journal-input mt-3"
                                        >
                                            <option
                                                v-for="option in currentUnitOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <InputError
                                            class="mt-2"
                                            :message="errors.current_unit"
                                        />
                                    </label>

                                    <label class="journal-soft-card md:col-span-2">
                                        <span class="journal-field-label"
                                            >Temperature</span
                                        >
                                        <select
                                            v-model="temperatureUnit"
                                            name="temperature_unit"
                                            class="journal-input mt-3"
                                        >
                                            <option
                                                v-for="option in temperatureUnitOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <InputError
                                            class="mt-2"
                                            :message="errors.temperature_unit"
                                        />
                                    </label>
                                </div>
                            </article>

                            <article class="journal-soft-card md:col-span-2">
                                <Label class="journal-field-label"
                                    >Default planning + session map area</Label
                                >
                                <p
                                    class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]"
                                >
                                    New planning maps and the Place session map
                                    both open here when there is no saved pin
                                    yet. Click the map or drag the marker to
                                    set your local starting area once.
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
                                <p
                                    v-if="
                                        !profile.settings.hasCustomDefaultMapView
                                    "
                                    class="mt-3 text-xs leading-5 text-[color:var(--journal-muted)]"
                                >
                                    You are still on the fallback area, so the
                                    planner will open around Iceland until you
                                    save your own local map.
                                </p>
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

                    <section
                        id="reports"
                        class="journal-panel px-5 py-5 md:px-6"
                    >
                        <div class="space-y-2">
                            <p class="journal-kicker">Reports</p>
                            <h2
                                class="text-[1.65rem] leading-[0.98] text-[color:var(--journal-text)]"
                            >
                                Application report
                            </h2>
                            <p class="journal-copy text-sm md:text-base">
                                Generate a polished report from your full
                                journal data when you need to apply for an
                                advanced course, assessment, or coaching block.
                            </p>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <a
                                :href="profile.reportUrl"
                                target="_blank"
                                rel="noopener"
                                class="journal-primary-link"
                            >
                                <FileText class="h-4 w-4" />
                                Generate report
                            </a>
                            <p
                                class="max-w-sm text-xs leading-5 text-[color:var(--journal-muted)]"
                            >
                                Opens a print-ready application report. Use
                                browser Save as PDF when you want the final
                                file.
                            </p>
                        </div>
                    </section>

                    <section
                        id="privacy"
                        class="journal-panel px-5 py-5 md:px-6"
                    >
                        <div class="space-y-2">
                            <p class="journal-kicker">Privacy</p>
                            <h2
                                class="text-[1.65rem] leading-[0.98] text-[color:var(--journal-text)]"
                            >
                                Your data
                            </h2>
                            <p class="journal-copy text-sm md:text-base">
                                Download a JSON copy of your account, profile,
                                logged sessions, planned sessions, folders, and
                                journal metadata.
                            </p>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <a
                                :href="profile.backupUrl"
                                class="journal-primary-link"
                            >
                                <Download class="h-4 w-4" />
                                Download backup package
                            </a>
                            <a
                                :href="profile.exportUrl"
                                class="journal-utility-link"
                            >
                                Raw JSON export
                            </a>
                            <p
                                class="max-w-md text-xs leading-5 text-[color:var(--journal-muted)]"
                            >
                                The hosted journal is backed up on the server
                                side, and this gives you a local copy under
                                your control too. The backup package includes a
                                JSON export, planned-route GPX files, and any
                                uploaded GPX, FIT, or photo files that still
                                exist in storage.
                            </p>
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
