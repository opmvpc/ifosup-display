<script setup lang="ts">
import { MailIcon, HashIcon, CalendarIcon } from 'lucide-vue-next';
import actions from '@/actions/App/Http/Controllers/Admin/UserController';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceShowLayout from '@/layouts/resources/ResourceShowLayout.vue';

const props = defineProps<{
    user: { id: number; name: string; email: string; created_at: string };
    deletionBlockedReason?: string | null;
}>();

const routes = useResourceRoutes(props.user.id, actions);
</script>

<template>
    <ResourceShowLayout
        :title="user.name"
        description="Consultez et gérez les informations de ce compte utilisateur."
        type="Utilisateurs"
        :routes="routes"
        deletion-warning="Supprimer définitivement ce compte utilisateur."
        deletion-confirmation-message="Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible."
        :deletion-blocked-reason="props.deletionBlockedReason"
    >
        <section
            class="overflow-hidden rounded-2xl border border-sidebar-border/60 bg-linear-to-br from-zinc-100 via-card to-zinc-200/60 dark:from-zinc-900 dark:via-zinc-900/90 dark:to-zinc-800/50"
        >
            <div class="flex flex-col gap-4 p-6">
                <h2 class="text-2xl font-semibold tracking-tight">
                    {{ user.name }}
                </h2>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span
                        class="flex items-center gap-1 rounded-full border border-sidebar-border/70 px-2.5 py-1"
                    >
                        <HashIcon class="mt-px h-3 w-3" />
                        {{ user.id }}
                    </span>
                    <span
                        class="flex items-center gap-2 rounded-full border border-sidebar-border/70 px-2.5 py-1"
                    >
                        <MailIcon class="mt-px h-3 w-3" />
                        {{ user.email }}
                    </span>
                    <span
                        class="flex items-center gap-2 rounded-full border border-sidebar-border/70 px-2.5 py-1"
                    >
                        <CalendarIcon class="mt-px h-3 w-3" />
                        Créé le
                        {{
                            new Date(user.created_at).toLocaleDateString(
                                'fr-BE',
                            )
                        }}
                    </span>
                </div>
            </div>
        </section>
    </ResourceShowLayout>
</template>
