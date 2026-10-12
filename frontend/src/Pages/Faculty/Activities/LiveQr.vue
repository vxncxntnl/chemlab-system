<script setup lang="ts">
/**
 * Classroom (Live) QR: shown full screen on the projector. The QR changes
 * every minute, so a picture of it shared to another room stops working.
 * Students in the room just scan whatever is on screen.
 */
import QrCode from '@/Components/QrCode.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    activity: { id: number; title: string; opens_at: string | null; closes_at: string | null };
    seconds: number;
}>();

const url = ref('');
const secondsLeft = ref(props.seconds);
const scanned = ref(0);
const waiting = ref(0);
const message = ref<string | null>(null);
const live = ref(true);
let tick: ReturnType<typeof setInterval> | null = null;

const load = async () => {
    try {
        const { data } = await axios.get(route('faculty.activities.qr.live-code', props.activity.id));
        url.value = data.url;
        secondsLeft.value = data.refresh_in;
        scanned.value = data.scanned;
        waiting.value = data.waiting;
        message.value = data.message;
        live.value = data.live;
    } catch {
        message.value = 'Could not load the QR code. Check the internet connection.';
    }
};

onMounted(() => {
    load();
    tick = setInterval(() => {
        secondsLeft.value -= 1;
        // New code at the start of each minute; counts refresh every 10 seconds.
        if (secondsLeft.value <= 0 || secondsLeft.value % 10 === 0) load();
    }, 1000);
});
onBeforeUnmount(() => { if (tick) clearInterval(tick); });

const timeText = (d: string | null) => d ? new Date(d).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }) : null;
</script>

<template>
    <Head :title="`Live QR — ${activity.title}`" />
    <div class="min-h-screen bg-white flex flex-col items-center justify-center px-4 py-8 text-center">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ activity.title }}</h1>
        <p v-if="activity.closes_at" class="mt-1 text-gray-500">QR closes at {{ timeText(activity.closes_at) }}</p>

        <div v-if="message" class="mt-8 max-w-md rounded-xl border border-red-200 bg-red-50 px-6 py-4 text-red-800">{{ message }}</div>
        <div v-else-if="!live" class="mt-8 max-w-md rounded-xl border border-yellow-200 bg-yellow-50 px-6 py-4 text-yellow-800">
            Classroom mode is off. Turn it on from the activity page to show the Live QR.
        </div>
        <template v-else>
            <div class="mt-6 rounded-2xl border border-gray-200 p-4 shadow-sm">
                <QrCode v-if="url" :value="url" :size="360" :label="activity.title" />
            </div>
            <div class="mt-5 w-72">
                <p class="text-sm text-gray-600">🔄 New code in <span class="font-semibold tabular-nums">0:{{ String(Math.max(0, secondsLeft)).padStart(2, '0') }}</span></p>
                <div class="mt-2 h-2 rounded-full bg-gray-200 overflow-hidden">
                    <div class="h-full bg-green-500 transition-all duration-1000" :style="{ width: `${(Math.max(0, secondsLeft) / seconds) * 100}%` }" />
                </div>
            </div>
        </template>

        <div class="mt-6 flex gap-6 text-lg">
            <p>✅ <span class="font-semibold">{{ scanned }}</span> scanned</p>
            <p>⏳ <span class="font-semibold">{{ waiting }}</span> waiting for approval</p>
        </div>

        <Link :href="route('faculty.activities.show', activity.id)" class="mt-8 text-sm text-gray-500 hover:text-gray-800 underline">
            Back to the activity (approve students)
        </Link>
    </div>
</template>
