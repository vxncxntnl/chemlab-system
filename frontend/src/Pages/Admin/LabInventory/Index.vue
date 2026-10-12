<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useLiveSearch } from '@/composables/useLiveSearch';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps<{
    inventory: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
    stats: {
        total_items: number;
        chemistry_items: number;
        physics_items: number;
        general_items: number;
        pasco_items: number;
        transferred_iligan: number;
        transferred_butuan: number;
    };
    storageLocations: Array<string>;
    filters: {
        search?: string;
        lab?: string;
        category?: string;
        condition?: string;
        storage_location?: string;
        sort_by?: string;
        sort_dir?: string;
    };
}>();

const search = ref(props.filters.search || '');
const labFilter = ref(props.filters.lab || '');
const categoryFilter = ref(props.filters.category || '');
const conditionFilter = ref(props.filters.condition || '');
const locationFilter = ref(props.filters.storage_location || '');

const columns = [
    { key: 'item_no', label: 'Item #' },
    { key: 'description', label: 'Description' },
    { key: 'size', label: 'Size' },
    { key: 'quantity', label: 'Qty' },
    { key: 'uom', label: 'UOM' },
    { key: 'brand', label: 'Brand' },
    { key: 'storage_location', label: 'Location' },
    { key: 'condition_remarks', label: 'Condition' },
    { key: 'transfers', label: 'Transfers' },
];

const getConditionBadge = (remarks: string | null) => {
    if (!remarks) return 'default';
    const lower = remarks.toLowerCase();
    if (lower.includes('disposal')) return 'default';
    if (lower.includes('not working') || lower.includes('broken') || lower.includes('damage')) return 'danger';
    if (lower.includes('repair')) return 'warning';
    return 'success';
};

const getConditionLabel = (remarks: string | null) => {
    if (!remarks) return 'N/A';
    const lower = remarks.toLowerCase();
    if (lower.includes('disposal')) return 'For Disposal';
    if (lower.includes('not working')) return 'Not Working';
    if (lower.includes('broken')) return 'Broken';
    if (lower.includes('damage')) return 'Damaged';
    if (lower.includes('repair')) return 'Needs Repair';
    if (lower.includes('good') || lower.includes('working')) return 'Good/Working';
    return remarks.slice(0, 20);
};

const getLabBadge = (lab: string) => {
    return lab === 'chemistry' ? 'info' : 'warning';
};

const getCategoryBadge = (category: string) => {
    return category === 'pasco' ? 'success' : 'default';
};

const applyFilters = () => {
    router.get(route('admin.lab-inventory.index'), {
        search: search.value,
        lab: labFilter.value,
        category: categoryFilter.value,
        condition: conditionFilter.value,
        storage_location: locationFilter.value,
    }, { preserveState: true });
};

const clearFilters = () => {
    search.value = '';
    labFilter.value = '';
    categoryFilter.value = '';
    conditionFilter.value = '';
    locationFilter.value = '';
    router.get(route('admin.lab-inventory.index'));
};

const showDeleteModal = ref(false);
const deleteTargetId = ref<number | null>(null);
const deleteItem = (id: number) => { deleteTargetId.value = id; showDeleteModal.value = true; };
const doDelete = () => { router.delete(route('admin.lab-inventory.destroy', deleteTargetId.value!)); showDeleteModal.value = false; };

// Search as you type — clearing the box shows everything again.
useLiveSearch(search, () => applyFilters());
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-800">Laboratory Inventory</h1>
                    <p class="text-xs text-gray-500 mt-0.5">Max Suniel Carmen, Cagayan de Oro City — S.Y. 2024-2025</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('admin.lab-inventory.dashboard')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors"
                    >
                        Dashboard
                    </Link>
                    <Link
                        :href="route('admin.lab-inventory.create')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Item
                    </Link>
                </div>
            </div>
        </template>

        <!-- Stats Cards -->
        <div class="mb-6 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 border-l-4 border-l-green-500">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Total</p>
                <p class="text-2xl font-bold text-green-600">{{ stats.total_items }}</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 border-l-4 border-l-blue-400">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Chemistry</p>
                <p class="text-2xl font-bold text-blue-600">{{ stats.chemistry_items }}</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 border-l-4 border-l-orange-400">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Physics</p>
                <p class="text-2xl font-bold text-orange-600">{{ stats.physics_items }}</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 border-l-4 border-l-gray-400">
                <p class="text-xs text-gray-400 uppercase tracking-wide">General</p>
                <p class="text-2xl font-bold text-gray-600">{{ stats.general_items }}</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 border-l-4 border-l-emerald-400">
                <p class="text-xs text-gray-400 uppercase tracking-wide">PASCO</p>
                <p class="text-2xl font-bold text-emerald-600">{{ stats.pasco_items }}</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 border-l-4 border-l-purple-400">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Iligan</p>
                <p class="text-2xl font-bold text-purple-600">{{ stats.transferred_iligan }}</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 border-l-4 border-l-pink-400">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Butuan</p>
                <p class="text-2xl font-bold text-pink-600">{{ stats.transferred_butuan }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-6 rounded-xl border border-gray-100 bg-white shadow-sm p-4">
            <div class="flex flex-wrap gap-3 items-end">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search description, item #, location, brand..."
                        class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                        @keyup.enter="applyFilters"
                    />
                </div>
                <select v-model="labFilter" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20" @change="applyFilters">
                    <option value="">All Labs</option>
                    <option value="chemistry">Chemistry</option>
                    <option value="physics">Physics</option>
                </select>
                <select v-model="categoryFilter" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20" @change="applyFilters">
                    <option value="">All Categories</option>
                    <option value="general">General</option>
                    <option value="pasco">PASCO</option>
                </select>
                <select v-model="conditionFilter" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20" @change="applyFilters">
                    <option value="">All Conditions</option>
                    <option value="good">Good/Working</option>
                    <option value="needs_repair">Needs Repair</option>
                    <option value="defective">Defective/Broken</option>
                    <option value="for_disposal">For Disposal</option>
                </select>
                <select v-model="locationFilter" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20" @change="applyFilters">
                    <option value="">All Locations</option>
                    <option v-for="loc in storageLocations" :key="loc" :value="loc">{{ loc }}</option>
                </select>
                <button @click="clearFilters" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">
                    Clear
                </button>
            </div>
        </div>

        <!-- Data Table -->
        <div class="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Lab</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Item #</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Size</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Qty</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">UOM</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Brand</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Location</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Condition</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Transfers</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-50">
                    <tr
                        v-for="(item, index) in inventory.data"
                        :key="item.id"
                        :class="[
                            index % 2 === 0 ? 'bg-white' : 'bg-blue-50',
                            item.condition_remarks?.toLowerCase().includes('broken') || item.condition_remarks?.toLowerCase().includes('damage') ? '!bg-red-50' : '',
                            item.condition_remarks?.toLowerCase().includes('repair') ? '!bg-yellow-50' : '',
                            item.condition_remarks?.toLowerCase().includes('disposal') ? '!bg-gray-100' : '',
                        ]"
                    >
                        <td class="px-4 py-3 whitespace-nowrap">
                            <Badge :variant="getLabBadge(item.lab)">
                                {{ item.lab === 'chemistry' ? 'Chem' : 'Phys' }}
                            </Badge>
                            <Badge :variant="getCategoryBadge(item.category)" class="ml-1">
                                {{ item.category === 'pasco' ? 'PASCO' : 'Gen' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ item.item_no || '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <div class="font-medium">{{ item.description }}</div>
                            <div v-if="item.current_campus_location" class="text-xs text-purple-600 mt-1">
                                Currently at: {{ item.current_campus_location }}
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ item.size || '-' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-green-600">{{ item.quantity }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ item.uom || '-' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ item.brand || '-' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ item.storage_location || '-' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <Badge :variant="getConditionBadge(item.condition_remarks)">
                                {{ getConditionLabel(item.condition_remarks) }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <div v-if="item.qty_sent_iligan > 0" class="text-purple-600">
                                Iligan: {{ item.qty_sent_iligan }}
                            </div>
                            <div v-if="item.qty_sent_butuan > 0" class="text-pink-600">
                                Butuan: {{ item.qty_sent_butuan }}
                            </div>
                            <span v-if="!item.qty_sent_iligan && !item.qty_sent_butuan" class="text-gray-400">-</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <div class="flex space-x-2">
                                <Link :href="route('admin.lab-inventory.show', item.id)" class="text-gray-600 hover:text-gray-900">
                                    View
                                </Link>
                                <Link :href="route('admin.lab-inventory.edit', item.id)" class="text-green-600 hover:text-green-900">
                                    Edit
                                </Link>
                                <button @click="deleteItem(item.id)" class="text-red-600 hover:text-red-900">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="inventory.data.length === 0">
                        <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                            No inventory items found.
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-100 sm:px-6">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        Showing {{ (inventory.current_page - 1) * inventory.per_page + 1 }}–{{ Math.min(inventory.current_page * inventory.per_page, inventory.total) }} of {{ inventory.total }} items
                    </div>
                    <div class="flex gap-1">
                        <template v-for="link in inventory.links" :key="link.label">
                            <Link v-if="link.url" :href="link.url"
                                :class="['inline-flex items-center justify-center min-w-[32px] h-8 rounded-lg text-sm border transition-colors', link.active ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50']"
                                v-html="link.label" />
                            <span v-else class="inline-flex items-center justify-center min-w-[32px] h-8 rounded-lg text-sm border border-gray-100 text-gray-400" v-html="link.label" />
                        </template>
                    </div>
                </div>
            </div>
        </div>
        <ConfirmModal :show="showDeleteModal" title="Delete Inventory Item" message="Are you sure you want to delete this inventory item?" confirmLabel="Yes, Delete" confirmClass="bg-red-600 hover:bg-red-700" @confirmed="doDelete" @cancelled="showDeleteModal = false" />
    </AdminLayout>
</template>
