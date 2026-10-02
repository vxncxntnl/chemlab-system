<script setup lang="ts">
defineProps<{
    item: any;
}>();

const emit = defineEmits<{
    (e: 'view', item: any): void;
}>();

const availabilityColor = (item: any) => {
    if (item.available_quantity === 0) return 'text-red-600';
    if (item.available_quantity / item.total_quantity < 0.3) return 'text-amber-600';
    return 'text-emerald-600';
};
</script>

<template>
    <div
        :class="[
            'group relative flex flex-col bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden cursor-pointer transition-all duration-200 hover:shadow-md hover:-translate-y-0.5',
            item.available_quantity === 0 ? 'opacity-70' : ''
        ]"
        @click="emit('view', item)"
    >
        <!-- Status badge -->
        <div v-if="item.available_quantity === 0" class="absolute top-2 right-2 z-10">
            <span class="bg-red-100 text-red-700 text-[10px] font-semibold px-2 py-0.5 rounded-full border border-red-200">Out of Stock</span>
        </div>
        <div v-else-if="item.available_quantity / item.total_quantity < 0.3" class="absolute top-2 right-2 z-10">
            <span class="bg-amber-100 text-amber-700 text-[10px] font-semibold px-2 py-0.5 rounded-full border border-amber-200">Low Stock</span>
        </div>

        <!-- Thumbnail -->
        <div class="h-32 sm:h-36 bg-green-50 flex items-center justify-center border-b border-green-100 overflow-hidden">
            <img
                v-if="item.equipment?.image_path"
                :src="`/storage/${item.equipment.image_path}`"
                :alt="item.equipment.name"
                class="h-full w-full object-contain p-2 group-hover:scale-105 transition-transform duration-300"
            />
            <svg v-else class="h-10 w-10 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
        </div>

        <!-- Essentials only: name, category, quick availability -->
        <div class="flex flex-col gap-1.5 p-4">
            <span class="self-start text-xs font-medium text-green-700 bg-green-50 px-2 py-1 rounded-full border border-green-100">
                {{ item.equipment?.category || 'General' }}
            </span>
            <h3 class="text-base font-semibold text-gray-900 leading-snug truncate" :title="item.equipment?.name">
                {{ item.equipment?.name }}
            </h3>
            <p :class="['text-sm font-medium', availabilityColor(item)]">
                {{ item.available_quantity === 0 ? 'Unavailable' : `${item.available_quantity} / ${item.total_quantity} available` }}
            </p>
        </div>
    </div>
</template>
