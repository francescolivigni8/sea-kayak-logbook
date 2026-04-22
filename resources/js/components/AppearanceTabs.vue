<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { appearanceThemes, useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();
</script>

<template>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <button
            v-for="theme in appearanceThemes"
            :key="theme.value"
            type="button"
            @click="updateAppearance(theme.value)"
            :class="[
                'journal-soft-card text-left transition hover:-translate-y-px',
                appearance === theme.value
                    ? 'border-[color:var(--journal-line-strong)] shadow-[0_18px_36px_rgba(103,114,255,0.14)]'
                    : 'opacity-95 hover:opacity-100',
            ]"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <p
                            class="text-sm font-semibold text-[color:var(--journal-text)]"
                        >
                            {{ theme.label }}
                        </p>
                    </div>
                    <p
                        class="text-sm leading-6 text-[color:var(--journal-muted)]"
                    >
                        {{ theme.description }}
                    </p>
                </div>

                <span
                    v-if="appearance === theme.value"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[color:var(--journal-sea)] text-white shadow-[0_10px_20px_rgba(103,114,255,0.2)]"
                >
                    <Check class="h-4 w-4" />
                </span>
            </div>

            <div class="mt-4 flex items-center gap-2">
                <span
                    v-for="swatch in theme.swatches"
                    :key="`${theme.value}-${swatch}`"
                    class="h-3.5 w-10 rounded-full border border-white/60 shadow-[inset_0_1px_0_rgba(255,255,255,0.45)]"
                    :style="{ background: swatch }"
                />
            </div>
        </button>
    </div>
</template>
