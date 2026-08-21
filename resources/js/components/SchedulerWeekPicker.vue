<script setup lang="ts">
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';

interface SchedulerWeekPickerProps {
    modelValue: string;
}

const props = defineProps<SchedulerWeekPickerProps>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const isOpen = ref(false);
const monthCursor = ref(
    new Date(
        Date.UTC(new Date().getUTCFullYear(), new Date().getUTCMonth(), 1),
    ),
);

const frDateFormatter = new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    timeZone: 'UTC',
});

const monthLabelFormatter = new Intl.DateTimeFormat('fr-FR', {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

const shortDayFormatter = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'short',
    timeZone: 'UTC',
});

const toIsoDate = (date: Date) => date.toISOString().slice(0, 10);

const parseIsoDate = (isoDate: string) => {
    const [year, month, day] = isoDate.split('-').map(Number);
    return new Date(Date.UTC(year, month - 1, day));
};

const parseIsoWeek = (isoWeek: unknown) => {
    if (typeof isoWeek !== 'string') {
        return null;
    }

    const match = isoWeek.match(/^(\d{4})-W(0[1-9]|[1-4]\d|5[0-3])$/);

    if (!match) {
        return null;
    }

    return {
        year: Number(match[1]),
        week: Number(match[2]),
    };
};

const weekStartDateFromIsoWeek = (isoWeek: string) => {
    const parsed = parseIsoWeek(isoWeek);

    if (!parsed) {
        return null;
    }

    const jan4 = new Date(Date.UTC(parsed.year, 0, 4));
    const jan4IsoDay = jan4.getUTCDay() || 7;
    const weekOneMonday = new Date(jan4);
    weekOneMonday.setUTCDate(jan4.getUTCDate() - (jan4IsoDay - 1));

    const weekStart = new Date(weekOneMonday);
    weekStart.setUTCDate(weekOneMonday.getUTCDate() + (parsed.week - 1) * 7);

    return weekStart;
};

const startOfUtcDay = (date: Date) => {
    return new Date(
        Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()),
    );
};

const addDays = (date: Date, days: number) => {
    const next = new Date(date);
    next.setUTCDate(next.getUTCDate() + days);
    return next;
};

const addMonths = (date: Date, months: number) => {
    return new Date(
        Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + months, 1),
    );
};

const startOfIsoWeek = (date: Date) => {
    const normalized = startOfUtcDay(date);
    const utcDay = normalized.getUTCDay() || 7;
    return addDays(normalized, -(utcDay - 1));
};

const endOfIsoWeek = (date: Date) => {
    return addDays(startOfIsoWeek(date), 6);
};

const isoWeekFromDate = (date: Date) => {
    const normalized = startOfUtcDay(date);
    const day = normalized.getUTCDay() || 7;
    normalized.setUTCDate(normalized.getUTCDate() + 4 - day);

    const isoYear = normalized.getUTCFullYear();
    const yearStart = new Date(Date.UTC(isoYear, 0, 1));
    const week = Math.ceil(
        ((normalized.getTime() - yearStart.getTime()) / 86400000 + 1) / 7,
    );

    return {
        year: isoYear,
        week,
    };
};

const selectedWeekStart = computed(() => {
    const fromModel = weekStartDateFromIsoWeek(props.modelValue);

    if (fromModel) {
        return fromModel;
    }

    return startOfIsoWeek(startOfUtcDay(new Date()));
});

const selectedWeekEnd = computed(() => endOfIsoWeek(selectedWeekStart.value));

const weekLabel = computed(() => {
    const { year, week } = isoWeekFromDate(selectedWeekStart.value);
    return `S${String(week).padStart(2, '0')} ${year}`;
});

const monthTitle = computed(() => {
    return monthLabelFormatter.format(monthCursor.value);
});

const dayHeaders = computed(() => {
    const monday = startOfIsoWeek(new Date(Date.UTC(2026, 0, 5)));

    return Array.from({ length: 7 }).map((_, index) => {
        const d = addDays(monday, index);
        const value = shortDayFormatter.format(d);
        return value.charAt(0).toUpperCase() + value.slice(1).replace('.', '');
    });
});

const calendarWeeks = computed(() => {
    const monthStart = new Date(
        Date.UTC(
            monthCursor.value.getUTCFullYear(),
            monthCursor.value.getUTCMonth(),
            1,
        ),
    );
    const monthEnd = new Date(
        Date.UTC(
            monthCursor.value.getUTCFullYear(),
            monthCursor.value.getUTCMonth() + 1,
            0,
        ),
    );
    const gridStart = startOfIsoWeek(monthStart);

    const rows: Array<{
        key: string;
        weekNumber: number;
        weekStartIso: string;
        days: Array<{
            iso: string;
            dayOfMonth: number;
            inMonth: boolean;
            isToday: boolean;
        }>;
    }> = [];

    let cursor = new Date(gridStart);

    while (cursor <= monthEnd || rows.length % 6 !== 0 || rows.length < 5) {
        const start = new Date(cursor);
        const { week } = isoWeekFromDate(start);

        rows.push({
            key: toIsoDate(start),
            weekNumber: week,
            weekStartIso: toIsoDate(start),
            days: Array.from({ length: 7 }).map((_, index) => {
                const d = addDays(start, index);
                const today = startOfUtcDay(new Date());
                return {
                    iso: toIsoDate(d),
                    dayOfMonth: d.getUTCDate(),
                    inMonth:
                        d.getUTCMonth() === monthCursor.value.getUTCMonth(),
                    isToday: toIsoDate(d) === toIsoDate(today),
                };
            }),
        });

        cursor = addDays(cursor, 7);

        if (rows.length >= 6 && cursor > monthEnd) {
            break;
        }
    }

    return rows;
});

const goToPreviousMonth = () => {
    monthCursor.value = addMonths(monthCursor.value, -1);
};

const goToNextMonth = () => {
    monthCursor.value = addMonths(monthCursor.value, 1);
};

const isoWeekFromDateString = (isoDate: string) => {
    const { year, week } = isoWeekFromDate(parseIsoDate(isoDate));
    return `${year}-W${String(week).padStart(2, '0')}`;
};

const selectedWeekKey = computed(() =>
    isoWeekFromDateString(toIsoDate(selectedWeekStart.value)),
);

const selectWeek = (weekStartIso: string) => {
    emit('update:modelValue', isoWeekFromDateString(weekStartIso));
    isOpen.value = false;
};

const displayDate = computed(() => {
    return `${frDateFormatter.format(selectedWeekStart.value)} - ${frDateFormatter.format(selectedWeekEnd.value)}`;
});

watch(
    () => props.modelValue,
    (value) => {
        const weekStart = weekStartDateFromIsoWeek(value);

        if (!weekStart) {
            return;
        }

        monthCursor.value = new Date(
            Date.UTC(weekStart.getUTCFullYear(), weekStart.getUTCMonth(), 1),
        );
    },
    { immediate: true },
);
</script>

<template>
    <PopoverRoot v-model:open="isOpen">
        <PopoverTrigger as-child>
            <button
                type="button"
                class="inline-flex h-9 w-full cursor-pointer items-center justify-between gap-2 rounded-md border border-zinc-300 bg-white px-3 text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >
                <span class="inline-flex items-center gap-2">
                    <CalendarDays class="h-3.5 w-3.5" />
                    <span>{{ weekLabel }}</span>
                </span>
                <span class="text-[11px] text-zinc-500 dark:text-zinc-400">{{
                    displayDate
                }}</span>
            </button>
        </PopoverTrigger>

        <PopoverPortal>
            <PopoverContent
                side="bottom"
                align="start"
                :side-offset="8"
                class="z-50 w-80 rounded-xl border border-zinc-200 bg-white p-3 shadow-lg outline-none dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-zinc-600 transition-colors hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                            @click="goToPreviousMonth"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>

                        <p
                            class="text-sm font-semibold text-zinc-800 capitalize dark:text-zinc-200"
                        >
                            {{ monthTitle }}
                        </p>

                        <button
                            type="button"
                            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-zinc-600 transition-colors hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                            @click="goToNextMonth"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>

                    <div
                        class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700"
                    >
                        <div
                            class="grid grid-cols-[2.2rem_repeat(7,minmax(0,1fr))] border-b bg-zinc-50 text-[11px] text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/70 dark:text-zinc-400"
                        >
                            <div class="py-1.5 text-center font-medium">W</div>
                            <div
                                v-for="header in dayHeaders"
                                :key="header"
                                class="py-1.5 text-center font-medium"
                            >
                                {{ header }}
                            </div>
                        </div>

                        <button
                            v-for="week in calendarWeeks"
                            :key="week.key"
                            type="button"
                            class="grid w-full cursor-pointer grid-cols-[2.2rem_repeat(7,minmax(0,1fr))] border-b text-left transition-colors last:border-b-0 dark:border-zinc-700"
                            :class="
                                selectedWeekKey ===
                                isoWeekFromDateString(week.weekStartIso)
                                    ? 'bg-zinc-800 text-white dark:bg-zinc-200 dark:text-zinc-900'
                                    : 'hover:bg-zinc-100 dark:hover:bg-zinc-800/80'
                            "
                            @click="selectWeek(week.weekStartIso)"
                        >
                            <span
                                class="flex items-center justify-center py-1.5 text-[11px] font-semibold"
                                :class="
                                    selectedWeekKey ===
                                    isoWeekFromDateString(week.weekStartIso)
                                        ? 'text-white/90 dark:text-zinc-900/90'
                                        : 'text-zinc-500 dark:text-zinc-400'
                                "
                            >
                                {{ String(week.weekNumber).padStart(2, '0') }}
                            </span>

                            <span
                                v-for="day in week.days"
                                :key="day.iso"
                                class="flex items-center justify-center py-1.5 text-xs"
                                :class="[
                                    selectedWeekKey ===
                                    isoWeekFromDateString(week.weekStartIso)
                                        ? 'text-white dark:text-zinc-900'
                                        : day.inMonth
                                          ? 'text-zinc-800 dark:text-zinc-200'
                                          : 'text-zinc-400 dark:text-zinc-500',
                                    day.isToday &&
                                    selectedWeekKey !==
                                        isoWeekFromDateString(week.weekStartIso)
                                        ? 'font-semibold'
                                        : '',
                                ]"
                            >
                                {{ day.dayOfMonth }}
                            </span>
                        </button>
                    </div>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
