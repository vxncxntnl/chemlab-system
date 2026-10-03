<script setup lang="ts">
import LabLayout from '@/Layouts/LabLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Link } from '@inertiajs/vue3';
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

const columns = [
    { key: 'user', label: 'Borrower' },
    { key: 'faculty', label: 'Faculty / Instructor' },
    { key: 'borrow_date', label: 'Borrow Date' },
    { key: 'return_date', label: 'Return Date' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <LabLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Lab Borrowings</h1>
                <Link
                    :href="route('lab.borrowings.create')"
                    class="inline-flex items-center rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                >
                    New Request
                </Link>
            </div>
        </template>

        <DataTable :columns="columns" :data="borrowings.data" :pagination="borrowings">
            <template #empty>
                <EmptyState
                    icon="clipboard"
                    title="No borrowing requests"
                    description="There are no borrowing requests to review at this time."
                />
            </template>
            <template #cell-user="{ row }">
                <div>
                    <div class="text-sm font-medium text-gray-900">{{ row.user?.name }}</div>
                    <div class="text-sm text-gray-500">{{ row.user?.student_id }}</div>
                </div>
            </template>

            <template #cell-faculty="{ row }">
                {{ row.faculty?.name ?? '—' }}
            </template>

            <template #cell-borrow_date="{ value }">
                {{ new Date(value).toLocaleDateString() }}
            </template>

            <template #cell-return_date="{ value }">
                {{ new Date(value).toLocaleDateString() }}
            </template>

            <template #cell-status="{ value }">
                <Badge :variant="statusVariant(value)">{{ formatStatus(value) }}</Badge>
            </template>


            <template #actions="{ row }">
                <div class="flex space-x-2">
                    <Link :href="route('lab.borrowings.show', row.id)" class="text-green-600 hover:text-green-900">
                        View
                    </Link>
                </div>
            </template>
        </DataTable>
    </LabLayout>
</template>

