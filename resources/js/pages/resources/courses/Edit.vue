<script setup lang="ts">
import actions from '@/actions/App/Http/Controllers/CourseController';
import Combobox from '@/components/Combobox.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceFormLayout from '@/layouts/resources/ResourceFormLayout.vue';

const props = defineProps<{
    course: Course;
    teachers: Teacher[];
    groups: Group[];
}>();

const routes = useResourceRoutes(null, actions);

const yearOptions = [
    { id: 1, name: '1ère année' },
    { id: 2, name: '2e année' },
    { id: 3, name: '3e année' },
];

const defaultYear =
    yearOptions.find((opt) => opt.id === props.course.year) ?? null;
</script>

<template>
    <ResourceFormLayout
        :title="course.name"
        description="Modifiez les informations du cours."
        type="Cours"
        :routes="routes"
        :form-action="actions.update.form(props.course.id)"
        :is-edit="true"
    >
        <template #default="{ errors }">
            <div class="grid gap-2">
                <Label for="code">Code du cours</Label>
                <Input
                    id="code"
                    class="mt-1 block w-full"
                    name="code"
                    required
                    autocomplete="code"
                    placeholder="Code du cours"
                    :default-value="course.code"
                />
                <InputError class="mt-2" :message="errors.code" />
            </div>
            <div class="grid gap-2">
                <Label for="name">Nom du cours</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    required
                    autocomplete="name"
                    placeholder="Nom complet"
                    :default-value="course.name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="year">Année</Label>
                <Combobox
                    :options="yearOptions"
                    placeholder="Sélectionner une année"
                    name="year"
                    valueKey="id"
                    :displayFunction="(opt) => opt.name"
                    :default-value="defaultYear"
                />
                <InputError class="mt-2" :message="errors.year" />
            </div>
            <div class="grid gap-2">
                <Label for="teacher">Enseignant</Label>
                <Combobox
                    :options="props.teachers"
                    placeholder="Séléctionner un enseignant"
                    name="teacher_id"
                    valueKey="id"
                    :displayFunction="(opt) => opt.name"
                    :default-value="course.teacher"
                />
                <InputError class="mt-2" :message="errors.teacher_id" />
            </div>
            <div class="grid gap-2">
                <Label for="group">Sections</Label>
                <Combobox
                    :options="props.groups"
                    multiple
                    placeholder="Séléctionner le(s) section(s)"
                    name="groups"
                    valueKey="id"
                    :displayFunction="(opt) => opt.name"
                    :default-value="course.groups"
                />
                <InputError class="mt-2" :message="errors.groups" />
            </div>
        </template>
    </ResourceFormLayout>
</template>
