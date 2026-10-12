<script setup lang="ts">
/**
 * For serial-tracked equipment, choose which unit (serial number) is being
 * maintained. Hidden for items counted by quantity only. Used on the admin
 * and lab staff maintenance forms.
 */
import InputError from '@/Components/InputError.vue';
import { computed, watch } from 'vue';

type Option = { id: number; units?: Array<{ id: number; label: string; under_maintenance: boolean }> };

const props = defineProps<{
    equipment: Option[];
    equipmentId: number | string;
    modelValue: number | string | null;
    error?: string;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: number | string] }>();

const units = computed(() => props.equipment.find(e => String(e.id) === String(props.equipmentId))?.units ?? []);

// A different item was picked: clear a unit that belongs to the old one.
watch(() => props.equipmentId, () => {
    if (props.modelValue && !units.value.some(u => String(u.id) === String(props.modelValue))) {
        emit('update:modelValue', '');
    }
});
</script>

<template>
    <div v-if="units.length">
        <label class="block text-sm font-medium text-gray-700">Which unit (serial number)? <span class="text-red-500">*</span></label>
        <select
            :value="modelValue ?? ''"
            @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
            required
            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
        >
            <option value="">Select the unit</option>
            <option v-for="u in units" :key="u.id" :value="u.id">
                {{ u.label }}{{ u.under_maintenance ? ' — already under maintenance' : '' }}
            </option>
        </select>
        <p class="mt-1 text-xs text-gray-500">While this log is Scheduled or In Progress, the unit shows “Under Maintenance” on the equipment's page.</p>
        <InputError :message="error" class="mt-1" />
    </div>
</template>
