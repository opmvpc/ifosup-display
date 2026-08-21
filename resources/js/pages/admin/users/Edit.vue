<script setup lang="ts">
import actions from '@/actions/App/Http/Controllers/Admin/UserController';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceFormLayout from '@/layouts/resources/ResourceFormLayout.vue';

const props = defineProps<{
    user: { id: number; name: string; email: string };
}>();

const routes = useResourceRoutes(props.user.id, actions);
</script>

<template>
    <ResourceFormLayout
        :title="user.name"
        description="Modifiez les informations du compte utilisateur."
        type="Utilisateurs"
        :routes="routes"
        :form-action="actions.update.form(user.id)"
        :is-edit="true"
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
                    :default-value="user.name"
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
                    :default-value="user.email"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password"
                    >Nouveau mot de passe
                    <span class="text-xs text-muted-foreground"
                        >(laisser vide pour ne pas changer)</span
                    ></Label
                >
                <Input
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    name="password"
                    autocomplete="new-password"
                    placeholder="••••••••"
                />
                <InputError class="mt-2" :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation"
                    >Confirmer le nouveau mot de passe</Label
                >
                <Input
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    name="password_confirmation"
                    autocomplete="new-password"
                    placeholder="••••••••"
                />
            </div>
        </template>
    </ResourceFormLayout>
</template>
