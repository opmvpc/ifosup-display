<script setup lang="ts">
interface DocsImageProps {
    src: string;
    alt: string;
    caption?: string;
}

defineProps<DocsImageProps>();
</script>

<template>
    <figure class="my-6">
        <div
            class="overflow-hidden rounded-lg border border-border bg-muted/40"
        >
            <img :src="src" :alt="alt" class="h-auto w-full" loading="lazy" />
        </div>
        <figcaption
            v-if="caption"
            class="mt-2 text-center text-xs text-muted-foreground"
        >
            {{ caption }}
        </figcaption>
    </figure>
</template>
