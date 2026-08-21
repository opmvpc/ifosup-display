<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface ScheduleRowGroup {
    name: string;
}

interface ScheduleRowTeacher {
    name: string;
}

interface ScheduleRowCourse {
    name: string;
    code?: string | null;
    teacher?: ScheduleRowTeacher | null;
    groups?: ScheduleRowGroup[];
}

interface ScheduleRowRoom {
    name: string;
}

interface ScheduleRow {
    id?: number;
    status?: AssignmentStatus;
    course?: ScheduleRowCourse | null;
    room?: ScheduleRowRoom | null;
}

const props = defineProps({ data: Object });
const emit = defineEmits(['next']);

const now = new Date();

const dateLabel = now.toLocaleDateString('fr-BE', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const timeLabel = ref('');

function updateTimeLabel() {
    timeLabel.value = new Date().toLocaleTimeString('fr-BE', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });
}

const title = computed<string>(() => props.data?.title ?? 'Planning des cours');

function compareRooms(a: string, b: string) {
    const isInt = (s: string) => /^-?\d+$/.test(s);
    const na = parseInt(a, 10),
        nb = parseInt(b, 10);
    const group = (s: string, n: number) => (!isInt(s) ? 2 : n < 0 ? 0 : 1);
    const ga = group(a, na),
        gb = group(b, nb);
    if (ga !== gb) return ga - gb;
    if (ga === 0) return Math.abs(na) - Math.abs(nb);
    if (ga === 1) return na - nb;
    return a.localeCompare(b);
}

const sortedRows = computed(() => {
    const rows: ScheduleRow[] = props.data?.rows ?? [];
    return [...rows].sort((a, b) =>
        compareRooms(a.room?.name ?? '', b.room?.name ?? ''),
    );
});

// Auto-scroll via transform
const outerContainer = ref<HTMLElement | null>(null);
const innerContent = ref<HTMLElement | null>(null);
const translateY = ref(0);
const scrollProgress = ref(0);
const barWidth = ref(100);

const SPEED = 36; // px/s  (≈ 0.6 px/frame at 60 fps)
const INITIAL_PAUSE_MS = 5000;
const BOTTOM_PAUSE_MS = 5000;
const NO_SCROLL_MS = 10000;

// phases: 'initial-wait' → next               (no content)
//         'initial-wait' → 'no-scroll-wait' → next  (content, no scroll)
//         'initial-wait' → 'scrolling' → 'bottom-wait' → next  (scroll)
let phase = 'initial-wait';
let phaseElapsed = 0;
let lastTimestamp: number | null = null;
let animationId: number | null = null;
let timeIntervalId: ReturnType<typeof setInterval> | null = null;

function animate(timestamp: number) {
    const outer = outerContainer.value;
    const inner = innerContent.value;
    if (!outer || !inner) {
        animationId = requestAnimationFrame(animate);
        return;
    }

    const delta = lastTimestamp !== null ? timestamp - lastTimestamp : 0;
    lastTimestamp = timestamp;

    const maxTranslate = inner.offsetHeight - outer.offsetHeight;
    barWidth.value = (outer.offsetHeight / inner.offsetHeight) * 100;

    if (phase === 'initial-wait') {
        phaseElapsed += delta;
        if (phaseElapsed >= INITIAL_PAUSE_MS) {
            if (!props.data?.rows?.length) {
                emit('next');
                return;
            }
            phaseElapsed = 0;
            if (maxTranslate > 0) {
                phase = 'scrolling';
            } else {
                phase = 'no-scroll-wait';
            }
        }
    } else if (phase === 'scrolling') {
        translateY.value = Math.min(
            translateY.value + (SPEED * delta) / 1000,
            maxTranslate,
        );
        scrollProgress.value = translateY.value / maxTranslate;
        if (translateY.value >= maxTranslate) {
            phase = 'bottom-wait';
            phaseElapsed = 0;
        }
    } else if (phase === 'bottom-wait') {
        phaseElapsed += delta;
        if (phaseElapsed >= BOTTOM_PAUSE_MS) {
            emit('next');
            return;
        }
    } else if (phase === 'no-scroll-wait') {
        phaseElapsed += delta;
        if (phaseElapsed >= NO_SCROLL_MS) {
            emit('next');
            return;
        }
    }

    animationId = requestAnimationFrame(animate);
}

onMounted(() => {
    updateTimeLabel();
    timeIntervalId = window.setInterval(updateTimeLabel, 1000);
    animationId = requestAnimationFrame(animate);
});

onUnmounted(() => {
    if (animationId !== null) cancelAnimationFrame(animationId);
    if (timeIntervalId !== null) clearInterval(timeIntervalId);
});
</script>

<template>
    <div
        class="flex h-screen w-screen flex-col overflow-hidden bg-gray-100 font-sans"
    >
        <!-- Header -->
        <div
            class="flex shrink-0 items-center justify-between bg-[#1e2d55] px-14 py-8"
        >
            <div class="flex items-center gap-8">
                <img src="/IFO_Gimmick_SUPERIEUR.png" class="size-16" />
                <div>
                    <p
                        class="mb-0.5 text-base font-semibold tracking-[0.3em] text-[#f2ae35] uppercase"
                    >
                        Locaux
                    </p>
                    <h1
                        class="text-5xl leading-tight font-black text-white uppercase"
                    >
                        {{ title }}
                    </h1>
                </div>
            </div>
            <div class="text-right">
                <p
                    class="text-lg font-bold tracking-widest text-[#f2ae35] uppercase"
                >
                    {{ dateLabel }}
                </p>
                <p class="mt-2 text-3xl font-black tracking-[0.2em] text-white">
                    {{ timeLabel }}
                </p>
            </div>
        </div>

        <!-- Column headers -->
        <div
            v-if="sortedRows.length"
            class="grid shrink-0 grid-cols-[2fr_2fr_2fr_1fr] gap-6 bg-[#f2ae35] px-14 py-6"
        >
            <span
                class="text-lg font-black tracking-widest text-black uppercase"
                >Cours</span
            >
            <span
                class="text-lg font-black tracking-widest text-black uppercase"
                >Professeur</span
            >
            <span
                class="text-lg font-black tracking-widest text-black uppercase"
                >Sections</span
            >
            <span
                class="text-lg font-black tracking-widest text-black uppercase"
                >Local</span
            >
        </div>

        <!-- Rows -->
        <div class="min-h-0 flex-1 overflow-hidden">
            <div ref="outerContainer" class="relative h-full overflow-hidden">
                <div
                    ref="innerContent"
                    :style="{ transform: `translateY(-${translateY}px)` }"
                >
                    <div
                        v-for="(row, index) in sortedRows"
                        :key="row.id ?? index"
                        class="grid grid-cols-[2fr_2fr_2fr_1fr] items-center gap-6 border-b border-gray-300 px-14 py-8"
                        :class="
                            row.status === 'cancelled'
                                ? 'bg-red-50'
                                : row.status === 'late'
                                  ? 'bg-orange-50'
                                  : index % 2 === 0
                                    ? 'bg-white'
                                    : ''
                        "
                    >
                        <!-- Course -->
                        <div class="min-w-0">
                            <p
                                class="truncate text-2xl leading-tight font-bold"
                                :class="
                                    row.status === 'cancelled'
                                        ? 'text-red-600'
                                        : 'text-gray-900'
                                "
                            >
                                {{ row.course?.name }}
                            </p>
                            <p
                                v-if="row.course?.code"
                                class="mt-2 text-base leading-none font-bold tracking-widest uppercase italic"
                                :class="
                                    row.status === 'cancelled'
                                        ? 'text-red-400'
                                        : 'text-[#1e2d55]'
                                "
                            >
                                {{ row.course.code }}
                            </p>
                        </div>

                        <!-- Teacher -->
                        <span
                            class="inline-flex items-center gap-3 truncate text-xl font-semibold"
                            :class="
                                row.status === 'cancelled'
                                    ? 'text-red-600'
                                    : row.status === 'late'
                                      ? 'text-orange-400'
                                      : 'text-gray-700'
                            "
                        >
                            <span>{{ row.course?.teacher?.name ?? '—' }}</span>
                            <span
                                v-if="row.status === 'late'"
                                class="shrink-0 rounded-full border border-orange-300 bg-orange-100 px-3 py-0.5 text-sm font-semibold text-orange-700"
                            >
                                En retard
                            </span>
                        </span>

                        <!-- Groups -->
                        <span
                            class="truncate text-xl font-semibold"
                            :class="
                                row.status === 'cancelled'
                                    ? 'text-red-600'
                                    : 'text-gray-700'
                            "
                        >
                            {{
                                row.course?.groups
                                    ?.map((g) => g.name)
                                    .join(', ') || '—'
                            }}
                        </span>

                        <!-- Room -->
                        <span
                            v-if="row.status === 'cancelled'"
                            class="text-2xl font-bold tracking-wide text-red-600 uppercase"
                        >
                            Annulé
                        </span>
                        <span
                            v-else
                            class="text-2xl font-semibold text-gray-700"
                        >
                            {{ row.room?.name ?? '—' }}
                        </span>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-if="!sortedRows.length"
                        class="flex flex-col items-center justify-center gap-6"
                        :style="{
                            height: `${outerContainer?.offsetHeight}px`,
                        }"
                    >
                        <img
                            src="/empty.svg"
                            class="size-96 opacity-60"
                            alt=""
                        />
                        <p class="text-2xl font-semibold text-gray-400">
                            Aucun cours prévu pour le moment.
                        </p>
                    </div>
                </div>

                <!-- Scrollbar — absolute overlay, right edge -->
                <div
                    v-if="barWidth < 100"
                    class="absolute top-4 right-4 bottom-4 w-1.5 rounded-full bg-black/10"
                >
                    <div
                        class="absolute inset-x-0 rounded-full bg-[#1e2d55]"
                        :style="{
                            height: barWidth + '%',
                            top: scrollProgress * (100 - barWidth) + '%',
                        }"
                    ></div>
                </div>
            </div>
        </div>
    </div>
</template>
