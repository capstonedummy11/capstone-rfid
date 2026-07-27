<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

defineOptions({ name: 'CameraCapture' });

const emit = defineEmits(['captured']);

// Exposed so parent can trigger a capture programmatically
defineExpose({ captureFrame, resetCapture });

const videoRef = ref(null);
const canvasRef = ref(null);
const capturedDataUrl = ref(null); // null = showing live feed
const cameraError = ref(null);
const cameraReady = ref(false);

let stream = null;

async function startCamera() {
    cameraError.value = null;
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 640 },
                height: { ideal: 480 },
                facingMode: 'user',
            },
            audio: false,
        });
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
        }
        cameraReady.value = true;
    } catch (err) {
        cameraError.value =
            err?.name === 'NotAllowedError'
                ? 'Camera access denied. Please allow camera permissions.'
                : 'Camera not available.';
    }
}

function stopCamera() {
    stream?.getTracks().forEach((t) => t.stop());
    stream = null;
    cameraReady.value = false;
}

/**
 * Capture the current video frame into a base64 JPEG data URL.
 * Returns the data URL string (or null on failure).
 */
function captureFrame() {
    if (!cameraReady.value || !videoRef.value || !canvasRef.value) return null;

    const video = videoRef.value;
    const canvas = canvasRef.value;
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;

    const ctx = canvas.getContext('2d');
    // Mirror-flip to match the mirrored video preview
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
    capturedDataUrl.value = dataUrl;
    emit('captured', dataUrl);
    return dataUrl;
}

function resetCapture() {
    capturedDataUrl.value = null;
}

onMounted(() => startCamera());
onUnmounted(() => stopCamera());
</script>

<template>
    <div
        class="relative flex items-center justify-center overflow-hidden rounded-3xl bg-slate-900"
        style="width: 144px; height: 144px"
    >
        <!-- Camera error state -->
        <div
            v-if="cameraError"
            class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-slate-800 p-2 text-center"
        >
            <svg
                class="h-8 w-8 text-slate-500"
                fill="none"
                stroke="currentColor"
                stroke-width="1.6"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 10l4.553-2.069A1 1 0 0 1 21 8.82v6.36a1 1 0 0 1-1.447.89L15 14M4 8h8a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2z"
                />
                <line
                    x1="2"
                    y1="2"
                    x2="22"
                    y2="22"
                    stroke-linecap="round"
                    stroke-width="1.6"
                />
            </svg>
            <span
                class="text-[10px] leading-tight font-semibold text-slate-400"
                >{{ cameraError }}</span
            >
        </div>

        <!-- Live video feed (hidden once a frame is captured) -->
        <video
            v-show="!capturedDataUrl && !cameraError"
            ref="videoRef"
            autoplay
            playsinline
            muted
            class="h-full w-full object-cover"
            style="transform: scaleX(-1)"
        />

        <!-- Captured snapshot -->
        <img
            v-if="capturedDataUrl"
            :src="capturedDataUrl"
            alt="captured photo"
            class="h-full w-full object-cover"
        />

        <!-- Hidden canvas used for frame extraction -->
        <canvas ref="canvasRef" class="hidden" />

        <!-- Waiting overlay when camera hasn't started yet -->
        <div
            v-if="!cameraReady && !cameraError"
            class="absolute inset-0 flex items-center justify-center bg-slate-900"
        >
            <svg
                class="h-6 w-6 animate-spin text-slate-500"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                />
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8v8H4z"
                />
            </svg>
        </div>

        <!-- "LIVE" indicator on the video feed -->
        <span
            v-if="cameraReady && !capturedDataUrl"
            class="absolute top-1.5 left-1.5 rounded px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-white uppercase"
            style="background: rgba(16, 185, 129, 0.85)"
            >LIVE</span
        >

        <!-- "CAPTURED" indicator when a frame is frozen -->
        <span
            v-if="capturedDataUrl"
            class="absolute top-1.5 left-1.5 rounded px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-white uppercase"
            style="background: rgba(59, 130, 246, 0.85)"
            >CAPTURED</span
        >
    </div>
</template>
