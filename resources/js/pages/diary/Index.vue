<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sea Kayak Logbook',
                href: dashboard(),
            },
            {
                title: 'Diary',
                href: '/diary',
            },
        ],
    },
});

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
    const months = Array.from(new Set(
        props.entries
            .map((entry) => entry.date?.slice(0, 7))
            .filter((value): value is string => Boolean(value)),
    ));

    return months.sort().reverse();
});

const activeMonth = ref(availableMonths.value[0] ?? new Date().toISOString().slice(0, 7));
const selectedDate = ref<string | null>(props.entries.find((entry) => entry.date)?.date ?? null);

watch(
    availableMonths,
    (months) => {
        if (!months.length) {
            return;
        }

        if (!months.includes(activeMonth.value)) {
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

    <div class="flex flex-1 flex-col gap-6 rounded-[2rem] p-4 md:p-6">
        <section class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Diary"
                    :description="`Calendar-first logbook view for ${profile.name}. Browse paddle days, jump into sessions, and review notes by date instead of by import order.`"
                />

                <div class="flex items-center gap-3">
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-500">
                        {{ profile.homeWater }}
                    </span>
                    <Button as-child variant="outline">
                        <Link href="/sessions">All sessions</Link>
                    </Button>
                    <Button as-child>
                        <Link href="/sessions/create">Add session</Link>
                    </Button>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-[1.5rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Paddled days</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ stats.paddledDays }}</p>
            </article>
            <article class="rounded-[1.5rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Sessions</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ stats.sessionCount }}</p>
            </article>
            <article class="rounded-[1.5rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Distance</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ stats.distanceKm.toFixed(1) }} km</p>
            </article>
            <article class="rounded-[1.5rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-400">Expeditions</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ stats.expeditionTrips }}</p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.05fr)_minmax(340px,0.95fr)]">
            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                            Calendar
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                            {{ monthLabel }}
                        </h2>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" :disabled="availableMonths.indexOf(activeMonth) === availableMonths.length - 1" @click="stepMonth(-1)">
                            Prev
                        </Button>
                        <Button variant="outline" size="sm" :disabled="availableMonths.indexOf(activeMonth) <= 0" @click="stepMonth(1)">
                            Next
                        </Button>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                        {{ monthSummary.sessions }} sessions
                    </span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                        {{ monthSummary.days }} paddled days
                    </span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                        {{ monthSummary.distanceKm.toFixed(1) }} km
                    </span>
                </div>

                <div class="mt-6 grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                    <span v-for="label in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="label">
                        {{ label }}
                    </span>
                </div>

                <div class="mt-3 grid grid-cols-7 gap-2">
                    <button
                        v-for="day in monthDays"
                        :key="day.iso"
                        type="button"
                        class="min-h-[84px] rounded-[1.1rem] border px-2 py-2 text-left transition"
                        :class="[
                            day.inMonth ? 'border-slate-200 bg-slate-50/80' : 'border-slate-100 bg-slate-50/40 text-slate-300',
                            selectedDate === day.iso ? 'ring-2 ring-orange-300' : '',
                            day.entries.length ? 'hover:border-orange-200 hover:bg-orange-50/50' : '',
                        ]"
                        @click="selectedDate = day.entries.length ? day.iso : selectedDate"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-sm font-semibold text-slate-700">
                                {{ day.day }}
                            </span>
                            <span
                                v-if="day.entries.length"
                                class="h-2.5 w-2.5 rounded-full bg-gradient-to-r from-sky-400 to-violet-400"
                            />
                        </div>

                        <div v-if="day.entries.length" class="mt-4 space-y-1">
                            <p class="text-[11px] font-medium text-slate-500">
                                {{ day.entries.length }} paddle{{ day.entries.length > 1 ? 's' : '' }}
                            </p>
                            <p class="text-[11px] font-semibold text-slate-700">
                                {{ day.distanceKm.toFixed(1) }} km
                            </p>
                        </div>
                    </button>
                </div>
            </article>

            <article class="rounded-[1.75rem] border border-sidebar-border/70 bg-white/95 p-5 shadow-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-400">
                        Selected day
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                        {{ selectedLabel }}
                    </h2>
                </div>

                <div v-if="selectedEntries.length" class="mt-6 grid gap-3">
                    <article
                        v-for="entry in selectedEntries"
                        :key="entry.id"
                        class="overflow-hidden rounded-[1.25rem] border border-slate-200 bg-slate-50/80"
                    >
                        <img
                            v-if="entry.photoUrl"
                            :src="entry.photoUrl"
                            :alt="entry.title"
                            class="h-40 w-full object-cover"
                        />
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                                        <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600">
                                            {{ entry.routeCategoryLabel }}
                                        </span>
                                        <span
                                            class="rounded-full px-3 py-1"
                                            :class="entry.isExpedition ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-600'"
                                        >
                                            {{ entry.isExpedition ? 'Expedition' : 'Day session' }}
                                        </span>
                                    </div>
                                    <h3 class="mt-3 text-lg font-semibold text-slate-900">
                                        {{ entry.title }}
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ entry.launchName ?? profile.homeWater }}
                                    </p>
                                </div>

                                <Button as-child variant="outline" size="sm">
                                    <Link :href="entry.path">Open</Link>
                                </Button>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">
                                    {{ entry.distanceKm.toFixed(1) }} km
                                </span>
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">
                                    {{ entry.durationMinutes }} min
                                </span>
                                <span
                                    v-if="entry.beaufort !== null"
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1"
                                >
                                    F{{ entry.beaufort }}
                                </span>
                                <span
                                    v-if="entry.isExpedition && entry.expeditionDays"
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1"
                                >
                                    {{ entry.expeditionDays }} days out
                                </span>
                            </div>

                            <p v-if="entry.notesPreview" class="mt-4 text-sm leading-6 text-slate-500">
                                {{ entry.notesPreview }}
                            </p>
                            <p v-else-if="entry.weatherSummary" class="mt-4 text-sm leading-6 text-slate-500">
                                {{ entry.weatherSummary }}
                            </p>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-6 rounded-[1.25rem] border border-dashed border-slate-300 bg-slate-50/80 px-4 py-10 text-sm text-slate-500"
                >
                    No paddles on the selected day yet.
                </div>
            </article>
        </section>
    </div>
</template>
