<script setup lang="ts">
import actions from '@/actions/App/Http/Controllers/RoomController';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceFormLayout from '@/layouts/resources/ResourceFormLayout.vue';

const routes = useResourceRoutes(null, actions);
</script>

<template>
    <ResourceFormLayout
        title="Nouveau Local"
        description="Ajoutez un nouveau local dans la base de données."
        type="Locaux"
        :routes="routes"
        :form-action="actions.store.form()"
    >
        <template #default="{ errors }">
            <div class="grid gap-2">
                <Label for="name">Nom du local</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    required
                    autocomplete="name"
                    placeholder="Nom complet"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>
        </template>
    </ResourceFormLayout>
</template>
