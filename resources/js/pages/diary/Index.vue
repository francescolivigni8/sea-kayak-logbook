<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface ProfileSummary {
    name: string;
    slug: string;
    homeWater: string;
    timezone: string;
}

interface DiaryStats {
    sessionCount: number;
    paddledDays: number;
    distanceKm: number;
    expeditionTrips: number;
}

interface DiaryEntry {
    id: number;
    title: string;
    date: string | null;
    dateLabel: string | null;
    launchName: string | null;
    distanceKm: number;
    durationMinutes: number;
    beaufort: number | null;
    routeCategoryLabel: string;
    isExpedition: boolean;
    expeditionDays: number | null;
    isPublic: boolean;
    hasTrack: boolean;
    photoUrl: string | null;
    notesPreview: string | null;
    weatherSummary: string | null;
    path: string;
}

const props = defineProps<{
    profile: ProfileSummary;
    stats: DiaryStats;
    entries: DiaryEntry[];
}>();

const entryMap = computed(() => {
    const grouped = new Map<string, DiaryEntry[]>();

    props.entries.forEach((entry) => {
        if (!entry.date) {
            return;
        }

        const current = grouped.get(entry.date) ?? [];
        current.push(entry);
        grouped.set(entry.date, current);
    });

    return grouped;
});

const availableMonths = computed(() => {
    const months = Array.from(
        new Set(
            props.entries
                .map((entry) => entry.date?.slice(0, 7))
                .filter((value): value is string => Boolean(value)),
        ),
    );

    return months.sort().reverse();
});

const activeMonth = ref(availableMonths.value[0] ?? new Date().toISOString().slice(0, 7));
const selectedDate = ref<string | null>(props.entries.find((entry) => entry.date)?.date ?? null);

watch(
    availableMonths,
    (months) => {
        if (months.length && !months.includes(activeMonth.value)) {
            activeMonth.value = months[0];
        }
    },
    { immediate: true },
);

const currentMonthDate = computed(() => {
    const [year, month] = activeMonth.value.split('-').map(Number);
    return new Date(year, (month || 1) - 1, 1);
});

const monthLabel = computed(() =>
    currentMonthDate.value.toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
    }),
);

const monthDays = computed(() => {
    const start = new Date(currentMonthDate.value);
    const firstWeekday = (start.getDay() + 6) % 7;
    const firstCell = new Date(start);
    firstCell.setDate(start.getDate() - firstWeekday);

    return Array.from({ length: 42 }, (_, index) => {
        const cellDate = new Date(firstCell);
        cellDate.setDate(firstCell.getDate() + index);
        const iso = cellDate.toISOString().slice(0, 10);
        const entries = entryMap.value.get(iso) ?? [];

        return {
            iso,
            day: cellDate.getDate(),
            inMonth: cellDate.getMonth() === currentMonthDate.value.getMonth(),
            entries,
            distanceKm: entries.reduce((total, entry) => total + entry.distanceKm, 0),
        };
    });
});

const monthSummary = computed(() => {
    const visibleEntries = props.entries.filter((entry) => entry.date?.startsWith(activeMonth.value));

    return {
        sessions: visibleEntries.length,
        days: new Set(visibleEntries.map((entry) => entry.date)).size,
        distanceKm: visibleEntries.reduce((total, entry) => total + entry.distanceKm, 0),
    };
});

watch(
    monthDays,
    (days) => {
        if (selectedDate.value && days.some((day) => day.iso === selectedDate.value && day.entries.length)) {
            return;
        }

        selectedDate.value = days.find((day) => day.entries.length)?.iso ?? null;
    },
    { immediate: true },
);

const selectedEntries = computed(() => {
    if (!selectedDate.value) {
        return [];
    }

    return entryMap.value.get(selectedDate.value) ?? [];
});

const selectedPrimaryEntry = computed(() => selectedEntries.value[0] ?? null);
const selectedExtraEntries = computed(() => selectedEntries.value.slice(1));

const selectedLabel = computed(() => {
    if (!selectedDate.value) {
        return 'No day selected';
    }

    return new Date(`${selectedDate.value}T12:00:00`).toLocaleDateString('en-US', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

function stepMonth(direction: -1 | 1) {
    const monthIndex = availableMonths.value.indexOf(activeMonth.value);

    if (monthIndex === -1) {
        return;
    }

    const next = availableMonths.value[monthIndex - direction];

    if (next) {
        activeMonth.value = next;
    }
}
</script>

<template>
    <Head title="Diary" />

    <div class="flex flex-col gap-5">
        <section class="journal-panel px-5 py-5 md:px-6 md:py-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="journal-kicker">Diary</p>
                    <h2 class="mt-2 text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96]">
                        Paddle days
                    </h2>
                </div>
                <p class="text-sm font-medium text-[color:var(--journal-muted)]">
                    Pick a day.
                </p>
            </div>

            <div class="mt-5 journal-banner journal-banner--soft">
                The diary keeps the calendar compact and lets the selected day open into a clearer reading layout on the right.
            </div>

            <div class="mt-6 grid gap-4 xl:grid-cols-[280px_minmax(0,1fr)]">
                <aside class="rounded-[28px] border border-[color:var(--journal-line)] bg-white/76 px-4 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="journal-kicker">{{ monthLabel }}</p>
                            <h3 class="mt-2 text-[1.75rem] leading-none">Paddle calendar</h3>
                        </div>
                        <span class="journal-chip">{{ monthSummary.sessions }} paddles</span>
                    </div>

                    <div class="mt-4 grid gap-2">
                        <span class="journal-chip">{{ monthSummary.distanceKm.toFixed(1) }} km</span>
                        <span class="journal-chip">{{ props.stats.paddledDays }} paddled days</span>
                    </div>

                    <div class="mt-5 flex items-center gap-2">
                        <button
                            type="button"
                            class="journal-utility-link disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="availableMonths.indexOf(activeMonth) === availableMonths.length - 1"
                            @click="stepMonth(-1)"
                        >
                            Prev
                        </button>
                        <button
                            type="button"
                            class="journal-utility-link disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="availableMonths.indexOf(activeMonth) <= 0"
                            @click="stepMonth(1)"
                        >
                            Next
                        </button>
                    </div>

                    <div class="mt-5 text-xs font-medium text-[color:var(--journal-muted)]">
                        Dots mark paddled days.
                    </div>

                    <div class="mt-6 grid grid-cols-7 gap-2 text-center text-[11px] font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">
                        <span v-for="label in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="label">
                            {{ label }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-7 gap-2">
                        <button
                            v-for="day in monthDays"
                            :key="day.iso"
                            type="button"
                            class="min-h-[58px] rounded-[1rem] border px-1.5 py-2 text-center transition"
                            :class="[
                                day.inMonth
                                    ? 'border-[color:var(--journal-line)] bg-white/78'
                                    : 'border-[rgba(103,114,255,0.08)] bg-white/38 text-[color:var(--journal-faint)]',
                                selectedDate === day.iso ? 'shadow-[0_0_0_3px_rgba(255,156,107,0.18)]' : '',
                                day.entries.length ? 'hover:border-[color:var(--journal-line-strong)] hover:bg-white/88' : '',
                            ]"
                            @click="selectedDate = day.entries.length ? day.iso : selectedDate"
                        >
                            <div class="grid justify-items-center gap-1">
                                <span class="text-sm font-semibold text-[color:var(--journal-text)]">
                                    {{ day.day }}
                                </span>
                                <span
                                    v-if="day.entries.length"
                                    class="h-2 w-2 rounded-full"
                                    :style="{ background: day.entries.some((entry) => !entry.isPublic) ? '#948dff' : '#ff9c6b' }"
                                />
                            </div>
                        </button>
                    </div>
                </aside>

                <article class="rounded-[28px] border border-[color:var(--journal-line)] bg-white/82 px-5 py-5">
                    <div v-if="selectedPrimaryEntry" class="space-y-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-3">
                                <p class="journal-kicker">{{ selectedLabel }}</p>
                                <div>
                                    <h3 class="text-[1.95rem] leading-none text-[color:var(--journal-text)]">
                                        {{ selectedPrimaryEntry.title }}
                                    </h3>
                                    <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                                        {{ selectedPrimaryEntry.notesPreview || selectedPrimaryEntry.weatherSummary || 'A paddle day in the logbook.' }}
                                    </p>
                                </div>
                            </div>

                            <span class="journal-chip" :class="selectedPrimaryEntry.isPublic ? '' : 'journal-chip--primary'">
                                {{ selectedPrimaryEntry.isPublic ? 'Public' : 'Private' }}
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span class="journal-chip">{{ selectedPrimaryEntry.routeCategoryLabel }}</span>
                            <span v-if="selectedPrimaryEntry.launchName" class="journal-chip">{{ selectedPrimaryEntry.launchName }}</span>
                            <span v-if="selectedPrimaryEntry.beaufort !== null" class="journal-chip">F{{ selectedPrimaryEntry.beaufort }}</span>
                            <span v-if="selectedPrimaryEntry.isExpedition" class="journal-chip journal-chip--primary">Expedition</span>
                            <span v-if="selectedPrimaryEntry.hasTrack" class="journal-chip">Track attached</span>
                        </div>

                        <div class="grid gap-3 md:grid-cols-4">
                            <article class="rounded-[20px] border border-[color:var(--journal-line)] bg-white/84 px-4 py-4">
                                <p class="journal-kicker">Distance</p>
                                <p class="mt-3 text-2xl font-semibold text-[color:var(--journal-text)]">
                                    {{ selectedPrimaryEntry.distanceKm.toFixed(1) }} km
                                </p>
                            </article>
                            <article class="rounded-[20px] border border-[color:var(--journal-line)] bg-white/84 px-4 py-4">
                                <p class="journal-kicker">Time</p>
                                <p class="mt-3 text-2xl font-semibold text-[color:var(--journal-text)]">
                                    {{ selectedPrimaryEntry.durationMinutes }} min
                                </p>
                            </article>
                            <article class="rounded-[20px] border border-[color:var(--journal-line)] bg-white/84 px-4 py-4">
                                <p class="journal-kicker">Wind</p>
                                <p class="mt-3 text-2xl font-semibold text-[color:var(--journal-text)]">
                                    {{ selectedPrimaryEntry.beaufort !== null ? `F${selectedPrimaryEntry.beaufort}` : '—' }}
                                </p>
                            </article>
                            <article class="rounded-[20px] border border-[color:var(--journal-line)] bg-white/84 px-4 py-4">
                                <p class="journal-kicker">Place</p>
                                <p class="mt-3 text-lg font-semibold text-[color:var(--journal-text)]">
                                    {{ selectedPrimaryEntry.launchName ?? profile.homeWater }}
                                </p>
                            </article>
                        </div>

                        <div class="grid gap-3 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_minmax(0,0.82fr)]">
                            <article class="rounded-[20px] border border-[color:var(--journal-line)] bg-white/84 px-4 py-4">
                                <p class="journal-kicker">Journey</p>
                                <div class="mt-4 grid gap-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.18em] text-[color:var(--journal-faint)]">Distance</p>
                                        <p class="mt-1 text-base font-semibold text-[color:var(--journal-text)]">{{ selectedPrimaryEntry.distanceKm.toFixed(1) }} km</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.18em] text-[color:var(--journal-faint)]">Duration</p>
                                        <p class="mt-1 text-base font-semibold text-[color:var(--journal-text)]">{{ selectedPrimaryEntry.durationMinutes }} min</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.18em] text-[color:var(--journal-faint)]">Launch</p>
                                        <p class="mt-1 text-base font-semibold text-[color:var(--journal-text)]">{{ selectedPrimaryEntry.launchName ?? profile.homeWater }}</p>
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-[20px] border border-[color:var(--journal-line)] bg-white/84 px-4 py-4">
                                <p class="journal-kicker">Session</p>
                                <div class="mt-4 grid gap-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.18em] text-[color:var(--journal-faint)]">Category</p>
                                        <p class="mt-1 text-base font-semibold text-[color:var(--journal-text)]">{{ selectedPrimaryEntry.routeCategoryLabel }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.18em] text-[color:var(--journal-faint)]">Visibility</p>
                                        <p class="mt-1 text-base font-semibold text-[color:var(--journal-text)]">{{ selectedPrimaryEntry.isPublic ? 'Public' : 'Private' }}</p>
                                    </div>
                                    <div v-if="selectedPrimaryEntry.isExpedition && selectedPrimaryEntry.expeditionDays">
                                        <p class="text-xs uppercase tracking-[0.18em] text-[color:var(--journal-faint)]">Days out</p>
                                        <p class="mt-1 text-base font-semibold text-[color:var(--journal-text)]">{{ selectedPrimaryEntry.expeditionDays }}</p>
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-[20px] border border-[color:var(--journal-line)] bg-white/84 px-4 py-4">
                                <p class="journal-kicker">Notes</p>
                                <p class="mt-4 text-sm leading-6 text-[color:var(--journal-muted)]">
                                    {{ selectedPrimaryEntry.notesPreview || selectedPrimaryEntry.weatherSummary || 'No notes for this day yet.' }}
                                </p>
                            </article>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Link :href="selectedPrimaryEntry.path" class="journal-utility-link">
                                Open session
                            </Link>
                        </div>

                        <div v-if="selectedExtraEntries.length" class="grid gap-3">
                            <article
                                v-for="entry in selectedExtraEntries"
                                :key="entry.id"
                                class="rounded-[20px] border border-[color:var(--journal-line)] bg-white/76 px-4 py-4"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h4 class="text-lg font-semibold text-[color:var(--journal-text)]">{{ entry.title }}</h4>
                                        <p class="mt-1 text-sm text-[color:var(--journal-muted)]">
                                            {{ entry.launchName ?? profile.homeWater }} · {{ entry.distanceKm.toFixed(1) }} km
                                        </p>
                                    </div>
                                    <Link :href="entry.path" class="journal-utility-link">Open</Link>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div
                        v-else
                        class="rounded-[1.25rem] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-4 py-10 text-sm text-[color:var(--journal-muted)]"
                    >
                        No paddles on the selected day yet.
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
