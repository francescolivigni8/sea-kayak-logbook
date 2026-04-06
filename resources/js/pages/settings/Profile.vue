<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

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
                    <Heading
                        variant="small"
                        title="Profile information"
                        description="Keep the core identity clean here. This controls how your account appears across the private journal workspace."
                    />

                    <div class="flex flex-wrap gap-2">
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
