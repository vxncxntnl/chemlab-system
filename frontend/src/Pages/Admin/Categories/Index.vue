<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    categories: {
        data: Array<{
            id: number;
            name: string;
            description: string | null;
            is_active: boolean;
            equipment_count: number;
            created_at: string;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
}>();

const columns = [
    { key: 'name', label: 'Category Name' },
    { key: 'description', label: 'Description' },
    { key: 'equipment_count', label: 'Equipment' },
    { key: 'is_active', label: 'Status' },
    { key: 'created_at', label: 'Created' },
];

const showDeleteModal = ref(false);
const deleteTargetId = ref<number | null>(null);
const deleteCategory = (id: number) => { deleteTargetId.value = id; showDeleteModal.value = true; };
const doDelete = () => { router.delete(route('admin.categories.destroy', deleteTargetId.value!)); showDeleteModal.value = false; };
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Equipment Categories</h1>
                <Link
                    :href="route('admin.categories.create')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Category
                </Link>
            </div>
        </template>

        <DataTable :columns="columns" :data="categories.data" :pagination="categories">
            <template #cell-name="{ value }">
                <span class="font-medium text-gray-900">{{ value }}</span>
            </template>
            <template #cell-description="{ value }">
                <span class="text-gray-600">{{ value || '-' }}</span>
            </template>
            <template #cell-equipment_count="{ value }">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ value }} items
                </span>
            </template>
            <template #cell-is_active="{ value }">
                <Badge :variant="value ? 'success' : 'danger'">
                    {{ value ? 'Active' : 'Inactive' }}
                </Badge>
            </template>
            <template #cell-created_at="{ value }">
                {{ new Date(value).toLocaleDateString() }}
            </template>
            <template #actions="{ row }">
                <div class="flex space-x-2">
                    <Link :href="route('admin.categories.edit', row.id)" class="text-green-600 hover:text-green-900">
                        Edit
                    </Link>
                    <button
                        v-if="row.equipment_count === 0"
                        @click="deleteCategory(row.id)"
                        class="text-red-600 hover:text-red-900"
                    >
                        Delete
                    </button>
                    <span v-else class="text-gray-400 cursor-not-allowed" title="Cannot delete category with equipment">
                        Delete
                    </span>
                </div>
            </template>
        </DataTable>
        <ConfirmModal :show="showDeleteModal" title="Delete Category" message="Are you sure you want to delete this category?" confirmLabel="Yes, Delete" confirmClass="bg-red-600 hover:bg-red-700" @confirmed="doDelete" @cancelled="showDeleteModal = false" />
    </AdminLayout>
</template>
