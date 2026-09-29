<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
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
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Borrowing Details</h1>
                <BackButton :href="route('admin.borrowings.index')">Back to List</BackButton>
            </div>
        </template>

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

                <!-- Action Buttons -->
                <div class="mt-6 flex flex-wrap gap-3">
                    <PrimaryButton v-if="borrowing.status === 'pending_admin'" @click="approve">
                        Approve
                    </PrimaryButton>
                    <button
                        v-if="borrowing.status === 'pending_admin'"
                        @click="reject"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                    >
                        Reject
                    </button>
                    <PrimaryButton v-if="['borrowed', 'overdue'].includes(borrowing.status)" @click="markReturned">
                        Mark as Returned
                    </PrimaryButton>
                </div>
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
    </AdminLayout>
</template>
