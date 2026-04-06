<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 text-white shadow-sm"
    >
        <AppLogoIcon class="size-5 fill-current text-white" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">
            Sea Kayak Logbook
        </span>
        <span class="truncate text-xs font-medium text-muted-foreground">
            Expedition journal
        </span>
    </div>
</template>
