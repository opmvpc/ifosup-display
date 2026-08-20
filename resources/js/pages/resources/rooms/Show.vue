<script setup lang="ts">
import actions from '@/actions/App/Http/Controllers/RoomController';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceShowLayout from '@/layouts/resources/ResourceShowLayout.vue';

const props = defineProps<{
    room: Room;
}>();

const routes = useResourceRoutes(props.room.id, actions);

const getAvatarUrl = (name: string) => {
    return `https://api.dicebear.com/9.x/shapes/svg?seed=${encodeURIComponent(name)}`;
};
</script>

<template>
    <ResourceShowLayout
        :title="room.name"
        description="Consultez les détails du local et gérez ses paramètres."
        type="Locaux"
        :routes="routes"
    >
        <section
            class="overflow-hidden rounded-2xl border border-sidebar-border/60 bg-linear-to-br from-zinc-100 via-card to-zinc-200/60 dark:from-zinc-900 dark:via-zinc-900/90 dark:to-zinc-800/50"
        >
            <div class="flex items-center gap-6 p-6">
                <img
                    :src="getAvatarUrl(room.name)"
                    :alt="room.name"
                    class="h-20 w-20 shrink-0 rounded-2xl border border-sidebar-border/80 bg-white/70 p-1 dark:bg-zinc-800/70"
                />
                <div class="flex flex-col gap-3">
                    <h2 class="text-2xl font-semibold tracking-tight">
                        {{ room.name }}
                    </h2>
                </div>
            </div>
        </section>
    </ResourceShowLayout>
</template>
