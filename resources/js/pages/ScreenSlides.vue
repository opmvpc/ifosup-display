<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock,
    Image as ImageIcon,
    Lock,
    Play,
    Plus,
    Save,
    Settings2,
    Trash2,
    Video,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type SlideType = 'welcome' | 'schedule' | 'image' | 'video';

type ScreenSlideItem = {
    id: number;
    key: string;
    type: SlideType;
    position: number;
    is_locked: boolean;
    motd: string | null;
    duration: number | null;
    image_url: string | null;
    video_url: string | null;
};

const props = defineProps<{
    slides: ScreenSlideItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Slides ecran',
        href: '/screen/slides',
    },
];

const slides = ref<ScreenSlideItem[]>([...props.slides]);

watch(
    () => props.slides,
    (next) => {
        slides.value = [...next];
    },
);

const isSubmitting = ref(false);
const formError = ref<string | null>(null);
const videoDurations = ref<Record<number, number>>({});

const isTypeDialogOpen = ref(false);
const isModalOpen = ref(false);
const modalMode = ref<'create' | 'edit'>('edit');
const modalSlideType = ref<SlideType>('schedule');
const editingSlideId = ref<number | null>(null);

const formMotd = ref('');
const formDuration = ref<number>(5000);
const formImageFile = ref<File | null>(null);
const formVideoFile = ref<File | null>(null);

const modalTitle = computed(() => {
    if (modalMode.value === 'create') {
        if (modalSlideType.value === 'schedule')
            return 'Ajouter un slide planning';
        if (modalSlideType.value === 'image') return 'Ajouter un slide image';
        if (modalSlideType.value === 'video') return 'Ajouter un slide video';
        return 'Ajouter un slide';
    }

    if (modalSlideType.value === 'welcome')
        return 'Personnaliser le slide bienvenue';
    if (modalSlideType.value === 'schedule')
        return 'Personnaliser le slide planning';
    if (modalSlideType.value === 'image') return 'Personnaliser le slide image';
    if (modalSlideType.value === 'video') return 'Personnaliser le slide video';
    return 'Personnaliser le slide';
});

const modalDescription = computed(() => {
    if (modalSlideType.value === 'welcome') {
        return "Le slide bienvenue est toujours le premier et est obligatoire. Il permet d'afficher un mot du jour.";
    }

    if (modalSlideType.value === 'schedule') {
        return "Le slide planning se génère automatiquement sur base du planning du jour, et de l'heure qu'il est.\n Il n'est pas possible de personnaliser sa mise en page ou sa durée qui s'adapteront tout seul au contenu.";
    }

    if (modalSlideType.value === 'image') {
        return 'Selectionnez une image et une duree d affichage.';
    }

    return 'Selectionnez une video.';
});

const readCsrfToken = () => {
    const fromMeta = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');

    if (fromMeta) {
        return fromMeta;
    }

    const xsrfCookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    if (!xsrfCookie) {
        return '';
    }

    return decodeURIComponent(xsrfCookie.split('=').slice(1).join('='));
};

const extractErrorMessage = (payload: unknown, fallback: string): string => {
    if (payload && typeof payload === 'object') {
        const body = payload as {
            errors?: Record<string, string[] | string>;
            error?: string;
            message?: string;
        };

        if (body.errors && typeof body.errors === 'object') {
            const messages = Object.values(body.errors).flat();
            if (messages.length > 0) return messages.join(' ');
        }

        if (typeof body.error === 'string') return body.error;
        if (typeof body.message === 'string') return body.message;
    }

    return fallback;
};

const parseResponseError = async (
    response: Response,
    fallbackMessage: string,
) => {
    try {
        const payload = await response.json();
        return extractErrorMessage(payload, fallbackMessage);
    } catch {
        return fallbackMessage;
    }
};

const requestJson = async <T,>(
    url: string,
    method: 'PATCH' | 'DELETE',
    payload?: object,
): Promise<T | null> => {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': readCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: payload ? JSON.stringify(payload) : undefined,
    });

    if (!response.ok) {
        throw new Error(
            await parseResponseError(response, 'Une erreur est survenue.'),
        );
    }

    if (response.status === 204) {
        return null;
    }

    return (await response.json()) as T;
};

const requestForm = async <T,>(
    url: string,
    method: 'POST',
    formData: FormData,
): Promise<T> => {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': readCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
    });

    if (!response.ok) {
        throw new Error(
            await parseResponseError(response, 'Une erreur est survenue.'),
        );
    }

    return (await response.json()) as T;
};

const sortedSlides = computed(() => {
    return [...slides.value].sort((a, b) => a.position - b.position);
});

const resetModalForm = () => {
    formMotd.value = '';
    formDuration.value = 5000;
    formImageFile.value = null;
    formVideoFile.value = null;
};

const createScheduleSlideDirectly = async () => {
    isTypeDialogOpen.value = false;
    try {
        const formData = new FormData();
        formData.append('type', 'schedule');
        const payload = await requestForm<{ slide: ScreenSlideItem }>(
            '/screen/slides',
            'POST',
            formData,
        );
        slides.value = [...slides.value, payload.slide].sort(
            (a, b) => a.position - b.position,
        );
    } catch (error) {
        const message =
            error instanceof Error
                ? error.message
                : 'Impossible de créer le slide planning.';
        window.alert(message);
    }
};

const openCreateModal = (type: Exclude<SlideType, 'welcome' | 'schedule'>) => {
    modalMode.value = 'create';
    modalSlideType.value = type;
    editingSlideId.value = null;
    formError.value = null;
    resetModalForm();
    isModalOpen.value = true;
};

const openEditModal = (slide: ScreenSlideItem) => {
    modalMode.value = 'edit';
    modalSlideType.value = slide.type;
    editingSlideId.value = slide.id;
    formError.value = null;
    resetModalForm();

    formMotd.value = slide.motd ?? '';
    formDuration.value = slide.duration ?? 5000;

    isModalOpen.value = true;
};

const onImageFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    formImageFile.value = input.files?.[0] ?? null;
};

const onVideoFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    formVideoFile.value = input.files?.[0] ?? null;
};

const replaceSlide = (nextSlide: ScreenSlideItem) => {
    const index = slides.value.findIndex((slide) => slide.id === nextSlide.id);

    if (index === -1) {
        return;
    }

    slides.value[index] = nextSlide;
    slides.value = [...slides.value];
};

const createSlide = async () => {
    const formData = new FormData();
    formData.append('type', modalSlideType.value);

    if (modalSlideType.value === 'image') {
        formData.append('duration', String(formDuration.value));

        if (!formImageFile.value) {
            throw new Error('Selectionnez une image.');
        }

        formData.append('image', formImageFile.value);
    }

    if (modalSlideType.value === 'video') {
        if (!formVideoFile.value) {
            throw new Error('Veuillez sélectionner un fichier vidéo.');
        }

        formData.append('video', formVideoFile.value);
    }

    const payload = await requestForm<{ slide: ScreenSlideItem }>(
        '/screen/slides',
        'POST',
        formData,
    );

    slides.value = [...slides.value, payload.slide].sort(
        (a, b) => a.position - b.position,
    );
};

const updateSlide = async () => {
    if (!editingSlideId.value) {
        return;
    }

    const formData = new FormData();
    formData.append('_method', 'PATCH');

    if (modalSlideType.value === 'welcome') {
        formData.append('motd', formMotd.value);
    }

    if (modalSlideType.value === 'image') {
        formData.append('duration', String(formDuration.value));

        if (formImageFile.value) {
            formData.append('image', formImageFile.value);
        }
    }

    if (modalSlideType.value === 'video') {
        if (formVideoFile.value) {
            formData.append('video', formVideoFile.value);
        }
    }

    const payload = await requestForm<{ slide: ScreenSlideItem }>(
        `/screen/slides/${editingSlideId.value}`,
        'POST',
        formData,
    );

    replaceSlide(payload.slide);
};

const saveModal = async () => {
    if (isSubmitting.value) {
        return;
    }

    formError.value = null;
    isSubmitting.value = true;

    try {
        if (modalMode.value === 'create') {
            await createSlide();
        } else {
            await updateSlide();
        }

        isModalOpen.value = false;
    } catch (error) {
        formError.value =
            error instanceof Error
                ? error.message
                : 'Impossible de sauvegarder le slide.';
    } finally {
        isSubmitting.value = false;
    }
};

const deleteSlide = async (slide: ScreenSlideItem) => {
    if (slide.is_locked) {
        return;
    }

    if (!window.confirm('Supprimer ce slide ?')) {
        return;
    }

    try {
        await requestJson(`/screen/slides/${slide.id}`, 'DELETE');
        slides.value = slides.value
            .filter((item) => item.id !== slide.id)
            .map((item, index) => ({
                ...item,
                position: index,
            }));
    } catch (error) {
        const message =
            error instanceof Error
                ? error.message
                : 'Impossible de supprimer ce slide.';
        window.alert(message);
    }
};

const reorderSlides = async (nextSlides: ScreenSlideItem[]) => {
    const previousSlides = [...slides.value];

    slides.value = nextSlides.map((slide, index) => ({
        ...slide,
        position: index,
    }));

    try {
        await requestJson<{ slides: ScreenSlideItem[] }>(
            '/screen/slides/order',
            'PATCH',
            {
                slide_ids: slides.value.map((slide) => slide.id),
            },
        );
    } catch (error) {
        slides.value = previousSlides;
        const message =
            error instanceof Error
                ? error.message
                : 'Impossible de reordonner les slides.';
        window.alert(message);
    }
};

const moveSlide = (slide: ScreenSlideItem, direction: -1 | 1) => {
    const working = [...sortedSlides.value];
    const index = working.findIndex((s) => s.id === slide.id);
    if (index === -1) return;
    const targetIndex = index + direction;
    if (targetIndex < 0 || targetIndex >= working.length) return;
    [working[index], working[targetIndex]] = [
        working[targetIndex],
        working[index],
    ];
    const locked = working.find((s) => s.is_locked);
    if (locked && working[0].id !== locked.id) {
        working.splice(
            working.findIndex((s) => s.id === locked.id),
            1,
        );
        working.unshift(locked);
    }
    void reorderSlides(working);
};

const slideTypeLabel = (type: SlideType) => {
    if (type === 'welcome') return 'Bienvenue';
    if (type === 'schedule') return 'Planning';
    if (type === 'image') return 'Image';
    return 'Video';
};

const onVideoLoadedMetadata = (event: Event, slideId: number) => {
    const video = event.target as HTMLVideoElement;
    video.currentTime = 1;
    if (video.duration && isFinite(video.duration)) {
        videoDurations.value = {
            ...videoDurations.value,
            [slideId]: video.duration,
        };
    }
};

const durationLabel = (slide: ScreenSlideItem) => {
    if (slide.type === 'image') {
        return `${Math.round((slide.duration ?? 5000) / 1000)} secondes`;
    }

    if (slide.type === 'video') {
        const real = videoDurations.value[slide.id];
        if (real !== undefined) {
            const mins = Math.floor(real / 60);
            const secs = Math.floor(real % 60);
            return mins > 0
                ? `${mins} minutes ${secs.toString().padStart(2, '0')} secondes`
                : `${secs} secondes`;
        }
        return null;
    }

    return null;
};
</script>

<template>
    <Head title="Slides écran" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full px-4 py-6">
            <div class="mx-auto flex w-full max-w-350 flex-col gap-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1
                            class="text-xl font-bold text-zinc-900 dark:text-zinc-100"
                        >
                            Slides écran
                        </h1>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Ajoutez, modifiez ou supprimez des slides d'images,
                            vidéos ou planning sur l'écran de la télé et
                            configurez leurs paramètres.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2"></div>
                </div>

                <div class="pb-4">
                    <div
                        class="grid grid-cols-1 items-start gap-4 md:grid-cols-2 2xl:grid-cols-3"
                    >
                        <div
                            v-for="(slide, index) in sortedSlides"
                            :key="slide.id"
                            class="w-full"
                        >
                            <div
                                class="mb-2 flex items-center justify-between gap-2"
                            >
                                <div
                                    class="inline-flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400"
                                >
                                    <Lock
                                        v-if="slide.is_locked"
                                        class="h-3.5 w-3.5"
                                    />
                                    {{ slide.position + 1 }} ·
                                    {{ slideTypeLabel(slide.type) }}
                                </div>

                                <div class="flex items-center gap-1">
                                    <template v-if="!slide.is_locked">
                                        <button
                                            type="button"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-800 disabled:opacity-30 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                            :disabled="index === 1"
                                            title="Déplacer à gauche"
                                            @click="moveSlide(slide, -1)"
                                        >
                                            <ChevronLeft class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-800 disabled:opacity-30 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                            :disabled="
                                                index ===
                                                sortedSlides.length - 1
                                            "
                                            title="Déplacer à droite"
                                            @click="moveSlide(slide, 1)"
                                        >
                                            <ChevronRight class="h-3.5 w-3.5" />
                                        </button>
                                    </template>
                                    <button
                                        type="button"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                        title="Customiser"
                                        @click="openEditModal(slide)"
                                    >
                                        <Settings2 class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        v-if="slide.type !== 'welcome'"
                                        type="button"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded text-zinc-500 transition-colors hover:bg-red-50 hover:text-red-700 disabled:opacity-35 dark:text-zinc-300 dark:hover:bg-red-950/40 dark:hover:text-red-300"
                                        title="Supprimer"
                                        @click="deleteSlide(slide)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <div
                                class="aspect-video overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800"
                            >
                                <div
                                    v-if="slide.type === 'welcome'"
                                    class="relative flex h-full w-full items-center justify-center bg-[#1e2d55]"
                                >
                                    <span
                                        class="text-sm font-black tracking-[0.25em] text-white uppercase"
                                        >Welcome</span
                                    >
                                    <p
                                        v-if="slide.motd"
                                        class="absolute right-0 bottom-4 left-0 line-clamp-2 px-3 text-center text-xs leading-tight text-white/60"
                                    >
                                        {{ slide.motd }}
                                    </p>
                                </div>

                                <div
                                    v-else-if="slide.type === 'schedule'"
                                    class="flex h-full w-full items-center justify-center bg-[#f2ae35]"
                                >
                                    <span
                                        class="text-sm font-black tracking-[0.25em] text-white uppercase"
                                        >Planning</span
                                    >
                                </div>

                                <img
                                    v-else-if="
                                        slide.type === 'image' &&
                                        slide.image_url
                                    "
                                    :src="slide.image_url"
                                    alt="Apercu image"
                                    class="h-full w-full bg-[#1e2d55] object-contain"
                                />

                                <div
                                    v-else-if="slide.type === 'video'"
                                    class="relative h-full w-full bg-[#1e2d55]"
                                >
                                    <video
                                        v-if="slide.video_url"
                                        :src="slide.video_url"
                                        class="h-full w-full object-contain"
                                        muted
                                        preload="metadata"
                                        @loadedmetadata="
                                            onVideoLoadedMetadata(
                                                $event,
                                                slide.id,
                                            )
                                        "
                                    />
                                    <div
                                        class="absolute inset-0 flex items-center justify-center bg-black/20"
                                    >
                                        <Play
                                            class="h-10 w-10 text-white drop-shadow-md"
                                        />
                                    </div>
                                </div>
                            </div>

                            <p
                                v-if="durationLabel(slide)"
                                class="mt-2 flex items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400"
                            >
                                <Clock class="h-3 w-3" />
                                <span>{{ durationLabel(slide) }}</span>
                            </p>
                            <p
                                v-else
                                class="mt-2 flex items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400"
                            >
                                <Clock class="h-3 w-3" />
                                <span>Auto</span>
                            </p>
                        </div>

                        <div class="w-full">
                            <div class="mb-2 h-6"></div>
                            <button
                                type="button"
                                class="aspect-video w-full cursor-pointer rounded-md border-2 border-dashed border-zinc-300 bg-zinc-50 transition-colors hover:border-zinc-400 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/50 dark:hover:border-zinc-600 dark:hover:bg-zinc-900"
                                @click="isTypeDialogOpen = true"
                            >
                                <div
                                    class="flex h-full w-full flex-col items-center justify-center gap-2"
                                >
                                    <Plus class="h-5 w-5 text-zinc-400" />
                                    <span
                                        class="text-xs font-medium text-zinc-500 dark:text-zinc-400"
                                        >Ajouter</span
                                    >
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog :open="isModalOpen" @update:open="isModalOpen = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                    <DialogDescription>{{
                        modalDescription
                    }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div v-if="modalSlideType === 'welcome'" class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="motd"
                                >Message du jour (optionnel)</Label
                            >
                            <span
                                class="text-xs"
                                :class="
                                    formMotd.length > 280
                                        ? 'text-red-500'
                                        : 'text-zinc-400'
                                "
                                >{{ formMotd.length }}&nbsp;/&nbsp;280</span
                            >
                        </div>
                        <textarea
                            id="motd"
                            v-model="formMotd"
                            rows="4"
                            maxlength="280"
                            class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm transition-colors outline-none focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                            placeholder="Ajoutez un message qui apparaîtra sur le slide de bienvenue"
                        />
                    </div>

                    <div v-if="modalSlideType === 'image'" class="space-y-3">
                        <div class="space-y-2">
                            <Label for="image-duration"
                                >Durée en millisecondes</Label
                            >
                            <Input
                                id="image-duration"
                                v-model.number="formDuration"
                                type="number"
                                min="1000"
                                step="500"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="image-file">Image</Label>
                            <Input
                                id="image-file"
                                type="file"
                                accept="image/*"
                                @change="onImageFileChange"
                            />
                        </div>
                    </div>

                    <div v-if="modalSlideType === 'video'" class="space-y-3">
                        <div class="space-y-2">
                            <Label for="video-file">Fichier vidéo</Label>
                            <Input
                                id="video-file"
                                type="file"
                                accept="video/*"
                                @change="onVideoFileChange"
                            />
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <p
                        v-if="formError"
                        class="mr-auto text-sm text-red-600 dark:text-red-400"
                    >
                        {{ formError }}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        @click="isModalOpen = false"
                        >{{
                            modalSlideType === 'schedule' ? 'Ok' : 'Annuler'
                        }}</Button
                    >
                    <Button
                        v-if="modalSlideType !== 'schedule'"
                        type="button"
                        :disabled="isSubmitting"
                        @click="saveModal"
                    >
                        <Save class="h-4 w-4" />
                        Enregistrer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="isTypeDialogOpen"
            @update:open="isTypeDialogOpen = $event"
        >
            <DialogContent class="overflow-hidden p-0 sm:max-w-sm">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <DialogTitle class="text-base"
                        >Ajouter un slide</DialogTitle
                    >
                </DialogHeader>

                <div class="flex flex-col">
                    <button
                        type="button"
                        class="group flex cursor-pointer items-center gap-5 px-6 py-5 text-left transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                        @click="createScheduleSlideDirectly"
                    >
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#f2ae35]/15"
                        >
                            <CalendarDays class="h-6 w-6 text-[#f2ae35]" />
                        </div>
                        <div>
                            <div
                                class="font-semibold text-zinc-900 dark:text-zinc-100"
                            >
                                Planning
                            </div>
                            <div
                                class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400"
                            >
                                Affiche le planning du jour automatiquement
                            </div>
                        </div>
                    </button>

                    <div
                        class="mx-6 border-t border-zinc-100 dark:border-zinc-800"
                    ></div>

                    <button
                        type="button"
                        class="group flex cursor-pointer items-center gap-5 px-6 py-5 text-left transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                        @click="
                            openCreateModal('image');
                            isTypeDialogOpen = false;
                        "
                    >
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-500/10"
                        >
                            <ImageIcon class="h-6 w-6 text-blue-500" />
                        </div>
                        <div>
                            <div
                                class="font-semibold text-zinc-900 dark:text-zinc-100"
                            >
                                Image
                            </div>
                            <div
                                class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400"
                            >
                                Affiche une image avec une durée définie
                            </div>
                        </div>
                    </button>

                    <div
                        class="mx-6 border-t border-zinc-100 dark:border-zinc-800"
                    ></div>

                    <button
                        type="button"
                        class="group flex cursor-pointer items-center gap-5 px-6 py-5 text-left transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                        @click="
                            openCreateModal('video');
                            isTypeDialogOpen = false;
                        "
                    >
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-500/10"
                        >
                            <Video class="h-6 w-6 text-violet-500" />
                        </div>
                        <div>
                            <div
                                class="font-semibold text-zinc-900 dark:text-zinc-100"
                            >
                                Vidéo
                            </div>
                            <div
                                class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400"
                            >
                                Affiche une vidéo jusqu'à la fin
                            </div>
                        </div>
                    </button>
                </div>

                <div class="h-3"></div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
