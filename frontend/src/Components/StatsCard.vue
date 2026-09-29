<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        value: string | number;
        icon?: string;
        trend?: number;
        color?: 'green' | 'red' | 'yellow' | 'blue' | 'gray' | 'purple';
    }>(),
    { color: 'green' }
);

const colorMap = computed(() => {
    const map = {
        green:  { bg: 'bg-green-500',  light: 'bg-green-50',  text: 'text-green-600',  border: 'border-green-100' },
        red:    { bg: 'bg-red-500',    light: 'bg-red-50',    text: 'text-red-600',    border: 'border-red-100' },
        yellow: { bg: 'bg-amber-400',  light: 'bg-amber-50',  text: 'text-amber-600',  border: 'border-amber-100' },
        blue:   { bg: 'bg-blue-500',   light: 'bg-blue-50',   text: 'text-blue-600',   border: 'border-blue-100' },
        gray:   { bg: 'bg-gray-500',   light: 'bg-gray-50',   text: 'text-gray-600',   border: 'border-gray-100' },
        purple: { bg: 'bg-purple-500', light: 'bg-purple-50', text: 'text-purple-600', border: 'border-purple-100' },
    };
    return map[props.color ?? 'green'];
});
</script>

<template>
    <div :class="['relative overflow-hidden rounded-xl bg-white border shadow-sm p-5', colorMap.border]">
        <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider truncate">{{ title }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900 leading-none">{{ value }}</p>
                <div v-if="trend !== undefined" :class="['mt-2 flex items-center gap-1 text-xs font-semibold', trend >= 0 ? 'text-emerald-600' : 'text-red-500']">
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path v-if="trend >= 0" fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        <path v-else fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    {{ Math.abs(trend) }}% from last month
                </div>
            </div>
            <div v-if="icon" :class="['flex-shrink-0 rounded-xl p-3', colorMap.light]">
                <svg :class="['h-6 w-6', colorMap.text]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="icon" />
                </svg>
            </div>
        </div>
    </div>
</template>
