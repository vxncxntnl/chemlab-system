<script setup lang="ts">
withDefaults(defineProps<{
    title?: string;
    subtitle?: string;
    accent?: 'green' | 'yellow' | 'red' | 'blue' | 'none';
}>(), { accent: 'none' });
</script>

<template>
    <div :class="[
        'overflow-hidden rounded-xl bg-white shadow-sm border border-gray-100',
        accent === 'green' && 'border-l-4 border-l-green-500',
        accent === 'yellow' && 'border-l-4 border-l-amber-400',
        accent === 'red' && 'border-l-4 border-l-red-500',
        accent === 'blue' && 'border-l-4 border-l-blue-500',
    ]">
        <div v-if="title || $slots.header" class="border-b border-gray-100 px-5 py-4">
            <slot name="header">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">{{ title }}</h3>
                        <p v-if="subtitle" class="mt-0.5 text-xs text-gray-400">{{ subtitle }}</p>
                    </div>
                </div>
            </slot>
        </div>
        <div class="px-5 py-5">
            <slot />
        </div>
        <div v-if="$slots.footer" class="border-t border-gray-100 bg-gray-50/60 px-5 py-3">
            <slot name="footer" />
        </div>
    </div>
</template>
