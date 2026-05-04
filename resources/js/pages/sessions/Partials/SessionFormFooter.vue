<script setup lang="ts">
defineProps<{
    isQuickMode: boolean;
    currentStep: number;
    stepsLength: number;
    minimumRequirementText: string;
    submitLabel: string;
    processing: boolean;
}>();

const emit = defineEmits<{
    (event: 'previous'): void;
    (event: 'next'): void;
}>();
</script>

<template>
    <section
        class="journal-panel flex flex-col gap-3 px-4 py-4 md:flex-row md:flex-wrap md:items-center md:justify-between md:px-6 md:py-5"
    >
        <p class="text-sm text-[color:var(--journal-muted)]">
            {{ minimumRequirementText }}
        </p>

        <div
            class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center md:w-auto"
        >
            <button
                v-if="!isQuickMode"
                type="button"
                class="journal-utility-link w-full disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                :disabled="currentStep === 0"
                @click="emit('previous')"
            >
                Back
            </button>

            <button
                v-if="!isQuickMode && currentStep < stepsLength - 1"
                type="button"
                class="journal-primary-link w-full sm:w-auto"
                @click="emit('next')"
            >
                Next
            </button>

            <button
                v-else
                type="submit"
                class="journal-primary-link w-full disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                :disabled="processing"
            >
                {{ processing ? 'Saving...' : submitLabel }}
            </button>
        </div>
    </section>
</template>
