<template>
    <div class="h-full w-full bg-black">
        <video
            ref="videoPlayer"
            class="h-full w-full object-cover"
            autoplay
            muted
            playsinline
            :src="data?.src"
            @ended="emit('next')"
            @error="emit('next')"
        ></video>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';

defineProps({ data: Object });
const emit = defineEmits(['next']);

const videoPlayer = ref<HTMLVideoElement | null>(null);

onMounted(() => {
    if (videoPlayer.value) {
        videoPlayer.value.currentTime = 0;
        videoPlayer.value.play().catch(() => emit('next'));
    }
});
</script>
