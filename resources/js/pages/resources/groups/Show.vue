<script setup lang="ts">
import { BookOpenIcon, Layers3Icon } from 'lucide-vue-next';
import courseActions from '@/actions/App/Http/Controllers/CourseController';
import actions from '@/actions/App/Http/Controllers/GroupController';
import ResourceListItem from '@/components/resources/ResourceListItem.vue';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceShowLayout from '@/layouts/resources/ResourceShowLayout.vue';

const props = defineProps<{
    group: Group;
}>();

const routes = useResourceRoutes(props.group.id, actions);
const courses = props.group.courses ?? [];

const getGroupAvatarUrl = (name: string) => {
    return `https://api.dicebear.com/9.x/glass/svg?seed=${encodeURIComponent(name)}`;
};

const getCourseAvatarUrl = (seed: string) => {
    return `https://api.dicebear.com/9.x/initials/svg?seed=${encodeURIComponent(seed)}`;
};
</script>

<template>
    <ResourceShowLayout
        :title="group.name"
        description="Consultez les détails de la section et gérez ses paramètres."
        type="Sections"
        :routes="routes"
    >
        <div class="grid gap-6">
            <section
                class="overflow-hidden rounded-2xl border border-sidebar-border/60 bg-linear-to-br from-zinc-100 via-card to-zinc-200/60 dark:from-zinc-900 dark:via-zinc-900/90 dark:to-zinc-800/50"
            >
                <div class="flex items-start gap-6 p-6">
                    <img
                        :src="getGroupAvatarUrl(group.name)"
                        :alt="group.name"
                        class="h-20 w-20 shrink-0 rounded-2xl border border-sidebar-border/80 bg-white/70 p-1 dark:bg-zinc-800/70"
                    />
                    <div class="flex flex-col gap-3">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            {{ group.name }}
                        </h2>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span
                                class="flex items-center gap-2 rounded-full border border-sidebar-border/70 px-2.5 py-1"
                            >
                                <BookOpenIcon class="mt-px h-3 w-3" />
                                {{ courses.length }} cours
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-3">
                <h3
                    class="text-sm font-semibold tracking-wide text-muted-foreground"
                >
                    Cours suivis
                </h3>

                <div v-if="courses.length" class="grid gap-3">
                    <ResourceListItem
                        v-for="course in courses"
                        :key="course.id"
                        :href="courseActions.show(course.id).url"
                        :title="course.name"
                        :image="getCourseAvatarUrl(course.code)"
                    >
                    </ResourceListItem>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-dashed border-sidebar-border/70 bg-zinc-50/50 p-6 text-sm text-muted-foreground dark:bg-zinc-900/30"
                >
                    <div class="flex items-center gap-2">
                        <Layers3Icon class="h-4 w-4" />
                        <p>
                            Aucun cours associé à cette section pour le moment.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </ResourceShowLayout>
</template>
