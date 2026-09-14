<template>
    <div class="relative h-screen w-screen overflow-hidden bg-black">
        <transition
            name="slide"
            @before-enter="debugLog('transition: before-enter')"
            @after-enter="debugLog('transition: after-enter')"
            @after-leave="debugLog('transition: after-leave')"
        >
            <component
                v-if="currentSlide"
                :is="components[currentSlide.type]"
                :key="currentSlideKey"
                :data="currentSlide.data"
                @next="goToNextSlide"
            />
        </transition>

        <button
            v-if="!isFullscreen"
            @click="enterFullscreen"
            class="absolute top-4 right-4 z-50 cursor-pointer rounded-xl bg-white/20 p-3 text-white transition-colors hover:bg-white/40"
            title="Plein écran"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="size-6"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5-5-5m5 5v-4m0 4h-4"
                />
            </svg>
        </button>
    </div>
</template>

<script setup lang="ts">
import {
    computed,
    defineAsyncComponent,
    onMounted,
    onUnmounted,
    ref,
    watch,
} from 'vue';
import type { KioskLogger } from '@/lib/kioskDebug';
import { isKioskDebugEnabled, setupKioskDebug } from '@/lib/kioskDebug';

type SlideType = 'welcome' | 'schedule' | 'image' | 'video';

interface AssignmentGroup {
    id?: number;
    name: string;
}

interface AssignmentTeacher {
    name: string;
}

interface AssignmentCourse {
    code?: string | null;
    name: string;
    teacher?: AssignmentTeacher | null;
    groups?: AssignmentGroup[];
}

interface AssignmentRoom {
    name: string;
}

interface AssignmentRow {
    id?: number;
    course?: AssignmentCourse | null;
    room?: AssignmentRoom | null;
}

interface WelcomeSlideData {
    minimumDuration?: number;
    isReady?: boolean;
    motd?: string | null;
}

interface ScheduleSlideData {
    title: string;
    rows: AssignmentRow[];
}

interface MediaSlideData {
    src: string;
    duration?: number;
}

type SlideData = WelcomeSlideData | ScheduleSlideData | MediaSlideData;

interface KioskSlide {
    key: string;
    type: SlideType;
    data: SlideData;
}

interface ScreenPayload {
    now?: string;
    timezone?: string;
    slides: KioskSlide[];
}

const FALLBACK_WELCOME_SLIDE: KioskSlide = {
    key: 'welcome',
    type: 'welcome',
    data: {
        minimumDuration: 5000,
        isReady: true,
    },
};

const SCREEN_CACHE_KEY = 'screen:kiosk-payload:v1';

// Pas de cache média (API Cache) : sur le navigateur des TV Samsung, ses
// promesses ne se résolvent jamais et figeaient le kiosque (IFO-023). Les
// slides utilisent leurs URL directes ; le navigateur gère le cache HTTP.

const components = {
    welcome: defineAsyncComponent(() => import('./slides/Welcome.vue')),
    image: defineAsyncComponent(() => import('./slides/ImageSlide.vue')),
    video: defineAsyncComponent(() => import('./slides/VideoSlide.vue')),
    schedule: defineAsyncComponent(() => import('./slides/ScheduleSlide.vue')),
};

const isFullscreen = ref(false);

// Journal à l'écran en mode debug (`?debug=1`), muet sinon.
let debugLog: KioskLogger = () => {};

function enterFullscreen() {
    document.documentElement.requestFullscreen().catch(console.error);
}

function onFullscreenChange() {
    isFullscreen.value = !!document.fullscreenElement;
}

const slides = ref<KioskSlide[]>([FALLBACK_WELCOME_SLIDE]);
const isRefreshing = ref(false);
let pendingRefresh: Promise<void> | null = null;

const currentIndex = ref(0);
const currentSlide = computed<KioskSlide | undefined>(
    () => slides.value[currentIndex.value],
);
const currentSlideKey = computed(
    () => currentSlide.value?.key ?? `slide-${currentIndex.value}`,
);

function normalizePayload(payload: unknown): ScreenPayload {
    const candidate = payload as Partial<ScreenPayload> | null;

    return {
        now: typeof candidate?.now === 'string' ? candidate.now : undefined,
        timezone:
            typeof candidate?.timezone === 'string'
                ? candidate.timezone
                : undefined,
        slides: Array.isArray(candidate?.slides)
            ? (candidate.slides as KioskSlide[])
            : [],
    };
}

function withWelcomeState(
    baseSlides: KioskSlide[],
    isReady: boolean,
): KioskSlide[] {
    return baseSlides.map((slide, index) => {
        if (index !== 0 || slide.type !== 'welcome') {
            return slide;
        }

        return {
            ...slide,
            data: {
                ...(slide.data as WelcomeSlideData),
                isReady,
            },
        };
    });
}

function readCachedPayload(): ScreenPayload | null {
    try {
        const raw = window.localStorage.getItem(SCREEN_CACHE_KEY);

        if (!raw) {
            return null;
        }

        return normalizePayload(JSON.parse(raw));
    } catch (error) {
        console.error('Unable to read cached screen data.', error);
        return null;
    }
}

function writeCachedPayload(payload: ScreenPayload): void {
    try {
        window.localStorage.setItem(SCREEN_CACHE_KEY, JSON.stringify(payload));
    } catch (error) {
        console.error('Unable to write cached screen data.', error);
    }
}

async function applyPayload(
    payload: ScreenPayload,
    isReady = true,
): Promise<void> {
    const baseSlides =
        payload.slides.length > 0 ? payload.slides : [FALLBACK_WELCOME_SLIDE];

    slides.value = withWelcomeState(baseSlides, isReady);

    if (currentIndex.value >= slides.value.length) {
        currentIndex.value = 0;
    }
}

async function refreshAssignments(): Promise<void> {
    if (pendingRefresh) {
        return pendingRefresh;
    }

    isRefreshing.value = true;
    slides.value = withWelcomeState(slides.value, false);

    pendingRefresh = (async () => {
        try {
            debugLog('refresh: fetch /screen/data');
            const response = await fetch('/screen/data');

            if (!response.ok) {
                throw new Error(
                    `Request failed with status ${response.status}`,
                );
            }

            const payload = normalizePayload(await response.json());

            writeCachedPayload(payload);
            await applyPayload(payload, true);
            debugLog(
                'refresh: ok',
                slides.value.map((slide) => slide.type).join(','),
            );
        } catch (error) {
            console.error('Unable to refresh screen data.', error);
            slides.value = withWelcomeState(slides.value, true);
        } finally {
            isRefreshing.value = false;
            pendingRefresh = null;
        }
    })();

    return pendingRefresh;
}

onMounted(async () => {
    if (isKioskDebugEnabled()) {
        debugLog = setupKioskDebug();
    }

    document.addEventListener('fullscreenchange', onFullscreenChange);

    const cachedPayload = readCachedPayload();

    if (cachedPayload) {
        debugLog('payload en localStorage appliqué');
        await applyPayload(cachedPayload, true);
    }

    await refreshAssignments();
});

onUnmounted(() => {
    document.removeEventListener('fullscreenchange', onFullscreenChange);
});

watch(currentIndex, async (newIndex, previousIndex) => {
    debugLog(
        `slide ${newIndex + 1}/${slides.value.length}`,
        currentSlide.value?.type ?? '?',
        currentSlide.value?.key ?? '?',
    );

    if (newIndex !== 0 || previousIndex === undefined || previousIndex === 0) {
        return;
    }

    await refreshAssignments();
});

async function goToNextSlide() {
    debugLog('next demandé');

    if (currentIndex.value === 0 && slides.value.length <= 1) {
        await refreshAssignments();

        if (slides.value.length <= 1) {
            return;
        }
    }

    currentIndex.value = (currentIndex.value + 1) % slides.value.length;
}
</script>

<style scoped>
.slide-enter-active {
    transition: transform 700ms cubic-bezier(0.76, 0, 0.24, 1);
}

.slide-leave-active {
    transition: transform 700ms cubic-bezier(0.76, 0, 0.24, 1);
    position: absolute;
    inset: 0;
}

.slide-enter-from {
    transform: translateX(100%);
}

.slide-leave-to {
    transform: translateX(-100%);
}
</style>
