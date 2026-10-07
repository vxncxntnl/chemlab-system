<script setup lang="ts">
import { formatDateTime } from '@/utils/date';
import LabLayout from '@/Layouts/LabLayout.vue';
import Card from '@/Components/Card.vue';
import BorrowingStatusTracker from '@/Components/BorrowingStatusTracker.vue';
import Badge from '@/Components/Badge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import QrCode from '@/Components/QrCode.vue';
import BackButton from '@/Components/BackButton.vue';
import RecordDamageModal from '@/Components/RecordDamageModal.vue';
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    borrowing: any;
}>();

const statusVariant = (status: string): 'success' | 'default' | 'warning' | 'danger' | 'info' | 'primary' => {
    const variants: Record<string, 'success' | 'default' | 'warning' | 'danger' | 'info' | 'primary'> = {
        pending_faculty: 'warning',
        pending_admin:   'info',
        approved:        'info',
        borrowed:        'primary',
        returned:        'success',
        overdue:         'danger',
        cancelled:       'default',
    };
    return variants[status] || 'default';
};

const formatStatus = (status: string) => {
    const labels: Record<string, string> = {
        pending_faculty: 'Pending Faculty Approval',
        pending_admin:   'Pending Admin Approval',
        approved:        'Approved',
        borrowed:        'Borrowed',
        returned:        'Returned',
        overdue:         'Overdue',
        cancelled:       'Cancelled',
    };
    return labels[status] || status;
};

const formatDate = (date: string) => new Date(date).toLocaleDateString();

const claimForm = useForm({
    claim_code: props.borrowing?.claim_code || '',
    student_id: props.borrowing?.user?.student_id || '',
});

const release = () => {
    claimForm.post(route('lab.borrowings.release', props.borrowing.id));
};

const returnForm = useForm({
    conditions: {} as Record<string, { good: number; damaged: number }>,
    remarks: '',
    liable_party: '',
    liable_reason: '',
});

const hasDamage = computed(() =>
    Object.values(returnForm.conditions).some(c => c.damaged > 0)
);

if (props.borrowing?.borrowing_equipment) {
    props.borrowing.borrowing_equipment.forEach((item: any) => {
        if (!returnForm.conditions[item.id]) {
            returnForm.conditions[item.id] = { good: item.quantity, damaged: 0 };
        }
    });
}

const onGoodChange = (itemId: number, totalQty: number) => {
    const good = returnForm.conditions[itemId].good;
    const clamped = Math.max(0, Math.min(good, totalQty));
    returnForm.conditions[itemId].good = clamped;
    returnForm.conditions[itemId].damaged = totalQty - clamped;
};

const onDamagedChange = (itemId: number, totalQty: number) => {
    const damaged = returnForm.conditions[itemId].damaged;
    const clamped = Math.max(0, Math.min(damaged, totalQty));
    returnForm.conditions[itemId].damaged = clamped;
    returnForm.conditions[itemId].good = totalQty - clamped;
};

const submitReturn = () => {
    returnForm.post(route('lab.borrowings.return', props.borrowing.id));
};
// Record a verified damage report for one borrowed item (with optional photo).
const isOut = computed(() => ['borrowed', 'overdue'].includes(props.borrowing.status));
const damageItem = ref<{ id: number; name: string; quantity: number } | null>(null);

// Items the student reported as possibly damaged, still waiting for a check.
const reportedItems = computed(() =>
    (props.borrowing.borrowing_equipment ?? []).filter((i: any) => i.damage_report_status === 'reported')
);
const markNoDamage = (itemId: number) => {
    router.post(route('lab.borrowings.no-damage', props.borrowing.id), { borrowing_equipment_id: itemId }, { preserveScroll: true });
};
</script>

<template>
    <LabLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Borrowing Details</h1>
                <BackButton :href="route('lab.borrowings.index')">Back to List</BackButton>
            </div>
        </template>

        <div class="mb-6">
            <BorrowingStatusTracker :status="borrowing.status" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Card title="Request Info">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Borrower</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.user?.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Student ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ borrowing.user?.student_id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <Badge :variant="statusVariant(borrowing.status)" size="lg">{{ formatStatus(borrowing.status) }}</Badge>
                        </dd>
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
                    <div class="sm:col-span-2" v-if="borrowing.subject">
                        <dt class="text-sm font-medium text-gray-500">Activity / Subject</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.subject }}</dd>
                    </div>
                    <div class="sm:col-span-2" v-if="borrowing.faculty">
                        <dt class="text-sm font-medium text-gray-500">Instructor</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.faculty?.name }}</dd>
                    </div>
                    <div class="sm:col-span-2" v-if="borrowing.remarks">
                        <dt class="text-sm font-medium text-gray-500">Return Remarks</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ borrowing.remarks }}</dd>
                    </div>
                    <div class="sm:col-span-2" v-if="borrowing.claim_code">
                        <dt class="text-sm font-medium text-gray-500">Claim Code &amp; QR</dt>
                        <dd class="mt-1 flex items-center gap-4">
                            <span class="text-sm text-gray-900 font-mono">{{ borrowing.claim_code }}</span>
                            <QrCode
                                :value="borrowing.claim_code"
                                :label="borrowing.claim_code"
                                :size="100"
                            />
                        </dd>
                        <div class="mt-2">
                            <img
                                v-if="borrowing.claim_code"
                                :src="`https://chart.googleapis.com/chart?cht=qr&chs=180x180&chl=${encodeURIComponent(borrowing.claim_code)}`"
                                alt="QR Code"
                                class="rounded-md border border-gray-200"
                            />
                        </div>
                    </div>
                </dl>
            </Card>

            <div v-if="reportedItems.length" class="mb-4 rounded-xl border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-900">
                <p class="font-semibold">⚠ The borrower reported possible damage on {{ reportedItems.length === 1 ? 'an item' : reportedItems.length + ' items' }}.</p>
                <p class="mt-0.5">Check the item when they bring it in, then use <strong>Record Damage</strong> or <strong>No Damage Found</strong> on the highlighted row below.</p>
            </div>
            <Card title="Equipment Items">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Equipment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Condition (Borrow)</th>
                                <th v-if="isOut" class="px-6 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="item in borrowing.borrowing_equipment" :key="item.id"
                                :class="{ 'bg-yellow-50': item.damage_report_status === 'reported' }">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.equipment?.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ item.inventory?.campus?.campus_name }} - {{ item.inventory?.location?.location_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.quantity }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Badge variant="info">{{ item.condition_on_borrow }}</Badge>
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
        </div>

        <!-- Release Section -->
        <Card
            title="Item Release (Lab Staff)"
            class="mt-6"
            v-if="borrowing.status === 'approved'"
        >
            <form @submit.prevent="release" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="claim_code" value="Claim Code (QR/Code)" />
                        <TextInput
                            id="claim_code"
                            v-model="claimForm.claim_code"
                            class="mt-1 block w-full font-mono"
                        />
                        <InputError :message="claimForm.errors.claim_code" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <PrimaryButton :disabled="claimForm.processing">
                        Release & Mark as Borrowed
                    </PrimaryButton>
                </div>
            </form>
        </Card>

        <!-- Return Section -->
        <Card
            title="Return Equipment (Lab Staff)"
            class="mt-6"
            v-if="['borrowed', 'overdue'].includes(borrowing.status)"
        >
            <form @submit.prevent="submitReturn" class="space-y-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Equipment</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Borrowed Qty</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-green-600">Good Qty</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-red-600">Damaged Qty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="item in borrowing.borrowing_equipment" :key="item.id">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ item.equipment?.name }}
                                    <span class="block text-xs text-gray-400">{{ item.inventory?.location?.location_name }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-700 font-semibold">
                                    {{ item.quantity }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input
                                        type="number"
                                        v-model.number="returnForm.conditions[item.id].good"
                                        min="0"
                                        :max="item.quantity"
                                        @change="onGoodChange(item.id, item.quantity)"
                                        class="w-20 rounded-md border-green-300 text-center shadow-sm focus:border-green-500 focus:ring-green-500 text-sm"
                                    />
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input
                                        type="number"
                                        v-model.number="returnForm.conditions[item.id].damaged"
                                        min="0"
                                        :max="item.quantity"
                                        @change="onDamagedChange(item.id, item.quantity)"
                                        class="w-20 rounded-md border-red-300 text-center shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-gray-500">Good + Damaged must equal the borrowed quantity for each item. Damaged items will not be restored to available stock and a damage report will be created automatically.</p>

                <InputError :message="returnForm.errors.conditions" class="mt-1" />

                <!-- Incident Report section — only shown when damaged items are detected -->
                <div v-if="hasDamage" class="rounded-lg border border-orange-200 bg-orange-50 p-4 space-y-4">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        <h4 class="text-sm font-semibold text-orange-800">Incident Report — Damaged items detected</h4>
                    </div>
                    <p class="text-xs text-orange-700">Fill in the details below. This will be used to generate an official incident report and notify the faculty and student.</p>
                    <div>
                        <InputLabel value="Who is liable for the damage?" />
                        <select v-model="returnForm.liable_party"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
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
                            placeholder="e.g. Equipment was documented as Good at release but returned cracked. Student confirmed condition at pickup."
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm"
                        />
                        <InputError :message="returnForm.errors.liable_reason" class="mt-1" />
                    </div>
                </div>

                <div>
                    <InputLabel for="return_remarks" value="Return Remarks (Optional)" />
                    <textarea
                        id="return_remarks"
                        v-model="returnForm.remarks"
                        rows="3"
                        maxlength="1000"
                        placeholder="e.g. Item returned with minor scratches, missing rubber stopper, equipment returned clean and intact..."
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm"
                    />
                    <InputError :message="returnForm.errors.remarks" class="mt-1" />
                </div>

                <div class="flex items-center justify-end">
                    <PrimaryButton :disabled="returnForm.processing">
                        Submit Return
                    </PrimaryButton>
                </div>
            </form>
        </Card>
        <RecordDamageModal :item="damageItem" :borrowing-id="borrowing.id" route-name="lab.borrowings.record-damage" @close="damageItem = null" />
    </LabLayout>
</template>

