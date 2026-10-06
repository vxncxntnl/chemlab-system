<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import BorrowingStatusTracker from '@/Components/BorrowingStatusTracker.vue';
import BackButton from '@/Components/BackButton.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useBorrowingStatus } from '@/composables/useBorrowingStatus';

const { formatStatus, statusVariant } = useBorrowingStatus();

const props = defineProps<{
    borrowing: any;
}>();

const formatDate = (date: string) => new Date(date).toLocaleDateString();

const showApproveModal = ref(false);
const showRejectModal = ref(false);
const showReturnModal = ref(false);

const approve = () => { showApproveModal.value = true; };
const doApprove = () => { router.post(route('admin.borrowings.approve', props.borrowing.id)); showApproveModal.value = false; };

const rejectReason = ref('');
const reject = () => { rejectReason.value = ''; showRejectModal.value = true; };
const doReject = () => {
    router.post(route('admin.borrowings.reject', props.borrowing.id), { reason: rejectReason.value });
    showRejectModal.value = false;
};

const markReturned = () => { showReturnModal.value = true; };
const doReturn = () => { router.post(route('admin.borrowings.return', props.borrowing.id)); showReturnModal.value = false; };

// Admin can release as a backup when no lab staff is available.
const showReleaseModal = ref(false);
const doRelease = () => { router.post(route('admin.borrowings.release', props.borrowing.id)); showReleaseModal.value = false; };
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2 sm:gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Borrowing Details</h1>
                <BackButton :href="route('admin.borrowings.index')">Back to List</BackButton>
            </div>
        </template>

        <!-- Actions: at the top so they're seen first, stacked full-width on phones -->
        <div v-if="['pending_admin', 'pending_faculty'].includes(borrowing.status)"
            class="mb-6 flex flex-col gap-3 rounded-xl border border-yellow-200 bg-yellow-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-yellow-800">This request is waiting for approval.</p>
            <div class="flex flex-col gap-2 sm:flex-row sm:gap-3">
                <button @click="approve"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-800 transition-colors sm:w-auto">
                    Approve
                </button>
                <button @click="reject"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700 transition-colors sm:w-auto">
                    Reject
                </button>
            </div>
        </div>

        <div v-else-if="borrowing.status === 'approved'"
            class="mb-6 flex flex-col gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-blue-800">
                <p class="font-semibold">Approved — ready for release.</p>
                <p class="mt-0.5">Usually done by lab staff. You can release it yourself if no lab staff is available.</p>
            </div>
            <button @click="showReleaseModal = true"
                class="inline-flex w-full flex-shrink-0 items-center justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 transition-colors sm:w-auto">
                Release Equipment
            </button>
        </div>

        <div v-else-if="['borrowed', 'overdue'].includes(borrowing.status)"
            class="mb-6 flex flex-col gap-3 rounded-xl border border-green-200 bg-green-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-green-800">
                The borrower has this equipment<span v-if="borrowing.status === 'overdue'" class="font-semibold text-red-700"> — it is overdue</span>.
            </p>
            <button @click="markReturned"
                class="inline-flex w-full flex-shrink-0 items-center justify-center rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-800 transition-colors sm:w-auto">
                Mark as Returned
            </button>
        </div>

        <div class="mb-6">
            <BorrowingStatusTracker :status="borrowing.status" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Borrower Info -->
            <Card title="Borrower Information">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.user?.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.user?.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Role</dt>
                        <dd class="mt-1">
                            <Badge variant="primary">{{ borrowing.user?.role }}</Badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Department</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.user?.department || 'N/A' }}</dd>
                    </div>
                    <div v-if="borrowing.faculty">
                        <dt class="text-sm font-medium text-gray-500">Instructor</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.faculty?.name }}</dd>
                    </div>
                </dl>
            </Card>

            <!-- Borrowing Status -->
            <Card title="Borrowing Status">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <Badge :variant="statusVariant(borrowing.status)" size="lg">
                                {{ borrowing.status }}
                            </Badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Approved By</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.approver?.name || 'Pending' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Borrow Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ formatDate(borrowing.borrow_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Return Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ formatDate(borrowing.return_date) }}</dd>
                    </div>
                    <div v-if="borrowing.actual_return_date">
                        <dt class="text-sm font-medium text-gray-500">Actual Return</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ formatDate(borrowing.actual_return_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Purpose</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.purpose || 'N/A' }}</dd>
                    </div>
                    <div v-if="borrowing.rejection_reason" class="sm:col-span-2">
                        <dt class="text-sm font-medium text-red-600">Reason for Rejection</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.rejection_reason }}</dd>
                    </div>
                </dl>
            </Card>
        </div>

        <!-- Equipment List -->
        <Card title="Equipment Items" class="mt-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Equipment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Condition (Borrow)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Condition (Return)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="item in borrowing.borrowing_equipment" :key="item.id">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ item.equipment?.name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ item.inventory?.campus?.campus_name }} - {{ item.inventory?.location?.location_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ item.quantity }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <Badge variant="info">{{ item.condition_on_borrow }}</Badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <Badge v-if="item.condition_on_return" variant="info">{{ item.condition_on_return }}</Badge>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
        <ConfirmModal
            :show="showApproveModal"
            title="Approve Request"
            message="Are you sure you want to approve this borrowing request?"
            confirmLabel="Yes, Approve"
            confirmClass="bg-green-600 hover:bg-green-700"
            @confirmed="doApprove"
            @cancelled="showApproveModal = false"
        />
        <ConfirmModal
            :show="showRejectModal"
            title="Reject Request"
            message="Are you sure you want to reject this borrowing request?"
            confirmLabel="Yes, Reject"
            confirmClass="bg-red-600 hover:bg-red-700"
            @confirmed="doReject"
            @cancelled="showRejectModal = false"
        >
            <label for="admin-reject-reason" class="block text-xs font-medium text-gray-500 mb-1">Reason (optional, sent to the student)</label>
            <textarea
                id="admin-reject-reason"
                v-model="rejectReason"
                rows="2"
                maxlength="500"
                placeholder="e.g. Equipment needed for another scheduled activity"
                class="w-full rounded-lg border-gray-200 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
            />
        </ConfirmModal>
        <ConfirmModal
            :show="showReturnModal"
            title="Mark as Returned"
            message="Confirm that all equipment has been returned and inspected?"
            confirmLabel="Yes, Mark Returned"
            confirmClass="bg-green-600 hover:bg-green-700"
            @confirmed="doReturn"
            @cancelled="showReturnModal = false"
        />
        <ConfirmModal
            :show="showReleaseModal"
            title="Release Equipment"
            message="Hand the equipment to the borrower and mark it as borrowed? Check that the person in front of you matches these details:"
            confirmLabel="Yes, Release"
            confirmClass="bg-blue-700 hover:bg-blue-800"
            @confirmed="doRelease"
            @cancelled="showReleaseModal = false"
        >
            <dl class="space-y-1.5 rounded-lg bg-gray-50 p-3 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Borrower</dt>
                    <dd class="text-right font-medium text-gray-900">{{ borrowing.user?.name }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">ID</dt>
                    <dd class="text-right font-mono text-gray-900">{{ borrowing.user?.student_id || borrowing.user?.employee_id || '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Claim code</dt>
                    <dd class="text-right font-mono text-gray-900 break-all">{{ borrowing.claim_code || '—' }}</dd>
                </div>
            </dl>
        </ConfirmModal>
    </AdminLayout>
</template>
