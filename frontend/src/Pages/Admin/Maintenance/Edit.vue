<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import MaintenanceUnitPicker from '@/Components/MaintenanceUnitPicker.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps<{
    maintenance: {
        id: number;
        equipment_id: number;
        equipment_unit_id?: number | null;
        type: string;
        description: string;
        cost: number;
        maintenance_date: string;
        next_maintenance_date?: string;
        status: string;
        notes?: string;
    };
    equipment: Array<{
        id: number;
        name: string;
        serial_number: string;
    }>;
}>();

const form = useForm({
    equipment_id: props.maintenance.equipment_id,
    equipment_unit_id: (props.maintenance.equipment_unit_id ?? '') as number | string,
    type: props.maintenance.type,
    description: props.maintenance.description,
    cost: props.maintenance.cost || '',
    maintenance_date: props.maintenance.maintenance_date,
    next_maintenance_date: props.maintenance.next_maintenance_date || '',
    status: props.maintenance.status,
    notes: props.maintenance.notes || '',
});

const submit = () => {
    form.put(route('admin.maintenance.update', props.maintenance.id));
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Edit Maintenance Log</h1>
        </template>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="space-y-6 bg-white p-6 rounded-lg shadow">
                <div>
                    <InputLabel value="Equipment" />
                    <select
                        v-model="form.equipment_id"
                        class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        required
                    >
                        <option value="">Select Equipment</option>
                        <option v-for="eq in equipment" :key="eq.id" :value="eq.id">
                            {{ eq.name }} ({{ eq.serial_number || 'No S/N' }})
                        </option>
                    </select>
                    <InputError :message="form.errors.equipment_id" class="mt-2" />
                </div>

                <MaintenanceUnitPicker :equipment="equipment" :equipment-id="form.equipment_id" v-model="form.equipment_unit_id" :error="form.errors.equipment_unit_id" />

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Maintenance Type" />
                        <select
                            v-model="form.type"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        >
                            <option value="routine">Routine</option>
                            <option value="repair">Repair</option>
                            <option value="inspection">Inspection</option>
                            <option value="calibration">Calibration</option>
                            <option value="replacement">Replacement</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Status" />
                        <select
                            v-model="form.status"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        >
                            <option value="scheduled">Scheduled</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div>
                    <InputLabel value="Description" />
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        required
                    ></textarea>
                    <InputError :message="form.errors.description" class="mt-2" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Maintenance Date" />
                        <TextInput v-model="form.maintenance_date" type="date" class="mt-1 block w-full" required />
                    </div>
                    <div>
                        <InputLabel value="Next Maintenance Date" />
                        <TextInput v-model="form.next_maintenance_date" type="date" class="mt-1 block w-full" />
                    </div>
                </div>

                <div>
                    <InputLabel value="Cost (₱)" />
                    <TextInput v-model="form.cost" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                </div>

                <div>
                    <InputLabel value="Notes" />
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                    ></textarea>
                </div>

                <div class="flex items-center gap-4">
                    <PrimaryButton :disabled="form.processing">
                        Update Maintenance Log
                    </PrimaryButton>
                    <Link :href="route('admin.maintenance.index')" class="text-gray-600 hover:text-gray-900">
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
