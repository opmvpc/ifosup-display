<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
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

const pageErrors = computed(
    () => ((page.props as any).errors ?? {}) as Record<string, string>,
);
const hasErrors = computed(() => Object.keys(pageErrors.value).length > 0);

// `_formKey` est un identifiant régénéré à chaque requête par AppServiceProvider :
// il sert à remonter le formulaire après « Créer et créer un autre », afin de repartir
// de champs vides. Mais il change AUSSI au retour d'une validation échouée — le
// formulaire était alors détruit et recréé, ce qui effaçait les messages d'erreur et
// les valeurs saisies. La clé n'est donc mise à jour que lorsqu'aucune erreur n'est
// présente.
const formKey = ref((page.props as any)._formKey as string);

watch(
    () => (page.props as any)._formKey as string,
    (nouvelleCle) => {
        if (!hasErrors.value) {
            formKey.value = nouvelleCle;
        }
    },
);
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

                    <!-- Les erreurs de la page servent de filet : si le formulaire
                         a malgré tout été remonté, celles du composant Form sont vides. -->
                    <slot
                        :errors="{ ...pageErrors, ...errors }"
                        :processing="processing"
                    />

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
                        <!-- À la création, l'action mise en avant (noire) est
                             « Enregistrer et créer un autre » : l'encodage se
                             fait en série. En édition, « Enregistrer » est seul
                             et reste l'action principale. -->
                        <Button
                            :variant="isEdit ? 'default' : 'outline'"
                            @mousedown="createAnother = false"
                            :disabled="processing"
                            >Enregistrer</Button
                        >
                        <Button
                            v-if="!isEdit"
                            type="submit"
                            @mousedown="createAnother = true"
                            :disabled="processing"
                            >Enregistrer et créer un autre</Button
                        >
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
