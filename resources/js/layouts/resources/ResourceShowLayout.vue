<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { ResourceRoutes } from '@/composables/useResourceRoutes';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface ResourceShowLayoutProps {
    title: string;
    type: string;
    routes: ResourceRoutes;
    description?: string;
    deletionWarning?: string;
    deletionConfirmationMessage?: string;
    deletionBlockedReason?: string | null;
}

const props = withDefaults(defineProps<ResourceShowLayoutProps>(), {
    description: 'Consultez les détails de la ressource.',
    deletionWarning:
        'Supprimer définitivement cette ressource et toutes les données associées.',
    deletionConfirmationMessage:
        'Êtes-vous sûr de vouloir supprimer cette ressource ? Cette action est irréversible.',
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
    {
        title: props.title,
        href: props.routes.show,
    },
];

const deleteResource = () => {
    if (confirm(props.deletionConfirmationMessage)) {
        // Remplace par ton helper de route si tu en as un, sinon l'URL brute
        router.delete(props.routes.destroy);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="mx-auto w-full max-w-5xl px-4 py-6">
            <div class="flex flex-col space-y-6">
                <div class="flex items-start justify-between">
                    <Heading
                        :title="props.title"
                        :description="props.description"
                    />
                    <Button as-child variant="default">
                        <Link :href="props.routes.edit"> Modifier </Link>
                    </Button>
                </div>
                <div>
                    <slot />
                </div>
                <div class="pt-10">
                    <div
                        class="rounded-xl border border-red-200 bg-red-50/30 p-6 dark:border-red-900/50 dark:bg-red-900/10"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex flex-col space-y-1">
                                <h3
                                    class="text-sm font-bold text-red-600 dark:text-red-400"
                                >
                                    Zone de danger
                                </h3>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        props.deletionBlockedReason ??
                                        props.deletionWarning
                                    }}
                                </p>
                            </div>
                            <Button
                                v-if="!props.deletionBlockedReason"
                                variant="destructive"
                                @click="deleteResource"
                            >
                                Supprimer
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
