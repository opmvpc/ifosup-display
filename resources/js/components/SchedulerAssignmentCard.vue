<script setup lang="ts">
import {
    CheckCircle2,
    Clock3,
    Settings2,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const ACTION_MENU_OPEN_EVENT = 'scheduler-assignment-card-menu-open';

interface AssignmentCardCourse {
    code: string;
    name: string;
}

interface AssignmentCardTheme {
    primary: string;
    outline: string;
    background: string;
    accentBackground: string;
    accentForeground: string;
    foreground: string;
}

interface AssignmentCardDetails {
    course: AssignmentCardCourse;
    theme: AssignmentCardTheme;
}

interface AssignmentCardProps {
    details: AssignmentCardDetails;
    zoom: string;
    isDragged: boolean;
    status?: AssignmentStatus;
    showActions?: boolean;
}

defineEmits<{
    dragstart: [event: DragEvent];
    dragend: [event: DragEvent];
    delete: [];
    markCancelled: [];
    markLate: [];
    resetStatus: [];
}>();

const props = withDefaults(defineProps<AssignmentCardProps>(), {
    status: 'planned',
    showActions: false,
});

const cardPaddingClass = computed(() => {
    return props.zoom === 'small' ? 'px-2 py-1.5' : 'px-3 py-2';
});

const badgeClass = computed(() => {
    return props.zoom === 'small'
        ? 'text-[8px] px-1.5 py-0'
        : 'text-[10px] px-2 py-0.5';
});

const titleClass = computed(() => {
    return props.zoom === 'small' ? 'text-[10px]' : 'mt-2 text-sm';
});

const isActionMenuOpen = ref(false);
const actionMenuRef = ref<HTMLElement | null>(null);
const actionMenuTriggerRef = ref<HTMLElement | null>(null);
const actionMenuPosition = ref({ top: 0, left: 0 });
const actionMenuInstanceId = `assignment-card-${Math.random().toString(36).slice(2, 11)}`;

const ACTION_MENU_WIDTH = 232;
const ACTION_MENU_ESTIMATED_HEIGHT = 176;
const VIEWPORT_MARGIN = 8;

const statusLabel = computed(() => {
    if (props.status === 'cancelled') {
        return 'Annulé';
    }

    if (props.status === 'late') {
        return 'En retard';
    }

    return 'Planifié';
});

const showStatusBadge = computed(() => props.status !== 'planned');

const statusClass = computed(() => {
    if (props.status === 'cancelled') {
        return 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300';
    }

    if (props.status === 'late') {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300';
    }

    return '';
});

const statusCardClass = computed(() => {
    if (props.status === 'cancelled') {
        return 'opacity-75';
    }

    if (props.status === 'late') {
        return 'ring-1 ring-amber-300/70 dark:ring-amber-700/70';
    }

    return '';
});

const updateActionMenuPosition = () => {
    if (!actionMenuTriggerRef.value) {
        return;
    }

    const triggerRect = actionMenuTriggerRef.value.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    let left = triggerRect.right - ACTION_MENU_WIDTH;
    left = Math.max(
        VIEWPORT_MARGIN,
        Math.min(left, viewportWidth - ACTION_MENU_WIDTH - VIEWPORT_MARGIN),
    );

    let top = triggerRect.bottom + 8;
    const maxTop =
        viewportHeight - ACTION_MENU_ESTIMATED_HEIGHT - VIEWPORT_MARGIN;

    if (top > maxTop) {
        top = triggerRect.top - ACTION_MENU_ESTIMATED_HEIGHT - 8;
    }

    top = Math.max(VIEWPORT_MARGIN, top);

    actionMenuPosition.value = {
        top,
        left,
    };
};

const toggleActionMenu = async () => {
    isActionMenuOpen.value = !isActionMenuOpen.value;

    if (isActionMenuOpen.value) {
        window.dispatchEvent(
            new CustomEvent(ACTION_MENU_OPEN_EVENT, {
                detail: {
                    sourceId: actionMenuInstanceId,
                },
            }),
        );

        await nextTick();
        updateActionMenuPosition();
    }
};

const closeActionMenu = () => {
    isActionMenuOpen.value = false;
};

const onWindowClick = (event: MouseEvent) => {
    if (!isActionMenuOpen.value) {
        return;
    }

    const target = event.target as Node;
    const clickedInsideMenu = actionMenuRef.value?.contains(target) ?? false;
    const clickedOnTrigger =
        actionMenuTriggerRef.value?.contains(target) ?? false;

    if (!clickedInsideMenu && !clickedOnTrigger) {
        closeActionMenu();
    }
};

const onWindowKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closeActionMenu();
    }
};

const onWindowScrollOrResize = () => {
    if (!isActionMenuOpen.value) {
        return;
    }

    updateActionMenuPosition();
};

const onAnotherMenuOpened = (event: Event) => {
    const customEvent = event as CustomEvent<{ sourceId?: string }>;
    const sourceId = customEvent.detail?.sourceId;

    if (!sourceId || sourceId === actionMenuInstanceId) {
        return;
    }

    closeActionMenu();
};

onMounted(() => {
    window.addEventListener('click', onWindowClick);
    window.addEventListener('keydown', onWindowKeydown);
    window.addEventListener('scroll', onWindowScrollOrResize, true);
    window.addEventListener('resize', onWindowScrollOrResize);
    window.addEventListener(
        ACTION_MENU_OPEN_EVENT,
        onAnotherMenuOpened as EventListener,
    );
});

onBeforeUnmount(() => {
    window.removeEventListener('click', onWindowClick);
    window.removeEventListener('keydown', onWindowKeydown);
    window.removeEventListener('scroll', onWindowScrollOrResize, true);
    window.removeEventListener('resize', onWindowScrollOrResize);
    window.removeEventListener(
        ACTION_MENU_OPEN_EVENT,
        onAnotherMenuOpened as EventListener,
    );
});
</script>

<template>
    <div
        class="group/card m-1 flex h-[calc(100%-0.5rem)] flex-col justify-between rounded-xl border shadow-[0_10px_20px_rgba(0,0,0,0.08)]"
        :class="[
            cardPaddingClass,
            isDragged ? 'cursor-grabbing opacity-55' : 'cursor-grab',
            statusCardClass,
        ]"
        :style="{
            backgroundColor: details.theme.background,
            borderColor: details.theme.outline,
        }"
        draggable="true"
        @dragstart="$emit('dragstart', $event)"
        @dragend="$emit('dragend', $event)"
    >
        <div class="flex items-start justify-between gap-2">
            <span
                class="truncate rounded-full font-bold tracking-wide uppercase"
                :class="badgeClass"
                :style="{
                    backgroundColor: details.theme.accentBackground,
                    color: details.theme.accentForeground,
                }"
            >
                {{ details.course.code }}
            </span>

            <div class="flex items-center gap-1.5">
                <span
                    v-if="showStatusBadge"
                    class="inline-flex items-center rounded-full border border-black px-2 py-0.5 text-xs font-semibold dark:border-white"
                    :class="statusClass"
                >
                    {{ statusLabel }}
                </span>

                <div
                    v-if="showActions"
                    ref="actionMenuTriggerRef"
                    class="inline-flex cursor-pointer items-center text-neutral-700 opacity-0 transition-colors group-hover:opacity-100 hover:text-black dark:text-neutral-200 dark:hover:text-white"
                    title="Actions"
                    role="button"
                    tabindex="0"
                    @mousedown.stop
                    @click.stop="toggleActionMenu"
                    @keydown.enter.prevent.stop="toggleActionMenu"
                    @keydown.space.prevent.stop="toggleActionMenu"
                >
                    <Settings2 class="h-4 w-4" />
                </div>
            </div>
        </div>

        <div
            class="leading-tight font-semibold wrap-break-word"
            :class="titleClass"
            :style="{ color: details.theme.foreground }"
        >
            {{ details.course.name }}
        </div>

        <Teleport to="body">
            <div
                v-if="isActionMenuOpen"
                ref="actionMenuRef"
                class="fixed z-120 w-58 rounded-lg border border-zinc-200/80 bg-white/95 p-1 shadow-lg backdrop-blur-sm dark:border-zinc-700/80 dark:bg-zinc-900/95"
                :style="{
                    top: `${actionMenuPosition.top}px`,
                    left: `${actionMenuPosition.left}px`,
                }"
                @mousedown.stop
                @click.stop
            >
                <button
                    v-if="status !== 'cancelled'"
                    type="button"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    @click="
                        $emit('markCancelled');
                        closeActionMenu();
                    "
                >
                    <XCircle class="h-3.5 w-3.5" />
                    Marquer comme annulé
                </button>

                <button
                    v-if="status !== 'late'"
                    type="button"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    @click="
                        $emit('markLate');
                        closeActionMenu();
                    "
                >
                    <Clock3 class="h-3.5 w-3.5" />
                    Signaler comme en retard
                </button>

                <button
                    v-if="status !== 'planned'"
                    type="button"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    @click="
                        $emit('resetStatus');
                        closeActionMenu();
                    "
                >
                    <CheckCircle2 class="h-3.5 w-3.5" />
                    Replanifier
                </button>

                <div
                    class="my-1 border-t border-zinc-200/80 dark:border-zinc-700/80"
                />

                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs font-medium text-red-700 transition-colors hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30"
                    @click="
                        $emit('delete');
                        closeActionMenu();
                    "
                >
                    <Trash2 class="h-3.5 w-3.5" />
                    Supprimer
                </button>
            </div>
        </Teleport>
    </div>
</template>
