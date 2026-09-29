<script setup lang="ts">
defineProps<{
    disabled?: boolean;
}>();
</script>

<template>
    <button
        :disabled="disabled"
        class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500/40 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
    >
        <svg v-if="disabled" class="h-4 w-4 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <slot />
    </button>
</template>
