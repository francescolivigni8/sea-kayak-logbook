<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

interface ProfileSummary {
    name: string;
    slug: string;
    homeWater: string;
    timezone: string;
    isPublic: boolean;
    publicPath: string;
}

interface NoteCard {
    id: number;
    title: string;
    date: string | null;
    visibility: string;
    category: string;
    beaufort: number | null;
    launchName: string | null;
    summary: string;
    chips: string[];
    photoUrl: string | null;
    path: string;
}

defineProps<{
    profile: ProfileSummary;
    mode: 'observations' | 'expedition-notes';
    title: string;
    description: string;
    count: number;
    items: NoteCard[];
}>();
</script>

<template>
    <Head :title="title" />

    <div class="space-y-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <p class="journal-kicker">{{ mode === 'observations' ? 'Observations' : 'Expedition notes' }}</p>
                    <div class="space-y-2">
                        <h2 class="text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96] text-[color:var(--journal-text)]">
                            {{ title }}
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            {{ description }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-col items-start gap-2 xl:items-end">
                    <span class="journal-chip journal-chip--primary">{{ profile.homeWater }}</span>
                    <span class="text-sm font-medium text-[color:var(--journal-muted)]">{{ count }} notes</span>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <article
                v-for="item in items"
                :key="item.id"
                class="journal-card overflow-hidden px-5 py-5 md:px-6"
            >
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                        <span class="journal-kicker">{{ item.date ?? 'No date' }}</span>
                        <span class="journal-chip">{{ item.visibility }}</span>
                        <span class="journal-chip">{{ item.category }}</span>
                        <span v-if="item.beaufort !== null" class="journal-chip">F{{ item.beaufort }}</span>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-[1.4rem] leading-none text-[color:var(--journal-text)]">
                            {{ item.title }}
                        </h3>
                        <p class="text-sm leading-6 text-[color:var(--journal-muted)]">
                            {{ item.summary }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="chip in item.chips"
                            :key="chip"
                            class="journal-chip"
                        >
                            {{ chip }}
                        </span>
                    </div>

                    <div v-if="item.photoUrl" class="overflow-hidden rounded-[20px] border border-[color:var(--journal-line)] bg-white/70">
                        <img :src="item.photoUrl" :alt="item.title" class="h-44 w-full object-cover" />
                    </div>

                    <Link :href="item.path" class="journal-utility-link w-full justify-center">
                        Open session
                    </Link>
                </div>
            </article>

            <article
                v-if="!items.length"
                class="rounded-[1.75rem] border border-dashed border-[color:var(--journal-line)] bg-white/78 px-5 py-10 text-sm leading-7 text-[color:var(--journal-muted)]"
            >
                No notes yet on this surface. Add observations or expedition notes while editing a session and they will appear here.
            </article>
        </section>
    </div>
</template>
