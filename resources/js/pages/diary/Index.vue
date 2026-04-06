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

const statCards = computed(() => [
    { label: 'Paddled days', value: String(props.stats.paddledDays), detail: 'Days with at least one session' },
    { label: 'Sessions', value: String(props.stats.sessionCount), detail: 'Logged paddles in the diary' },
    { label: 'Distance', value: `${props.stats.distanceKm.toFixed(1)} km`, detail: 'Total distance recorded' },
    { label: 'Expeditions', value: String(props.stats.expeditionTrips), detail: 'Sessions tagged as multiday' },
]);

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
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <p class="journal-kicker">Diary</p>
                    <div class="space-y-2">
                        <h2 class="text-[clamp(1.9rem,3vw,2.6rem)] leading-[0.96]">
                            Calendar-first logbook
                        </h2>
                        <p class="journal-copy max-w-3xl text-sm md:text-base">
                            Browse paddles by day, then jump into the entries that matter instead of scanning a long admin list.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link href="/sessions" class="journal-utility-link">
                        All sessions
                    </Link>
                    <Link href="/sessions/create" class="journal-primary-link">
                        Add session
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in statCards"
                :key="card.label"
                class="journal-metric-card"
                style="background: rgba(255, 255, 255, 0.86)"
            >
                <p class="journal-kicker">{{ card.label }}</p>
                <p class="mt-4 text-3xl font-semibold text-[color:var(--journal-text)]">
                    {{ card.value }}
                </p>
                <p class="mt-2 text-sm leading-6 text-[color:var(--journal-muted)]">
                    {{ card.detail }}
                </p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.12fr)_minmax(340px,0.88fr)]">
            <article class="journal-panel px-5 py-5 md:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="journal-kicker">Calendar</p>
                        <h3 class="mt-2 text-[1.8rem] leading-none">
                            {{ monthLabel }}
                        </h3>
                    </div>

                    <div class="flex items-center gap-2">
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
                </div>

                <div class="mt-5 flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]">
                    <span class="journal-chip">{{ monthSummary.sessions }} sessions</span>
                    <span class="journal-chip">{{ monthSummary.days }} paddled days</span>
                    <span class="journal-chip">{{ monthSummary.distanceKm.toFixed(1) }} km</span>
                </div>

                <div class="mt-6 grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-[0.24em] text-[color:var(--journal-faint)]">
                    <span v-for="label in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="label">
                        {{ label }}
                    </span>
                </div>

                <div class="mt-3 grid grid-cols-7 gap-2">
                    <button
                        v-for="day in monthDays"
                        :key="day.iso"
                        type="button"
                        class="min-h-[86px] rounded-[1.2rem] border px-2 py-2 text-left transition"
                        :class="[
                            day.inMonth
                                ? 'border-[color:var(--journal-line)] bg-white/78'
                                : 'border-[rgba(103,114,255,0.08)] bg-white/38 text-[color:var(--journal-faint)]',
                            selectedDate === day.iso ? 'shadow-[0_0_0_3px_rgba(255,156,107,0.18)]' : '',
                            day.entries.length ? 'hover:border-[color:var(--journal-line-strong)] hover:bg-white/88' : '',
                        ]"
                        @click="selectedDate = day.entries.length ? day.iso : selectedDate"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-sm font-semibold text-[color:var(--journal-text)]">
                                {{ day.day }}
                            </span>
                            <span
                                v-if="day.entries.length"
                                class="mt-0.5 h-2.5 w-2.5 rounded-full"
                                style="background: linear-gradient(90deg, #6772ff, #ff9c6b)"
                            />
                        </div>

                        <div v-if="day.entries.length" class="mt-5 text-[11px] font-medium text-[color:var(--journal-muted)]">
                            Paddled
                        </div>
                    </button>
                </div>
            </article>

            <article class="journal-panel px-5 py-5 md:px-6">
                <div>
                    <p class="journal-kicker">Selected day</p>
                    <h3 class="mt-2 text-[1.8rem] leading-none">
                        {{ selectedLabel }}
                    </h3>
                </div>

                <div v-if="selectedEntries.length" class="mt-6 grid gap-3">
                    <article
                        v-for="entry in selectedEntries"
                        :key="entry.id"
                        class="overflow-hidden rounded-[24px] border border-[color:var(--journal-line)] bg-white/78"
                    >
                        <img
                            v-if="entry.photoUrl"
                            :src="entry.photoUrl"
                            :alt="entry.title"
                            class="h-40 w-full object-cover"
                        />
                        <div class="space-y-4 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                                        <span class="journal-chip">{{ entry.routeCategoryLabel }}</span>
                                        <span v-if="entry.isExpedition" class="journal-chip journal-chip--primary">
                                            Expedition
                                        </span>
                                    </div>
                                    <h4 class="mt-3 text-xl font-semibold text-[color:var(--journal-text)]">
                                        {{ entry.title }}
                                    </h4>
                                    <p class="mt-1 text-sm text-[color:var(--journal-muted)]">
                                        {{ entry.launchName ?? profile.homeWater }}
                                    </p>
                                </div>

                                <Link :href="entry.path" class="journal-utility-link">
                                    Open
                                </Link>
                            </div>

                            <div class="flex flex-wrap gap-2 text-xs font-medium text-[color:var(--journal-muted)]">
                                <span class="journal-chip">{{ entry.distanceKm.toFixed(1) }} km</span>
                                <span class="journal-chip">{{ entry.durationMinutes }} min</span>
                                <span v-if="entry.beaufort !== null" class="journal-chip">F{{ entry.beaufort }}</span>
                                <span v-if="entry.isExpedition && entry.expeditionDays" class="journal-chip">
                                    {{ entry.expeditionDays }} days out
                                </span>
                                <span v-if="entry.hasTrack" class="journal-chip">Track attached</span>
                            </div>

                            <p v-if="entry.notesPreview" class="text-sm leading-6 text-[color:var(--journal-muted)]">
                                {{ entry.notesPreview }}
                            </p>
                            <p v-else-if="entry.weatherSummary" class="text-sm leading-6 text-[color:var(--journal-muted)]">
                                {{ entry.weatherSummary }}
                            </p>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-6 rounded-[1.25rem] border border-dashed border-[color:var(--journal-line)] bg-white/72 px-4 py-10 text-sm text-[color:var(--journal-muted)]"
                >
                    No paddles on the selected day yet.
                </div>
            </article>
        </section>
    </div>
</template>
