<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/brand/ykj-logo-192.png"
        alt=""
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    />
</template>
