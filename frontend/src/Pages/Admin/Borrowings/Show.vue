<script setup lang="ts">
import { formatDateTime } from '@/utils/date';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import BorrowingStatusTracker from '@/Components/BorrowingStatusTracker.vue';
import BackButton from '@/Components/BackButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import RecordDamageModal from '@/Components/RecordDamageModal.vue';
import ReleaseIdCheck from '@/Components/ReleaseIdCheck.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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

// Same return form lab staff use (good/damaged per item, liability,
// remarks), so the admin can fully cover when no lab staff is available.
// Damaged items create a damage report and aren't restored to stock.
const returnForm = useForm({
    conditions: {} as Record<string, { good: number; damaged: number }>,
    remarks: '',
    liable_party: '',
    liable_reason: '',
});

(props.borrowing?.borrowing_equipment ?? []).forEach((item: any) => {
    returnForm.conditions[item.id] = { good: item.quantity, damaged: 0 };
});

const hasDamage = computed(() => Object.values(returnForm.conditions).some(c => c.damaged > 0));

const onGoodChange = (itemId: number, totalQty: number) => {
    const clamped = Math.max(0, Math.min(returnForm.conditions[itemId].good, totalQty));
    returnForm.conditions[itemId].good = clamped;
    returnForm.conditions[itemId].damaged = totalQty - clamped;
};

const onDamagedChange = (itemId: number, totalQty: number) => {
    const clamped = Math.max(0, Math.min(returnForm.conditions[itemId].damaged, totalQty));
    returnForm.conditions[itemId].damaged = clamped;
    returnForm.conditions[itemId].good = totalQty - clamped;
};

const submitReturn = () => { showReturnModal.value = true; };
const doReturn = () => {
    returnForm.post(route('admin.borrowings.return', props.borrowing.id), { preserveScroll: true });
    showReturnModal.value = false;
};

// Admin can release as a backup when no lab staff is available.
const showReleaseModal = ref(false);
// Same ID check as lab staff: the number is typed from the school ID.
const releaseForm = useForm({ student_id: '', id_checked: false });
const doRelease = () => {
    releaseForm.post(route('admin.borrowings.release', props.borrowing.id), {
        preserveScroll: true,
        onSuccess: () => { showReleaseModal.value = false; releaseForm.reset(); },
    });
};

// Record a verified damage report for one borrowed item (with optional photo).
const isOut = computed(() => ['borrowed', 'overdue'].includes(props.borrowing.status));
const damageItem = ref<{ id: number; name: string; quantity: number } | null>(null);

// Items the student reported as possibly damaged, still waiting for a check.
const reportedItems = computed(() =>
    (props.borrowing.borrowing_equipment ?? []).filter((i: any) => i.damage_report_status === 'reported')
);
const markNoDamage = (itemId: number) => {
    router.post(route('admin.borrowings.no-damage', props.borrowing.id), { borrowing_equipment_id: itemId }, { preserveScroll: true });
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2 sm:gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Borrowing Details</h1>
                <BackButton :href="borrowing.activity_id ? route('admin.borrowings.activity', borrowing.activity_id) : route('admin.borrowings.index')">{{ borrowing.activity_id ? 'Back to Students' : 'Back to List' }}</BackButton>
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

        <!-- Return form: same as lab staff's, at the top while the equipment is out -->
        <div v-else-if="['borrowed', 'overdue'].includes(borrowing.status)"
            class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 sm:p-5">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-green-900">Return Equipment</h2>
                <p class="mt-0.5 text-sm text-green-800">
                    The borrower has this equipment<span v-if="borrowing.status === 'overdue'" class="font-semibold text-red-700"> — it is overdue</span>.
                    When it comes back, record the condition of each item.
                </p>
            </div>

            <form @submit.prevent="submitReturn" class="space-y-4">
                <!-- Phones: one card per item -->
                <div class="space-y-3 sm:hidden">
                    <div v-for="item in borrowing.borrowing_equipment" :key="item.id" class="rounded-lg bg-white p-3 shadow-sm">
                        <p class="text-sm font-medium text-gray-900">{{ item.equipment?.name }}</p>
                        <p class="text-xs text-gray-500">{{ item.inventory?.location?.location_name }} · Borrowed: {{ item.quantity }}</p>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <label class="text-xs font-medium text-green-700">Good
                                <input type="number" v-model.number="returnForm.conditions[item.id].good" min="0" :max="item.quantity"
                                    @change="onGoodChange(item.id, item.quantity)"
                                    class="mt-1 block w-full rounded-md border-green-300 text-center text-sm shadow-sm focus:border-green-500 focus:ring-green-500" />
                            </label>
                            <label class="text-xs font-medium text-red-700">Damaged
                                <input type="number" v-model.number="returnForm.conditions[item.id].damaged" min="0" :max="item.quantity"
                                    @change="onDamagedChange(item.id, item.quantity)"
                                    class="mt-1 block w-full rounded-md border-red-300 text-center text-sm shadow-sm focus:border-red-500 focus:ring-red-500" />
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Larger screens: table -->
                <div class="hidden overflow-x-auto rounded-lg bg-white sm:block">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Equipment</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Borrowed Qty</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-green-600">Good Qty</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-red-600">Damaged Qty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="item in borrowing.borrowing_equipment" :key="item.id">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ item.equipment?.name }}
                                    <span class="block text-xs text-gray-400">{{ item.inventory?.location?.location_name }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-gray-700">{{ item.quantity }}</td>
                                <td class="px-4 py-3 text-center">
                                    <input type="number" v-model.number="returnForm.conditions[item.id].good" min="0" :max="item.quantity"
                                        @change="onGoodChange(item.id, item.quantity)"
                                        class="w-20 rounded-md border-green-300 text-center text-sm shadow-sm focus:border-green-500 focus:ring-green-500" />
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input type="number" v-model.number="returnForm.conditions[item.id].damaged" min="0" :max="item.quantity"
                                        @change="onDamagedChange(item.id, item.quantity)"
                                        class="w-20 rounded-md border-red-300 text-center text-sm shadow-sm focus:border-red-500 focus:ring-red-500" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-gray-600">Good + Damaged must equal the borrowed quantity for each item. Damaged items are not restored to available stock, and a damage report is created automatically.</p>
                <InputError :message="returnForm.errors.conditions" />

                <!-- Incident details — only when something came back damaged -->
                <div v-if="hasDamage" class="space-y-4 rounded-lg border border-orange-200 bg-orange-50 p-4">
                    <h3 class="text-sm font-semibold text-orange-800">Incident Report — damaged items detected</h3>
                    <p class="text-xs text-orange-700">This is used for the official incident report and to notify the faculty and student.</p>
                    <div>
                        <InputLabel value="Who is liable for the damage?" />
                        <select v-model="returnForm.liable_party"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                            <option value="">— Select liable party —</option>
                            <option value="student">Student (borrower is responsible)</option>
                            <option value="faculty">Faculty (instructor in charge is responsible)</option>
                            <option value="shared">Shared (both student and faculty)</option>
                        </select>
                        <InputError :message="returnForm.errors.liable_party" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Reason / Justification" />
                        <textarea v-model="returnForm.liable_reason" rows="3" maxlength="1000"
                            placeholder="e.g. Equipment was documented as Good at release but returned cracked."
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500" />
                        <InputError :message="returnForm.errors.liable_reason" class="mt-1" />
                    </div>
                </div>

                <div>
                    <InputLabel for="return_remarks" value="Return Remarks (Optional)" />
                    <textarea id="return_remarks" v-model="returnForm.remarks" rows="2" maxlength="1000"
                        placeholder="e.g. Returned clean and intact, missing rubber stopper..."
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500" />
                    <InputError :message="returnForm.errors.remarks" class="mt-1" />
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="returnForm.processing"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-800 transition-colors disabled:opacity-50 sm:w-auto">
                        Submit Return
                    </button>
                </div>
            </form>
        </div>

        <div class="mb-6">
            <BorrowingStatusTracker :status="borrowing.status" />
        </div>

        <!-- Where this request came from (the borrowing's activity link) -->
        <div class="mb-6 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm">
            <span>
                <span class="text-gray-500">Source:</span>
                <span v-if="borrowing.activity" class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">QR Activity</span>
                <span v-else class="ml-1 font-medium text-gray-800">Walk-in / Direct Request</span>
            </span>
            <template v-if="borrowing.activity">
                <span><span class="text-gray-500">Activity:</span> <span class="font-semibold text-gray-900">{{ borrowing.activity.title }}</span></span>
                <span><span class="text-gray-500">Instructor:</span> <span class="font-medium text-gray-800">{{ borrowing.activity.instructor?.name ?? '—' }}</span></span>
            </template>
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
                        <dt class="text-sm font-medium text-gray-500">Requested On</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(borrowing.created_at) }}</dd>
                    </div>
                    <div v-if="borrowing.admin_approved_at">
                        <dt class="text-sm font-medium text-gray-500">Approved On</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(borrowing.admin_approved_at) }}</dd>
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
        <div v-if="reportedItems.length" class="mt-6 rounded-xl border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-900">
            <p class="font-semibold">⚠ The borrower reported possible damage on {{ reportedItems.length === 1 ? 'an item' : reportedItems.length + ' items' }}.</p>
            <p class="mt-0.5">Check the item when they bring it in, then use <strong>Record Damage</strong> or <strong>No Damage Found</strong> on the highlighted row below.</p>
        </div>
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
                            <th v-if="isOut" class="px-6 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="item in borrowing.borrowing_equipment" :key="item.id"
                            :class="{ 'bg-yellow-50': item.damage_report_status === 'reported' }">
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
                            <td v-if="isOut" class="px-6 py-4 text-right">
                                <div class="flex flex-col items-end gap-1.5">
                                    <template v-if="item.damage_report_status === 'reported'">
                                        <span class="rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-800">⚠ Student reported damage</span>
                                        <span v-if="item.damage_reported_at" class="text-xs text-gray-500">Reported {{ formatDateTime(item.damage_reported_at) }}</span>
                                        <span v-if="item.damage_report_note" class="max-w-[16rem] text-xs text-gray-600">"{{ item.damage_report_note }}"</span>
                                    </template>
                                    <span v-else-if="item.damage_report_status === 'confirmed'" class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Damage recorded</span>
                                    <span v-else-if="item.damage_report_status === 'no_damage'" class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">Checked — no damage</span>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button v-if="item.damage_report_status === 'reported'" type="button" @click="markNoDamage(item.id)"
                                            class="inline-flex items-center whitespace-nowrap rounded-lg border border-green-300 bg-white px-3 py-1.5 text-sm font-semibold text-green-700 hover:bg-green-50">
                                            No Damage Found
                                        </button>
                                        <button type="button" @click="damageItem = { id: item.id, name: item.equipment?.name, quantity: item.quantity }"
                                            class="inline-flex items-center whitespace-nowrap rounded-lg border border-red-300 bg-white px-3 py-1.5 text-sm font-semibold text-red-600 hover:bg-red-50">
                                            Record Damage
                                        </button>
                                    </div>
                                </div>
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
            title="Submit Return"
            message="Confirm that the equipment has been returned and the good/damaged counts are correct?"
            confirmLabel="Yes, Submit Return"
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
            <ReleaseIdCheck :borrower="borrowing.user" :activity-title="(borrowing as any).activity?.title" :form="releaseForm" />
            <p class="mt-2 text-xs text-gray-500">Claim code: <span class="font-mono">{{ borrowing.claim_code || '—' }}</span></p>
        </ConfirmModal>
        <RecordDamageModal :item="damageItem" :borrowing-id="borrowing.id" route-name="admin.borrowings.record-damage" @close="damageItem = null" />
    </AdminLayout>
</template>
