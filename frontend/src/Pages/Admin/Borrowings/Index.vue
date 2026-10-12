<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useLiveSearch } from '@/composables/useLiveSearch';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { formatDateTime } from '@/utils/date';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useBorrowingStatus } from '@/composables/useBorrowingStatus';

const { formatStatus, statusVariant } = useBorrowingStatus();

const props = defineProps<{
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

const search = ref(props.filters?.search || '');
const statusFilter = ref('');

// A QR activity row stands for all of that activity's student requests:
// row.group = { requests, students, need_approval, in_progress, unread }.
const columns = [
    { key: 'user', label: 'Borrower / Activity' },
    { key: 'faculty', label: 'Faculty / Instructor' },
    { key: 'source', label: 'Source' },
    { key: 'created_at', label: 'Requested On' },
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

// Search as you type — clearing the box shows everything again.
useLiveSearch(search, () => applyFilters());
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Borrowings</h1>
                <Link
                    :href="route('admin.borrowings.create')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    New Request (Walk-in)
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
                <!-- QR activity: one row for the whole class -->
                <div v-if="row.group">
                    <div class="text-sm font-semibold text-gray-900">{{ row.activity?.title }}</div>
                    <div class="text-sm text-gray-500">{{ row.group.students }} student{{ row.group.students === 1 ? '' : 's' }}</div>
                </div>
                <div v-else>
                    <div class="text-sm font-medium text-gray-900">{{ row.user?.name }}</div>
                    <div class="text-sm text-gray-500">{{ row.user?.email }}</div>
                </div>
            </template>
            <template #cell-faculty="{ row }">
                {{ (row.group ? row.activity?.instructor?.name : null) ?? row.faculty?.name ?? '—' }}
            </template>
            <template #cell-created_at="{ value, row }">
                <span class="whitespace-nowrap text-xs text-gray-700">{{ formatDateTime(value) }}</span>
                <div v-if="row.group" class="text-[11px] text-gray-400">latest request</div>
            </template>
            <template #cell-borrow_date="{ value }">
                {{ formatDate(value) }}
            </template>
            <template #cell-return_date="{ value }">
                {{ formatDate(value) }}
            </template>
            <template #cell-source="{ row }">
                <span v-if="row.group" class="rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">QR Activity</span>
                <span v-else class="text-xs text-gray-500">Walk-in / Direct Request</span>
            </template>
            <template #cell-status="{ value, row }">
                <!-- QR activity: one simple status for the whole group -->
                <div v-if="row.group" class="flex flex-wrap items-center gap-1.5">
                    <span v-if="row.group.need_approval" class="whitespace-nowrap rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-800">⚠ {{ row.group.need_approval }} need approval</span>
                    <span v-else-if="row.group.in_progress" class="whitespace-nowrap rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700">In progress</span>
                    <span v-else class="whitespace-nowrap rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">Completed</span>
                    <span v-if="row.group.unread" class="whitespace-nowrap rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700" :title="`${row.group.unread} new request(s)`">🔔 {{ row.group.unread }} new</span>
                </div>
                <div v-else class="flex flex-col items-start gap-1">
                    <Badge :variant="statusVariant(value)">{{ formatStatus(value) }}</Badge>
                    <span v-if="row.has_damage_report" class="whitespace-nowrap rounded-full bg-yellow-100 px-2 py-0.5 text-[11px] font-semibold text-yellow-800">⚠ Damage reported</span>
                </div>
            </template>
            <template #actions="{ row }">
                <Link v-if="row.group" :href="route('admin.borrowings.activity', row.activity_id)" class="text-green-600 hover:text-green-900">
                    View Students
                </Link>
                <Link v-else :href="route('admin.borrowings.show', row.id)" class="text-green-600 hover:text-green-900">
                    View Details
                </Link>
            </template>
        </DataTable>
    </AdminLayout>
</template>
