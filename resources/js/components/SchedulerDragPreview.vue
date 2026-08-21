<script setup lang="ts">
import { computed } from 'vue';

interface DragPreviewCourse {
    code: string;
    name: string;
}

interface DragPreviewTheme {
    outline: string;
    background: string;
    accentBackground: string;
    accentForeground: string;
    foreground: string;
}

interface DragPreviewDetails {
    course: DragPreviewCourse;
    theme: DragPreviewTheme;
}

interface DragPreviewProps {
    details: DragPreviewDetails;
    zoom: string;
    width: number;
}

const props = defineProps<DragPreviewProps>();

const cardPaddingClass = computed(() => {
    return props.zoom === 'small' ? 'px-2 py-1.5' : 'px-3 py-2.5';
});

const badgeClass = computed(() => {
    return props.zoom === 'small'
        ? 'text-[8px] px-1.5 py-0'
        : 'text-[10px] px-2 py-0.5';
});

const titleClass = computed(() => {
    return props.zoom === 'small' ? 'text-[10px]' : 'text-[13px]';
});
</script>

<template>
    <div
        class="rounded-2xl border shadow-[0_18px_40px_rgba(0,0,0,0.18)] backdrop-blur-sm"
        :class="cardPaddingClass"
        :style="{
            width: `${width}px`,
            borderColor: details.theme.outline,
            backgroundColor: details.theme.background,
        }"
    >
        <span
            class="inline-block max-w-full truncate rounded-full font-bold tracking-wide uppercase"
            :class="badgeClass"
            :style="{
                backgroundColor: details.theme.accentBackground,
                color: details.theme.accentForeground,
            }"
        >
            {{ details.course.code }}
        </span>

        <div
            class="mt-2 leading-tight font-semibold wrap-break-word"
            :class="titleClass"
            :style="{ color: details.theme.foreground }"
        >
            {{ details.course.name }}
        </div>
    </div>
</template>
