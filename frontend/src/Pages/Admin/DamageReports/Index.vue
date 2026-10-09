<script setup lang="ts">
import { formatDateTime } from '@/utils/date';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    reports: {
        data: Array<{
            id: number;
            borrowing_id: number;
            equipment_id: number;
            reported_by: number;
            description: string;
            status: string;
            date_reported: string;
            resolved_date: string | null;
            borrowing: {
                id: number;
                user: {
                    id: number;
                    name: string;
                };
            };
            equipment: {
                id: number;
                name: string;
            };
            reporter: {
                id: number;
                name: string;
            };
            created_at: string;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
    filters: {
        status?: string;
    };
}>();

const statusFilter = ref(props.filters.status || '');

const columns = [
    { key: 'equipment', label: 'Equipment' },
    { key: 'borrower', label: 'Borrower' },
    { key: 'reporter', label: 'Reported By' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Date & Time Reported' },
];

const getStatusBadge = (status: string) => {
    const variants: Record<string, 'warning' | 'primary' | 'success' | 'danger'> = {
        pending: 'warning',
        assessed: 'primary',
        resolved: 'success',
        rejected: 'danger',
    };
    return variants[status] || 'default';
};

const applyFilters = () => {
    router.get(route('admin.damage-reports.index'), {
        status: statusFilter.value,
    }, { preserveState: true });
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Damage Reports</h1>
        </template>

        <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-3">
            <select
                v-model="statusFilter"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                @change="applyFilters"
            >
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="assessed">Assessed</option>
                <option value="resolved">Resolved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <DataTable :columns="columns" :data="reports.data" :pagination="reports">
            <template #cell-equipment="{ row }">
                <span class="font-medium text-gray-900">{{ row.equipment?.name }}</span>
            </template>
            <template #cell-borrower="{ row }">
                {{ row.borrowing?.user?.name }}
            </template>
            <template #cell-reporter="{ row }">
                {{ row.reporter?.name }}
            </template>
            <template #cell-status="{ value }">
                <Badge :variant="getStatusBadge(value)" class="capitalize">{{ value?.replace(/_/g, ' ') }}</Badge>
            </template>
            <template #cell-created_at="{ value }">
                {{ formatDateTime(value) }}
            </template>
            <template #actions="{ row }">
                <Link :href="route('admin.damage-reports.show', row.id)" class="text-green-600 hover:text-green-900">
                    View
                </Link>
            </template>
        </DataTable>
    </AdminLayout>
</template>
