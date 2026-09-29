<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    equipment: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
    categories: Array<string>;
    filters: {
        search?: string;
        category?: string;
    };
}>();

const search = ref('');
const categoryFilter = ref('');

const columns = [
    { key: 'equipment_code', label: 'Equipment ID' },
    { key: 'name', label: 'Name' },
    { key: 'category', label: 'Category' },
    { key: 'serial_number', label: 'Serial #' },
    { key: 'condition', label: 'Condition' },
    { key: 'is_available', label: 'Status' },
];

const getConditionBadge = (condition: string) => {
    const variants: Record<string, any> = {
        new: 'success',
        good: 'info',
        fair: 'warning',
        poor: 'warning',
        damaged: 'danger',
    };
    return variants[condition] || 'default';
};

const applyFilters = () => {
    router.get(route('admin.equipment.index'), {
        search: search.value,
        category: categoryFilter.value,
    }, { preserveState: true });
};

const showDeleteModal = ref(false);
const deleteTargetId = ref<number | null>(null);
const deleteEquipment = (id: number) => { deleteTargetId.value = id; showDeleteModal.value = true; };
const doDelete = () => { router.delete(route('admin.equipment.destroy', deleteTargetId.value!)); showDeleteModal.value = false; };
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Equipment</h1>
                <Link
                    :href="route('admin.equipment.create')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Equipment
                </Link>
            </div>
        </template>

        <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="relative flex-1 max-w-xs">
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
                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
            </select>
        </div>

        <DataTable :columns="columns" :data="equipment.data" :pagination="equipment">
            <template #cell-equipment_code="{ value }">
                <span class="text-sm font-mono text-gray-600">{{ value }}</span>
            </template>
            <template #cell-serial_number="{ value }">
                <span :class="value ? 'text-gray-700' : 'text-gray-400 italic'">{{ value || 'N/A' }}</span>
            </template>
            <template #cell-name="{ row }">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100 border border-gray-200">
                        <img
                            v-if="row.image_path"
                            :src="`/storage/${row.image_path}`"
                            :alt="row.name"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="h-full w-full flex items-center justify-center">
                            <svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ row.name }}</div>
                        <div class="text-xs text-gray-400">{{ row.description?.slice(0, 50) }}</div>
                    </div>
                </div>
            </template>
            <template #cell-condition="{ value }">
                <Badge :variant="getConditionBadge(value)">{{ value }}</Badge>
            </template>
            <template #cell-is_available="{ value }">
                <Badge :variant="value ? 'success' : 'danger'">
                    {{ value ? 'Available' : 'Unavailable' }}
                </Badge>
            </template>
            <template #actions="{ row }">
                <div class="flex space-x-2">
                    <Link :href="route('admin.equipment.show', row.id)" class="text-gray-600 hover:text-gray-900">
                        View
                    </Link>
                    <Link :href="route('admin.equipment.edit', row.id)" class="text-green-600 hover:text-green-900">
                        Edit
                    </Link>
                    <button @click="deleteEquipment(row.id)" class="text-red-600 hover:text-red-900">
                        Delete
                    </button>
                </div>
            </template>
        </DataTable>
        <ConfirmModal :show="showDeleteModal" title="Delete Equipment" message="Are you sure you want to delete this equipment?" confirmLabel="Yes, Delete" confirmClass="bg-red-600 hover:bg-red-700" @confirmed="doDelete" @cancelled="showDeleteModal = false" />
    </AdminLayout>
</template>
