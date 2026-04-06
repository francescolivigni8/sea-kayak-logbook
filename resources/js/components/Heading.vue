<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? 'space-y-1' : 'space-y-2'">
        <h2
            :class="
                variant === 'small'
                    ? 'text-[1.2rem] font-semibold leading-none text-[color:var(--journal-text)]'
                    : 'text-[clamp(1.8rem,3vw,2.45rem)] font-semibold leading-[0.96] text-[color:var(--journal-text)]'
            "
        >
            {{ title }}
        </h2>
        <p v-if="description" class="text-sm leading-7 text-[color:var(--journal-muted)]">
            {{ description }}
        </p>
    </header>
</template>
