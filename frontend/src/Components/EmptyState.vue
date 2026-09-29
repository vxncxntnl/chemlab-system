<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
    actionLabel?: string;
    actionHref?: string;
    icon?: 'clipboard' | 'equipment' | 'users' | 'bell' | 'search' | 'history';
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center py-14 px-4 text-center">
        <!-- Icon -->
        <div class="mb-4 w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
            <!-- Clipboard (borrowings) -->
            <svg v-if="!icon || icon === 'clipboard'" class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <!-- Equipment -->
            <svg v-else-if="icon === 'equipment'" class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <!-- Users -->
            <svg v-else-if="icon === 'users'" class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <!-- Bell (notifications) -->
            <svg v-else-if="icon === 'bell'" class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <!-- Search -->
            <svg v-else-if="icon === 'search'" class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <!-- History -->
            <svg v-else-if="icon === 'history'" class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>

        <h3 class="text-sm font-semibold text-gray-900 mb-1">{{ title || 'No data found' }}</h3>
        <p class="text-sm text-gray-500 max-w-xs mb-5">{{ description || 'Nothing to show here yet.' }}</p>

        <a
            v-if="actionLabel && actionHref"
            :href="actionHref"
            class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition"
        >
            {{ actionLabel }}
        </a>
        <slot />
    </div>
</template>
