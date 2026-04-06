<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div class="inline-flex flex-wrap items-center gap-2 rounded-full border border-[color:var(--journal-line)] bg-white/74 p-1.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.8)]">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            :class="[
                'journal-tab gap-2',
                appearance === value
                    ? 'journal-tab--active'
                    : '',
            ]"
        >
            <component :is="Icon" class="h-4 w-4" />
            <span class="text-sm font-medium">{{ label }}</span>
        </button>
    </div>
</template>
