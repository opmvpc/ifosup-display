<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    href?: string;
    image?: string;
    title: string;
    actionText?: string;
    showAction?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showAction: true,
    actionText: 'Voir',
});

// Détermine si on rend un Inertia Link ou une simple Div (si pas de href)
const componentType = computed(() => (props.href ? Link : 'div'));
</script>

<template>
    <component
        :is="componentType"
        :href="href"
        class="group relative flex items-center justify-between rounded-xl border border-sidebar-border/60 bg-white p-4 no-underline transition-all duration-200 hover:border-sidebar-border hover:bg-zinc-50 hover:shadow-md hover:shadow-black/5 dark:bg-zinc-900/50 dark:hover:bg-zinc-900"
    >
        <div class="flex items-center space-x-4">
            <div
                v-if="image || $slots.image"
                class="relative h-12 w-12 shrink-0 self-start overflow-hidden rounded-lg border border-sidebar-border bg-zinc-100 transition-transform group-hover:scale-105 dark:bg-zinc-800"
            >
                <slot name="image">
                    <img
                        :src="image"
                        :alt="title"
                        class="h-full w-full object-cover"
                    />
                </slot>
            </div>

            <div class="flex flex-col justify-center">
                <span
                    class="text-sm font-semibold text-zinc-900 transition-colors group-hover:text-primary dark:text-zinc-100"
                >
                    {{ title }}
                </span>

                <div class="mt-0.5 flex flex-col space-y-0.5">
                    <slot />
                </div>
            </div>
        </div>

        <div v-if="showAction" class="flex items-center gap-3">
            <span
                v-if="actionText"
                class="hidden translate-x-2 transform text-[10px] font-bold tracking-widest text-zinc-400 uppercase opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100 sm:inline-block"
            >
                {{ actionText }}
            </span>
            <div
                class="rounded-full p-1 text-zinc-300 transition-colors group-hover:text-zinc-900 dark:group-hover:text-zinc-100"
            >
                <slot name="action-icon">
                    <ChevronRightIcon
                        class="h-4 w-4 translate-x-0 transition-transform group-hover:translate-x-0.5"
                    />
                </slot>
            </div>
        </div>
    </component>
</template>
