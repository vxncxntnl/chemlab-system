<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useBorrowingStatus } from '@/composables/useBorrowingStatus';

const { formatStatus, statusVariant } = useBorrowingStatus();

defineProps<{
    borrowings: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
    filters: {
        status?: string;
        search?: string;
    };
}>();

const search = ref('');
const statusFilter = ref('');

const columns = [
    { key: 'user', label: 'Borrower' },
    { key: 'faculty', label: 'Faculty / Instructor' },
    { key: 'borrow_date', label: 'Borrow Date' },
    { key: 'return_date', label: 'Return Date' },
    { key: 'status', label: 'Status' },
];

const applyFilters = () => {
    router.get(route('admin.borrowings.index'), {
        search: search.value,
        status: statusFilter.value,
    }, { preserveState: true });
};

const formatDate = (date: string) => new Date(date).toLocaleDateString();
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Borrowings</h1>
        </template>

        <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="relative flex-1 max-w-xs">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by borrower..."
                    class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                    @keyup.enter="applyFilters"
                />
            </div>
            <select
                v-model="statusFilter"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                @change="applyFilters"
            >
                <option value="">All Status</option>
                <option value="pending_faculty">Pending (Faculty)</option>
                <option value="pending_admin">Pending (Admin)</option>
                <option value="approved">Approved</option>
                <option value="borrowed">Borrowed</option>
                <option value="returned">Returned</option>
                <option value="overdue">Overdue</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <DataTable :columns="columns" :data="borrowings.data" :pagination="borrowings">
            <template #empty>
                <EmptyState
                    icon="clipboard"
                    title="No borrowing requests"
                    description="There are no borrowing requests matching your filters."
                />
            </template>
            <template #cell-user="{ row }">
                <div>
                    <div class="text-sm font-medium text-gray-900">{{ row.user?.name }}</div>
                    <div class="text-sm text-gray-500">{{ row.user?.email }}</div>
                </div>
            </template>
            <template #cell-faculty="{ row }">
                {{ row.faculty?.name ?? '—' }}
            </template>
            <template #cell-borrow_date="{ value }">
                {{ formatDate(value) }}
            </template>
            <template #cell-return_date="{ value }">
                {{ formatDate(value) }}
            </template>
            <template #cell-status="{ value }">
                <Badge :variant="statusVariant(value)">{{ formatStatus(value) }}</Badge>
            </template>
            <template #actions="{ row }">
                <Link :href="route('admin.borrowings.show', row.id)" class="text-green-600 hover:text-green-900">
                    View Details
                </Link>
            </template>
        </DataTable>
    </AdminLayout>
</template>
