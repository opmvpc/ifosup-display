<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    BookOpen,
    DoorOpen,
    Layers,
    Maximize2,
    Minimize2,
    Minus,
    Plus,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useAppearance } from '@/composables/useAppearance';
import useSchedulerView from '@/composables/useSchedulerView';
import type { ZoomLevel } from '@/composables/useSchedulerView';
import { useSyncScroll } from '@/composables/useSyncScroll';
import type { DerivedTheme } from '@/composables/useThemeDerivation';
import { useThemeDerivation } from '@/composables/useThemeDerivation';
import { schedule } from '@/routes';
import Combobox from './Combobox.vue';
import PlaceholderPattern from './PlaceholderPattern.vue';
import SchedulerAssignmentCard from './SchedulerAssignmentCard.vue';
import SchedulerDateRangePicker from './SchedulerDateRangePicker.vue';
import SchedulerDragPreview from './SchedulerDragPreview.vue';
import SchedulerWeekPicker from './SchedulerWeekPicker.vue';

interface SchedulerProps {
    fromDate?: string;
    toDate?: string;
    rooms: Room[];
    courses: Course[];
    assignments: AssignmentWithRelations[];
}

type CellDetails = {
    course: Course;
    theme: DerivedTheme;
    assignment?: PersistedAssignment;
};

type PersistedAssignment = AssignmentWithRelations & {
    id: number;
};

const props = withDefaults(defineProps<SchedulerProps>(), {
    fromDate: '2026-03-01',
    toDate: '2026-03-30',
});

const SCHEDULER_ZOOM_COOKIE = 'scheduler_zoom';
const SCHEDULER_DRAWER_COOKIE = 'scheduler_drawer_open';
const COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 30;

const readCookie = (name: string) => {
    const prefix = `${name}=`;
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith(prefix));

    if (!cookie) {
        return null;
    }

    return decodeURIComponent(cookie.slice(prefix.length));
};

const writeCookie = (name: string, value: string) => {
    document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${COOKIE_MAX_AGE_SECONDS}; Path=/; SameSite=Lax`;
};

const parseInitialZoom = (): ZoomLevel => {
    const value = readCookie(SCHEDULER_ZOOM_COOKIE);

    if (value === '0.5') {
        return 'small';
    }

    if (value === '1.5') {
        return 'large';
    }

    if (value === '2') {
        return 'xl';
    }

    return 'normal';
};

const parseInitialDrawerState = () => {
    return readCookie(SCHEDULER_DRAWER_COOKIE) === '1';
};

const zoomToCookieValue = (value: ZoomLevel) => {
    if (value === 'small') {
        return '0.5';
    }

    if (value === 'large') {
        return '1.5';
    }

    if (value === 'xl') {
        return '2';
    }

    return '1';
};

const initialZoom = parseInitialZoom();
const initialCoursePanelOpen = parseInitialDrawerState();

const formatDate = (date: Date) => {
    return date.toISOString().slice(0, 10);
};

const now = new Date();
const defaultFromDate = formatDate(
    new Date(now.getTime() - 24 * 60 * 60 * 1000),
);
const defaultToDate = formatDate(
    new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000),
);
const todayDateKey = formatDate(now);

const toUtcDate = (value: string) => new Date(`${value}T00:00:00Z`);

const fromDateInput = ref(props.fromDate ?? defaultFromDate);
const toDateInput = ref(props.toDate ?? defaultToDate);
const isRangeLoading = ref(false);
let rangeReloadTimer: number | null = null;

const periods: Array<{ key: AssignmentPeriod; label: string }> = [
    { key: 'morning', label: 'Matin' },
    { key: 'afternoon', label: 'Après-midi' },
    { key: 'evening', label: 'Soir' },
];

const days = [
    { value: 1, label: 'Lundi', short: 'L' },
    { value: 2, label: 'Mardi', short: 'M' },
    { value: 3, label: 'Mercredi', short: 'M' },
    { value: 4, label: 'Jeudi', short: 'J' },
    { value: 5, label: 'Vendredi', short: 'V' },
    { value: 6, label: 'Samedi', short: 'S' },
    { value: 7, label: 'Dimanche', short: 'D' },
];

const buildDateKeys = (startDate: string, endDate: string) => {
    const start = toUtcDate(startDate);
    const end = toUtcDate(endDate);
    const list: string[] = [];

    for (
        const cursor = new Date(start);
        cursor.getTime() <= end.getTime();
        cursor.setUTCDate(cursor.getUTCDate() + 1)
    ) {
        list.push(cursor.toISOString().slice(0, 10));
    }

    return list;
};

const dateKeys = computed(() => buildDateKeys(props.fromDate, props.toDate));

const assignments = ref<PersistedAssignment[]>([
    ...(props.assignments as PersistedAssignment[]),
]);
const courses = computed(() => {
    return [...props.courses].sort((a, b) =>
        a.code.localeCompare(b.code, 'fr'),
    );
});

const compareRooms = (a: string, b: string): number => {
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
};

const sortedRooms = computed(() =>
    [...props.rooms].sort((a, b) => compareRooms(a.name, b.name)),
);

const courseSearchQuery = ref('');

const normalizeSearchText = (value: string) => {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('fr')
        .trim();
};

const filteredCourses = computed(() => {
    const query = normalizeSearchText(courseSearchQuery.value);

    if (!query) {
        return courses.value;
    }

    return courses.value.filter((course) => {
        return (
            normalizeSearchText(course.code).includes(query) ||
            normalizeSearchText(course.name).includes(query)
        );
    });
});

const dateLabelFormatter = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
    timeZone: 'UTC',
});

const dates = computed(() => {
    return dateKeys.value.map((iso) => {
        const cursor = toUtcDate(iso);

        return {
            key: iso,
            label: dateLabelFormatter.format(cursor),
        };
    });
});

const { resolvedAppearance } = useAppearance();
const isDarkMode = computed(() => resolvedAppearance.value === 'dark');
const { getThemeFromSeed } = useThemeDerivation(isDarkMode);

const buildCellKey = (
    roomId: number,
    dateKey: string,
    period: AssignmentPeriod,
) => {
    return `${roomId}|${dateKey}|${period}`;
};

const isTodayDate = (dateKey: string) => dateKey === todayDateKey;

const cellDetailsMap = computed(() => {
    const map = new Map<string, CellDetails>();

    for (const assignment of assignments.value) {
        if (!assignment.room_id) {
            continue;
        }

        const key = buildCellKey(
            assignment.room_id,
            assignment.date,
            assignment.period,
        );

        map.set(key, {
            course: assignment.course,
            theme: getThemeFromSeed(assignment.course.name),
            assignment,
        });
    }

    return map;
});

const getCellDetails = (
    roomId: number,
    dateKey: string,
    period: AssignmentPeriod,
) => {
    const key = buildCellKey(roomId, dateKey, period);
    return cellDetailsMap.value.get(key);
};

const getCourseDetails = (course: Course): CellDetails => {
    return {
        course,
        theme: getThemeFromSeed(course.name),
    };
};

const draggedAssignmentKey = ref<string | null>(null);
const draggedCourseId = ref<number | null>(null);
const dropTargetKey = ref<string | null>(null);
const draggedCellDetails = ref<CellDetails | null>(null);
const dragPreviewRef = ref<HTMLElement | null>(null);

const isCoursePanelOpen = ref(initialCoursePanelOpen);
const isCoursePanelContentVisible = ref(initialCoursePanelOpen);
const COURSE_PANEL_MIN_WIDTH = 264;
const COURSE_PANEL_MAX_WIDTH = 560;
const COURSE_PANEL_TRANSITION_MS = 300;
const coursePanelWidth = ref(320);
const isCoursePanelResizing = ref(false);
const coursePanelCardZoom = 'normal';

const clampCoursePanelWidth = (value: number) => {
    return Math.min(
        COURSE_PANEL_MAX_WIDTH,
        Math.max(COURSE_PANEL_MIN_WIDTH, value),
    );
};

const coursePanelStyle = computed(() => {
    return {
        width: isCoursePanelOpen.value ? `${coursePanelWidth.value}px` : '0px',
    };
});

let resizeStartX = 0;
let resizeStartWidth = coursePanelWidth.value;
let coursePanelContentTimer: number | null = null;

const clearCoursePanelTimer = () => {
    if (coursePanelContentTimer !== null) {
        window.clearTimeout(coursePanelContentTimer);
        coursePanelContentTimer = null;
    }
};

const toggleCoursePanel = () => {
    const nextOpenState = !isCoursePanelOpen.value;

    clearCoursePanelTimer();

    if (nextOpenState) {
        isCoursePanelOpen.value = true;
        isCoursePanelContentVisible.value = false;
        coursePanelContentTimer = window.setTimeout(() => {
            isCoursePanelContentVisible.value = true;
            coursePanelContentTimer = null;
        }, COURSE_PANEL_TRANSITION_MS);
        return;
    }

    stopCoursePanelResize();
    isCoursePanelContentVisible.value = false;
    isCoursePanelOpen.value = false;
};

const onCoursePanelResizeStart = (event: MouseEvent) => {
    if (!isCoursePanelOpen.value) {
        return;
    }

    event.preventDefault();
    isCoursePanelResizing.value = true;
    resizeStartX = event.clientX;
    resizeStartWidth = coursePanelWidth.value;
    document.body.classList.add('select-none', 'cursor-col-resize');
};

const onWindowMouseMove = (event: MouseEvent) => {
    if (!isCoursePanelResizing.value) {
        return;
    }

    const delta = resizeStartX - event.clientX;
    coursePanelWidth.value = clampCoursePanelWidth(resizeStartWidth + delta);
};

const stopCoursePanelResize = () => {
    if (!isCoursePanelResizing.value) {
        return;
    }

    isCoursePanelResizing.value = false;
    document.body.classList.remove('select-none', 'cursor-col-resize');
};

const isCellOccupied = (
    roomId: number,
    dateKey: string,
    period: AssignmentPeriod,
) => {
    return cellDetailsMap.value.has(buildCellKey(roomId, dateKey, period));
};

const findAssignmentIndex = (
    roomId: number,
    dateKey: string,
    period: AssignmentPeriod,
) => {
    return assignments.value.findIndex((assignment) => {
        return (
            assignment.room_id === roomId &&
            assignment.date === dateKey &&
            assignment.period === period
        );
    });
};

const updateAssignmentSlot = (
    assignment: PersistedAssignment,
    roomId: number,
    dateKey: string,
    period: AssignmentPeriod,
) => {
    assignment.room_id = roomId;
    assignment.date = dateKey;
    assignment.period = period;
};

const clearDragState = () => {
    draggedAssignmentKey.value = null;
    draggedCourseId.value = null;
    dropTargetKey.value = null;
    draggedCellDetails.value = null;
};

const clearRangeReloadTimer = () => {
    if (rangeReloadTimer !== null) {
        window.clearTimeout(rangeReloadTimer);
        rangeReloadTimer = null;
    }
};

const reloadRange = (from: string, to: string) => {
    isRangeLoading.value = true;

    router.get(
        schedule({
            query: {
                from,
                to,
            },
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                isRangeLoading.value = false;
            },
        },
    );
};

const scheduleRangeReload = () => {
    if (!fromDateInput.value || !toDateInput.value) {
        return;
    }

    let normalizedFrom = fromDateInput.value;
    let normalizedTo = toDateInput.value;

    if (normalizedFrom > normalizedTo) {
        [normalizedFrom, normalizedTo] = [normalizedTo, normalizedFrom];
        fromDateInput.value = normalizedFrom;
        toDateInput.value = normalizedTo;
    }

    if (normalizedFrom === props.fromDate && normalizedTo === props.toDate) {
        return;
    }

    clearRangeReloadTimer();
    rangeReloadTimer = window.setTimeout(() => {
        reloadRange(normalizedFrom, normalizedTo);
        rangeReloadTimer = null;
    }, 250);
};

const onDateRangeChange = (payload: { from: string; to: string }) => {
    fromDateInput.value = payload.from;
    toDateInput.value = payload.to;
    scheduleRangeReload();
};

const getCsrfToken = () => {
    const fromMeta = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');

    if (fromMeta) {
        return fromMeta;
    }

    const xsrfCookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    if (!xsrfCookie) {
        return '';
    }

    return decodeURIComponent(xsrfCookie.split('=').slice(1).join('='));
};

const parseResponseError = async (
    response: Response,
    fallbackMessage: string,
) => {
    try {
        const payload = (await response.json()) as { message?: string };
        return payload.message ?? fallbackMessage;
    } catch {
        return fallbackMessage;
    }
};

const persistAssignmentMove = async (
    assignmentId: number,
    roomId: number,
    dateKey: string,
    period: AssignmentPeriod,
) => {
    const response = await fetch(`/scheduler/assignments/${assignmentId}`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            room_id: roomId,
            date: dateKey,
            period,
        }),
    });

    if (!response.ok) {
        throw new Error(
            await parseResponseError(
                response,
                'La mise a jour du planning a echoue.',
            ),
        );
    }

    const payload = (await response.json()) as {
        assignment: PersistedAssignment;
    };

    return payload.assignment;
};

const persistAssignmentCreate = async (
    courseId: number,
    roomId: number,
    dateKey: string,
    period: AssignmentPeriod,
) => {
    const response = await fetch('/scheduler/assignments', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            course_id: courseId,
            room_id: roomId,
            date: dateKey,
            period,
        }),
    });

    if (!response.ok) {
        throw new Error(
            await parseResponseError(
                response,
                'La creation du cours a echoue.',
            ),
        );
    }

    const payload = (await response.json()) as {
        assignment: PersistedAssignment;
    };

    return payload.assignment;
};

const persistAssignmentDelete = async (assignmentId: number) => {
    const response = await fetch(`/scheduler/assignments/${assignmentId}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(
            await parseResponseError(
                response,
                'La suppression du cours a echoue.',
            ),
        );
    }
};

const persistAssignmentStatus = async (
    assignmentId: number,
    status: AssignmentStatus,
) => {
    const response = await fetch(
        `/scheduler/assignments/${assignmentId}/status`,
        {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ status }),
        },
    );

    if (!response.ok) {
        throw new Error(
            await parseResponseError(
                response,
                'La mise a jour du statut a echoue.',
            ),
        );
    }

    const payload = (await response.json()) as {
        assignment: PersistedAssignment;
    };

    return payload.assignment;
};

const showError = (error: unknown, fallbackMessage: string) => {
    const message = error instanceof Error ? error.message : fallbackMessage;

    window.alert(message);
};

const BASE_CELL_WIDTH = 288;
const BASE_CELL_HEIGHT = 96;

const roomColWidth = '176px';

const {
    zoom,
    zoomRatio,
    isFullscreen,
    canZoomIn,
    canZoomOut,
    zoomIn,
    zoomOut,
    toggleFullscreen,
    zoomLabel,
} = useSchedulerView(initialZoom);

const cellWidth = computed(
    () => `${Math.round(BASE_CELL_WIDTH * zoomRatio.value)}px`,
);
const cellHeightRatio = computed(() => 0.75 + (zoomRatio.value - 0.5) * 0.5);
const cellHeight = computed(
    () => `${Math.round(BASE_CELL_HEIGHT * cellHeightRatio.value)}px`,
);
const dateGroupWidth = computed(
    () => `${Math.round(BASE_CELL_WIDTH * zoomRatio.value) * periods.length}px`,
);
const dragPreviewWidth = computed(() =>
    Math.min(Math.round(BASE_CELL_WIDTH * zoomRatio.value) - 8, 280),
);

const preserveScrollDuring = (action: () => void) => {
    if (!gridScrollerRef.value) {
        action();
        return;
    }

    const scroller = gridScrollerRef.value;
    const {
        scrollLeft,
        scrollTop,
        scrollWidth,
        scrollHeight,
        clientWidth,
        clientHeight,
    } = scroller;

    const relativeX = (scrollLeft + clientWidth / 2) / scrollWidth;
    const relativeY = (scrollTop + clientHeight / 2) / scrollHeight;

    action();

    nextTick(() => {
        if (!gridScrollerRef.value) return;
        const newScroller = gridScrollerRef.value;
        newScroller.scrollLeft =
            relativeX * newScroller.scrollWidth - clientWidth / 2;
        newScroller.scrollTop =
            relativeY * newScroller.scrollHeight - clientHeight / 2;
        forceSync();
    });
};

const handleZoomIn = () => preserveScrollDuring(() => zoomIn());
const handleZoomOut = () => preserveScrollDuring(() => zoomOut());

const onAssignmentDragStart = async (
    roomId: number,
    dateKey: string,
    period: AssignmentPeriod,
    event: DragEvent,
) => {
    const key = buildCellKey(roomId, dateKey, period);
    const details = getCellDetails(roomId, dateKey, period);

    if (!details) {
        return;
    }

    draggedAssignmentKey.value = key;
    draggedCourseId.value = null;
    dropTargetKey.value = key;
    draggedCellDetails.value = details;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', key);
        await nextTick();

        if (dragPreviewRef.value) {
            event.dataTransfer.setDragImage(dragPreviewRef.value, 24, 20);
        }
    }
};

const onCourseDragStart = async (course: Course, event: DragEvent) => {
    draggedAssignmentKey.value = null;
    draggedCourseId.value = course.id;
    dropTargetKey.value = null;
    draggedCellDetails.value = getCourseDetails(course);

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('text/plain', String(course.id));
        await nextTick();

        if (dragPreviewRef.value) {
            event.dataTransfer.setDragImage(dragPreviewRef.value, 24, 20);
        }
    }
};

const onCellDragOver = (
    roomId: number,
    dateKey: string,
    period: AssignmentPeriod,
    event: DragEvent,
) => {
    const hasAssignmentDrag = draggedAssignmentKey.value !== null;
    const hasCourseDrag = draggedCourseId.value !== null;

    if (!hasAssignmentDrag && !hasCourseDrag) {
        return;
    }

    const sourceKey = draggedAssignmentKey.value;
    const targetKey = buildCellKey(roomId, dateKey, period);
    const isSameCell = sourceKey === targetKey;
    const targetUsed = isCellOccupied(roomId, dateKey, period);
    const canDrop = hasAssignmentDrag ? isSameCell || !targetUsed : !targetUsed;

    if (!canDrop) {
        dropTargetKey.value = null;

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'none';
        }

        return;
    }

    event.preventDefault();
    dropTargetKey.value = targetKey;

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = hasAssignmentDrag ? 'move' : 'copy';
    }
};

const onCellDrop = async (
    roomId: number,
    dateKey: string,
    period: AssignmentPeriod,
    event: DragEvent,
) => {
    event.preventDefault();

    const sourceKey = draggedAssignmentKey.value;
    const sourceCourseId = draggedCourseId.value;
    const targetKey = buildCellKey(roomId, dateKey, period);
    const isSameCell = sourceKey === targetKey;
    const targetUsed = isCellOccupied(roomId, dateKey, period);

    if (!sourceKey && sourceCourseId === null) {
        clearDragState();
        return;
    }

    if (sourceCourseId !== null) {
        if (targetUsed) {
            clearDragState();
            return;
        }

        try {
            const createdAssignment = await persistAssignmentCreate(
                sourceCourseId,
                roomId,
                dateKey,
                period,
            );
            assignments.value = [...assignments.value, createdAssignment];
        } catch (error) {
            showError(error, 'La creation du cours a echoue.');
        }

        clearDragState();
        return;
    }

    if (!sourceKey || isSameCell || targetUsed) {
        clearDragState();
        return;
    }

    const [sourceRoomId, sourceDate, sourcePeriod] = sourceKey.split('|');
    const sourceIndex = findAssignmentIndex(
        Number.parseInt(sourceRoomId, 10),
        sourceDate,
        sourcePeriod as AssignmentPeriod,
    );

    if (sourceIndex === -1) {
        clearDragState();
        return;
    }

    const sourceAssignment = assignments.value[sourceIndex];

    if (!sourceAssignment.id || sourceAssignment.room_id == null) {
        clearDragState();
        return;
    }

    const previousRoomId = sourceAssignment.room_id;
    const previousDate = sourceAssignment.date;
    const previousPeriod = sourceAssignment.period;

    updateAssignmentSlot(sourceAssignment, roomId, dateKey, period);
    assignments.value = [...assignments.value];

    try {
        const persistedAssignment = await persistAssignmentMove(
            sourceAssignment.id,
            roomId,
            dateKey,
            period,
        );
        assignments.value[sourceIndex] = persistedAssignment;
        assignments.value = [...assignments.value];
    } catch (error) {
        updateAssignmentSlot(
            sourceAssignment,
            previousRoomId,
            previousDate,
            previousPeriod,
        );
        assignments.value = [...assignments.value];
        showError(error, 'La mise a jour du planning a echoue.');
    }

    clearDragState();
};

const updateAssignmentStatus = async (
    assignment: PersistedAssignment,
    status: AssignmentStatus,
) => {
    if (!assignment.id) {
        return;
    }

    const index = assignments.value.findIndex(
        (item) => item.id === assignment.id,
    );

    if (index === -1) {
        return;
    }

    const previousStatus = assignments.value[index].status;
    assignments.value[index] = {
        ...assignments.value[index],
        status,
    };
    assignments.value = [...assignments.value];

    try {
        const persistedAssignment = await persistAssignmentStatus(
            assignment.id,
            status,
        );
        assignments.value[index] = persistedAssignment;
        assignments.value = [...assignments.value];
    } catch (error) {
        assignments.value[index] = {
            ...assignments.value[index],
            status: previousStatus,
        };
        assignments.value = [...assignments.value];
        showError(error, 'La mise a jour du statut a echoue.');
    }
};

const deleteAssignment = async (assignment: PersistedAssignment) => {
    if (!assignment.id) {
        return;
    }

    const index = assignments.value.findIndex(
        (item) => item.id === assignment.id,
    );

    if (index === -1) {
        return;
    }

    const previousAssignments = [...assignments.value];
    assignments.value = assignments.value.filter(
        (item) => item.id !== assignment.id,
    );

    try {
        await persistAssignmentDelete(assignment.id);
    } catch (error) {
        assignments.value = previousAssignments;
        showError(error, 'La suppression du cours a echoue.');
    }
};

const isDropTarget = (
    roomId: number,
    dateKey: string,
    period: AssignmentPeriod,
) => {
    return dropTargetKey.value === buildCellKey(roomId, dateKey, period);
};

const isDraggedAssignment = (
    roomId: number,
    dateKey: string,
    period: AssignmentPeriod,
) => {
    return draggedAssignmentKey.value === buildCellKey(roomId, dateKey, period);
};

const {
    headerTrackRef,
    sidebarTrackRef,
    gridScrollerRef,
    onGridScroll,
    forceSync,
} = useSyncScroll();

onMounted(() => {
    forceSync();
    window.addEventListener('mousemove', onWindowMouseMove);
    window.addEventListener('mouseup', stopCoursePanelResize);
});

onBeforeUnmount(() => {
    clearCoursePanelTimer();
    clearRangeReloadTimer();
    window.removeEventListener('mousemove', onWindowMouseMove);
    window.removeEventListener('mouseup', stopCoursePanelResize);
    document.body.classList.remove('select-none', 'cursor-col-resize');
});

watch(
    () => props.assignments,
    (value) => {
        assignments.value = [...(value as PersistedAssignment[])];
    },
);

watch(
    () => [props.fromDate, props.toDate],
    ([fromDate, toDate]) => {
        fromDateInput.value = fromDate;
        toDateInput.value = toDate;
    },
);

watch(zoom, () => {
    forceSync();
});

watch(zoom, (value) => {
    writeCookie(SCHEDULER_ZOOM_COOKIE, zoomToCookieValue(value));
});

watch(isCoursePanelOpen, (isOpen) => {
    writeCookie(SCHEDULER_DRAWER_COOKIE, isOpen ? '1' : '0');
});

// ── Bulk insert ──────────────────────────────────────────────────────────
const isBulkDialogOpen = ref(false);
const bulkStep = ref<1 | 2>(1);
const isBulkSubmitting = ref(false);
const bulkError = ref<string | null>(null);

const currentIsoWeek = (): string => {
    const now = new Date();
    const tmp = new Date(
        Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()),
    );
    tmp.setUTCDate(tmp.getUTCDate() + 4 - (tmp.getUTCDay() || 7));
    const yearStart = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1));
    const week = Math.ceil(
        ((tmp.getTime() - yearStart.getTime()) / 86400000 + 1) / 7,
    );
    return `${tmp.getUTCFullYear()}-W${String(week).padStart(2, '0')}`;
};

const bulkForm = ref({
    course: null as Course | null,
    room: null as Room | null,
    day_of_week: 1 as number,
    period: 'morning' as AssignmentPeriod,
    start_week: currentIsoWeek(),
    end_week: currentIsoWeek(),
});

type BulkExisting = {
    id: number;
    date: string;
    room_id: number;
    course: { id: number; name: string; code: string };
};

type BulkPreviewRow = {
    date: string;
    room: Room;
    selected: boolean;
};

const previewExisting = ref<BulkExisting[]>([]);
const previewRows = ref<BulkPreviewRow[]>([]);

const previewDateFormatter = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

const formatPreviewDate = (iso: string) => {
    const [y, m, d] = iso.split('-').map(Number);
    const s = previewDateFormatter.format(new Date(Date.UTC(y, m - 1, d)));
    return s.charAt(0).toUpperCase() + s.slice(1);
};

const getConflict = (date: string, roomId: number): BulkExisting | undefined =>
    previewExisting.value.find((e) => e.date === date && e.room_id === roomId);

const isSameCourse = (date: string, roomId: number): boolean => {
    const conflict = getConflict(date, roomId);
    return (
        !!conflict &&
        !!bulkForm.value.course &&
        conflict.course.id === bulkForm.value.course.id
    );
};

const isRealConflict = (date: string, roomId: number): boolean =>
    !!getConflict(date, roomId) && !isSameCourse(date, roomId);

const openBulkDialog = () => {
    bulkError.value = null;
    bulkStep.value = 1;
    bulkForm.value = {
        course: null,
        room: null,
        day_of_week: 1,
        period: 'morning',
        start_week: currentIsoWeek(),
        end_week: currentIsoWeek(),
    };
    previewRows.value = [];
    previewExisting.value = [];
    isBulkDialogOpen.value = true;
};

const submitStep1 = async () => {
    bulkError.value = null;
    if (!bulkForm.value.course || !bulkForm.value.room) {
        bulkError.value = 'Cours et local sont obligatoires.';
        return;
    }
    if (bulkForm.value.end_week < bulkForm.value.start_week) {
        bulkError.value = 'La semaine de fin doit être >= la semaine de début.';
        return;
    }
    isBulkSubmitting.value = true;
    try {
        const res = await fetch('/scheduler/assignments/bulk/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                course_id: bulkForm.value.course.id,
                room_id: bulkForm.value.room.id,
                day_of_week: bulkForm.value.day_of_week,
                period: bulkForm.value.period,
                start_week: bulkForm.value.start_week,
                end_week: bulkForm.value.end_week,
            }),
        });
        if (!res.ok) {
            const json = (await res.json().catch(() => ({}))) as {
                message?: string;
            };
            throw new Error(json?.message ?? `Erreur ${res.status}`);
        }
        const payload = (await res.json()) as {
            dates: string[];
            existing: BulkExisting[];
        };
        previewExisting.value = payload.existing;
        previewRows.value = payload.dates.map((date) => ({
            date,
            room: bulkForm.value.room!,
            selected: true,
        }));
        bulkError.value = null;
        bulkStep.value = 2;
    } catch (e: unknown) {
        bulkError.value =
            e instanceof Error ? e.message : 'Une erreur est survenue.';
    } finally {
        isBulkSubmitting.value = false;
    }
};

const submitBulk = async () => {
    bulkError.value = null;
    const selectedRows = previewRows.value.filter((r) => r.selected);
    if (selectedRows.length === 0) {
        bulkError.value = 'Sélectionnez au moins une date.';
        return;
    }
    isBulkSubmitting.value = true;
    try {
        const res = await fetch('/scheduler/assignments/bulk', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                course_id: bulkForm.value.course!.id,
                period: bulkForm.value.period,
                rows: selectedRows.map((r) => ({
                    date: r.date,
                    room_id: r.room.id,
                })),
            }),
        });
        if (!res.ok) {
            const json = (await res.json().catch(() => ({}))) as {
                message?: string;
            };
            throw new Error(json?.message ?? `Erreur ${res.status}`);
        }
        isBulkDialogOpen.value = false;
        router.reload({ only: ['assignments'] });
    } catch (e: unknown) {
        bulkError.value =
            e instanceof Error ? e.message : 'Une erreur est survenue.';
    } finally {
        isBulkSubmitting.value = false;
    }
};
const selectedConflictCount = computed(
    () =>
        previewRows.value.filter(
            (r) => r.selected && isRealConflict(r.date, r.room.id),
        ).length,
);
</script>

<template>
    <div
        :class="[
            'overflow-hidden border border-zinc-300/60 bg-linear-to-br from-zinc-50 via-white to-zinc-100 dark:border-zinc-700/60 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-900',
            isFullscreen
                ? 'fixed inset-0 z-50 rounded-none'
                : 'relative h-[calc(100vh-8rem)] max-h-[calc(100vh-8rem)] min-h-112 w-full rounded-xl',
        ]"
    >
        <div class="flex h-full min-h-0">
            <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                <div
                    class="shrink-0 border-b border-zinc-300/70 bg-white/65 px-3 py-2 dark:border-zinc-700/70 dark:bg-zinc-900/55"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <SchedulerDateRangePicker
                                :from-date="fromDateInput"
                                :to-date="toDateInput"
                                @change="onDateRangeChange"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-1.5">
                            <div
                                class="flex items-center overflow-hidden rounded-lg border border-zinc-200/80 bg-white/80 shadow-sm backdrop-blur-sm dark:border-zinc-700/80 dark:bg-zinc-900/80"
                            >
                                <button
                                    class="flex cursor-pointer items-center justify-center px-2 py-1.5 text-zinc-400 transition-colors hover:text-zinc-600 disabled:cursor-not-allowed disabled:opacity-30 dark:text-zinc-500 dark:hover:text-zinc-300"
                                    :disabled="!canZoomOut"
                                    @click="handleZoomOut"
                                >
                                    <Minus class="h-3.5 w-3.5" />
                                </button>
                                <span
                                    class="border-x border-zinc-200/80 px-2 py-1.5 text-[10px] font-semibold tracking-wide text-zinc-400 uppercase dark:border-zinc-700/80 dark:text-zinc-500"
                                >
                                    {{ zoomLabel }}
                                </span>
                                <button
                                    class="flex cursor-pointer items-center justify-center px-2 py-1.5 text-zinc-400 transition-colors hover:text-zinc-600 disabled:cursor-not-allowed disabled:opacity-30 dark:text-zinc-500 dark:hover:text-zinc-300"
                                    :disabled="!canZoomIn"
                                    @click="handleZoomIn"
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <button
                                class="flex cursor-pointer items-center justify-center rounded-lg border border-zinc-200/80 bg-white/80 p-1.5 text-zinc-400 shadow-sm backdrop-blur-sm transition-colors hover:border-zinc-300 hover:text-zinc-600 dark:border-zinc-700/80 dark:bg-zinc-900/80 dark:text-zinc-500 dark:hover:border-zinc-600 dark:hover:text-zinc-300"
                                @click="toggleFullscreen"
                            >
                                <Maximize2
                                    v-if="!isFullscreen"
                                    class="h-3.5 w-3.5"
                                />
                                <Minimize2 v-else class="h-3.5 w-3.5" />
                            </button>

                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-zinc-300/80 bg-zinc-900 px-3 py-1.5 text-xs font-semibold tracking-wide text-zinc-100 transition-colors hover:bg-zinc-800 dark:border-zinc-600/80 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                                @click="openBulkDialog"
                            >
                                <Layers class="h-3.5 w-3.5" />
                                Insertion en masse
                            </button>

                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-zinc-300/80 bg-zinc-900 px-3 py-1.5 text-xs font-semibold tracking-wide text-zinc-100 transition-colors hover:bg-zinc-800 dark:border-zinc-600/80 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                                @click="toggleCoursePanel"
                            >
                                <BookOpen class="h-3.5 w-3.5" />
                                {{
                                    isCoursePanelOpen
                                        ? 'Masquer les cours'
                                        : 'Afficher les cours'
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1">
                    <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                        <div
                            class="shrink-0 border-b border-zinc-300/70 dark:border-zinc-700/70"
                        >
                            <div class="flex">
                                <div
                                    class="flex h-22 shrink-0 items-center justify-center gap-2 border-r border-zinc-200/60 bg-zinc-100/80 px-4 align-middle text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:border-zinc-700/60 dark:bg-zinc-900/70 dark:text-zinc-300"
                                    :style="{ width: roomColWidth }"
                                >
                                    <DoorOpen class="h-4 w-4" />
                                    Locaux
                                </div>

                                <div class="relative flex-1 overflow-hidden">
                                    <div
                                        ref="headerTrackRef"
                                        class="min-w-full transform-gpu will-change-transform"
                                    >
                                        <div class="flex min-w-full">
                                            <div
                                                v-for="date in dates"
                                                :key="`date-${date.key}`"
                                                class="flex h-12 flex-1 items-center justify-center border-r border-b px-3 text-center text-xs font-semibold tracking-wide uppercase"
                                                :class="
                                                    isTodayDate(date.key)
                                                        ? 'border-blue-400/80 bg-blue-100 text-blue-800 dark:border-blue-500/70 dark:bg-blue-900/35 dark:text-blue-100'
                                                        : 'border-zinc-200/70 bg-zinc-100/70 text-zinc-500 dark:border-zinc-700/70 dark:bg-zinc-900/60 dark:text-zinc-300'
                                                "
                                                :style="{
                                                    minWidth: dateGroupWidth,
                                                }"
                                            >
                                                {{
                                                    isTodayDate(date.key)
                                                        ? "Aujourd'hui"
                                                        : date.label
                                                }}
                                            </div>
                                        </div>

                                        <div class="flex min-w-full">
                                            <template
                                                v-for="date in dates"
                                                :key="`periods-${date.key}`"
                                            >
                                                <div
                                                    v-for="period in periods"
                                                    :key="`${date.key}-${period.key}`"
                                                    class="flex h-10 flex-1 items-center justify-center border-r px-3 text-center text-[11px] font-semibold tracking-wide"
                                                    :class="
                                                        isTodayDate(date.key)
                                                            ? 'border-blue-300/75 bg-blue-100/80 text-blue-800/90 dark:border-blue-700/75 dark:bg-blue-900/25 dark:text-blue-200/95'
                                                            : 'border-zinc-200/60 bg-zinc-100/55 text-zinc-400 dark:border-zinc-700/70 dark:bg-zinc-900/45 dark:text-zinc-400'
                                                    "
                                                    :style="{
                                                        minWidth: cellWidth,
                                                    }"
                                                >
                                                    {{ period.label }}
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex min-h-0 flex-1">
                            <div
                                class="shrink-0 overflow-hidden border-r border-zinc-300/70 dark:border-zinc-700/70"
                                :style="{ width: roomColWidth }"
                            >
                                <div
                                    ref="sidebarTrackRef"
                                    class="min-h-full transform-gpu will-change-transform"
                                >
                                    <div
                                        v-for="room in sortedRooms"
                                        :key="`room-${room.id}`"
                                        class="flex items-center border-b border-zinc-200/70 bg-zinc-100/60 px-4 py-2.5 text-left font-medium text-zinc-500 dark:border-zinc-700/70 dark:bg-zinc-900/55 dark:text-zinc-300"
                                        :style="{ height: cellHeight }"
                                    >
                                        {{ room.name }}
                                    </div>
                                </div>
                            </div>

                            <div
                                ref="gridScrollerRef"
                                class="min-h-0 min-w-0 flex-1 overflow-auto"
                                @scroll="onGridScroll"
                            >
                                <div class="min-w-full">
                                    <div
                                        v-for="room in sortedRooms"
                                        :key="`row-${room.id}`"
                                        class="flex min-w-full"
                                    >
                                        <template
                                            v-for="date in dates"
                                            :key="`${room.id}-${date.key}`"
                                        >
                                            <div
                                                v-for="period in periods"
                                                :key="`${room.id}-${date.key}-${period.key}`"
                                                class="group relative flex-1 border-r border-b border-zinc-100 bg-white px-0 py-0 transition-colors dark:border-zinc-800 dark:bg-zinc-950"
                                                :class="
                                                    isDropTarget(
                                                        room.id,
                                                        date.key,
                                                        period.key,
                                                    )
                                                        ? 'bg-zinc-100/80 dark:bg-zinc-900/80'
                                                        : ''
                                                "
                                                :style="{
                                                    minWidth: cellWidth,
                                                    height: cellHeight,
                                                }"
                                                @dragover="
                                                    onCellDragOver(
                                                        room.id,
                                                        date.key,
                                                        period.key,
                                                        $event,
                                                    )
                                                "
                                                @drop="
                                                    onCellDrop(
                                                        room.id,
                                                        date.key,
                                                        period.key,
                                                        $event,
                                                    )
                                                "
                                            >
                                                <SchedulerAssignmentCard
                                                    v-if="
                                                        getCellDetails(
                                                            room.id,
                                                            date.key,
                                                            period.key,
                                                        )
                                                    "
                                                    :details="
                                                        getCellDetails(
                                                            room.id,
                                                            date.key,
                                                            period.key,
                                                        )!
                                                    "
                                                    :zoom="zoom"
                                                    :status="
                                                        getCellDetails(
                                                            room.id,
                                                            date.key,
                                                            period.key,
                                                        )!.assignment?.status
                                                    "
                                                    :show-actions="true"
                                                    :is-dragged="
                                                        isDraggedAssignment(
                                                            room.id,
                                                            date.key,
                                                            period.key,
                                                        )
                                                    "
                                                    @dragstart="
                                                        onAssignmentDragStart(
                                                            room.id,
                                                            date.key,
                                                            period.key,
                                                            $event,
                                                        )
                                                    "
                                                    @dragend="clearDragState()"
                                                    @mark-cancelled="
                                                        updateAssignmentStatus(
                                                            getCellDetails(
                                                                room.id,
                                                                date.key,
                                                                period.key,
                                                            )!.assignment!,
                                                            'cancelled',
                                                        )
                                                    "
                                                    @mark-late="
                                                        updateAssignmentStatus(
                                                            getCellDetails(
                                                                room.id,
                                                                date.key,
                                                                period.key,
                                                            )!.assignment!,
                                                            'late',
                                                        )
                                                    "
                                                    @reset-status="
                                                        updateAssignmentStatus(
                                                            getCellDetails(
                                                                room.id,
                                                                date.key,
                                                                period.key,
                                                            )!.assignment!,
                                                            'planned',
                                                        )
                                                    "
                                                    @delete="
                                                        deleteAssignment(
                                                            getCellDetails(
                                                                room.id,
                                                                date.key,
                                                                period.key,
                                                            )!.assignment!,
                                                        )
                                                    "
                                                />

                                                <div
                                                    v-else
                                                    class="relative flex h-full items-center justify-center"
                                                >
                                                    <PlaceholderPattern
                                                        v-if="
                                                            !isDropTarget(
                                                                room.id,
                                                                date.key,
                                                                period.key,
                                                            )
                                                        "
                                                        :accent="
                                                            isTodayDate(
                                                                date.key,
                                                            )
                                                        "
                                                    />
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside
                        class="relative flex h-full shrink-0 flex-col border-l border-zinc-300/70 bg-zinc-50/70 transition-[width,opacity] duration-300 ease-out dark:border-zinc-700/70 dark:bg-zinc-900/45"
                        :class="[
                            isCoursePanelOpen ? 'opacity-100' : 'opacity-0',
                            isCoursePanelResizing ? 'transition-none' : '',
                        ]"
                        :style="coursePanelStyle"
                    >
                        <button
                            v-if="isCoursePanelOpen"
                            type="button"
                            class="absolute inset-y-0 left-0 z-20 w-2 -translate-x-1/2 cursor-col-resize"
                            aria-label="Redimensionner la bibliotheque des cours"
                            @mousedown="onCoursePanelResizeStart"
                        />

                        <div
                            class="flex h-full min-w-0 flex-col transition-opacity duration-200"
                            :class="
                                isCoursePanelContentVisible
                                    ? 'pointer-events-auto opacity-100'
                                    : 'pointer-events-none opacity-0'
                            "
                        >
                            <div
                                class="shrink-0 border-b border-zinc-200/70 px-3 py-2 dark:border-zinc-700/70"
                            >
                                <p
                                    class="text-xs font-semibold tracking-wide text-zinc-600 uppercase dark:text-zinc-300"
                                >
                                    Bibliotheque des cours
                                </p>
                                <p
                                    class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"
                                >
                                    Faites glisser une carte vers une case vide.
                                </p>

                                <input
                                    v-model="courseSearchQuery"
                                    type="search"
                                    placeholder="Rechercher code ou nom..."
                                    class="mt-2 h-8 w-full rounded-md border border-zinc-300 bg-white px-2 text-xs text-zinc-700 outline-none placeholder:text-zinc-400 focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:placeholder:text-zinc-500 dark:focus:border-zinc-500"
                                />
                            </div>

                            <div class="min-h-0 flex-1 overflow-y-auto p-2">
                                <div class="space-y-1.5">
                                    <div
                                        v-for="course in filteredCourses"
                                        :key="`course-${course.id}`"
                                        class="h-24"
                                    >
                                        <SchedulerAssignmentCard
                                            :details="getCourseDetails(course)"
                                            :zoom="coursePanelCardZoom"
                                            :is-dragged="
                                                draggedCourseId === course.id
                                            "
                                            :show-actions="false"
                                            @dragstart="
                                                onCourseDragStart(
                                                    course,
                                                    $event,
                                                )
                                            "
                                            @dragend="clearDragState()"
                                        />
                                    </div>

                                    <p
                                        v-if="!filteredCourses.length"
                                        class="px-2 py-4 text-center text-xs text-zinc-500 dark:text-zinc-400"
                                    >
                                        Aucun cours ne correspond a la
                                        recherche.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>

        <div
            v-if="isRangeLoading"
            class="absolute inset-0 z-40 flex items-center justify-center bg-white/55 backdrop-blur-[1px] dark:bg-zinc-950/55"
        >
            <div
                class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-white/90 px-3 py-2 text-sm font-medium text-zinc-700 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/90 dark:text-zinc-200"
            >
                <span
                    class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-700 dark:border-zinc-600 dark:border-t-zinc-200"
                />
                Chargement du planning...
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="draggedCellDetails"
                ref="dragPreviewRef"
                class="pointer-events-none fixed -rotate-2 opacity-95"
                :style="{ top: '-9999px', left: '-9999px', zIndex: 9999 }"
            >
                <SchedulerDragPreview
                    :details="draggedCellDetails"
                    :zoom="zoom"
                    :width="dragPreviewWidth"
                />
            </div>
        </Teleport>

        <!-- Bulk insert dialog -->
        <Dialog v-model:open="isBulkDialogOpen">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            bulkStep === 1
                                ? 'Insertion en masse'
                                : 'Confirmer les dates et locaux'
                        }}
                    </DialogTitle>
                </DialogHeader>

                <!-- Step 1: configuration -->
                <template v-if="bulkStep === 1">
                    <div class="flex flex-col gap-5 py-2">
                        <!-- Course -->
                        <div class="flex flex-col gap-1.5">
                            <Label>Cours</Label>
                            <Combobox
                                v-model="bulkForm.course"
                                :options="courses"
                                :display-function="
                                    (c: Course) => `${c.code} — ${c.name}`
                                "
                                placeholder="Sélectionner un cours…"
                                :nullable="true"
                            />
                        </div>

                        <!-- Default room -->
                        <div class="flex flex-col gap-1.5">
                            <Label>Local</Label>
                            <Combobox
                                v-model="bulkForm.room"
                                :options="props.rooms"
                                :display-function="(r: Room) => r.name"
                                placeholder="Sélectionner un local…"
                                :nullable="true"
                            />
                        </div>

                        <!-- Day radio buttons -->
                        <div class="flex flex-col gap-1.5">
                            <Label>Jour</Label>
                            <div class="flex gap-1.5">
                                <button
                                    v-for="d in days"
                                    :key="d.value"
                                    type="button"
                                    class="flex-1 cursor-pointer rounded-md border py-2.5 text-sm font-bold transition-colors"
                                    :class="
                                        bulkForm.day_of_week === d.value
                                            ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                                            : 'border-zinc-200 text-zinc-500 hover:border-zinc-400 hover:text-zinc-800 dark:border-zinc-700 dark:text-zinc-400 dark:hover:border-zinc-500 dark:hover:text-zinc-100'
                                    "
                                    @click="bulkForm.day_of_week = d.value"
                                >
                                    {{ d.short }}
                                </button>
                            </div>
                        </div>

                        <!-- Period radio buttons -->
                        <div class="flex flex-col gap-1.5">
                            <Label>Période</Label>
                            <div class="flex gap-1.5">
                                <button
                                    v-for="p in periods"
                                    :key="p.key"
                                    type="button"
                                    class="flex-1 cursor-pointer rounded-md border py-2.5 text-sm font-semibold transition-colors"
                                    :class="
                                        bulkForm.period === p.key
                                            ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                                            : 'border-zinc-200 text-zinc-500 hover:border-zinc-400 hover:text-zinc-800 dark:border-zinc-700 dark:text-zinc-400 dark:hover:border-zinc-500 dark:hover:text-zinc-100'
                                    "
                                    @click="bulkForm.period = p.key"
                                >
                                    {{ p.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Week range -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <Label>Semaine de début</Label>
                                <SchedulerWeekPicker
                                    v-model="bulkForm.start_week"
                                />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label>Semaine de fin</Label>
                                <SchedulerWeekPicker
                                    v-model="bulkForm.end_week"
                                />
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <p
                            v-if="bulkError"
                            class="mr-auto text-sm text-red-600 dark:text-red-400"
                        >
                            {{ bulkError }}
                        </p>
                        <Button
                            variant="outline"
                            @click="isBulkDialogOpen = false"
                            :disabled="isBulkSubmitting"
                            >Annuler</Button
                        >
                        <Button
                            @click="submitStep1"
                            :disabled="isBulkSubmitting"
                        >
                            {{ isBulkSubmitting ? 'Chargement…' : 'Suivant →' }}
                        </Button>
                    </DialogFooter>
                </template>

                <!-- Step 2: preview & confirm -->
                <template v-else>
                    <div class="flex flex-col gap-3 py-2">
                        <!-- Selection recap -->
                        <div class="flex flex-wrap gap-2">
                            <div
                                class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <span
                                    class="rounded bg-zinc-900 px-1.5 py-0.5 font-mono text-xs font-bold text-white dark:bg-zinc-100 dark:text-zinc-900"
                                >
                                    {{ bulkForm.course?.code }}
                                </span>
                                <span
                                    class="text-sm font-medium text-zinc-800 dark:text-zinc-200"
                                    >{{ bulkForm.course?.name }}</span
                                >
                            </div>
                            <div
                                class="flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
                            >
                                <span>📅</span>
                                <span>{{
                                    days.find(
                                        (d) => d.value === bulkForm.day_of_week,
                                    )?.label
                                }}</span>
                            </div>
                            <div
                                class="flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
                            >
                                <span>🕐</span>
                                <span>{{
                                    periods.find(
                                        (p) => p.key === bulkForm.period,
                                    )?.label
                                }}</span>
                            </div>
                        </div>

                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ previewRows.filter((r) => r.selected).length }} /
                            {{ previewRows.length }} dates sélectionnées
                        </p>

                        <div
                            class="max-h-105 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700"
                        >
                            <div
                                v-for="(row, index) in previewRows"
                                :key="row.date"
                                class="flex items-center gap-3 border-b border-zinc-200 px-4 py-3 last:border-b-0 dark:border-zinc-700"
                                :class="
                                    index % 2 === 0
                                        ? 'bg-white dark:bg-zinc-900'
                                        : 'bg-zinc-50/60 dark:bg-zinc-800/30'
                                "
                            >
                                <!-- Checkbox -->
                                <input
                                    type="checkbox"
                                    v-model="row.selected"
                                    class="h-4 w-4 shrink-0 cursor-pointer rounded accent-zinc-800"
                                />

                                <!-- Date + status block -->
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-sm font-medium text-zinc-800 dark:text-zinc-200"
                                    >
                                        {{ formatPreviewDate(row.date) }}
                                    </p>

                                    <!-- Real conflict (different course) -->
                                    <div
                                        v-if="
                                            isRealConflict(
                                                row.date,
                                                row.room.id,
                                            )
                                        "
                                        class="mt-1 flex items-center gap-1.5 text-xs"
                                    >
                                        <span
                                            class="rounded bg-amber-100 px-1.5 py-0.5 font-mono font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                                        >
                                            {{
                                                getConflict(
                                                    row.date,
                                                    row.room.id,
                                                )!.course.code
                                            }}
                                        </span>
                                        <span
                                            class="text-amber-600 dark:text-amber-400"
                                        >
                                            {{
                                                getConflict(
                                                    row.date,
                                                    row.room.id,
                                                )!.course.name
                                            }}
                                        </span>
                                    </div>

                                    <!-- Same course already here -->
                                    <p
                                        v-else-if="
                                            isSameCourse(row.date, row.room.id)
                                        "
                                        class="mt-1 text-xs text-zinc-400 dark:text-zinc-500"
                                    >
                                        Ce cours est déjà présent ici
                                    </p>

                                    <!-- Free -->
                                    <p
                                        v-else
                                        class="mt-1 text-xs text-emerald-600 dark:text-emerald-400"
                                    >
                                        Ce local est libre
                                    </p>
                                </div>

                                <!-- Per-row room override -->
                                <div class="w-44 shrink-0">
                                    <Combobox
                                        v-model="row.room"
                                        :options="props.rooms"
                                        :display-function="(r: Room) => r.name"
                                        :filter-function="
                                            (r: Room, q: string) =>
                                                r.name
                                                    .toLowerCase()
                                                    .includes(q.toLowerCase())
                                        "
                                        :badge-function="
                                            (r: Room) =>
                                                isRealConflict(row.date, r.id)
                                                    ? {
                                                          label: 'Occupé',
                                                          color: '#d97706',
                                                      }
                                                    : isSameCourse(
                                                            row.date,
                                                            r.id,
                                                        )
                                                      ? {
                                                            label: 'Déjà présent',
                                                            color: '#a1a1aa',
                                                        }
                                                      : {
                                                            label: 'Libre',
                                                            color: '#10b981',
                                                        }
                                        "
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <p
                            v-if="selectedConflictCount > 0"
                            class="text-right text-xs text-amber-600 dark:text-amber-400"
                        >
                            {{
                                selectedConflictCount === 1
                                    ? 'Un cours existant sera remplacé, cette action est destructive.'
                                    : `${selectedConflictCount} cours existants seront remplacés, cette action est destructive.`
                            }}
                        </p>
                        <div class="flex items-center justify-end gap-2">
                            <p
                                v-if="bulkError"
                                class="mr-auto text-sm text-red-600 dark:text-red-400"
                            >
                                {{ bulkError }}
                            </p>
                            <Button
                                variant="outline"
                                @click="
                                    bulkStep = 1;
                                    bulkError = null;
                                "
                                :disabled="isBulkSubmitting"
                                >← Retour</Button
                            >
                            <Button
                                @click="submitBulk"
                                :disabled="
                                    isBulkSubmitting ||
                                    previewRows.filter((r) => r.selected)
                                        .length === 0
                                "
                                :class="
                                    selectedConflictCount > 0
                                        ? 'border-transparent bg-amber-500 text-white hover:bg-amber-600'
                                        : ''
                                "
                            >
                                {{
                                    isBulkSubmitting
                                        ? 'Insertion…'
                                        : (() => {
                                              const total = previewRows.filter(
                                                  (r) => r.selected,
                                              ).length;
                                              const conflicts =
                                                  selectedConflictCount;
                                              const inserts = total - conflicts;
                                              if (conflicts === 0)
                                                  return `Insérer (${total})`;
                                              if (inserts === 0)
                                                  return `Remplacer (${conflicts})`;
                                              return `Insérer (${inserts}) et Remplacer (${conflicts})`;
                                          })()
                                }}
                            </Button>
                        </div>
                    </div>
                </template>
            </DialogContent>
        </Dialog>
    </div>
</template>
