<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import actions from '@/actions/App/Http/Controllers/RoomController';
import ResourceListItem from '@/components/resources/ResourceListItem.vue';
import { Input } from '@/components/ui/input';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceIndexLayout from '@/layouts/resources/ResourceIndexLayout.vue';

const props = defineProps<{
    rooms: Room[];
}>();

const routes = useResourceRoutes(null, actions);
const query = ref('');

function compareRooms(a: string, b: string): number {
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

const sortedRooms = computed(() =>
    [...props.rooms].sort((a, b) => compareRooms(a.name, b.name)),
);

const filteredRooms = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return sortedRooms.value;
    return sortedRooms.value.filter((r) => r.name.toLowerCase().includes(q));
});

const getAvatarUrl = (name: string) => {
    return `https://api.dicebear.com/9.x/shapes/svg?seed=${encodeURIComponent(name)}`;
};
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
                :image="getAvatarUrl(room.name)"
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
