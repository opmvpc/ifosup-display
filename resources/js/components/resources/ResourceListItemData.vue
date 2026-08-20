<script setup lang="ts">
import type { Component } from 'vue';

interface Props {
    icon?: Component; // On passe le composant Lucide directement
    label?: string | number;
}

defineProps<Props>();
</script>

<template>
    <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
        <component v-if="icon" :is="icon" class="h-3 w-3 shrink-0" />

        <span class="text-xs leading-none">
            <slot>{{ label }}</slot>
        </span>
    </div>
</template>
