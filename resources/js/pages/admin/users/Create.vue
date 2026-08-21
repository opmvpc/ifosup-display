<script setup lang="ts">
import actions from '@/actions/App/Http/Controllers/Admin/UserController';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceFormLayout from '@/layouts/resources/ResourceFormLayout.vue';

const routes = useResourceRoutes(null, actions);
</script>

<template>
    <ResourceFormLayout
        title="Nouvel utilisateur"
        description="Créez un nouveau compte utilisateur."
        type="Utilisateurs"
        :routes="routes"
        :form-action="actions.store.form()"
    >
        <template #default="{ errors }">
            <div class="grid gap-2">
                <Label for="name">Nom</Label>
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

            <div class="grid gap-2">
                <Label for="email">Adresse email</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="exemple@domaine.com"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Mot de passe</Label>
                <Input
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                />
                <InputError class="mt-2" :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation"
                    >Confirmer le mot de passe</Label
                >
                <Input
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                />
            </div>
        </template>
    </ResourceFormLayout>
</template>
