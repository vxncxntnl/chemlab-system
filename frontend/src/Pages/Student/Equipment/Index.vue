<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import EquipmentBrowseCard from '@/Components/EquipmentBrowseCard.vue';
import EquipmentDetailsModal from '@/Components/EquipmentDetailsModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps<{
    inventory: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
    campuses: Array<{ id: number; campus_name: string }>;
    categories: Array<string>;
    filters: {
        campus_id?: number;
        category?: string;
        search?: string;
    };
    studentCourse?: string | null;
}>();

const search = ref(props.filters.search || '');
const campusFilter = ref(props.filters.campus_id ? String(props.filters.campus_id) : '');
const categoryFilter = ref(props.filters.category || '');
const lastRefreshed = ref(new Date().toLocaleTimeString());
let refreshInterval: ReturnType<typeof setInterval> | null = null;

const applyFilters = () => {
    router.get(route('student.equipment'), {
        search: search.value,
        campus_id: campusFilter.value,
        category: categoryFilter.value,
    }, { preserveState: true, replace: true });
};

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});

const setCategory = (cat: string) => {
    categoryFilter.value = cat;
    applyFilters();
};

const availableCount = computed(() => props.inventory.data.filter(i => i.available_quantity > 0).length);

const selectedItem = ref<any | null>(null);
const showDetails = ref(false);

const openDetails = (item: any) => {
    selectedItem.value = item;
    showDetails.value = true;
};

const closeDetails = () => {
    showDetails.value = false;
};

const refreshAvailability = () => {
    router.reload({ only: ['inventory'], onSuccess: () => {
        lastRefreshed.value = new Date().toLocaleTimeString();
    }});
};

onMounted(() => { refreshInterval = setInterval(refreshAvailability, 30000); });
onBeforeUnmount(() => { if (refreshInterval) clearInterval(refreshInterval); });
</script>

<template>
    <UserLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Browse Equipment</h1>
        </template>

        <!-- Search + filters bar -->
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1 max-w-md">
                <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search equipment..."
                    class="w-full rounded-lg border border-gray-200 bg-white pl-9 pr-4 py-2.5 text-sm placeholder-gray-400 shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                />
            </div>
            <select
                v-model="campusFilter"
                class="rounded-lg border border-gray-200 bg-white pl-3 pr-8 py-2.5 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                @change="applyFilters"
            >
                <option value="">All Campuses</option>
                <option v-for="campus in campuses" :key="campus.id" :value="campus.id">{{ campus.campus_name }}</option>
            </select>
        </div>

        <!-- Category pills -->
        <div class="mb-5 flex flex-wrap gap-2">
            <button
                @click="setCategory('')"
                :class="[
                    'px-3.5 py-1.5 rounded-full text-xs font-semibold border transition-all',
                    categoryFilter === ''
                        ? 'bg-green-600 text-white border-green-600 shadow-sm'
                        : 'bg-white text-gray-600 border-gray-200 hover:border-green-400 hover:text-green-700'
                ]"
            >
                All
            </button>
            <button
                v-for="cat in categories"
                :key="cat"
                @click="setCategory(cat)"
                :class="[
                    'px-3.5 py-1.5 rounded-full text-xs font-semibold border transition-all',
                    categoryFilter === cat
                        ? 'bg-green-600 text-white border-green-600 shadow-sm'
                        : 'bg-white text-gray-600 border-gray-200 hover:border-green-400 hover:text-green-700'
                ]"
            >
                {{ cat }}
            </button>
        </div>

        <!-- Results summary + live indicator -->
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-500">
                <span class="font-semibold text-gray-800">{{ availableCount }}</span> available
                <span class="text-gray-400 mx-1">·</span>
                <span class="font-semibold text-gray-800">{{ inventory.total }}</span> total items
            </p>
            <span class="inline-flex items-center gap-1.5 text-xs text-gray-400">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                </span>
                Live · {{ lastRefreshed }}
            </span>
        </div>

        <!-- Equipment Grid (compact — click a card to view full details) -->
        <div v-if="inventory.data.length > 0" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            <EquipmentBrowseCard
                v-for="item in inventory.data"
                :key="item.id"
                :item="item"
                @view="openDetails"
            />
        </div>

        <!-- Empty state -->
        <div v-else>
            <EmptyState
                icon="search"
                title="No equipment found"
                description="No equipment matches your filters. Try adjusting your search or category."
            />
        </div>

        <EquipmentDetailsModal
            :show="showDetails"
            :item="selectedItem"
            borrow-route-name="student.borrowings.create"
            @close="closeDetails"
        />

        <!-- Pagination -->
        <div v-if="inventory.last_page > 1" class="mt-6 flex flex-wrap items-center justify-center gap-1">
            <Link
                v-for="link in inventory.links"
                :key="link.label"
                :href="link.url || '#'"
                :class="[
                    'inline-flex items-center justify-center min-w-[36px] h-9 px-3 text-sm rounded-lg transition-colors',
                    link.active ? 'bg-green-600 text-white shadow-sm font-semibold' : 'text-gray-500 hover:bg-gray-100',
                    !link.url ? 'pointer-events-none opacity-40' : ''
                ]"
                v-html="link.label"
            />
        </div>
    </UserLayout>
</template>
