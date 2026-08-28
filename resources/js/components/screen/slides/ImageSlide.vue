<template>
    <div class="h-full w-full bg-[#1e2d55]">
        <img
            :src="data?.src"
            class="h-full w-full object-contain"
            alt="Slide"
        />
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({ data: Object });
const emit = defineEmits(['next']);

let timer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    timer = window.setTimeout(() => {
        emit('next');
    }, props.data?.duration);
});

onUnmounted(() => {
    if (timer !== null) {
        window.clearTimeout(timer);
    }
});
</script>
