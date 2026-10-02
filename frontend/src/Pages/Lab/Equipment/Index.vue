<script setup lang="ts">
import LabLayout from '@/Layouts/LabLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    inventory: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
    categories: Array<string>;
    filters: {
        category?: string;
        search?: string;
    };
    campus?: { id: number; campus_name: string } | null;
}>();

const search = ref(props.filters.search || '');
const categoryFilter = ref(props.filters.category || '');

const applyFilters = () => {
    router.get(route('lab.equipment'), {
        search: search.value,
        category: categoryFilter.value,
    }, { preserveState: true });
};
</script>

<template>
    <LabLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Browse Equipment</h1>
                <div v-if="props.campus" class="flex items-center gap-1.5 text-sm text-gray-500">
                    <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="font-medium text-gray-700">{{ props.campus.campus_name }}</span>
                </div>
            </div>
        </template>

        <!-- Filters -->
        <div class="mb-6 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1 max-w-sm">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search equipment..."
                    class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                    @keyup.enter="applyFilters"
                />
            </div>
            <select
                v-model="categoryFilter"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                @change="applyFilters"
            >
                <option value="">All Categories</option>
                <option v-for="cat in props.categories" :key="cat" :value="cat">{{ cat }}</option>
            </select>
        </div>

        <!-- Equipment Grid -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="item in props.inventory.data" :key="item.id" :class="item.available_quantity === 0 ? 'opacity-60' : ''">
                <div class="flex flex-col h-full relative">
                    <!-- Not Available overlay badge -->
                    <div v-if="item.available_quantity === 0"
                        class="absolute top-2 right-2 z-10 bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded-full border border-red-200">
                        Not Available
                    </div>
                    <div class="flex-1">
                        <!-- Image -->
                        <div class="mb-3 h-40 w-full rounded-lg overflow-hidden bg-green-50 border border-green-100">
                            <img
                                v-if="item.equipment?.image_path"
                                :src="`/storage/${item.equipment.image_path}`"
                                :alt="item.equipment?.name"
                                class="h-full w-full object-contain p-2"
                            />
                            <div v-else class="h-full w-full flex items-center justify-center">
                                <svg class="h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ item.equipment?.name }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ item.equipment?.description }}
                        </p>

                        <div class="mt-3 space-y-1 text-sm text-gray-500">
                            <p><span class="font-medium">Code:</span> {{ item.equipment?.equipment_code || 'N/A' }}</p>
                            <p><span class="font-medium">Location:</span> {{ item.location?.location_name }}</p>
                            <div class="pt-1">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-medium text-gray-700">Availability</span>
                                    <span class="text-xs font-semibold"
                                        :class="item.available_quantity === 0 ? 'text-red-600' : item.available_quantity / item.total_quantity < 0.3 ? 'text-yellow-600' : 'text-green-600'">
                                        {{ item.available_quantity === 0 ? 'Out of stock' : item.available_quantity + ' of ' + item.total_quantity + ' available' }}
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all"
                                        :class="item.available_quantity === 0 ? 'bg-red-500' : item.available_quantity / item.total_quantity < 0.3 ? 'bg-yellow-400' : 'bg-green-500'"
                                        :style="{ width: (item.total_quantity > 0 ? (item.available_quantity / item.total_quantity) * 100 : 0) + '%' }">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <Badge :variant="item.equipment?.condition === 'damaged' ? 'danger' : 'info'">
                            {{ item.equipment?.condition }}
                        </Badge>
                    </div>
                </div>
            </Card>
        </div>

        <div v-if="props.inventory.data.length === 0" class="text-center py-12">
            <p class="text-gray-500">No equipment found for this campus.</p>
        </div>

        <!-- Pagination -->
        <div v-if="props.inventory.last_page > 1" class="mt-6 flex justify-center">
            <nav class="flex flex-wrap justify-center gap-2">
                <Link
                    v-for="link in props.inventory.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    :class="[
                        'px-3 py-2 text-sm rounded-md',
                        link.active ? 'bg-green-600 text-white' : 'text-gray-500 hover:bg-gray-100',
                        !link.url ? 'cursor-not-allowed opacity-50' : ''
                    ]"
                    v-html="link.label"
                />
            </nav>
        </div>
    </LabLayout>
</template>

