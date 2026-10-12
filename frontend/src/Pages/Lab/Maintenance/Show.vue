<script setup lang="ts">
import LabLayout from '@/Layouts/LabLayout.vue';
import BackButton from '@/Components/BackButton.vue';

defineProps<{
    maintenance: any;
}>();

const typeBadge: Record<string, string> = {
    routine:     'bg-blue-100 text-blue-800',
    repair:      'bg-red-100 text-red-800',
    inspection:  'bg-yellow-100 text-yellow-800',
    calibration: 'bg-purple-100 text-purple-800',
    replacement: 'bg-orange-100 text-orange-800',
};

const statusBadge: Record<string, string> = {
    scheduled:   'bg-gray-100 text-gray-700',
    in_progress: 'bg-yellow-100 text-yellow-800',
    completed:   'bg-green-100 text-green-800',
    cancelled:   'bg-red-100 text-red-700',
};
</script>

<template>
    <LabLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <h1 class="text-xl font-semibold text-gray-800">Maintenance Record #{{ maintenance.id }}</h1>
                <BackButton :href="route('lab.maintenance.index')">Back to List</BackButton>
            </div>
        </template>

        <div class="max-w-2xl rounded-xl border border-gray-100 bg-white shadow-sm p-6 space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">Equipment</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ maintenance.equipment?.name }}</p>
                    <p v-if="maintenance.unit" class="text-xs text-gray-600">Unit <span class="font-mono">{{ maintenance.unit.unit_code }}</span> · S/N <span class="font-mono">{{ maintenance.unit.serial_number || 'not recorded' }}</span></p>
                    <p v-else class="text-xs text-gray-400">{{ maintenance.equipment?.serial_number }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">Type</p>
                    <span :class="typeBadge[maintenance.type] ?? 'bg-gray-100 text-gray-700'" class="inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium capitalize">
                        {{ maintenance.type }}
                    </span>
                </div>

                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">Status</p>
                    <span :class="statusBadge[maintenance.status] ?? 'bg-gray-100 text-gray-700'" class="inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium capitalize">
                        {{ maintenance.status?.replace('_', ' ') }}
                    </span>
                </div>

                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">Performed By</p>
                    <p class="mt-1 text-sm text-gray-900">{{ maintenance.performer?.name ?? '—' }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">Maintenance Date</p>
                    <p class="mt-1 text-sm text-gray-900">{{ new Date(maintenance.maintenance_date).toLocaleDateString() }}</p>
                </div>

                <div v-if="maintenance.next_maintenance_date">
                    <p class="text-xs font-medium text-gray-500 uppercase">Next Maintenance</p>
                    <p class="mt-1 text-sm text-gray-900">{{ new Date(maintenance.next_maintenance_date).toLocaleDateString() }}</p>
                </div>

                <div v-if="maintenance.cost">
                    <p class="text-xs font-medium text-gray-500 uppercase">Cost</p>
                    <p class="mt-1 text-sm text-gray-900">₱{{ Number(maintenance.cost).toFixed(2) }}</p>
                </div>
            </div>

            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Description</p>
                <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ maintenance.description }}</p>
            </div>

            <div v-if="maintenance.notes">
                <p class="text-xs font-medium text-gray-500 uppercase">Findings / Remarks</p>
                <p class="mt-1 text-sm text-gray-500 whitespace-pre-wrap">{{ maintenance.notes }}</p>
            </div>
        </div>
    </LabLayout>
</template>
