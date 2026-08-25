<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import actions from '@/actions/App/Http/Controllers/Admin/UserController';
import ResourceListItem from '@/components/resources/ResourceListItem.vue';
import { Input } from '@/components/ui/input';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceIndexLayout from '@/layouts/resources/ResourceIndexLayout.vue';
import { userAvatar } from '@/lib/avatars';

const props = defineProps<{
    users: { id: number; name: string; email: string; created_at: string }[];
}>();

const routes = useResourceRoutes(null, actions);
const query = ref('');

const filteredUsers = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.users;
    return props.users.filter(
        (u) =>
            u.name.toLowerCase().includes(q) ||
            u.email.toLowerCase().includes(q),
    );
});
</script>

<template>
    <ResourceIndexLayout
        type="Utilisateurs"
        description="Gérez les comptes utilisateurs de l'application."
        :routes="routes"
        :isEmpty="users.length === 0"
    >
        <template #empty-title>Aucun utilisateur.</template>
        <template #empty-action>Créer un utilisateur</template>
        <template #create-action>Créer un utilisateur</template>

        <div class="relative mb-4 max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="query"
                placeholder="Rechercher un utilisateur…"
                class="pl-9"
            />
        </div>
        <div class="grid gap-3">
            <ResourceListItem
                v-for="user in filteredUsers"
                :key="user.id"
                :href="actions.show(user.id).url"
                :title="user.name"
                :image="userAvatar(user.email)"
            >
                <span class="text-xs text-zinc-400">{{ user.email }}</span>
            </ResourceListItem>
            <p
                v-if="filteredUsers.length === 0 && query"
                class="py-6 text-center text-sm text-muted-foreground"
            >
                Aucun résultat pour « {{ query }} ».
            </p>
        </div>
    </ResourceIndexLayout>
</template>
