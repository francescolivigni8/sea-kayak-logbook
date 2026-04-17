<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center overflow-hidden rounded-xl shadow-sm"
    >
        <AppLogoIcon class="size-9 object-cover" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">
            Your Kayaking Journal
        </span>
        <span class="truncate text-xs font-medium text-muted-foreground">
            Sea kayak logbook
        </span>
    </div>
</template>
