<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import QrCode from '@/Components/QrCode.vue';
import BackButton from '@/Components/BackButton.vue';
import EquipmentUnits from '@/Components/EquipmentUnits.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    equipment: {
        id: number;
        equipment_code: string | null;
        name: string;
        description: string | null;
        category: string | null;
        serial_number: string | null;
        tracks_units?: boolean;
        condition: string;
        is_available: boolean;
        image_path: string | null;
        course_restriction: string | null;
        created_at: string;
        updated_at: string;
        inventory: Array<{
            id: number;
            total_quantity: number;
            available_quantity: number;
            reorder_level: number;
            campus: { id: number; campus_name: string };
            location: { id: number; location_name: string };
            stock_adjustments: Array<{
                id: number;
                type: 'in' | 'out';
                quantity: number;
                reason: string;
                created_at: string;
                performer: { id: number; name: string };
            }>;
        }>;
        borrowing_equipment: Array<{
            id: number;
            quantity: number;
            borrowing: {
                id: number;
                status: string;
                borrow_date: string;
                return_date: string;
                user: {
                    id: number;
                    name: string;
                };
            };
        }>;
    };
    units: Array<any>;
}>();

// Adjust Stock Modal
const showModal = ref(false);
const selectedInventory = ref<number | null>(null);

const adjustForm = useForm({
    type: 'in',
    quantity: 1,
    reason: '',
});

const openModal = (inventoryId: number) => {
    selectedInventory.value = inventoryId;
    adjustForm.reset();
    adjustForm.type = 'in';
    adjustForm.quantity = 1;
    showModal.value = true;
};

const submitAdjust = () => {
    if (!selectedInventory.value) return;
    adjustForm.post(route('admin.inventory.adjust', { inventory: selectedInventory.value }), {
        onSuccess: () => { showModal.value = false; },
    });
};

const getConditionBadge = (condition: string) => {
    const variants: Record<string, any> = {
        new: 'success',
        good: 'primary',
        fair: 'warning',
        poor: 'danger',
        damaged: 'danger',
    };
    return variants[condition] || 'default';
};

const getStatusBadge = (status: string) => {
    const variants: Record<string, any> = {
        pending: 'warning',
        approved: 'info',
        borrowed: 'primary',
        returned: 'success',
        overdue: 'danger',
        cancelled: 'default',
    };
    return variants[status] || 'default';
};

const printEquipment = () => {
    window.print();
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-semibold text-gray-800">{{ equipment.name }}</h1>
                    <BackButton :href="route('admin.equipment.index')">Back to Equipment</BackButton>
                </div>
                <div class="flex space-x-2">
                    <button
                        @click="printEquipment"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                    >
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print
                    </button>
                    <Link
                        :href="route('admin.equipment.edit', equipment.id)"
                        class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                    >
                        Edit
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6 print:space-y-4">
            <!-- Equipment Details -->
            <Card title="Equipment Details">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-if="equipment.image_path" class="sm:col-span-2 lg:col-span-1">
                        <img
                            :src="`/storage/${equipment.image_path}`"
                            :alt="equipment.name"
                            class="h-48 w-full object-cover rounded-lg"
                        />
                    </div>

                    <div :class="equipment.image_path ? 'lg:col-span-2' : 'sm:col-span-2 lg:col-span-3'">
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Equipment ID</dt>
                                <dd class="mt-1 text-sm font-mono text-gray-900">{{ equipment.equipment_code }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Category</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ equipment.category || 'Uncategorized' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Serial Number</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ equipment.serial_number || 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Condition</dt>
                                <dd class="mt-1">
                                    <Badge :variant="getConditionBadge(equipment.condition)">
                                        {{ equipment.condition }}
                                    </Badge>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Availability</dt>
                                <dd class="mt-1">
                                    <Badge :variant="equipment.is_available ? 'success' : 'danger'">
                                        {{ equipment.is_available ? 'Available' : 'Unavailable' }}
                                    </Badge>
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Description</dt>
                                <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ equipment.description || 'No description available' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Created</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ new Date(equipment.created_at).toLocaleString() }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ new Date(equipment.updated_at).toLocaleString() }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </Card>

            <!-- QR Code -->
            <Card title="Equipment QR Code">
                <div class="flex flex-col sm:flex-row items-start gap-8">
                    <QrCode
                        :value="`EQP:${equipment.equipment_code ?? equipment.id}|${equipment.name}`"
                        :label="equipment.equipment_code ?? String(equipment.id)"
                        :size="180"
                    />
                    <div class="text-sm text-gray-600 space-y-2">
                        <p class="font-medium text-gray-900">How to use this QR code:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Print and attach it to the equipment</li>
                            <li>Lab staff can scan it when releasing or receiving equipment</li>
                            <li>Quickly identifies the equipment code: <strong>{{ equipment.equipment_code }}</strong></li>
                        </ul>
                        <p class="text-xs text-gray-400 pt-2">Click "Download QR" below the code to save as PNG.</p>
                    </div>
                </div>
            </Card>

            <!-- Inventory -->
            <Card title="Inventory by Location">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Campus</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Available</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Borrowed</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="inv in equipment.inventory" :key="inv.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ inv.campus.campus_name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ inv.location.location_name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                    {{ inv.total_quantity }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-green-600 font-semibold">
                                    {{ inv.available_quantity }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-blue-600">
                                    {{ inv.total_quantity - inv.available_quantity }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <Badge :variant="inv.available_quantity <= inv.reorder_level ? 'danger' : 'success'">
                                        {{ inv.available_quantity <= inv.reorder_level ? 'Low Stock' : 'In Stock' }}
                                    </Badge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <button @click="openModal(inv.id)"
                                        class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-green-600 text-white hover:bg-green-700 transition-colors">
                                        Adjust Stock
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="equipment.inventory.length === 0">
                                <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No inventory records found
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <!-- Serial-tracked items: each unit, its serial number and maintenance status -->
            <EquipmentUnits v-if="equipment.tracks_units" :equipment-id="equipment.id" :units="units"
                :in-stock="equipment.inventory.reduce((n, i) => n + i.total_quantity, 0)" />

            <!-- Stock History -->
            <Card v-for="inv in equipment.inventory" :key="'history-' + inv.id"
                :title="`Stock History — ${inv.campus.campus_name}`">
                <div v-if="inv.stock_adjustments.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Quantity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reason</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Performed By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="adj in inv.stock_adjustments" :key="adj.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span :class="adj.type === 'in'
                                        ? 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800'
                                        : 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800'">
                                        {{ adj.type === 'in' ? 'Stock In' : 'Stock Out' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold"
                                    :class="adj.type === 'in' ? 'text-green-600' : 'text-red-600'">
                                    {{ adj.type === 'in' ? '+' : '-' }}{{ adj.quantity }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ adj.reason }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ adj.performer.name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-400">
                                    {{ new Date(adj.created_at).toLocaleString() }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-gray-400 text-center py-4">No stock adjustments yet.</p>
            </Card>

            <!-- Active Borrowings -->
            <Card title="Active Borrowings" class="print:hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Borrower</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Quantity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Borrow Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Return Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <template v-for="be in equipment.borrowing_equipment" :key="be.id">
                                <tr v-if="['pending', 'approved', 'borrowed', 'overdue'].includes(be.borrowing.status)">
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                        {{ be.borrowing.user.name }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                        {{ be.quantity }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                        {{ new Date(be.borrowing.borrow_date).toLocaleDateString() }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                        {{ new Date(be.borrowing.return_date).toLocaleDateString() }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <Badge :variant="getStatusBadge(be.borrowing.status)">
                                            {{ be.borrowing.status }}
                                        </Badge>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                        <Link :href="route('admin.borrowings.show', be.borrowing.id)" class="text-green-600 hover:text-green-900">
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!equipment.borrowing_equipment.some(be => ['pending', 'approved', 'borrowed', 'overdue'].includes(be.borrowing.status))">
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No active borrowings
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <!-- Adjust Stock Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Adjust Stock</h2>
                <form @submit.prevent="submitAdjust" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <div class="flex gap-3">
                            <button type="button" @click="adjustForm.type = 'in'"
                                :class="['flex-1 py-2 rounded-lg text-sm font-semibold border transition-colors',
                                    adjustForm.type === 'in'
                                        ? 'bg-green-600 text-white border-green-600'
                                        : 'bg-white text-gray-600 border-gray-300 hover:border-green-400']">
                                + Stock In (Add)
                            </button>
                            <button type="button" @click="adjustForm.type = 'out'"
                                :class="['flex-1 py-2 rounded-lg text-sm font-semibold border transition-colors',
                                    adjustForm.type === 'out'
                                        ? 'bg-red-600 text-white border-red-600'
                                        : 'bg-white text-gray-600 border-gray-300 hover:border-red-400']">
                                - Stock Out (Remove)
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                        <input v-model.number="adjustForm.quantity" type="number" min="1" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                        <input v-model="adjustForm.reason" type="text" required
                            placeholder="e.g. Purchased new batch, Broken during experiment"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" :disabled="adjustForm.processing"
                            :class="['flex-1 py-2.5 rounded-lg text-sm font-semibold text-white transition-colors disabled:opacity-50',
                                adjustForm.type === 'in' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700']">
                            {{ adjustForm.processing ? 'Saving...' : 'Confirm' }}
                        </button>
                        <button type="button" @click="showModal = false"
                            class="flex-1 py-2.5 rounded-lg text-sm font-semibold border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<style>
@media print {
    nav, .print\\:hidden, button, a {
        display: none !important;
    }
    .shadow {
        box-shadow: none !important;
    }
}
</style>
