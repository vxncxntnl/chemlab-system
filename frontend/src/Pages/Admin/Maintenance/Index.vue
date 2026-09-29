<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    maintenanceLogs: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
}>();

const columns = [
    { key: 'equipment.name', label: 'Equipment' },
    { key: 'type', label: 'Type' },
    { key: 'maintenance_date', label: 'Date' },
    { key: 'status', label: 'Status' },
    { key: 'cost', label: 'Cost' },
];

type BadgeVariant = 'success' | 'warning' | 'info' | 'default' | 'danger' | 'primary';

const getStatusBadge = (status: string): BadgeVariant => {
    const variants: Record<string, BadgeVariant> = {
        scheduled: 'warning',
        in_progress: 'primary',
        completed: 'success',
        cancelled: 'danger',
    };
    return variants[status] || 'default';
};

const getTypeBadge = (type: string): BadgeVariant => {
    const variants: Record<string, BadgeVariant> = {
        routine: 'primary',
        repair: 'warning',
        inspection: 'primary',
        calibration: 'default',
        replacement: 'danger',
    };
    return variants[type] || 'default';
};

const showDeleteModal = ref(false);
const deleteTargetId = ref<number | null>(null);
const deleteMaintenance = (id: number) => { deleteTargetId.value = id; showDeleteModal.value = true; };
const doDelete = () => { router.delete(route('admin.maintenance.destroy', deleteTargetId.value!)); showDeleteModal.value = false; };
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Maintenance Logs</h1>
                <Link
                    :href="route('admin.maintenance.create')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Maintenance Log
                </Link>
            </div>
        </template>

        <DataTable :columns="columns" :data="maintenanceLogs.data" :pagination="maintenanceLogs">
            <template #cell-equipment.name="{ row }">
                {{ row.equipment?.name || 'N/A' }}
            </template>
            <template #cell-type="{ value }">
                <Badge :variant="getTypeBadge(value)" class="capitalize">{{ value }}</Badge>
            </template>
            <template #cell-maintenance_date="{ value }">
                {{ new Date(value).toLocaleDateString() }}
            </template>
            <template #cell-status="{ value }">
                <Badge :variant="getStatusBadge(value)" class="capitalize">{{ value?.replace(/_/g, ' ') }}</Badge>
            </template>
            <template #cell-cost="{ value }">
                {{ value ? `₱${parseFloat(value).toLocaleString()}` : '-' }}
            </template>
            <template #actions="{ row }">
                <div class="flex space-x-2">
                    <Link :href="route('admin.maintenance.show', row.id)" class="text-green-600 hover:text-green-900">
                        View
                    </Link>
                    <Link :href="route('admin.maintenance.edit', row.id)" class="text-yellow-600 hover:text-yellow-900">
                        Edit
                    </Link>
                    <button @click="deleteMaintenance(row.id)" class="text-red-600 hover:text-red-900">
                        Delete
                    </button>
                </div>
            </template>
        </DataTable>
        <ConfirmModal :show="showDeleteModal" title="Delete Maintenance Log" message="Are you sure you want to delete this maintenance log?" confirmLabel="Yes, Delete" confirmClass="bg-red-600 hover:bg-red-700" @confirmed="doDelete" @cancelled="showDeleteModal = false" />
    </AdminLayout>
</template>
