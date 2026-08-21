<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { login, schedule } from '@/routes';

const page = usePage();
const isLoggedIn = computed(() => !!(page.props.auth as any)?.user);
const destination = computed(() =>
    isLoggedIn.value ? schedule.url() : login().url,
);
</script>

<template>
    <Head title="Bienvenue" />
    <div
        class="relative flex min-h-screen flex-col overflow-hidden"
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
            class="relative z-10 flex items-center justify-between border-b px-10 py-6"
            style="border-color: rgba(242, 174, 53, 0.15)"
        >
            <div class="flex items-center gap-4">
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
            </div>
            <div class="flex items-center gap-6">
                <Link
                    href="/screen"
                    class="text-sm font-bold tracking-wider uppercase transition-all duration-200 hover:opacity-80"
                    style="color: rgba(242, 174, 53, 0.6)"
                >
                    Écran TV
                </Link>
                <Link
                    :href="destination"
                    class="inline-flex items-center gap-2 rounded border-2 px-5 py-2 text-sm font-bold tracking-wider uppercase transition-all duration-200 hover:opacity-80"
                    style="border-color: #f2ae35; color: #f2ae35"
                >
                    {{ isLoggedIn ? 'Ouvrir' : 'Se connecter' }}
                </Link>
            </div>
        </header>

        <!-- Hero -->
        <main
            class="relative z-10 flex flex-1 flex-col items-center justify-center px-6 py-24 text-center"
        >
            <div class="flex w-full max-w-2xl flex-col items-center gap-10">
                <!-- Badge -->
                <span
                    class="inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-bold tracking-widest uppercase"
                    style="
                        border-color: rgba(242, 174, 53, 0.4);
                        color: #f2ae35;
                        background-color: rgba(242, 174, 53, 0.08);
                    "
                >
                    <span
                        class="inline-block h-1.5 w-1.5 animate-pulse rounded-full"
                        style="background-color: #f2ae35"
                    />
                    Outil interne IFOSUP
                </span>

                <!-- Headline -->
                <div class="flex flex-col gap-5">
                    <h1
                        class="text-6xl leading-none font-black tracking-tight text-white uppercase"
                    >
                        Gestion de<br />
                        <span style="color: #f2ae35">l'affichage</span>
                    </h1>
                    <p
                        class="mx-auto max-w-lg text-lg leading-relaxed"
                        style="color: rgba(255, 255, 255, 0.6)"
                    >
                        Pilotez en temps réel le contenu diffusé sur les écrans
                        de l'établissement &mdash; plannings, slides, annonces.
                    </p>
                </div>

                <!-- CTA -->
                <Link
                    :href="destination"
                    class="inline-flex items-center gap-3 rounded-lg px-8 py-4 text-base font-black tracking-widest uppercase shadow-lg transition-all duration-200 hover:brightness-110"
                    style="background-color: #f2ae35; color: #1e2d55"
                >
                    Accéder à la plateforme
                    <svg
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
                </Link>
            </div>
        </main>
    </div>
</template>
