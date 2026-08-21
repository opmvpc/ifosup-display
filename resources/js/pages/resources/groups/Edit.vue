<script setup lang="ts">
import actions from '@/actions/App/Http/Controllers/GroupController';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceFormLayout from '@/layouts/resources/ResourceFormLayout.vue';

const props = defineProps<{
    group: Group;
}>();

const routes = useResourceRoutes(null, actions);
</script>

<template>
    <ResourceFormLayout
        :title="group.name"
        description="Modifiez les informations de la section."
        type="Sections"
        :routes="routes"
        :form-action="actions.update.form(props.group.id)"
        :is-edit="true"
    >
        <template #default="{ errors }">
            <div class="grid gap-2">
                <Label for="name">Nom de la section</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    required
                    autocomplete="name"
                    placeholder="Nom complet"
                    :default-value="group.name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>
        </template>
    </ResourceFormLayout>
</template>
