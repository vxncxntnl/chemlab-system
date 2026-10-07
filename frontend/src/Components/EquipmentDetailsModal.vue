<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

// Photo URL that failed to load (e.g. wiped by a redeploy on free hosting).
const failedImage = ref<string | null>(null);

defineProps<{
    show: boolean;
    item: any | null;
    borrowRouteName: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const availabilityColor = (item: any) => {
    if (item.available_quantity === 0) return 'text-red-600';
    if (item.available_quantity / item.total_quantity < 0.3) return 'text-amber-600';
    return 'text-emerald-600';
};

const progressColor = (item: any) => {
    if (item.available_quantity === 0) return 'bg-red-400';
    if (item.available_quantity / item.total_quantity < 0.3) return 'bg-amber-400';
    return 'bg-emerald-500';
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div v-if="item">
            <!-- Image -->
            <div class="h-56 bg-green-50 flex items-center justify-center border-b border-green-100 overflow-hidden">
                <img
                    v-if="item.equipment?.image_path && failedImage !== item.equipment.image_path"
                    :src="`/storage/${item.equipment.image_path}`"
                    @error="failedImage = item.equipment.image_path"
                    :alt="item.equipment.name"
                    class="h-full w-full object-contain p-4"
                />
                <svg v-else class="h-16 w-16 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
            </div>

            <div class="p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="text-xs font-medium text-green-700 bg-green-50 px-2 py-0.5 rounded-full border border-green-100">
                            {{ item.equipment?.category || 'General' }}
                        </span>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900">{{ item.equipment?.name }}</h3>
                    </div>
                    <span v-if="item.available_quantity === 0" class="whitespace-nowrap bg-red-100 text-red-700 text-xs font-semibold px-2.5 py-1 rounded-full border border-red-200">Out of Stock</span>
                    <span v-else-if="item.available_quantity / item.total_quantity < 0.3" class="whitespace-nowrap bg-amber-100 text-amber-700 text-xs font-semibold px-2.5 py-1 rounded-full border border-amber-200">Low Stock</span>
                </div>

                <p v-if="item.equipment?.description" class="mt-3 text-sm text-gray-500">{{ item.equipment.description }}</p>

                <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-400">Location</p>
                        <p class="font-medium text-gray-700">{{ item.campus?.campus_name }} — {{ item.location?.location_name }}</p>
                    </div>
                    <div v-if="item.equipment?.condition">
                        <p class="text-xs text-gray-400">Condition</p>
                        <p class="font-medium text-gray-700">{{ item.equipment.condition }}</p>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-xs text-gray-400">Availability</span>
                        <span :class="['text-xs font-semibold', availabilityColor(item)]">
                            {{ item.available_quantity === 0 ? 'None' : `${item.available_quantity} / ${item.total_quantity}` }}
                        </span>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-gray-100 overflow-hidden">
                        <div
                            :class="['h-full rounded-full transition-all', progressColor(item)]"
                            :style="{ width: item.total_quantity > 0 ? `${(item.available_quantity / item.total_quantity) * 100}%` : '0%' }"
                        ></div>
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <Link
                        v-if="item.available_quantity > 0"
                        :href="route(borrowRouteName, { inventory_id: item.id })"
                        class="flex-1 text-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700 transition-colors shadow-sm"
                    >
                        Borrow This Item
                    </Link>
                    <div v-else class="flex-1 text-center rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-400 cursor-not-allowed">
                        Currently Unavailable
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors"
                        @click="emit('close')"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
