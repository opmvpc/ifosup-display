<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import actions from '@/actions/App/Http/Controllers/RoomController';
import ResourceListItem from '@/components/resources/ResourceListItem.vue';
import { Input } from '@/components/ui/input';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceIndexLayout from '@/layouts/resources/ResourceIndexLayout.vue';
import { roomAvatar } from '@/lib/avatars';
import { compareRoomNames } from '@/lib/rooms';

const props = defineProps<{
    rooms: Room[];
}>();

const routes = useResourceRoutes(null, actions);
const query = ref('');

const sortedRooms = computed(() =>
    [...props.rooms].sort((a, b) => compareRoomNames(a.name, b.name)),
);

const filteredRooms = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return sortedRooms.value;
    return sortedRooms.value.filter((r) => r.name.toLowerCase().includes(q));
});
</script>

<template>
    <ResourceIndexLayout
        type="Locaux"
        :routes="routes"
        :isEmpty="rooms.length === 0"
    >
        <template #empty-title>Il n'y a aucun local.</template>
        <template #empty-action>Créer un local</template>
        <template #create-action>Créer un local</template>

        <div class="relative mb-4 max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="query"
                placeholder="Rechercher un local…"
                class="pl-9"
            />
        </div>
        <div class="grid gap-3">
            <ResourceListItem
                v-for="room in filteredRooms"
                :key="room.id"
                :href="actions.show(room.id).url"
                :title="room.name"
                :image="roomAvatar(room.name)"
            >
            </ResourceListItem>
            <p
                v-if="filteredRooms.length === 0 && query"
                class="py-6 text-center text-sm text-muted-foreground"
            >
                Aucun résultat pour « {{ query }} ».
            </p>
        </div>
    </ResourceIndexLayout>
</template>
