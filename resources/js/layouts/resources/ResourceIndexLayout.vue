<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { ResourceRoutes } from '@/composables/useResourceRoutes';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface ResourceIndexLayoutProps {
    type: string;
    routes: ResourceRoutes;
    description?: string;
    isEmpty?: boolean;
}

const props = withDefaults(defineProps<ResourceIndexLayoutProps>(), {
    description: 'Gérez la liste des ressources.',
    isEmpty: false,
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Ressources',
        href: '#',
    },
    {
        title: props.type,
        href: props.routes.index,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="mx-auto w-full max-w-5xl px-4 py-6">
            <div class="flex flex-col space-y-6">
                <div class="flex items-start justify-between">
                    <Heading
                        :title="props.type"
                        :description="props.description"
                    />
                    <Button as-child variant="default">
                        <Link :href="props.routes.create">
                            <slot name="create-action">Créer</slot>
                        </Link>
                    </Button>
                </div>
                <div
                    v-if="isEmpty"
                    class="aspect-video w-full rounded-xl border-2 border-dashed border-secondary"
                >
                    <div
                        class="flex h-full w-full flex-col items-center justify-center gap-8"
                    >
                        <div class="flex flex-col items-center gap-2">
                            <p class="text-lg font-semibold">
                                <slot name="empty-title"
                                    >Cette liste est vide</slot
                                >
                            </p>
                            <p
                                class="max-w-xs text-center text-sm text-muted-foreground"
                            >
                                <slot name="empty-description"
                                    >Commencez par ajouter votre premier élément
                                    pour le voir apparaître ici.</slot
                                >
                            </p>
                        </div>
                        <Button as-child variant="default">
                            <Link :href="props.routes.create">
                                <slot name="empty-action"
                                    >Créer une ressource</slot
                                >
                            </Link>
                        </Button>
                    </div>
                </div>
                <div v-else>
                    <slot />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
