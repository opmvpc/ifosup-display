<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import actions from '@/actions/App/Http/Controllers/GroupController';
import ResourceListItem from '@/components/resources/ResourceListItem.vue';
import { Input } from '@/components/ui/input';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceIndexLayout from '@/layouts/resources/ResourceIndexLayout.vue';
import { groupAvatar } from '@/lib/avatars';

const props = defineProps<{
    groups: Group[];
}>();

const routes = useResourceRoutes(null, actions);
const query = ref('');

const filteredGroups = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.groups;
    return props.groups.filter((g) => g.name.toLowerCase().includes(q));
});
</script>

<template>
    <ResourceIndexLayout
        type="Sections"
        :routes="routes"
        :isEmpty="groups.length === 0"
    >
        <template #empty-title>Il n'y a aucune section.</template>
        <template #empty-action>Créer une section</template>
        <template #create-action>Créer une section</template>

        <div class="relative mb-4 max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="query"
                placeholder="Rechercher une section…"
                class="pl-9"
            />
        </div>
        <div class="grid gap-3">
            <ResourceListItem
                v-for="group in filteredGroups"
                :key="group.id"
                :href="actions.show(group.id).url"
                :title="group.name"
                :image="groupAvatar(group.name)"
            >
            </ResourceListItem>
            <p
                v-if="filteredGroups.length === 0 && query"
                class="py-6 text-center text-sm text-muted-foreground"
            >
                Aucun résultat pour « {{ query }} ».
            </p>
        </div>
    </ResourceIndexLayout>
</template>
