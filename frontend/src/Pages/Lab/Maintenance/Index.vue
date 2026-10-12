<script setup lang="ts">
import LabLayout from '@/Layouts/LabLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    logs: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        links: Array<any>;
        total: number;
    };
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
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Maintenance Logs</h1>
                <Link
                    :href="route('lab.maintenance.create')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Log Maintenance
                </Link>
            </div>
        </template>

        <div class="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
            <p class="sm:hidden px-4 pt-3 text-xs text-gray-400">◂ Swipe to see more ▸</p>
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Equipment</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Performed By</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 bg-white">
                    <tr v-if="logs.data.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">No maintenance records yet.</td>
                    </tr>
                    <tr v-for="log in logs.data" :key="log.id" class="hover:bg-green-50/30 transition-colors duration-100">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ log.equipment?.name }}<div v-if="log.unit" class="text-xs font-normal text-gray-500 font-mono">{{ log.unit.unit_code }}{{ log.unit.serial_number ? ` · S/N ${log.unit.serial_number}` : '' }}</div></td>
                        <td class="px-4 py-3">
                            <span :class="typeBadge[log.type] ?? 'bg-gray-100 text-gray-700'" class="px-2 py-0.5 rounded-full text-xs font-semibold capitalize">
                                {{ log.type }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="statusBadge[log.status] ?? 'bg-gray-100 text-gray-700'" class="px-2 py-0.5 rounded-full text-xs font-semibold capitalize">
                                {{ log.status?.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ new Date(log.maintenance_date).toLocaleDateString() }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ log.performer?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-center">
                            <Link :href="route('lab.maintenance.show', log.id)" class="text-green-600 hover:text-green-900 font-medium">
                                View
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="logs.last_page > 1" class="mt-4 flex justify-center gap-1">
            <a
                v-for="link in logs.links"
                :key="link.label"
                :href="link.url || '#'"
                :class="[
                    'inline-flex items-center justify-center min-w-[32px] h-8 rounded-lg text-sm border transition-colors',
                    link.active ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50',
                    !link.url ? 'opacity-40 cursor-not-allowed pointer-events-none' : ''
                ]"
                v-html="link.label"
            />
        </div>
    </LabLayout>
</template>
