<script setup lang="ts">
import LabLayout from '@/Layouts/LabLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    reports: {
        data: Array<any>;
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
    { key: 'created_at', label: 'Date Reported' },
];

const getStatusBadge = (status: string) => {
    const variants: Record<string, any> = {
        pending: 'warning',
        assessed: 'primary',
        resolved: 'success',
    };
    return variants[status] || 'default';
};

const applyFilters = () => {
    router.get(route('lab.damage-reports.index'), {
        status: statusFilter.value,
    }, { preserveState: true });
};
</script>

<template>
    <LabLayout>
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
                {{ new Date(value).toLocaleDateString() }}
            </template>
            <template #actions="{ row }">
                <Link :href="route('lab.damage-reports.show', row.id)" class="text-green-600 hover:text-green-900">
                    View
                </Link>
            </template>
        </DataTable>
    </LabLayout>
</template>

