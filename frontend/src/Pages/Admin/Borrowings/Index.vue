<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { formatDateTime } from '@/utils/date';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
        activity_id?: string;
    };
    // Activities that have requests (for the Activity filter).
    activities: Array<{ id: number; title: string; activity_date: string; instructor?: { name: string } | null }>;
    activityPendingCount: number;
}>();

const search = ref('');
const statusFilter = ref('');
const activityFilter = ref(props.filters.activity_id ?? '');
const selectedActivity = computed(() => props.activities.find(a => a.id === Number(activityFilter.value)));

// "Approve all" for the selected activity's requests waiting for admin.
const showApproveAll = ref(false);
const approvingAll = ref(false);
const approveAll = () => {
    if (!selectedActivity.value) return;
    approvingAll.value = true;
    router.post(route('admin.borrowings.approve-activity', selectedActivity.value.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            approvingAll.value = false;
            showApproveAll.value = false;
        },
    });
};

const columns = [
    { key: 'user', label: 'Borrower' },
    { key: 'faculty', label: 'Faculty / Instructor' },
    { key: 'source', label: 'Activity / Source' },
    { key: 'created_at', label: 'Requested On' },
    { key: 'borrow_date', label: 'Borrow Date' },
    { key: 'return_date', label: 'Return Date' },
    { key: 'status', label: 'Status' },
];

const applyFilters = () => {
    router.get(route('admin.borrowings.index'), {
        search: search.value,
        status: statusFilter.value,
        activity_id: activityFilter.value || undefined,
    }, { preserveState: true });
};

const formatDate = (date: string) => new Date(date).toLocaleDateString();
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
            <select
                v-model="activityFilter"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 sm:max-w-xs"
                @change="applyFilters"
            >
                <option value="">All Activities</option>
                <option v-for="a in activities" :key="a.id" :value="String(a.id)">
                    {{ a.title }}{{ a.instructor ? ` – ${a.instructor.name}` : '' }}
                </option>
            </select>
        </div>

        <!-- One activity selected: approve its whole class in one click -->
        <div v-if="selectedActivity" class="mb-4 flex flex-col gap-3 rounded-lg border border-indigo-200 bg-indigo-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-indigo-900">
                <span class="font-semibold">{{ selectedActivity.title }}</span>
                — {{ activityPendingCount }} request(s) waiting for admin approval
            </p>
            <button
                v-if="activityPendingCount > 0"
                type="button"
                class="w-full rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 sm:w-auto"
                @click="showApproveAll = true"
            >
                Approve all {{ activityPendingCount }}
            </button>
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
            <template #cell-created_at="{ value }">
                <span class="whitespace-nowrap text-xs text-gray-700">{{ formatDateTime(value) }}</span>
            </template>
            <template #cell-borrow_date="{ value }">
                {{ formatDate(value) }}
            </template>
            <template #cell-return_date="{ value }">
                {{ formatDate(value) }}
            </template>
            <template #cell-source="{ row }">
                <!-- From the borrowing's real activity link: QR activity vs. a direct request -->
                <div v-if="row.activity" class="flex flex-col items-start gap-0.5">
                    <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">QR Activity</span>
                    <span class="text-xs text-gray-700">{{ row.activity.title }}</span>
                </div>
                <span v-else class="text-xs text-gray-500">Walk-in / Direct Request</span>
            </template>
            <template #cell-status="{ value, row }">
                <div class="flex flex-col items-start gap-1">
                    <Badge :variant="statusVariant(value)">{{ formatStatus(value) }}</Badge>
                    <span v-if="row.has_damage_report" class="whitespace-nowrap rounded-full bg-yellow-100 px-2 py-0.5 text-[11px] font-semibold text-yellow-800">⚠ Damage reported</span>
                </div>
            </template>
            <template #actions="{ row }">
                <Link :href="route('admin.borrowings.show', row.id)" class="text-green-600 hover:text-green-900">
                    View Details
                </Link>
            </template>
        </DataTable>

        <ConfirmModal
            :show="showApproveAll"
            title="Approve All Requests"
            :message="`Approve ${activityPendingCount} request(s) for &quot;${selectedActivity?.title}&quot;? Each student keeps their own request and claim code, and each one will be notified.`"
            :confirmLabel="`Yes, Approve ${activityPendingCount}`"
            confirmClass="bg-green-600 hover:bg-green-700"
            :processing="approvingAll"
            @confirmed="approveAll"
            @cancelled="showApproveAll = false"
        />
    </AdminLayout>
</template>
