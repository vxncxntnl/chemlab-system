<script setup lang="ts">
import LabLayout from '@/Layouts/LabLayout.vue';
import DataTable from '@/Components/DataTable.vue';

defineProps<{
    logs: {
        data: Array<{
            id: number;
            action: string;
            table_affected: string | null;
            record_id: number | null;
            created_at: string;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
}>();

const columns = [
    { key: 'created_at', label: 'Date/Time' },
    { key: 'action', label: 'Activity' },
    { key: 'table_affected', label: 'Related To' },
];
</script>

<template>
    <LabLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">My History</h1>
        </template>

        <div class="space-y-6">
            <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-4">
                <p class="text-sm text-gray-500">
                    A record of your activity — equipment releases, returns processed, damage report assessments, and inventory changes.
                </p>
            </div>

            <DataTable :columns="columns" :data="logs.data" :pagination="logs">
                <template #cell-created_at="{ value }">
                    <div class="text-sm">
                        <div class="text-gray-900">{{ new Date(value).toLocaleDateString() }}</div>
                        <div class="text-gray-500">{{ new Date(value).toLocaleTimeString() }}</div>
                    </div>
                </template>
                <template #cell-action="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="{
                            'bg-green-100 text-green-800': value.toLowerCase().includes('created') || value.toLowerCase().includes('released') || value.toLowerCase().includes('returned') || value.toLowerCase().includes('resolved'),
                            'bg-red-100 text-red-800': value.toLowerCase().includes('rejected') || value.toLowerCase().includes('cancelled'),
                            'bg-gray-100 text-gray-800': !/(created|released|returned|resolved|rejected|cancelled)/i.test(value),
                        }"
                    >
                        {{ value }}
                    </span>
                </template>
                <template #cell-table_affected="{ value }">
                    <span class="text-gray-600 capitalize">{{ value?.replace(/_/g, ' ') || '—' }}</span>
                </template>
            </DataTable>

            <div v-if="logs.data.length === 0" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No activity yet</h3>
                <p class="mt-1 text-sm text-gray-500">Your history will appear here once you process a request.</p>
            </div>
        </div>
    </LabLayout>
</template>
