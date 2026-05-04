<script setup lang="ts">
type SessionEntryMode = 'quick' | 'extended';

const props = defineProps<{
    mode: SessionEntryMode;
    fullWidth?: boolean;
}>();

const emit = defineEmits<{
    (event: 'change', mode: SessionEntryMode): void;
}>();

function setMode(mode: SessionEntryMode) {
    emit('change', mode);
}
</script>

<template>
    <div
        :class="[
            fullWidth
                ? 'inline-flex w-full rounded-full border border-[color:var(--journal-line)] bg-white/78 p-1 shadow-[0_10px_24px_rgba(15,23,42,0.08)]'
                : 'inline-flex rounded-full border border-[color:var(--journal-line)] bg-white/78 p-1 shadow-[0_10px_24px_rgba(15,23,42,0.08)]',
        ]"
    >
        <button
            type="button"
            class="rounded-full px-4 py-2 text-sm font-semibold transition"
            :class="[
                fullWidth ? 'min-h-[44px] flex-1' : '',
                mode === 'quick'
                    ? 'bg-[#5f72ff] text-white shadow-[0_8px_18px_rgba(95,114,255,0.24)]'
                    : 'text-[color:var(--journal-muted)] hover:text-[color:var(--journal-text)]',
            ]"
            @click="setMode('quick')"
        >
            Quick
        </button>
        <button
            type="button"
            class="rounded-full px-4 py-2 text-sm font-semibold transition"
            :class="[
                fullWidth ? 'min-h-[44px] flex-1' : '',
                mode === 'extended'
                    ? 'bg-[#5f72ff] text-white shadow-[0_8px_18px_rgba(95,114,255,0.24)]'
                    : 'text-[color:var(--journal-muted)] hover:text-[color:var(--journal-text)]',
            ]"
            @click="setMode('extended')"
        >
            Extended
        </button>
    </div>
</template>
