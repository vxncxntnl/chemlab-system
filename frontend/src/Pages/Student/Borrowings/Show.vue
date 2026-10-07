<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import BorrowingStatusTracker from '@/Components/BorrowingStatusTracker.vue';
import BackButton from '@/Components/BackButton.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useBorrowingStatus } from '@/composables/useBorrowingStatus';

const { formatStatus, statusVariant } = useBorrowingStatus();

const props = defineProps<{
    borrowing: {
        id: number;
        user_id: number;
        borrow_date: string;
        return_date: string;
        actual_return_date: string | null;
        purpose: string | null;
        subject?: string | null;
        claim_code?: string | null;
        status: string;
        remarks: string | null;
        rejection_reason: string | null;
        created_at: string;
        approver: {
            id: number;
            name: string;
        } | null;
        faculty: {
            id: number;
            name: string;
        } | null;
        borrowing_equipment: Array<{
            id: number;
            quantity: number;
            damage_report_status?: 'reported' | 'confirmed' | 'no_damage' | null;
            equipment: {
                id: number;
                name: string;
                category: string;
            };
            inventory: {
                id: number;
                campus: {
                    id: number;
                    campus_name: string;
                };
                location: {
                    id: number;
                    location_name: string;
                };
            };
        }>;
        damage_reports: Array<{
            id: number;
            equipment_id: number;
            description: string;
            status: string;
            created_at: string;
        }>;
    };
}>();

const showCancelModal = ref(false);

const cancelRequest = () => {
    showCancelModal.value = true;
};

const doCancel = () => {
    router.post(route('student.borrowings.cancel', props.borrowing.id));
    showCancelModal.value = false;
};

// One tap: notifies the lab staff that this item may be damaged. No form or
// photo — the student brings the item in and the staff record the report.
const canReportDamage = ['borrowed', 'overdue'].includes(props.borrowing.status);
const damageTarget = ref<{ id: number; name: string } | null>(null);
const damageNote = ref('');
const doReportDamage = () => {
    if (!damageTarget.value) return;
    router.post(route('student.borrowings.report-damage', props.borrowing.id),
        { borrowing_equipment_id: damageTarget.value.id, note: damageNote.value || null },
        { preserveScroll: true });
    damageTarget.value = null;
    damageNote.value = '';
};

// Where each item's damage report stands (shown instead of the button).
const damageStatus: Record<string, { label: string; class: string }> = {
    reported: { label: 'Damage reported — waiting for lab staff', class: 'bg-yellow-100 text-yellow-800' },
    confirmed: { label: 'Damage confirmed by lab staff', class: 'bg-red-100 text-red-700' },
    no_damage: { label: 'Checked — no damage found', class: 'bg-green-100 text-green-700' },
};
const showDamageColumn = canReportDamage
    || props.borrowing.borrowing_equipment.some(i => i.damage_report_status);

</script>

<template>
    <UserLayout>
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-semibold text-gray-800">Borrowing Request #{{ borrowing.id }}</h1>
                    <BackButton :href="route('student.borrowings.index')">Back to My Borrowings</BackButton>
                </div>
                <Badge :variant="statusVariant(borrowing.status)" class="text-sm">{{ formatStatus(borrowing.status) }}</Badge>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Status Tracker -->
            <BorrowingStatusTracker :status="borrowing.status" />

            <!-- Borrowing Details -->
            <Card title="Request Details">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Borrow Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ new Date(borrowing.borrow_date).toLocaleDateString() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Expected Return Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ new Date(borrowing.return_date).toLocaleDateString() }}</dd>
                    </div>
                    <div v-if="borrowing.actual_return_date">
                        <dt class="text-sm font-medium text-gray-500">Actual Return Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ new Date(borrowing.actual_return_date).toLocaleDateString() }}</dd>
                    </div>
                    <div v-if="borrowing.faculty">
                        <dt class="text-sm font-medium text-gray-500">Faculty / Instructor</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.faculty.name }}</dd>
                    </div>
                    <div v-if="borrowing.approver">
                        <dt class="text-sm font-medium text-gray-500">Processed By</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.approver.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Purpose</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.purpose || 'Not specified' }}</dd>
                    </div>
                    <div v-if="borrowing.subject">
                        <dt class="text-sm font-medium text-gray-500">Activity / Subject</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.subject }}</dd>
                    </div>
                    <div v-if="borrowing.claim_code">
                        <dt class="text-sm font-medium text-gray-500">Claim Code</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ borrowing.claim_code }}</dd>
                    </div>
                    <div v-if="borrowing.remarks" class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Remarks</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.remarks }}</dd>
                    </div>
                    <div v-if="borrowing.rejection_reason" class="sm:col-span-2">
                        <dt class="text-sm font-medium text-red-600">Reason for Rejection</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.rejection_reason }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Requested On</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ new Date(borrowing.created_at).toLocaleString() }}</dd>
                    </div>
                </dl>
            </Card>

            <!-- Equipment List -->
            <Card title="Equipment Items">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Equipment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Quantity</th>
                                <th v-if="showDamageColumn" class="px-6 py-3"><span class="sr-only">Damage</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="item in borrowing.borrowing_equipment" :key="item.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ item.equipment.name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ item.equipment.category }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ item.inventory.campus.campus_name }} - {{ item.inventory.location.location_name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ item.quantity }}
                                </td>
                                <td v-if="showDamageColumn" class="px-6 py-4 text-right">
                                    <div class="flex flex-col items-end gap-1.5">
                                        <span v-if="item.damage_report_status"
                                            :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', damageStatus[item.damage_report_status]?.class]">
                                            {{ damageStatus[item.damage_report_status]?.label }}
                                        </span>
                                        <button v-if="canReportDamage && (!item.damage_report_status || item.damage_report_status === 'no_damage')"
                                            type="button" @click="damageTarget = { id: item.id, name: item.equipment.name }"
                                            class="inline-flex items-center whitespace-nowrap rounded-lg border border-red-300 bg-white px-3 py-1.5 text-sm font-semibold text-red-600 hover:bg-red-50">
                                            Report Damage
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Damage is recorded by lab staff (with a photo), not filed by students. -->
                <p v-if="borrowing.status === 'borrowed' || borrowing.status === 'overdue'"
                    class="mt-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                    Noticed damage on an item? Tap <strong>Report Damage</strong> to notify the Laboratory Staff, then bring the item to the laboratory so they can check it.
                </p>
            </Card>

            <!-- Damage Reports -->
            <Card v-if="borrowing.damage_reports.length > 0" title="Damage Reports">
                <div class="space-y-4">
                    <div
                        v-for="report in borrowing.damage_reports"
                        :key="report.id"
                        class="border border-gray-200 rounded-lg p-4"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ report.description }}</p>
                                <p class="text-xs text-gray-500 mt-1">Reported on {{ new Date(report.created_at).toLocaleString() }}</p>
                            </div>
                            <Badge :variant="statusVariant(report.status)">{{ formatStatus(report.status) }}</Badge>
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Actions -->
            <div v-if="borrowing.status === 'pending_faculty'" class="flex items-center justify-end">
                <button
                    @click="cancelRequest"
                    class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 transition-colors"
                >
                    Cancel Request
                </button>
            </div>
        </div>

        <ConfirmModal
            :show="showCancelModal"
            title="Cancel Request"
            message="Are you sure you want to cancel this borrowing request? This action cannot be undone."
            confirmLabel="Yes, Cancel Request"
            confirmClass="bg-red-600 hover:bg-red-700"
            @confirmed="doCancel"
            @cancelled="showCancelModal = false"
        />
        <ConfirmModal
            :show="!!damageTarget"
            title="Report Damage"
            :message="`Notify the Laboratory Staff that ${damageTarget?.name ?? 'this item'} may be damaged? After this, please bring the item to the laboratory so they can check it.`"
            confirmLabel="Yes, Notify Lab Staff"
            confirmClass="bg-red-600 hover:bg-red-700"
            @confirmed="doReportDamage"
            @cancelled="damageTarget = null; damageNote = ''"
        >
            <label for="damage-note" class="block text-xs font-medium text-gray-500 mb-1">What's wrong? (optional)</label>
            <input id="damage-note" v-model="damageNote" type="text" maxlength="255" placeholder="e.g. cracked rim, won't turn on"
                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500" />
        </ConfirmModal>
    </UserLayout>
</template>
