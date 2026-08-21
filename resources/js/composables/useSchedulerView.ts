import { computed, ref } from 'vue';

const ZOOM_STEPS = { small: 0.5, normal: 1, large: 1.5, xl: 2 } as const;

export type ZoomLevel = keyof typeof ZOOM_STEPS;

const ZOOM_ORDER = Object.keys(ZOOM_STEPS) as ZoomLevel[];

export default function useSchedulerView(
    initialZoom: ZoomLevel = 'normal',
    initialFullscreen = false,
) {
    const zoom = ref(initialZoom);
    const isFullscreen = ref(initialFullscreen);

    const zoomIndex = computed(() => ZOOM_ORDER.indexOf(zoom.value));
    const canZoomIn = computed(() => zoomIndex.value < ZOOM_ORDER.length - 1);
    const canZoomOut = computed(() => zoomIndex.value > 0);

    const zoomIn = () => {
        if (canZoomIn.value) zoom.value = ZOOM_ORDER[zoomIndex.value + 1];
    };
    const zoomOut = () => {
        if (canZoomOut.value) zoom.value = ZOOM_ORDER[zoomIndex.value - 1];
    };
    const toggleFullscreen = () => {
        isFullscreen.value = !isFullscreen.value;
    };

    const zoomRatio = computed(() => ZOOM_STEPS[zoom.value]);
    const zoomLabel = computed(() => zoomRatio.value.toFixed(1));

    return {
        zoom,
        zoomRatio,
        isFullscreen,
        canZoomIn,
        canZoomOut,
        zoomIn,
        zoomOut,
        toggleFullscreen,
        zoomLabel,
    };
}
