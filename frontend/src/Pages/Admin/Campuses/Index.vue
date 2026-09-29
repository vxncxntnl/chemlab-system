<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    campuses: {
        data: Array<{
            id: number;
            campus_name: string;
            address: string | null;
            contact_info: string | null;
            locations_count: number;
            created_at: string;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
}>();

const search = ref('');

const columns = [
    { key: 'campus_name', label: 'Campus Name' },
    { key: 'address', label: 'Address' },
    { key: 'contact_info', label: 'Contact Info' },
    { key: 'locations_count', label: 'Locations' },
    { key: 'created_at', label: 'Created' },
];

const showDeleteModal = ref(false);
const deleteTargetId = ref<number | null>(null);
const deleteCampus = (id: number) => { deleteTargetId.value = id; showDeleteModal.value = true; };
const doDelete = () => { router.delete(route('admin.campuses.destroy', deleteTargetId.value!)); showDeleteModal.value = false; };
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Campuses</h1>
                <Link
                    :href="route('admin.campuses.create')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Campus
                </Link>
            </div>
        </template>

        <DataTable :columns="columns" :data="campuses.data" :pagination="campuses">
            <template #cell-address="{ value }">
                <span class="text-gray-600">{{ value || '-' }}</span>
            </template>
            <template #cell-contact_info="{ value }">
                <span class="text-gray-600">{{ value || '-' }}</span>
            </template>
            <template #cell-locations_count="{ value }">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ value }} locations
                </span>
            </template>
            <template #cell-created_at="{ value }">
                {{ new Date(value).toLocaleDateString() }}
            </template>
            <template #actions="{ row }">
                <div class="flex space-x-2">
                    <Link :href="route('admin.campuses.edit', row.id)" class="text-green-600 hover:text-green-900">
                        Edit
                    </Link>
                    <button @click="deleteCampus(row.id)" class="text-red-600 hover:text-red-900">
                        Delete
                    </button>
                </div>
            </template>
        </DataTable>
        <ConfirmModal :show="showDeleteModal" title="Delete Campus" message="Are you sure? This will also delete all associated locations." confirmLabel="Yes, Delete" confirmClass="bg-red-600 hover:bg-red-700" @confirmed="doDelete" @cancelled="showDeleteModal = false" />
    </AdminLayout>
</template>
