<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { ResourceRoutes } from '@/composables/useResourceRoutes';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

interface ResourceFormLayoutProps {
    title: string;
    type: string;
    routes: ResourceRoutes;
    formAction: RouteFormDefinition<any>;
    description?: string;
    isEdit?: boolean;
}

const props = withDefaults(defineProps<ResourceFormLayoutProps>(), {
    description: 'Remplissez les informations de la ressource.',
    isEdit: false,
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
    ...(props.isEdit
        ? [
              {
                  title: props.title,
                  href: props.routes.show,
              },
              {
                  title: 'Modifier',
                  href: props.routes.edit,
              },
          ]
        : [
              {
                  title: 'Créer',
                  href: props.routes.create,
              },
          ]),
];

const createAnother = ref(false);
const page = usePage();
const formKey = computed(() => (page.props as any)._formKey as string);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="mx-auto w-full max-w-5xl px-4 py-6">
            <div class="flex flex-col space-y-6">
                <Heading
                    :title="props.title"
                    :description="props.description"
                />
                <Form
                    v-bind="props.formAction"
                    :key="formKey"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <input
                        type="hidden"
                        name="_create_another"
                        :value="createAnother ? '1' : '0'"
                    />

                    <slot :errors="errors" :processing="processing" />

                    <div class="flex items-center gap-4">
                        <Button
                            as-child
                            :disabled="processing"
                            variant="destructive"
                        >
                            <Link
                                :href="
                                    props.isEdit
                                        ? props.routes.show
                                        : props.routes.index
                                "
                            >
                                Annuler
                            </Link>
                        </Button>
                        <Button
                            @mousedown="createAnother = false"
                            :disabled="processing"
                            >Enregistrer</Button
                        >
                        <Button
                            v-if="!isEdit"
                            type="submit"
                            variant="outline"
                            @mousedown="createAnother = true"
                            :disabled="processing"
                            >Créer et créer un autre</Button
                        >
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
