<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'default' | 'success' | 'warning' | 'danger' | 'info' | 'primary';
        size?: 'sm' | 'md' | 'lg';
        dot?: boolean;
    }>(),
    {
        variant: 'default',
        size: 'md',
        dot: false,
    }
);

const styles = computed(() => ({
    default: { badge: 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-300/60', dot: 'bg-gray-400' },
    success: { badge: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200', dot: 'bg-emerald-500' },
    warning: { badge: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200', dot: 'bg-amber-400' },
    danger:  { badge: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200', dot: 'bg-red-500' },
    info:    { badge: 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200', dot: 'bg-sky-500' },
    primary: { badge: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-200', dot: 'bg-green-500' },
}[props.variant]));

const sizeClasses = computed(() => ({
    sm: 'px-2 py-0.5 text-xs gap-1',
    md: 'px-2.5 py-1 text-xs gap-1.5',
    lg: 'px-3 py-1.5 text-sm gap-1.5',
}[props.size]));
</script>

<template>
    <span :class="['inline-flex items-center font-medium rounded-full whitespace-nowrap', styles.badge, sizeClasses]">
        <span v-if="dot" :class="['w-1.5 h-1.5 rounded-full flex-shrink-0', styles.dot]"></span>
        <slot />
    </span>
</template>
