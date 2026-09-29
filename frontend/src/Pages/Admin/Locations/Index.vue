<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    locations: {
        data: Array<{
            id: number;
            location_name: string;
            description: string | null;
            campus_id: number;
            campus: {
                id: number;
                campus_name: string;
            };
            created_at: string;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
    campuses: Array<{
        id: number;
        campus_name: string;
    }>;
    filters: {
        campus_id?: string;
    };
}>();

const campusFilter = ref(props.filters.campus_id || '');

const columns = [
    { key: 'location_name', label: 'Location Name' },
    { key: 'campus', label: 'Campus' },
    { key: 'description', label: 'Description' },
    { key: 'created_at', label: 'Created' },
];

const applyFilters = () => {
    router.get(route('admin.locations.index'), {
        campus_id: campusFilter.value,
    }, { preserveState: true });
};

const showDeleteModal = ref(false);
const deleteTargetId = ref<number | null>(null);
const deleteLocation = (id: number) => { deleteTargetId.value = id; showDeleteModal.value = true; };
const doDelete = () => { router.delete(route('admin.locations.destroy', deleteTargetId.value!)); showDeleteModal.value = false; };
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Locations</h1>
        </template>

        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex flex-col sm:flex-row gap-4">
                <select
                    v-model="campusFilter"
                    class="rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                    @change="applyFilters"
                >
                    <option value="">All Campuses</option>
                    <option v-for="campus in campuses" :key="campus.id" :value="campus.id">
                        {{ campus.campus_name }}
                    </option>
                </select>
            </div>
            <Link
                :href="route('admin.locations.create')"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
            >
                Add Location
            </Link>
        </div>

        <DataTable :columns="columns" :data="locations.data" :pagination="locations">
            <template #cell-campus="{ row }">
                <span class="font-medium text-gray-900">{{ row.campus?.campus_name }}</span>
            </template>
            <template #cell-description="{ value }">
                <span class="text-gray-600">{{ value || '-' }}</span>
            </template>
            <template #cell-created_at="{ value }">
                {{ new Date(value).toLocaleDateString() }}
            </template>
            <template #actions="{ row }">
                <div class="flex space-x-2">
                    <Link :href="route('admin.locations.edit', row.id)" class="text-green-600 hover:text-green-900">
                        Edit
                    </Link>
                    <button @click="deleteLocation(row.id)" class="text-red-600 hover:text-red-900">
                        Delete
                    </button>
                </div>
            </template>
        </DataTable>
        <ConfirmModal :show="showDeleteModal" title="Delete Location" message="Are you sure you want to delete this location?" confirmLabel="Yes, Delete" confirmClass="bg-red-600 hover:bg-red-700" @confirmed="doDelete" @cancelled="showDeleteModal = false" />
    </AdminLayout>
</template>
