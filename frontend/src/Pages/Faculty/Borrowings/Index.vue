<script setup lang="ts">
import { formatDateTime } from '@/utils/date';
import UserLayout from '@/Layouts/UserLayout.vue';
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
    };
}>();

const statusFilter = ref('');

const columns = [
    { key: 'borrower', label: 'Borrower' },
    { key: 'equipment', label: 'Equipment' },
    { key: 'created_at', label: 'Requested On' },
    { key: 'borrow_date', label: 'Borrow Date' },
    { key: 'return_date', label: 'Return Date' },
    { key: 'status', label: 'Status' },
];

const formatDate = (date: string) => new Date(date).toLocaleDateString();

const applyFilters = () => {
    router.get(route('faculty.borrowings.index'), {
        status: statusFilter.value,
    }, { preserveState: true });
};

</script>

<template>
    <UserLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Borrowing Approvals</h1>
        </template>

        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
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
            </select>
            <Link
                :href="route('faculty.borrowings.create')"
                class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                New Request
            </Link>
        </div>

        <DataTable :columns="columns" :data="borrowings.data" :pagination="borrowings">
            <template #empty>
                <EmptyState
                    icon="clipboard"
                    title="No borrowings yet"
                    description="You haven't made any borrowing requests. Submit a request to borrow equipment."
                    actionLabel="New Borrowing Request"
                    :actionHref="route('faculty.borrowings.create')"
                />
            </template>
            <template #cell-borrower="{ row }">
                <div class="text-sm text-gray-900">{{ row.user?.name ?? '—' }}</div>
            </template>
            <template #cell-equipment="{ row }">
                <div class="text-sm text-gray-900">
                    {{ row.equipment?.map((e: any) => e.name).join(', ') || 'Multiple items' }}
                </div>
            </template>
            <template #cell-created_at="{ value }">
                <span class="whitespace-nowrap text-xs text-gray-700">{{ formatDateTime(value) }}</span>
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
                <div class="flex space-x-2">
                    <Link :href="route('faculty.borrowings.show', row.id)" class="text-green-600 hover:text-green-900">
                        View
                    </Link>
                </div>
            </template>
        </DataTable>
    </UserLayout>
</template>
