<template>
    <div class="flex flex-col gap-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="space-y-3">
                <p class="journal-kicker">Settings</p>
                <div class="space-y-2">
                    <h2 class="text-[clamp(1.9rem,3vw,2.35rem)] leading-[0.96] text-[color:var(--journal-text)]">
                        Account
                    </h2>
                    <p class="journal-copy max-w-3xl text-sm md:text-base">
                        Keep your paddler details, password, appearance, and recovery options together in one place.
                    </p>
                </div>
            </div>
        </section>

        <section class="space-y-5">
            <slot />
        </section>
    </div>
</template>
