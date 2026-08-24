<script setup lang="ts">
import { Lightbulb, TriangleAlert } from 'lucide-vue-next';

interface DocsCalloutProps {
    variant?: 'astuce' | 'attention';
    title?: string;
}

const props = withDefaults(defineProps<DocsCalloutProps>(), {
    variant: 'astuce',
});

const defaultTitles = {
    astuce: 'Astuce',
    attention: 'Attention',
};
</script>

<template>
    <div
        class="my-6 flex gap-3 rounded-lg border p-4"
        :class="
            props.variant === 'attention'
                ? 'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-950/30'
                : 'border-sky-300 bg-sky-50 dark:border-sky-500/40 dark:bg-sky-950/30'
        "
    >
        <TriangleAlert
            v-if="props.variant === 'attention'"
            class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400"
        />
        <Lightbulb
            v-else
            class="mt-0.5 size-5 shrink-0 text-sky-600 dark:text-sky-400"
        />
        <div class="min-w-0 text-sm leading-relaxed">
            <p class="mb-1 font-semibold text-foreground">
                {{ props.title ?? defaultTitles[props.variant] }}
            </p>
            <div class="docs-callout-body">
                <slot />
            </div>
        </div>
    </div>
</template>

<style>
.docs-callout-body p:last-child {
    margin-bottom: 0;
}
</style>
