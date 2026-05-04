<script setup lang="ts">
import SessionEntryModeToggle from '@/pages/sessions/Partials/SessionEntryModeToggle.vue';

interface SessionStepItem {
    key: string;
    title: string;
    description: string;
}

type SessionEntryMode = 'quick' | 'extended';
type SessionPageMode = 'create' | 'edit';

defineProps<{
    mode: SessionPageMode;
    pageTitle: string;
    pageDescription: string;
    canToggleEntryMode: boolean;
    isQuickMode: boolean;
    modeHelperText: string;
    extendedMobileSummary: string;
    stepProgressLabel: string;
    flashSuccessMessage: string | null | undefined;
    formErrorEntries: Array<[string, string]>;
    errorLeadMessage: string;
    steps: ReadonlyArray<SessionStepItem>;
    currentStep: number;
}>();

const emit = defineEmits<{
    (event: 'change-mode', mode: SessionEntryMode): void;
    (event: 'select-step', index: number): void;
}>();
</script>

<template>
    <section
        v-if="flashSuccessMessage"
        class="journal-banner journal-banner--success-strong"
    >
        <p class="journal-kicker text-[color:#256a48]">Session saved</p>
        <p class="mt-2 text-sm leading-6 font-semibold md:text-base">
            {{ flashSuccessMessage }}
        </p>
    </section>

    <section
        v-if="formErrorEntries.length"
        class="journal-banner journal-banner--danger"
    >
        <p class="journal-kicker">Session not saved</p>
        <p class="mt-2 text-sm leading-6 font-semibold md:text-base">
            {{ errorLeadMessage }}
        </p>
        <ul class="mt-3 space-y-1 text-sm leading-6">
            <li
                v-for="[field, message] in formErrorEntries.slice(0, 3)"
                :key="field"
            >
                {{ message }}
            </li>
        </ul>
    </section>

    <section
        class="journal-panel px-4 py-4 sm:px-5 sm:py-5 md:px-6 md:py-6"
        :class="!isQuickMode ? 'hidden md:block' : ''"
    >
        <div class="space-y-3">
            <div class="space-y-3">
                <p class="journal-kicker">
                    {{ mode === 'create' ? 'Add session' : 'Edit session' }}
                </p>
                <div class="space-y-2">
                    <h2
                        class="text-[1.75rem] leading-[0.96] sm:text-[clamp(1.9rem,3vw,2.5rem)]"
                    >
                        {{ pageTitle }}
                    </h2>
                    <p class="journal-copy max-w-3xl text-sm md:text-base">
                        {{ pageDescription }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section
        v-if="canToggleEntryMode"
        class="journal-panel px-4 py-4 sm:px-5 sm:py-5 md:px-6"
        :class="!isQuickMode ? 'hidden md:block' : ''"
    >
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="space-y-2">
                <p class="journal-kicker">Session mode</p>
                <h3 class="text-[1.25rem] leading-none sm:text-[1.5rem]">
                    {{ isQuickMode ? 'Quick session' : 'Extended session' }}
                </h3>
                <p class="journal-copy max-w-3xl text-sm md:text-base">
                    {{ modeHelperText }}
                </p>
            </div>

            <SessionEntryModeToggle
                :mode="isQuickMode ? 'quick' : 'extended'"
                @change="emit('change-mode', $event)"
            />
        </div>
    </section>

    <section class="journal-banner journal-banner--soft hidden sm:block">
        {{ modeHelperText }}
    </section>

    <section v-if="!isQuickMode" class="journal-panel px-4 py-4 md:hidden">
        <div class="space-y-4">
            <div class="space-y-2">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-2">
                        <p class="journal-kicker">Extended session</p>
                        <h2 class="text-[1.55rem] leading-[0.96]">
                            {{ pageTitle }}
                        </h2>
                    </div>

                    <div
                        class="rounded-full border border-[color:var(--journal-line)] bg-white/78 px-3 py-1.5 text-[0.74rem] font-semibold text-[color:var(--journal-muted)] shadow-[0_10px_24px_rgba(15,23,42,0.08)]"
                    >
                        {{ stepProgressLabel }}
                    </div>
                </div>

                <p class="journal-copy text-sm leading-6">
                    {{ extendedMobileSummary }}
                </p>
            </div>

            <SessionEntryModeToggle
                v-if="canToggleEntryMode"
                :mode="isQuickMode ? 'quick' : 'extended'"
                full-width
                @change="emit('change-mode', $event)"
            />

            <div
                class="rounded-[1.35rem] border border-[color:var(--journal-line)] bg-[color:var(--journal-surface-soft)] p-3"
            >
                <div class="mb-3 flex items-center justify-between gap-3">
                    <p
                        class="text-xs font-semibold tracking-[0.12em] text-[color:var(--journal-muted)] uppercase"
                    >
                        Step guide
                    </p>
                    <span
                        class="text-[0.72rem] font-medium text-[color:var(--journal-muted)]"
                    >
                        Title, date, and distance, a trace, or a route file
                        required
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <button
                        v-for="(step, index) in steps"
                        :key="`${step.key}-mobile`"
                        type="button"
                        :class="[
                            'journal-step journal-step--mobile',
                            currentStep === index ? 'journal-step--active' : '',
                        ]"
                        @click="emit('select-step', index)"
                    >
                        <span class="journal-kicker">{{
                            `Step ${index + 1}`
                        }}</span>
                        <strong
                            class="text-[0.95rem] text-[color:var(--journal-text)]"
                        >
                            {{ step.title }}
                        </strong>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section
        v-if="!isQuickMode"
        class="journal-panel hidden px-4 py-4 sm:px-5 sm:py-5 md:block md:px-6"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-medium text-[color:var(--journal-muted)]">
                {{ stepProgressLabel }}
            </p>
            <span
                class="w-full text-xs font-medium text-[color:var(--journal-muted)] sm:w-auto sm:text-sm"
                >Required: title, date, and distance, a manual trace, or a
                route file</span
            >
        </div>

        <div
            class="mt-4 flex gap-3 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] md:mt-5 md:grid md:grid-cols-2 md:overflow-visible md:pb-0 xl:grid-cols-4 [&::-webkit-scrollbar]:hidden"
        >
            <button
                v-for="(step, index) in steps"
                :key="step.key"
                type="button"
                :class="[
                    'journal-step',
                    'min-w-[170px] shrink-0 md:min-w-0',
                    currentStep === index ? 'journal-step--active' : '',
                ]"
                @click="emit('select-step', index)"
            >
                <span class="journal-kicker">{{ `Step ${index + 1}` }}</span>
                <strong
                    class="text-[0.92rem] text-[color:var(--journal-text)] sm:text-[1rem]"
                    >{{ step.title }}</strong
                >
                <span
                    class="hidden text-sm leading-6 text-[color:var(--journal-muted)] sm:block"
                    >{{ step.description }}</span
                >
            </button>
        </div>
    </section>
</template>
