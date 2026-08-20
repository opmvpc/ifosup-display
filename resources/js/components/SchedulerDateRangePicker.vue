<script setup lang="ts">
import { CalendarDate } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
    RangeCalendarCell,
    RangeCalendarCellTrigger,
    RangeCalendarGrid,
    RangeCalendarGridBody,
    RangeCalendarGridHead,
    RangeCalendarGridRow,
    RangeCalendarHeadCell,
    RangeCalendarNext,
    RangeCalendarPrev,
    RangeCalendarRoot,
    useDateFormatter,
} from 'reka-ui';
import type { DateRange } from 'reka-ui';
import { onMounted, ref, shallowRef, watch } from 'vue';

type DateRangePayload = {
    from: string;
    to: string;
};

interface SchedulerDateRangePickerProps {
    fromDate: string;
    toDate: string;
}

const props = defineProps<SchedulerDateRangePickerProps>();

const emit = defineEmits<{
    change: [payload: DateRangePayload];
}>();

const isDatePopoverOpen = ref(false);
const isSelectingNewRange = ref(false);

const frDateFormatter = new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    timeZone: 'UTC',
});

const calendarFormatter = useDateFormatter('fr-BE');

const formatDisplayDate = (isoDate: string) => {
    return frDateFormatter.format(new Date(`${isoDate}T00:00:00Z`));
};

const isoToCalendarDate = (iso: string) => {
    const [y, m, d] = iso.split('-').map(Number);
    return new CalendarDate(y, m, d);
};

const calendarDateToIso = (d: DateValue) =>
    `${d.year}-${String(d.month).padStart(2, '0')}-${String(d.day).padStart(2, '0')}`;

const isoDate = (d: Date): string =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

type Preset = { label: string; getRange: () => { from: string; to: string } };

const presets: Preset[] = [
    {
        label: "Aujourd'hui",
        getRange: () => {
            const d = isoDate(new Date());
            return { from: d, to: d };
        },
    },
    {
        label: 'Cette semaine',
        getRange: () => {
            const now = new Date();
            const diff = now.getDay() === 0 ? -6 : 1 - now.getDay();
            const mon = new Date(now);
            mon.setDate(now.getDate() + diff);
            const sun = new Date(mon);
            sun.setDate(mon.getDate() + 6);
            return { from: isoDate(mon), to: isoDate(sun) };
        },
    },
    {
        label: 'Ce mois-ci',
        getRange: () => {
            const now = new Date();
            return {
                from: isoDate(new Date(now.getFullYear(), now.getMonth(), 1)),
                to: isoDate(new Date(now.getFullYear(), now.getMonth() + 1, 0)),
            };
        },
    },
    {
        label: '7 prochains jours',
        getRange: () => {
            const now = new Date();
            const end = new Date(now);
            end.setDate(now.getDate() + 6);
            return { from: isoDate(now), to: isoDate(end) };
        },
    },
    {
        label: '30 prochains jours',
        getRange: () => {
            const now = new Date();
            const end = new Date(now);
            end.setDate(now.getDate() + 29);
            return { from: isoDate(now), to: isoDate(end) };
        },
    },
    {
        label: 'Mois prochain',
        getRange: () => {
            const now = new Date();
            return {
                from: isoDate(
                    new Date(now.getFullYear(), now.getMonth() + 1, 1),
                ),
                to: isoDate(new Date(now.getFullYear(), now.getMonth() + 2, 0)),
            };
        },
    },
];

const getMatchingPreset = (): string | null => {
    for (const preset of presets) {
        const r = preset.getRange();
        if (r.from === props.fromDate && r.to === props.toDate)
            return preset.label;
    }
    return null;
};

const activePresetLabel = ref<string | null>(null);

const applyPreset = (preset: Preset) => {
    activePresetLabel.value = preset.label;
    isDatePopoverOpen.value = false;
    emit('change', preset.getRange());
};

const isOutsideViewDate = (date: DateValue, monthValue: DateValue) => {
    return date.year !== monthValue.year || date.month !== monthValue.month;
};

const isMonthBoundaryCell = (
    weekDates: DateValue[],
    dayIndex: number,
    monthValue: DateValue,
    side: 'left' | 'right',
) => {
    const current = weekDates[dayIndex];

    if (!current || isOutsideViewDate(current, monthValue)) {
        return false;
    }

    if (side === 'left') {
        if (dayIndex === 0) {
            return false;
        }

        const previous = weekDates[dayIndex - 1];
        return !!previous && isOutsideViewDate(previous, monthValue);
    }

    if (dayIndex === weekDates.length - 1) {
        return false;
    }

    const next = weekDates[dayIndex + 1];
    return !!next && isOutsideViewDate(next, monthValue);
};

const buildCalendarRange = (from: string, to: string): DateRange => ({
    start: isoToCalendarDate(from),
    end: isoToCalendarDate(to),
});

const draftCalendarRange = shallowRef<DateRange>(
    buildCalendarRange(props.fromDate, props.toDate),
);

const resetDraftCalendarRange = () => {
    draftCalendarRange.value = buildCalendarRange(props.fromDate, props.toDate);
    isSelectingNewRange.value = false;
};

const onCalendarRangeChange = (range: DateRange) => {
    if (!range?.start) {
        return;
    }

    if (!isSelectingNewRange.value) {
        draftCalendarRange.value = {
            start: range.start,
            end: undefined,
        };
        isSelectingNewRange.value = true;
        return;
    }

    draftCalendarRange.value = range;

    if (!range.end) {
        return;
    }

    let normalizedFrom = calendarDateToIso(range.start);
    let normalizedTo = calendarDateToIso(range.end);

    if (normalizedFrom > normalizedTo) {
        [normalizedFrom, normalizedTo] = [normalizedTo, normalizedFrom];
    }

    isSelectingNewRange.value = false;
    isDatePopoverOpen.value = false;
    activePresetLabel.value = null;
    emit('change', {
        from: normalizedFrom,
        to: normalizedTo,
    });
};

watch(
    () => [props.fromDate, props.toDate],
    () => {
        resetDraftCalendarRange();
        activePresetLabel.value = getMatchingPreset();
    },
);

onMounted(() => {
    activePresetLabel.value = getMatchingPreset();
});

watch(isDatePopoverOpen, (isOpen) => {
    if (isOpen) {
        resetDraftCalendarRange();
        return;
    }

    isSelectingNewRange.value = false;
});
</script>

<template>
    <PopoverRoot v-model:open="isDatePopoverOpen">
        <PopoverTrigger as-child>
            <button
                type="button"
                class="inline-flex h-8 cursor-pointer items-center gap-2 rounded-md border border-zinc-300 bg-white px-2.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >
                <CalendarDays class="h-3.5 w-3.5" />
                <span>
                    {{
                        activePresetLabel ??
                        `${formatDisplayDate(props.fromDate)} – ${formatDisplayDate(props.toDate)}`
                    }}
                </span>
            </button>
        </PopoverTrigger>

        <PopoverPortal>
            <PopoverContent
                side="bottom"
                align="start"
                :side-offset="8"
                class="z-50 rounded-xl border border-zinc-200 bg-white shadow-lg outline-none dark:border-zinc-700 dark:bg-zinc-900"
            >
                <RangeCalendarRoot
                    v-slot="{ weekDays, grid }"
                    :model-value="draftCalendarRange"
                    locale="fr-BE"
                    :week-starts-on="1"
                    :number-of-months="2"
                    fixed-weeks
                    class="flex flex-col gap-4 p-4"
                    @update:model-value="onCalendarRangeChange"
                >
                    <div class="flex gap-6">
                        <div
                            v-for="(month, index) in grid"
                            :key="month.value.toString()"
                        >
                            <div class="mb-3 flex items-center">
                                <RangeCalendarPrev
                                    v-if="index === 0"
                                    class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-zinc-600 transition-colors hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                </RangeCalendarPrev>
                                <span v-else class="w-7" />

                                <span
                                    class="flex-1 text-center text-sm font-semibold text-zinc-800 capitalize dark:text-zinc-200"
                                >
                                    {{
                                        calendarFormatter.custom(
                                            month.value.toDate('UTC'),
                                            {
                                                month: 'long',
                                                year: 'numeric',
                                            },
                                        )
                                    }}
                                </span>

                                <RangeCalendarNext
                                    v-if="index === grid.length - 1"
                                    class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-zinc-600 transition-colors hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                                >
                                    <ChevronRight class="h-4 w-4" />
                                </RangeCalendarNext>
                                <span v-else class="w-7" />
                            </div>

                            <RangeCalendarGrid
                                class="w-full border-collapse select-none"
                            >
                                <RangeCalendarGridHead>
                                    <RangeCalendarGridRow
                                        class="mb-1 grid grid-cols-7"
                                    >
                                        <RangeCalendarHeadCell
                                            v-for="day in weekDays"
                                            :key="day"
                                            class="text-center text-[11px] font-medium text-zinc-400 dark:text-zinc-500"
                                        >
                                            {{ day }}
                                        </RangeCalendarHeadCell>
                                    </RangeCalendarGridRow>
                                </RangeCalendarGridHead>
                                <RangeCalendarGridBody class="grid">
                                    <RangeCalendarGridRow
                                        v-for="(
                                            weekDates, rowIndex
                                        ) in month.rows"
                                        :key="`week-${rowIndex}`"
                                        class="grid grid-cols-7"
                                    >
                                        <RangeCalendarCell
                                            v-for="(
                                                weekDate, dayIndex
                                            ) in weekDates"
                                            :key="weekDate.toString()"
                                            :date="weekDate"
                                            class="my-0.5 p-0 [&:has([data-highlighted-end])]:rounded-r-full [&:has([data-highlighted-start])]:rounded-l-full [&:has([data-highlighted])]:bg-zinc-100 first:[&:has([data-highlighted])]:rounded-l-full last:[&:has([data-highlighted])]:rounded-r-full dark:[&:has([data-highlighted])]:bg-zinc-800 [&:has([data-outside-view])]:pointer-events-none [&:has([data-outside-view])]:bg-transparent [&:has([data-outside-view][data-highlighted])]:bg-transparent! dark:[&:has([data-outside-view][data-highlighted])]:bg-transparent! [&:has([data-outside-view][data-selected])]:bg-transparent! dark:[&:has([data-outside-view][data-selected])]:bg-transparent! [&:has([data-selected])]:bg-zinc-100 first:[&:has([data-selected])]:rounded-l-full last:[&:has([data-selected])]:rounded-r-full dark:[&:has([data-selected])]:bg-zinc-800 [&:has([data-selected][data-selection-end])]:rounded-r-full [&:not(:has([data-highlighted])):has([data-selected][data-selection-start])]:rounded-l-full"
                                            :class="[
                                                isMonthBoundaryCell(
                                                    weekDates,
                                                    dayIndex,
                                                    month.value,
                                                    'left',
                                                )
                                                    ? 'rounded-l-full'
                                                    : '',
                                                isMonthBoundaryCell(
                                                    weekDates,
                                                    dayIndex,
                                                    month.value,
                                                    'right',
                                                )
                                                    ? 'rounded-r-full'
                                                    : '',
                                            ]"
                                        >
                                            <RangeCalendarCellTrigger
                                                :day="weekDate"
                                                :month="month.value"
                                                class="relative flex h-8 w-8 items-center justify-center rounded-full text-xs font-normal text-zinc-700 transition-colors outline-none before:absolute before:bottom-0.75 before:hidden before:h-1 before:w-1 before:rounded-full hover:bg-zinc-800 hover:text-white focus-visible:ring-2 focus-visible:ring-zinc-400 data-highlighted-end:bg-zinc-800 data-highlighted-end:text-white data-highlighted-start:bg-zinc-800 data-highlighted-start:text-white data-outside-view:pointer-events-none data-outside-view:bg-transparent! data-outside-view:text-transparent data-outside-view:hover:bg-transparent! data-selection-end:bg-zinc-800 data-selection-end:text-white data-selection-start:bg-zinc-800 data-selection-start:text-white data-today:before:block data-today:before:bg-zinc-400 data-unavailable:pointer-events-none data-unavailable:text-zinc-300 dark:text-zinc-200 dark:hover:bg-zinc-200 dark:hover:text-zinc-900 dark:data-outside-view:text-transparent dark:data-outside-view:hover:text-transparent dark:data-selection-end:bg-zinc-200 dark:data-selection-end:text-zinc-900 dark:data-selection-start:bg-zinc-200 dark:data-selection-start:text-zinc-900 dark:data-today:before:bg-zinc-500"
                                            />
                                        </RangeCalendarCell>
                                    </RangeCalendarGridRow>
                                </RangeCalendarGridBody>
                            </RangeCalendarGrid>
                        </div>
                    </div>
                </RangeCalendarRoot>

                <div
                    class="flex w-0 min-w-full flex-wrap gap-1.5 border-t border-zinc-100 px-4 py-3 dark:border-zinc-800"
                >
                    <button
                        v-for="preset in presets"
                        :key="preset.label"
                        type="button"
                        @click="applyPreset(preset)"
                        class="cursor-pointer rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                        :class="
                            activePresetLabel === preset.label
                                ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                                : 'border-zinc-200 text-zinc-600 hover:border-zinc-400 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-400 dark:hover:border-zinc-500 dark:hover:text-zinc-200'
                        "
                    >
                        {{ preset.label }}
                    </button>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
