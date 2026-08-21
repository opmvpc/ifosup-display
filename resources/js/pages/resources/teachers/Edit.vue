<script setup lang="ts">
import actions from '@/actions/App/Http/Controllers/TeacherController';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceFormLayout from '@/layouts/resources/ResourceFormLayout.vue';

const props = defineProps<{
    teacher: Teacher;
}>();

const routes = useResourceRoutes(null, actions);
</script>

<template>
    <ResourceFormLayout
        :title="teacher.name"
        description="Modifiez les informations de l'enseignant."
        type="Enseignants"
        :routes="routes"
        :form-action="actions.update.form(props.teacher.id)"
        :is-edit="true"
    >
        <template #default="{ errors }">
            <div class="grid gap-2">
                <Label for="name">Nom de l'enseignant</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    required
                    autocomplete="name"
                    placeholder="Nom complet"
                    :default-value="teacher.name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>
        </template>
    </ResourceFormLayout>
</template>
