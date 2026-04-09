<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    profile: {
        name: string;
        homeWater: string;
        settings: {
            paddlerName: string;
            kayakClub: string;
            registeredKayaksCount: number;
            registeredPaddlesCount: number;
            bio: string;
        };
    };
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profile settings" />

    <SettingsLayout>
        <div class="space-y-5">
            <section class="journal-panel px-5 py-5 md:px-6">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-2">
                        <p class="journal-kicker">Profile</p>
                        <h2 class="text-[1.85rem] leading-[0.98] text-[color:var(--journal-text)]">
                            Profile information
                        </h2>
                        <p class="journal-copy max-w-2xl text-sm md:text-base">
                            Keep the core identity clean here. This controls how your account appears across the private workspace.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span class="journal-chip journal-chip--primary">
                            {{ props.profile.settings.paddlerName || props.profile.name }}
                        </span>
                        <span class="journal-chip journal-chip--primary">
                            {{ user.name }}
                        </span>
                        <span class="journal-chip">
                            {{ user.email }}
                        </span>
                    </div>
                </div>

                <form
                    method="post"
                    :action="edit().url"
                    class="mt-6 space-y-5"
                >
                    <input type="hidden" name="_method" value="patch" />
                    <input type="hidden" name="_token" :value="page.props?.csrf_token" />

                    <div class="grid gap-5 md:grid-cols-2">
                        <article class="journal-soft-card">
                            <label class="journal-field-label" for="name">Name</label>
                            <input
                                id="name"
                                class="journal-input"
                                name="name"
                                :value="user.name"
                                required
                                autocomplete="name"
                                placeholder="Full name"
                            />
                            <InputError class="mt-2" :message="page.props.errors?.name" />
                        </article>

                        <article class="journal-soft-card">
                            <label class="journal-field-label" for="email">Email address</label>
                            <input
                                id="email"
                                type="email"
                                class="journal-input"
                                name="email"
                                :value="user.email"
                                required
                                autocomplete="username"
                                placeholder="Email address"
                            />
                            <InputError class="mt-2" :message="page.props.errors?.email" />
                        </article>
                    </div>

                    <section class="journal-panel px-5 py-5 md:px-6">
                        <div class="space-y-2">
                            <p class="journal-kicker">Paddler profile</p>
                            <h3 class="text-[1.55rem] leading-[0.98] text-[color:var(--journal-text)]">
                                Journal bio card
                            </h3>
                            <p class="journal-copy max-w-2xl text-sm md:text-base">
                                These fields feed the compact paddler card in the dashboard header and the broader profile bio surfaces.
                            </p>
                        </div>

                        <div class="mt-6 grid gap-5 md:grid-cols-2">
                            <article class="journal-soft-card">
                                <label class="journal-field-label" for="paddler_name">Paddler name</label>
                                <input
                                    id="paddler_name"
                                    class="journal-input"
                                    name="paddler_name"
                                    :value="props.profile.settings.paddlerName"
                                    placeholder="Francesco Li Vigni"
                                />
                                <InputError class="mt-2" :message="page.props.errors?.paddler_name" />
                            </article>

                            <article class="journal-soft-card">
                                <label class="journal-field-label" for="kayak_club">Kayak club affiliated</label>
                                <input
                                    id="kayak_club"
                                    class="journal-input"
                                    name="kayak_club"
                                    :value="props.profile.settings.kayakClub"
                                    placeholder="Club or affiliation"
                                />
                                <InputError class="mt-2" :message="page.props.errors?.kayak_club" />
                            </article>

                            <article class="journal-soft-card">
                                <label class="journal-field-label" for="registered_kayaks_count">Registered kayaks</label>
                                <input
                                    id="registered_kayaks_count"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="journal-input"
                                    name="registered_kayaks_count"
                                    :value="props.profile.settings.registeredKayaksCount"
                                />
                                <InputError class="mt-2" :message="page.props.errors?.registered_kayaks_count" />
                            </article>

                            <article class="journal-soft-card">
                                <label class="journal-field-label" for="registered_paddles_count">Registered paddles</label>
                                <input
                                    id="registered_paddles_count"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="journal-input"
                                    name="registered_paddles_count"
                                    :value="props.profile.settings.registeredPaddlesCount"
                                />
                                <InputError class="mt-2" :message="page.props.errors?.registered_paddles_count" />
                            </article>

                            <article class="journal-soft-card md:col-span-2">
                                <label class="journal-field-label" for="bio">Profile bio</label>
                                <textarea
                                    id="bio"
                                    class="journal-textarea min-h-[140px]"
                                    name="bio"
                                    :value="props.profile.settings.bio"
                                    placeholder="Short paddler bio for the dashboard and public profile."
                                />
                                <InputError class="mt-2" :message="page.props.errors?.bio" />
                            </article>
                        </div>
                    </section>

                    <div class="flex flex-wrap items-center gap-3">
                        <button class="journal-primary-link" type="submit" data-test="update-profile-button">
                            Save profile
                        </button>
                        <p v-if="status" class="journal-banner journal-banner--soft">
                            {{ status }}
                        </p>
                    </div>
                </form>
            </section>

            <DeleteUser />
        </div>
    </SettingsLayout>
</template>
