<script setup lang="ts">
import LabLayout from '@/Layouts/LabLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import MaintenanceUnitPicker from '@/Components/MaintenanceUnitPicker.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import BackButton from '@/Components/BackButton.vue';
import { Link, useForm } from '@inertiajs/vue3';

defineProps<{
    equipment: Array<{ id: number; name: string; units?: Array<{ id: number; label: string; under_maintenance: boolean }> }>;
}>();

const form = useForm({
    equipment_id:          '',
    equipment_unit_id:     '' as number | string,
    type:                  'routine',
    description:           '',
    cost:                  '',
    maintenance_date:      '',
    next_maintenance_date: '',
    status:                'completed',
    notes:                 '',
});

const submit = () => {
    form.post(route('lab.maintenance.store'));
};
</script>

<template>
    <LabLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Log Maintenance</h1>
                <BackButton :href="route('lab.maintenance.index')">Back to Maintenance Logs</BackButton>
            </div>
        </template>

        <div class="max-w-2xl">
            <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-6">
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <InputLabel for="equipment_id" value="Equipment" />
                        <select
                            id="equipment_id"
                            v-model="form.equipment_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            required
                        >
                            <option value="">Select equipment...</option>
                            <option v-for="eq in equipment" :key="eq.id" :value="eq.id">
                                {{ eq.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.equipment_id" class="mt-1" />
                    </div>

                    <MaintenanceUnitPicker :equipment="equipment" :equipment-id="form.equipment_id" v-model="form.equipment_unit_id" :error="form.errors.equipment_unit_id" />

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <InputLabel for="type" value="Maintenance Type" />
                            <select
                                id="type"
                                v-model="form.type"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required
                            >
                                <option value="routine">Routine</option>
                                <option value="repair">Repair</option>
                                <option value="inspection">Inspection</option>
                                <option value="calibration">Calibration</option>
                                <option value="replacement">Replacement</option>
                            </select>
                            <InputError :message="form.errors.type" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="status" value="Status" />
                            <select
                                id="status"
                                v-model="form.status"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required
                            >
                                <option value="scheduled">Scheduled</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <InputError :message="form.errors.status" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="maintenance_date" value="Maintenance Date" />
                            <TextInput
                                id="maintenance_date"
                                v-model="form.maintenance_date"
                                type="date"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.maintenance_date" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="next_maintenance_date" value="Next Maintenance Date (optional)" />
                            <TextInput
                                id="next_maintenance_date"
                                v-model="form.next_maintenance_date"
                                type="date"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.next_maintenance_date" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="cost" value="Cost (₱) (optional)" />
                            <TextInput
                                id="cost"
                                v-model="form.cost"
                                type="number"
                                step="0.01"
                                min="0"
                                class="mt-1 block w-full"
                                placeholder="0.00"
                            />
                            <InputError :message="form.errors.cost" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="What was done or needs to be done..."
                            required
                        ></textarea>
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="notes" value="Additional Notes (optional)" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Any additional remarks..."
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <Link :href="route('lab.maintenance.index')" class="text-gray-600 hover:text-gray-900 text-sm">
                            Cancel
                        </Link>
                        <PrimaryButton :disabled="form.processing">
                            Save Maintenance Log
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </LabLayout>
</template>
