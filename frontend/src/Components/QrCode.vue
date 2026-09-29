<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps<{
    value: string;
    size?: number;
    label?: string;
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);

const generate = async () => {
    if (!canvasRef.value || !props.value) return;
    await QRCode.toCanvas(canvasRef.value, props.value, {
        width: props.size ?? 160,
        margin: 2,
        color: { dark: '#1e1b4b', light: '#ffffff' },
    });
};

onMounted(generate);
watch(() => props.value, generate);

const download = () => {
    if (!canvasRef.value) return;
    const link = document.createElement('a');
    link.download = `qr-${props.label ?? 'equipment'}.png`;
    link.href = canvasRef.value.toDataURL('image/png');
    link.click();
};
</script>

<template>
    <div class="flex flex-col items-center gap-2">
        <canvas ref="canvasRef" class="rounded-lg border border-gray-200 shadow-sm" />
        <p v-if="label" class="text-xs font-mono text-gray-600 text-center">{{ label }}</p>
        <button
            type="button"
            @click="download"
            class="text-xs text-green-600 hover:text-green-500 underline"
        >
            Download QR
        </button>
    </div>
</template>
