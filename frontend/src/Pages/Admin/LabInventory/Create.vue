<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BackButton from '@/Components/BackButton.vue';
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    lab: 'chemistry',
    category: 'general',
    item_no: '',
    description: '',
    size: '',
    quantity: 0,
    uom: 'pcs.',
    brand: '',
    storage_location: '',
    condition_remarks: 'Good/Working',
    qty_sent_iligan: 0,
    qty_sent_butuan: 0,
    current_campus_location: '',
});

const submit = () => {
    form.post(route('admin.lab-inventory.store'));
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold" style="color: #16a34a;">Add Lab Inventory Item</h1>
                    <p class="text-sm text-gray-500">Max Suniel Carmen, Cagayan de Oro City - S.Y. 2024-2025</p>
                </div>
                <BackButton :href="route('admin.lab-inventory.index')">Back to List</BackButton>
            </div>
        </template>

        <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Lab and Category -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Laboratory *</label>
                        <select
                            v-model="form.lab"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            required
                        >
                            <option value="chemistry">Chemistry Laboratory</option>
                            <option value="physics">Physics Laboratory</option>
                        </select>
                        <p v-if="form.errors.lab" class="mt-1 text-sm text-red-600">{{ form.errors.lab }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select
                            v-model="form.category"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            required
                        >
                            <option value="general">General Apparatus & Equipment</option>
                            <option value="pasco">PASCO Instruments & Equipment</option>
                        </select>
                        <p v-if="form.errors.category" class="mt-1 text-sm text-red-600">{{ form.errors.category }}</p>
                    </div>
                </div>

                <!-- Item No and Description -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item Number</label>
                        <input
                            v-model="form.item_no"
                            type="text"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="e.g., 1, 2, PS-3201"
                        />
                        <p v-if="form.errors.item_no" class="mt-1 text-sm text-red-600">{{ form.errors.item_no }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                        <input
                            v-model="form.description"
                            type="text"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="e.g., BEAKER, WIRELESS TEMPERATURE SENSOR"
                            required
                        />
                        <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                    </div>
                </div>

                <!-- Size, Quantity, UOM -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Size</label>
                        <input
                            v-model="form.size"
                            type="text"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="e.g., 250mL, BIG, SMALL"
                        />
                        <p v-if="form.errors.size" class="mt-1 text-sm text-red-600">{{ form.errors.size }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                        <input
                            v-model.number="form.quantity"
                            type="number"
                            min="0"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            required
                        />
                        <p v-if="form.errors.quantity" class="mt-1 text-sm text-red-600">{{ form.errors.quantity }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit of Measure</label>
                        <select
                            v-model="form.uom"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="pcs.">pcs.</option>
                            <option value="pc.">pc.</option>
                            <option value="SET">SET</option>
                            <option value="UNIT">UNIT</option>
                            <option value="BOX">BOX</option>
                            <option value="PACK">PACK</option>
                        </select>
                        <p v-if="form.errors.uom" class="mt-1 text-sm text-red-600">{{ form.errors.uom }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Brand</label>
                        <input
                            v-model="form.brand"
                            type="text"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="e.g., PASCO, OHAUS, PYREX"
                        />
                        <p v-if="form.errors.brand" class="mt-1 text-sm text-red-600">{{ form.errors.brand }}</p>
                    </div>
                </div>

                <!-- Storage Location and Condition -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Storage Location</label>
                        <input
                            v-model="form.storage_location"
                            type="text"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="e.g., CD-1, C-3, At top of C-2"
                        />
                        <p v-if="form.errors.storage_location" class="mt-1 text-sm text-red-600">{{ form.errors.storage_location }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Campus Location</label>
                        <input
                            v-model="form.current_campus_location"
                            type="text"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="e.g., Butuan Campus"
                        />
                        <p v-if="form.errors.current_campus_location" class="mt-1 text-sm text-red-600">{{ form.errors.current_campus_location }}</p>
                    </div>
                </div>

                <!-- Condition Remarks -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Condition / Remarks</label>
                    <textarea
                        v-model="form.condition_remarks"
                        rows="3"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="e.g., Good/Working, 5pcs. Broken, Needs Repair"
                    ></textarea>
                    <p v-if="form.errors.condition_remarks" class="mt-1 text-sm text-red-600">{{ form.errors.condition_remarks }}</p>
                </div>

                <!-- Transfer Quantities -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Qty Sent to Iligan</label>
                        <input
                            v-model.number="form.qty_sent_iligan"
                            type="number"
                            min="0"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                        <p v-if="form.errors.qty_sent_iligan" class="mt-1 text-sm text-red-600">{{ form.errors.qty_sent_iligan }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Qty Sent to Butuan</label>
                        <input
                            v-model.number="form.qty_sent_butuan"
                            type="number"
                            min="0"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                        <p v-if="form.errors.qty_sent_butuan" class="mt-1 text-sm text-red-600">{{ form.errors.qty_sent_butuan }}</p>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end gap-4">
                    <Link
                        :href="route('admin.lab-inventory.index')"
                        class="px-4 py-2 border border-gray-300 rounded-md font-semibold text-sm text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 border border-transparent rounded-md font-semibold text-sm text-white hover:opacity-90 disabled:opacity-50"
                        style="background-color: #16a34a;"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Item' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
