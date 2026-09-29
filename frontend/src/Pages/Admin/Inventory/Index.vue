<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import Modal from '@/Components/Modal.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    inventory: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
    campuses: Array<{ id: number; campus_name: string }>;
    filters: {
        campus_id?: number;
        low_stock?: boolean;
    };
}>();

const showEditModal = ref(false);
const editingItem = ref<any>(null);

const form = useForm({
    total_quantity: 0,
    available_quantity: 0,
    reorder_level: 0,
});

const columns = [
    { key: 'equipment', label: 'Equipment' },
    { key: 'campus', label: 'Campus' },
    { key: 'location', label: 'Location' },
    { key: 'quantity', label: 'Quantity' },
    { key: 'status', label: 'Status' },
];

const openEditModal = (item: any) => {
    editingItem.value = item;
    form.total_quantity = item.total_quantity;
    form.available_quantity = item.available_quantity;
    form.reorder_level = item.reorder_level;
    showEditModal.value = true;
};

const updateInventory = () => {
    form.put(route('admin.inventory.update', editingItem.value.id), {
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
};

const applyFilters = (campusId: string, lowStock: boolean) => {
    router.get(route('admin.inventory.index'), {
        campus_id: campusId,
        low_stock: lowStock ? '1' : '',
    }, { preserveState: true });
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Inventory Management</h1>
        </template>

        <div class="mb-6 flex flex-col sm:flex-row gap-4">
            <select
                class="rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                @change="(e) => applyFilters((e.target as HTMLSelectElement).value, false)"
            >
                <option value="">All Campuses</option>
                <option v-for="campus in campuses" :key="campus.id" :value="campus.id">
                    {{ campus.campus_name }}
                </option>
            </select>
            <label class="flex items-center">
                <input
                    type="checkbox"
                    class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500"
                    @change="(e) => applyFilters('', (e.target as HTMLInputElement).checked)"
                />
                <span class="ml-2 text-sm text-gray-600">Show low stock only</span>
            </label>
        </div>

        <DataTable :columns="columns" :data="inventory.data" :pagination="inventory">
            <template #cell-equipment="{ row }">
                <div class="text-sm font-medium text-gray-900">{{ row.equipment?.name }}</div>
            </template>
            <template #cell-campus="{ row }">
                {{ row.campus?.campus_name }}
            </template>
            <template #cell-location="{ row }">
                {{ row.location?.location_name }}
            </template>
            <template #cell-quantity="{ row }">
                <span :class="row.available_quantity <= row.reorder_level ? 'text-red-600 font-medium' : ''">
                    {{ row.available_quantity }}
                </span>
                / {{ row.total_quantity }}
            </template>
            <template #cell-status="{ row }">
                <Badge :variant="row.available_quantity <= row.reorder_level ? 'danger' : 'success'">
                    {{ row.available_quantity <= row.reorder_level ? 'Low Stock' : 'OK' }}
                </Badge>
            </template>
            <template #actions="{ row }">
                <button @click="openEditModal(row)" class="text-green-600 hover:text-green-900">
                    Edit
                </button>
            </template>
        </DataTable>

        <!-- Edit Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Update Inventory</h3>
                <form @submit.prevent="updateInventory" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Total Quantity</label>
                        <input
                            v-model="form.total_quantity"
                            type="number"
                            min="0"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Available Quantity</label>
                        <input
                            v-model="form.available_quantity"
                            type="number"
                            min="0"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Reorder Level</label>
                        <input
                            v-model="form.reorder_level"
                            type="number"
                            min="0"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        />
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="showEditModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                        >
                            Update
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
