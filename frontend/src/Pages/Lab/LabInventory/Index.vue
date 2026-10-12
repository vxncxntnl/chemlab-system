<script setup lang="ts">
import LabLayout from '@/Layouts/LabLayout.vue';
import { useLiveSearch } from '@/composables/useLiveSearch';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    inventory: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        links: Array<any>;
        total: number;
    };
    stats: {
        total_items: number;
        chemistry_items: number;
        physics_items: number;
        low_stock: number;
        out_of_stock: number;
    };
    filters: { search?: string; lab?: string };
}>();

const search = ref(props.filters.search || '');
const labFilter = ref(props.filters.lab || '');
const editingId = ref<number | null>(null);

const editForm = useForm({
    quantity: 0,
    condition_remarks: '',
});

const applyFilters = () => {
    router.get(route('lab.lab-inventory.index'), {
        search: search.value,
        lab: labFilter.value,
    }, { preserveState: true });
};

const startEdit = (item: any) => {
    editingId.value = item.id;
    editForm.quantity = item.quantity;
    editForm.condition_remarks = item.condition_remarks || '';
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
};

const saveEdit = (item: any) => {
    editForm.put(route('lab.lab-inventory.update', item.id), {
        onSuccess: () => {
            editingId.value = null;
        },
    });
};

// Search as you type — clearing the box shows everything again.
useLiveSearch(search, () => applyFilters());
</script>

<template>
    <LabLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Lab Inventory</h1>
        </template>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5 mb-6">
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-gray-800">{{ stats.total_items }}</p>
                <p class="text-xs text-gray-400 mt-1 uppercase tracking-wide">Total</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ stats.chemistry_items }}</p>
                <p class="text-xs text-gray-400 mt-1 uppercase tracking-wide">Chemistry</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-purple-600">{{ stats.physics_items }}</p>
                <p class="text-xs text-gray-400 mt-1 uppercase tracking-wide">Physics</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-amber-500">{{ stats.low_stock }}</p>
                <p class="text-xs text-gray-400 mt-1 uppercase tracking-wide">Low Stock</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-red-500">{{ stats.out_of_stock }}</p>
                <p class="text-xs text-gray-400 mt-1 uppercase tracking-wide">Out of Stock</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name, item no, or brand..."
                    class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                    @keyup.enter="applyFilters"
                />
            </div>
            <select
                v-model="labFilter"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                @change="applyFilters"
            >
                <option value="">All Labs</option>
                <option value="chemistry">Chemistry</option>
                <option value="physics">Physics</option>
            </select>
        </div>

        <!-- Table -->
        <div class="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
            <p class="sm:hidden px-4 pt-3 text-xs text-gray-400">◂ Swipe to see more ▸</p>
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Item No.</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Lab</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Brand</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Qty</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Condition</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Storage</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 bg-white">
                    <tr v-if="inventory.data.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500 text-sm">No inventory items found.</td>
                    </tr>
                    <tr v-for="item in inventory.data" :key="item.id" :class="item.quantity === 0 ? 'bg-red-50' : item.quantity <= 5 ? 'bg-yellow-50' : ''">
                        <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ item.item_no || '—' }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ item.description }}</td>
                        <td class="px-4 py-3">
                            <span :class="item.lab === 'chemistry' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'" class="px-2 py-0.5 rounded text-xs font-medium capitalize">
                                {{ item.lab }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ item.brand || '—' }}</td>

                        <!-- Editable qty & condition -->
                        <template v-if="editingId === item.id">
                            <td class="px-4 py-3">
                                <input
                                    v-model="editForm.quantity"
                                    type="number"
                                    min="0"
                                    class="w-20 rounded border-gray-300 text-sm focus:ring-blue-500"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <input
                                    v-model="editForm.condition_remarks"
                                    type="text"
                                    class="w-36 rounded border-gray-300 text-sm focus:ring-blue-500"
                                    placeholder="e.g. Good"
                                />
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ item.storage_location || '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm flex gap-2">
                                <button @click="saveEdit(item)" :disabled="editForm.processing" class="text-green-700 hover:text-green-900 font-medium">Save</button>
                                <button @click="cancelEdit" class="text-gray-500 hover:text-gray-700">Cancel</button>
                            </td>
                        </template>
                        <template v-else>
                            <td class="px-4 py-3 text-sm">
                                <span :class="item.quantity === 0 ? 'text-red-600 font-bold' : item.quantity <= 5 ? 'text-yellow-700 font-semibold' : 'text-gray-900'">
                                    {{ item.quantity }}
                                </span>
                                <span class="text-gray-400 text-xs ml-1">{{ item.uom }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ item.condition_remarks || '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ item.storage_location || '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <button @click="startEdit(item)" class="text-blue-600 hover:text-blue-900 font-medium">Update</button>
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="inventory.last_page > 1" class="mt-4 flex justify-center gap-1">
            <a
                v-for="link in inventory.links"
                :key="link.label"
                :href="link.url || '#'"
                :class="[
                    'px-3 py-1 text-sm rounded',
                    link.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100',
                    !link.url ? 'opacity-40 cursor-not-allowed pointer-events-none' : ''
                ]"
                v-html="link.label"
            />
        </div>
    </LabLayout>
</template>
