<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    status: number;
}>();

const messages: Record<number, { title: string; body: string }> = {
    403: { title: 'Access denied', body: "You don't have permission to view this page." },
    404: { title: 'Page not found', body: "The page you're looking for doesn't exist or may have moved." },
    419: { title: 'Session expired', body: 'Your session expired. Please refresh and try again.' },
    429: { title: 'Too many requests', body: "You've made too many requests. Please wait a moment and try again." },
    500: { title: 'Something went wrong', body: "An unexpected error occurred on our end. We've logged it and will look into it." },
    503: { title: 'Down for maintenance', body: "We'll be back shortly. Please try again in a few minutes." },
};

const message = computed(() => messages[props.status] ?? {
    title: 'Unexpected error',
    body: 'Something went wrong while loading this page.',
});
</script>

<template>
    <Head :title="String(status)" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
        <div class="max-w-md w-full text-center">
            <p class="text-6xl font-bold text-green-600">{{ status }}</p>
            <h1 class="mt-4 text-xl font-semibold text-gray-800">{{ message.title }}</h1>
            <p class="mt-2 text-sm text-gray-500">{{ message.body }}</p>
            <Link href="/dashboard" class="mt-6 inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors">
                Back to Dashboard
            </Link>
        </div>
    </div>
</template>
