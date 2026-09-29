<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    item: {
        id: number;
        lab: string;
        category: string;
        item_no: string | null;
        description: string;
        size: string | null;
        quantity: number;
        uom: string | null;
        brand: string | null;
        storage_location: string | null;
        condition_remarks: string | null;
        qty_sent_iligan: number;
        qty_sent_butuan: number;
        current_campus_location: string | null;
        created_at: string;
        updated_at: string;
        transfers: Array<{
            id: number;
            campus: string;
            quantity: number;
            transfer_date: string;
            received_by: string | null;
            notes: string | null;
        }>;
        logs: Array<{
            id: number;
            action: string;
            changes: any;
            created_at: string;
            user: { name: string } | null;
        }>;
    };
}>();

const showTransferModal = ref(false);

const transferForm = useForm({
    campus: 'iligan',
    quantity: 1,
    transfer_date: new Date().toISOString().split('T')[0],
    received_by: '',
    notes: '',
});

const submitTransfer = () => {
    transferForm.post(route('admin.lab-inventory.transfer', props.item.id), {
        onSuccess: () => {
            showTransferModal.value = false;
            transferForm.reset();
        },
    });
};

const getConditionBadge = (remarks: string | null) => {
    if (!remarks) return 'default';
    const lower = remarks.toLowerCase();
    if (lower.includes('disposal')) return 'default';
    if (lower.includes('not working') || lower.includes('broken') || lower.includes('damage')) return 'danger';
    if (lower.includes('repair')) return 'warning';
    return 'success';
};

const showDeleteModal = ref(false);
const deleteItem = () => { showDeleteModal.value = true; };
const doDelete = () => { router.delete(route('admin.lab-inventory.destroy', props.item.id)); showDeleteModal.value = false; };

const availableQuantity = props.item.quantity - props.item.qty_sent_iligan - props.item.qty_sent_butuan;
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold" style="color: #16a34a;">{{ item.description }}</h1>
                    <p class="text-sm text-gray-500">Max Suniel Carmen, Cagayan de Oro City - S.Y. 2024-2025</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('admin.lab-inventory.edit', item.id)"
                        class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-90 transition-colors"
                        style="background-color: #16a34a;"
                    >
                        Edit
                    </Link>
                    <button
                        @click="deleteItem"
                        class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 transition-colors"
                    >
                        Delete
                    </button>
                    <Link
                        :href="route('admin.lab-inventory.index')"
                        class="inline-flex items-center rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors"
                    >
                        Back
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Item Details -->
            <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="px-6 py-4 border-b" style="background-color: #16a34a;">
                    <h2 class="text-lg font-semibold text-white">Item Details</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <p class="text-sm text-gray-500">Laboratory</p>
                            <Badge :variant="item.lab === 'chemistry' ? 'info' : 'warning'" class="mt-1">
                                {{ item.lab === 'chemistry' ? 'Chemistry Lab' : 'Physics Lab' }}
                            </Badge>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Category</p>
                            <Badge :variant="item.category === 'pasco' ? 'success' : 'default'" class="mt-1">
                                {{ item.category === 'pasco' ? 'PASCO Equipment' : 'General Equipment' }}
                            </Badge>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Item Number</p>
                            <p class="font-medium">{{ item.item_no || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Size</p>
                            <p class="font-medium">{{ item.size || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Quantity</p>
                            <p class="font-medium text-xl" style="color: #16a34a;">{{ item.quantity }} {{ item.uom }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Brand</p>
                            <p class="font-medium">{{ item.brand || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Storage Location</p>
                            <p class="font-medium">{{ item.storage_location || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Condition</p>
                            <Badge :variant="getConditionBadge(item.condition_remarks)" class="mt-1">
                                {{ item.condition_remarks || 'N/A' }}
                            </Badge>
                        </div>
                        <div v-if="item.current_campus_location">
                            <p class="text-sm text-gray-500">Current Campus Location</p>
                            <p class="font-medium text-purple-600">{{ item.current_campus_location }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfer Status -->
            <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="px-6 py-4 border-b flex items-center justify-between" style="background-color: #16a34a;">
                    <h2 class="text-lg font-semibold text-white">Transfer Status</h2>
                    <button
                        @click="showTransferModal = true"
                        class="px-3 py-1 bg-white text-sm font-medium rounded hover:bg-gray-100"
                        style="color: #16a34a;"
                    >
                        Record Transfer
                    </button>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-500">Total Quantity</p>
                            <p class="text-2xl font-bold" style="color: #16a34a;">{{ item.quantity }}</p>
                        </div>
                        <div class="text-center p-4 bg-purple-50 rounded-lg">
                            <p class="text-sm text-gray-500">Sent to Iligan</p>
                            <p class="text-2xl font-bold text-purple-600">{{ item.qty_sent_iligan }}</p>
                        </div>
                        <div class="text-center p-4 bg-pink-50 rounded-lg">
                            <p class="text-sm text-gray-500">Sent to Butuan</p>
                            <p class="text-2xl font-bold text-pink-600">{{ item.qty_sent_butuan }}</p>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <p class="text-sm text-gray-500">Available Here</p>
                            <p class="text-2xl font-bold text-green-600">{{ availableQuantity }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfer History -->
            <div v-if="item.transfers.length > 0" class="rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="px-6 py-4 border-b" style="background-color: #16a34a;">
                    <h2 class="text-lg font-semibold text-white">Transfer History</h2>
                </div>
                <div class="divide-y">
                    <div v-for="transfer in item.transfers" :key="transfer.id" class="px-6 py-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-medium">
                                    {{ transfer.quantity }} {{ item.uom }} transferred to
                                    <span :class="transfer.campus === 'iligan' ? 'text-purple-600' : 'text-pink-600'">
                                        {{ transfer.campus === 'iligan' ? 'Iligan' : 'Butuan' }} Campus
                                    </span>
                                </p>
                                <p v-if="transfer.received_by" class="text-sm text-gray-500">Received by: {{ transfer.received_by }}</p>
                                <p v-if="transfer.notes" class="text-sm text-gray-500 mt-1">{{ transfer.notes }}</p>
                            </div>
                            <span class="text-sm text-gray-400">{{ transfer.transfer_date }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div v-if="item.logs.length > 0" class="rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="px-6 py-4 border-b bg-gray-100">
                    <h2 class="text-lg font-semibold text-gray-700">Activity Log</h2>
                </div>
                <div class="divide-y max-h-64 overflow-y-auto">
                    <div v-for="log in item.logs" :key="log.id" class="px-6 py-3">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="font-medium capitalize">{{ log.action }}</span>
                                <span v-if="log.user" class="text-sm text-gray-500"> by {{ log.user.name }}</span>
                            </div>
                            <span class="text-xs text-gray-400">{{ new Date(log.created_at).toLocaleString() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfer Modal -->
        <div v-if="showTransferModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="rounded-xl border border-gray-100 bg-white shadow-sm-xl w-full max-w-md mx-4">
                <div class="px-6 py-4 border-b" style="background-color: #16a34a;">
                    <h2 class="text-lg font-semibold text-white">Record Transfer</h2>
                </div>
                <form @submit.prevent="submitTransfer" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Campus *</label>
                        <select
                            v-model="transferForm.campus"
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            required
                        >
                            <option value="iligan">Iligan Campus</option>
                            <option value="butuan">Butuan Campus</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                        <input
                            v-model.number="transferForm.quantity"
                            type="number"
                            min="1"
                            :max="availableQuantity"
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            required
                        />
                        <p class="text-xs text-gray-500 mt-1">Available: {{ availableQuantity }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transfer Date *</label>
                        <input
                            v-model="transferForm.transfer_date"
                            type="date"
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            required
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Received By</label>
                        <input
                            v-model="transferForm.received_by"
                            type="text"
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            placeholder="Name of receiver"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea
                            v-model="transferForm.notes"
                            rows="2"
                            class="w-full rounded-md border-gray-300 shadow-sm"
                            placeholder="Additional notes..."
                        ></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button
                            type="button"
                            @click="showTransferModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="transferForm.processing"
                            class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white hover:opacity-90 disabled:opacity-50"
                            style="background-color: #16a34a;"
                        >
                            {{ transferForm.processing ? 'Saving...' : 'Record Transfer' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <ConfirmModal :show="showDeleteModal" title="Delete Inventory Item" message="Are you sure you want to delete this inventory item?" confirmLabel="Yes, Delete" confirmClass="bg-red-600 hover:bg-red-700" @confirmed="doDelete" @cancelled="showDeleteModal = false" />
    </AdminLayout>
</template>
