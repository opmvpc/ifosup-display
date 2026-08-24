<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import actions from '@/actions/App/Http/Controllers/CourseController';
import ResourceListItem from '@/components/resources/ResourceListItem.vue';
import ResourceListItemData from '@/components/resources/ResourceListItemData.vue';
import { Input } from '@/components/ui/input';
import { useResourceRoutes } from '@/composables/useResourceRoutes';
import ResourceIndexLayout from '@/layouts/resources/ResourceIndexLayout.vue';
import { courseYearLabel } from '@/lib/courseYear';

const props = defineProps<{
    courses: Course[];
}>();

const routes = useResourceRoutes(null, actions);
const query = ref('');

const filteredCourses = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.courses;
    return props.courses.filter(
        (c) =>
            c.code.toLowerCase().includes(q) ||
            c.name.toLowerCase().includes(q),
    );
});

const getAvatarUrl = (name: string) => {
    return `https://api.dicebear.com/9.x/initials/svg?seed=${encodeURIComponent(name)}`;
};
</script>

<template>
    <ResourceIndexLayout
        type="Cours"
        :routes="routes"
        :isEmpty="courses.length === 0"
    >
        <template #empty-title>Il n'y a aucun cours.</template>
        <template #empty-action>Créer un cours</template>
        <template #create-action>Créer un cours</template>

        <div class="relative mb-4 max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="query"
                placeholder="Rechercher un cours…"
                class="pl-9"
            />
        </div>
        <div class="grid gap-3">
            <ResourceListItem
                v-for="course in filteredCourses"
                :key="course.id"
                :href="actions.show(course.id).url"
                :title="course.code"
                :image="getAvatarUrl(course.code)"
            >
                <ResourceListItemData>{{ course.name }}</ResourceListItemData>
                <ResourceListItemData v-if="courseYearLabel(course.year)">
                    {{ courseYearLabel(course.year) }}
                </ResourceListItemData>
            </ResourceListItem>
            <p
                v-if="filteredCourses.length === 0 && query"
                class="py-6 text-center text-sm text-muted-foreground"
            >
                Aucun résultat pour « {{ query }} ».
            </p>
        </div>
    </ResourceIndexLayout>
</template>
