<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { docsChapters } from '@/lib/docs';
import type { BreadcrumbItem } from '@/types';

interface DocsLayoutProps {
    slug: string;
    title: string;
    description: string;
}

const props = defineProps<DocsLayoutProps>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Documentation', href: '/docs' },
    {
        title: props.title,
        href:
            docsChapters.find((chapter) => chapter.slug === props.slug)?.href ??
            '/docs',
    },
];

const chapterIndex = computed(() =>
    docsChapters.findIndex((chapter) => chapter.slug === props.slug),
);
const previousChapter = computed(
    () => docsChapters[chapterIndex.value - 1] ?? null,
);
const nextChapter = computed(
    () => docsChapters[chapterIndex.value + 1] ?? null,
);

// « Sur cette page » : construit depuis les <h2 id> de l'article.
interface TocEntry {
    id: string;
    label: string;
}

const article = ref<HTMLElement | null>(null);
const tocEntries = ref<TocEntry[]>([]);
const activeId = ref('');
let observer: IntersectionObserver | null = null;

onMounted(() => {
    const headings = Array.from(
        article.value?.querySelectorAll<HTMLHeadingElement>('h2[id]') ?? [],
    );

    tocEntries.value = headings.map((heading) => ({
        id: heading.id,
        label: heading.textContent?.trim() ?? '',
    }));
    activeId.value = headings[0]?.id ?? '';

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    activeId.value = entry.target.id;
                }
            }
        },
        { rootMargin: '0px 0px -70% 0px' },
    );
    headings.forEach((heading) => observer?.observe(heading));
});

onUnmounted(() => {
    observer?.disconnect();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="mx-auto w-full max-w-7xl px-4 py-6">
            <div
                class="lg:grid lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-10 xl:grid-cols-[13rem_minmax(0,1fr)_13rem]"
            >
                <!-- Chapitres (gauche) -->
                <nav aria-label="Chapitres de la documentation">
                    <p
                        class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Documentation
                    </p>
                    <ul
                        class="mb-6 flex gap-1 overflow-x-auto pb-2 lg:sticky lg:top-6 lg:mb-0 lg:flex-col lg:overflow-visible lg:pb-0"
                    >
                        <li v-for="chapter in docsChapters" :key="chapter.slug">
                            <Link
                                :href="chapter.href"
                                class="block rounded-md px-3 py-1.5 text-sm whitespace-nowrap transition-colors"
                                :class="
                                    chapter.slug === props.slug
                                        ? 'bg-accent font-medium text-accent-foreground'
                                        : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground'
                                "
                            >
                                {{ chapter.title }}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <!-- Contenu -->
                <div class="min-w-0">
                    <Heading :title="title" :description="description" />
                    <article ref="article" class="docs-article mt-6">
                        <slot />
                    </article>

                    <div
                        class="mt-10 flex items-stretch justify-between gap-4 border-t border-border pt-6"
                    >
                        <Link
                            v-if="previousChapter"
                            :href="previousChapter.href"
                            class="flex items-center gap-2 rounded-lg border border-border px-4 py-3 text-sm transition-colors hover:bg-accent"
                        >
                            <ChevronLeft class="size-4 text-muted-foreground" />
                            <span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >Précédent</span
                                >
                                {{ previousChapter.title }}
                            </span>
                        </Link>
                        <span v-else></span>
                        <Link
                            v-if="nextChapter"
                            :href="nextChapter.href"
                            class="flex items-center gap-2 rounded-lg border border-border px-4 py-3 text-right text-sm transition-colors hover:bg-accent"
                        >
                            <span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >Suivant</span
                                >
                                {{ nextChapter.title }}
                            </span>
                            <ChevronRight
                                class="size-4 text-muted-foreground"
                            />
                        </Link>
                    </div>
                </div>

                <!-- Sur cette page (droite) -->
                <nav
                    v-if="tocEntries.length"
                    aria-label="Sur cette page"
                    class="hidden xl:block"
                >
                    <div class="sticky top-6">
                        <p
                            class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Sur cette page
                        </p>
                        <ul class="space-y-1 border-l border-border">
                            <li v-for="entry in tocEntries" :key="entry.id">
                                <a
                                    :href="`#${entry.id}`"
                                    class="-ml-px block border-l-2 py-1 pl-3 text-sm transition-colors"
                                    :class="
                                        entry.id === activeId
                                            ? 'border-primary font-medium text-foreground'
                                            : 'border-transparent text-muted-foreground hover:text-foreground'
                                    "
                                >
                                    {{ entry.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </div>
    </AppLayout>
</template>

<style>
.docs-article {
    font-size: 0.9375rem;
    line-height: 1.75;
    color: var(--foreground);
}

.docs-article h2 {
    margin-top: 2.5rem;
    margin-bottom: 0.75rem;
    scroll-margin-top: 1.5rem;
    font-size: 1.375rem;
    font-weight: 600;
    letter-spacing: -0.01em;
}

.docs-article h2:first-child {
    margin-top: 0;
}

.docs-article h3 {
    margin-top: 1.75rem;
    margin-bottom: 0.5rem;
    scroll-margin-top: 1.5rem;
    font-size: 1.0625rem;
    font-weight: 600;
}

.docs-article p {
    margin-bottom: 1rem;
    color: var(--muted-foreground);
}

.docs-article ul,
.docs-article ol {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
    color: var(--muted-foreground);
}

.docs-article ul {
    list-style: disc;
}

.docs-article ol {
    list-style: decimal;
}

.docs-article li {
    margin-bottom: 0.375rem;
    padding-left: 0.25rem;
}

.docs-article li::marker {
    color: var(--muted-foreground);
}

.docs-article strong {
    font-weight: 600;
    color: var(--foreground);
}

.docs-article a {
    font-weight: 500;
    color: var(--foreground);
    text-decoration: underline;
    text-underline-offset: 3px;
}
</style>
