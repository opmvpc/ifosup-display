<script setup lang="ts">
import {
    GraduationCapIcon,
    HashIcon,
    Layers3Icon,
    User,
    UsersRoundIcon,
} from 'lucide-vue-next';
import actions from '@/actions/App/Http/Controllers/CourseController';
import groupActions from '@/actions/App/Http/Controllers/GroupController';
import teacherActions from '@/actions/App/Http/Controllers/TeacherController';
import ResourceListItem from '@/components/resources/ResourceListItem.vue';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceShowLayout from '@/layouts/resources/ResourceShowLayout.vue';
import { courseAvatar, groupAvatar, teacherAvatar } from '@/lib/avatars';
import { courseYearLabel } from '@/lib/courseYear';

const props = defineProps<{
    course: Course;
}>();

const routes = useResourceRoutes(props.course.id, actions);
const groups = props.course.groups ?? [];
</script>

<template>
    <ResourceShowLayout
        :title="course.name"
        description="Consultez les détails du cours et gérez ses paramètres."
        type="Cours"
        :routes="routes"
    >
        <div class="grid gap-6">
            <section
                class="overflow-hidden rounded-2xl border border-sidebar-border/60 bg-linear-to-br from-zinc-100 via-card to-zinc-200/60 dark:from-zinc-900 dark:via-zinc-900/90 dark:to-zinc-800/50"
            >
                <div class="flex items-start gap-6 p-6">
                    <img
                        :src="courseAvatar(course.code)"
                        :alt="course.name"
                        class="h-20 w-20 shrink-0 rounded-2xl border border-sidebar-border/80 bg-white/70 p-1 dark:bg-zinc-800/70"
                    />
                    <div class="flex flex-col gap-3">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            {{ course.name }}
                        </h2>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span
                                class="flex items-center gap-2 rounded-full border border-sidebar-border/70 px-2.5 py-1"
                            >
                                <HashIcon class="mt-px h-3 w-3" />
                                {{ course.code }}
                            </span>
                            <span
                                v-if="courseYearLabel(course.year)"
                                class="flex items-center gap-2 rounded-full border border-sidebar-border/70 px-2.5 py-1"
                            >
                                <GraduationCapIcon class="mt-px h-3 w-3" />
                                {{ courseYearLabel(course.year) }}
                            </span>
                            <span
                                class="flex items-center gap-2 rounded-full border border-sidebar-border/70 px-2.5 py-1"
                            >
                                <User class="mt-px h-3 w-3" />
                                {{ course.teacher?.name ?? '—' }}
                            </span>
                            <span
                                class="flex items-center gap-2 rounded-full border border-sidebar-border/70 px-2.5 py-1"
                            >
                                <UsersRoundIcon class="mt-px h-3 w-3" />
                                {{ groups.length }} section{{
                                    groups.length !== 1 ? 's' : ''
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-3">
                <h3
                    class="text-sm font-semibold tracking-wide text-muted-foreground"
                >
                    Enseignant
                </h3>

                <ResourceListItem
                    v-if="course.teacher"
                    :href="teacherActions.show(course.teacher.id).url"
                    :title="course.teacher.name"
                    :image="teacherAvatar(course.teacher.name)"
                />

                <div
                    v-else
                    class="rounded-xl border border-dashed border-sidebar-border/70 bg-zinc-50/50 p-6 text-sm text-muted-foreground dark:bg-zinc-900/30"
                >
                    <div class="flex items-center gap-2">
                        <Layers3Icon class="h-4 w-4" />
                        <p>Aucun enseignant associé à ce cours.</p>
                    </div>
                </div>
            </section>

            <section class="space-y-3">
                <h3
                    class="text-sm font-semibold tracking-wide text-muted-foreground"
                >
                    Sections
                </h3>

                <div v-if="groups.length" class="grid gap-3">
                    <ResourceListItem
                        v-for="group in groups"
                        :key="group.id"
                        :href="groupActions.show(group.id).url"
                        :title="group.name"
                        :image="groupAvatar(group.name)"
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
                            Aucune section associée à ce cours pour le moment.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </ResourceShowLayout>
</template>
