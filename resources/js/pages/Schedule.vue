<script setup lang="ts">
import Scheduler from '@/components/Scheduler.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { schedule } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    rooms: Room[];
    courses: Course[];
    assignments: AssignmentWithRelations[];
    fromDate: string;
    toDate: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Planning',
        href: schedule(),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs>
        <div class="h-full p-4">
            <Scheduler
                :rooms
                :courses
                :assignments
                :from-date="props.fromDate"
                :to-date="props.toDate"
            />
        </div>
    </AppLayout>
</template>
