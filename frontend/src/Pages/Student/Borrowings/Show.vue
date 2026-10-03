<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import BorrowingStatusTracker from '@/Components/BorrowingStatusTracker.vue';
import BackButton from '@/Components/BackButton.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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
const showDamageForm = ref(false);
const selectedEquipmentId = ref<number | null>(null);

const damageForm = useForm({
    equipment_id: '',
    quantity: 1,
    description: '',
});

const selectedEquipmentMaxQuantity = computed(() => {
    const item = props.borrowing.borrowing_equipment.find(i => i.equipment.id === selectedEquipmentId.value);
    return item?.quantity ?? 1;
});


const cancelRequest = () => {
    showCancelModal.value = true;
};

const doCancel = () => {
    router.post(route('student.borrowings.cancel', props.borrowing.id));
    showCancelModal.value = false;
};

const openDamageForm = (equipmentId: number) => {
    selectedEquipmentId.value = equipmentId;
    damageForm.equipment_id = String(equipmentId);
    showDamageForm.value = true;
};

const submitDamageReport = () => {
    damageForm.post(route('student.borrowings.report-damage', props.borrowing.id), {
        onSuccess: () => {
            showDamageForm.value = false;
            damageForm.reset();
        },
    });
};

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
                                <th v-if="borrowing.status === 'borrowed' || borrowing.status === 'overdue'" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
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
                                <td v-if="borrowing.status === 'borrowed' || borrowing.status === 'overdue'" class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <button
                                        @click="openDamageForm(item.equipment.id)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Report Damage
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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

            <!-- Damage Report Form -->
            <Card v-if="showDamageForm" title="Report Damage">
                <form @submit.prevent="submitDamageReport" class="space-y-4">
                    <div>
                        <InputLabel for="damage-quantity" value="Damaged Quantity" />
                        <input
                            id="damage-quantity"
                            v-model.number="damageForm.quantity"
                            type="number"
                            min="1"
                            :max="selectedEquipmentMaxQuantity"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            required
                        />
                        <p class="text-xs text-gray-500 mt-1">You borrowed {{ selectedEquipmentMaxQuantity }} unit(s) of this item.</p>
                        <InputError :message="damageForm.errors.quantity" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="description" value="Damage Description" />
                        <textarea
                            id="description"
                            v-model="damageForm.description"
                            rows="4"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            placeholder="Please describe the damage in detail..."
                            required
                        />
                        <InputError :message="damageForm.errors.description" class="mt-2" />
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="showDamageForm = false"
                            class="text-gray-600 hover:text-gray-900"
                        >
                            Cancel
                        </button>
                        <PrimaryButton :disabled="damageForm.processing">
                            Submit Report
                        </PrimaryButton>
                    </div>
                </form>
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
    </UserLayout>
</template>
