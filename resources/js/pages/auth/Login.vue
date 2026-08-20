<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { store } from '@/routes/login';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Connexion" />

    <div
        class="relative flex min-h-screen flex-col"
        style="background-color: #1e2d55"
    >
        <!-- Background decoration -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                class="absolute -top-40 -right-40 h-150 w-150 rounded-full opacity-10"
                style="
                    background: radial-gradient(
                        circle,
                        #f2ae35,
                        transparent 70%
                    );
                "
            />
            <div
                class="absolute -bottom-32 -left-32 h-100 w-100 rounded-full opacity-[0.07]"
                style="
                    background: radial-gradient(
                        circle,
                        #f2ae35,
                        transparent 70%
                    );
                "
            />
        </div>

        <!-- Header -->
        <header
            class="relative z-10 flex items-center border-b px-10 py-6"
            style="border-color: rgba(242, 174, 53, 0.15)"
        >
            <Link :href="home()" class="flex items-center gap-4">
                <img
                    src="/IFO_Gimmick_SUPERIEUR.png"
                    alt="IFOSUP"
                    class="h-10 w-auto object-contain"
                />
                <span
                    class="text-base font-black tracking-[0.2em] uppercase"
                    style="color: #f2ae35"
                    >IFOSUP Display</span
                >
            </Link>
        </header>

        <!-- Login card -->
        <main
            class="relative z-10 flex flex-1 items-center justify-center px-6 py-16"
        >
            <div class="w-full max-w-md">
                <!-- Card -->
                <div
                    class="rounded-2xl border p-10 shadow-2xl"
                    style="
                        background-color: rgba(255, 255, 255, 0.04);
                        border-color: rgba(242, 174, 53, 0.2);
                    "
                >
                    <!-- Title -->
                    <div class="mb-8 flex flex-col gap-1">
                        <h1
                            class="text-2xl font-black tracking-wide text-white uppercase"
                        >
                            Connexion
                        </h1>
                        <p
                            class="text-sm"
                            style="color: rgba(255, 255, 255, 0.5)"
                        >
                            Accédez à la plateforme de gestion d'affichage.
                        </p>
                    </div>

                    <!-- Status message -->
                    <div
                        v-if="status"
                        class="mb-6 rounded-lg px-4 py-3 text-sm font-medium text-green-300"
                        style="
                            background-color: rgba(74, 222, 128, 0.1);
                            border: 1px solid rgba(74, 222, 128, 0.3);
                        "
                    >
                        {{ status }}
                    </div>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="flex flex-col gap-5"
                    >
                        <!-- Email -->
                        <div class="flex flex-col gap-2">
                            <Label
                                for="email"
                                class="text-xs font-bold tracking-widest uppercase"
                                style="color: rgba(255, 255, 255, 0.6)"
                            >
                                Adresse email
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                :tabindex="1"
                                autocomplete="email"
                                placeholder="exemple@ifosup.be"
                                class="border text-white placeholder:opacity-30 focus-visible:border-[#f2ae35] focus-visible:ring-0"
                                style="
                                    background-color: rgba(255, 255, 255, 0.06);
                                    border-color: rgba(242, 174, 53, 0.25);
                                    color: white;
                                "
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <!-- Password -->
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <Label
                                    for="password"
                                    class="text-xs font-bold tracking-widest uppercase"
                                    style="color: rgba(255, 255, 255, 0.6)"
                                >
                                    Mot de passe
                                </Label>
                            </div>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                :tabindex="2"
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="border text-white placeholder:opacity-30 focus-visible:border-[#f2ae35] focus-visible:ring-0"
                                style="
                                    background-color: rgba(255, 255, 255, 0.06);
                                    border-color: rgba(242, 174, 53, 0.25);
                                    color: white;
                                "
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <!-- Remember me -->
                        <Label
                            for="remember"
                            class="flex cursor-pointer items-center gap-3"
                        >
                            <Checkbox
                                id="remember"
                                name="remember"
                                :tabindex="3"
                            />
                            <span
                                class="text-sm"
                                style="color: rgba(255, 255, 255, 0.5)"
                                >Se souvenir de moi</span
                            >
                        </Label>

                        <!-- Submit -->
                        <button
                            type="submit"
                            :tabindex="4"
                            :disabled="processing"
                            class="mt-2 flex w-full items-center justify-center gap-3 rounded-lg py-3 text-sm font-black tracking-widest uppercase transition-all duration-200 hover:brightness-110 disabled:opacity-60"
                            style="background-color: #f2ae35; color: #1e2d55"
                            data-test="login-button"
                        >
                            <Spinner v-if="processing" />
                            <span>{{
                                processing ? 'Connexion…' : 'Se connecter'
                            }}</span>
                            <svg
                                v-if="!processing"
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"
                                />
                            </svg>
                        </button>
                    </Form>
                </div>
            </div>
        </main>
    </div>
</template>
