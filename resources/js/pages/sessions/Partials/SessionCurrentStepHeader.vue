<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    isQuickMode: boolean;
    currentStep: number;
    stepsLength: number;
    currentStepTitle: string;
    currentStepDescription: string;
}>();

const statusLabel = computed(() =>
    props.isQuickMode || props.currentStep === props.stepsLength - 1
        ? 'Ready to save'
        : 'Keep going',
);
</script>

<template>
    <div
        v-if="!isQuickMode"
        class="mb-4 rounded-[1.2rem] border border-[color:var(--journal-line)] bg-[color:var(--journal-surface-soft)] px-4 py-3 md:hidden"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="space-y-1">
                <p class="journal-kicker">
                    {{ `Step ${currentStep + 1}` }}
                </p>
                <h3 class="text-[1.2rem] leading-none">
                    {{ currentStepTitle }}
                </h3>
            </div>

            <span
                class="rounded-full border border-[color:var(--journal-line)] bg-white/82 px-3 py-1 text-[0.72rem] font-semibold text-[color:var(--journal-muted)]"
            >
                {{ statusLabel }}
            </span>
        </div>

        <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
            {{ currentStepDescription }}
        </p>
    </div>

    <div
        class="mb-6 flex flex-wrap items-start justify-between gap-3"
        :class="!isQuickMode ? 'hidden md:flex' : ''"
    >
        <div class="space-y-2">
            <p class="journal-kicker">
                {{ currentStepTitle }}
            </p>
            <h3 class="text-[1.5rem] leading-none sm:text-[1.9rem]">
                {{ currentStepTitle }}
            </h3>
            <p
                class="journal-copy hidden max-w-3xl text-sm md:block md:text-base"
            >
                {{ currentStepDescription }}
            </p>
        </div>

        <span
            class="hidden text-sm font-medium text-[color:var(--journal-muted)] sm:inline"
        >
            {{ statusLabel }}
        </span>
    </div>
</template>
