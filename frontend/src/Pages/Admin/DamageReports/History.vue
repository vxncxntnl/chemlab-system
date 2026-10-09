<script setup lang="ts">
/**
 * Damage Report History: every action on this campus's damage reports —
 * recorded, assessed, edited, rejected, reopened, resolved — with who did
 * it, when, and (for edits) what changed.
 */
import { formatDateTime } from '@/utils/date';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import BackButton from '@/Components/BackButton.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    entries: {
        data: Array<{
            id: number;
            report_id: number;
            equipment: string | null;
            borrower: string | null;
            action: string;
            reason: string | null;
            note: string | null;
            changes: Array<{ field: string; from: any; to: any }>;
            user: string | null;
            role: string | null;
            created_at: string;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { action?: string };
}>();

const actionFilter = ref(props.filters.action || '');
const applyFilter = () => {
    router.get(route('admin.damage-reports.history'), { action: actionFilter.value }, { preserveState: true, replace: true });
};

const columns = [
    { key: 'created_at', label: 'Date & Time' },
    { key: 'report_id', label: 'Report' },
    { key: 'action', label: 'Action' },
    { key: 'user', label: 'By' },
    { key: 'details', label: 'Details' },
];

const show = (v: any) =>
    v === null || v === undefined || v === ''
        ? '—'
        : ({ student: 'Student', faculty: 'Faculty', shared: 'Shared' } as Record<string, string>)[v] ?? String(v);

const roleLabel = (role: string | null) =>
    ({ admin: 'Admin', lab_staff: 'Lab Staff', faculty: 'Faculty', student: 'Student' } as Record<string, string>)[role ?? ''] ?? '';

const dot = (action: string) =>
    /reject/i.test(action) ? 'bg-red-500' : /reopen/i.test(action) ? 'bg-yellow-500' : /edit/i.test(action) ? 'bg-blue-500' : 'bg-green-500';
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4 flex-wrap">
                <h1 class="text-xl font-semibold text-gray-800">Damage Report History</h1>
                <BackButton :href="route('admin.damage-reports.index')">Back to Damage Reports</BackButton>
            </div>
        </template>

        <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-3">
            <select
                v-model="actionFilter"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                @change="applyFilter"
            >
                <option value="">All Actions</option>
                <option value="Recorded">Recorded</option>
                <option value="Assessed">Assessed</option>
                <option value="Edited">Edited</option>
                <option value="Rejected">Rejected</option>
                <option value="Reopened">Reopened</option>
                <option value="Resolved">Resolved</option>
            </select>
        </div>

        <DataTable :columns="columns" :data="entries.data" :pagination="entries">
            <template #cell-created_at="{ value }">
                <span class="whitespace-nowrap">{{ formatDateTime(value) }}</span>
            </template>
            <template #cell-report_id="{ row }">
                <Link :href="route('admin.damage-reports.show', row.report_id)" class="font-medium text-green-700 hover:text-green-900">
                    #{{ row.report_id }}
                </Link>
                <div class="text-xs text-gray-500">{{ row.equipment ?? '—' }}<span v-if="row.borrower"> · {{ row.borrower }}</span></div>
            </template>
            <template #cell-action="{ value }">
                <span class="inline-flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full" :class="dot(value)" />
                    {{ value }}
                </span>
            </template>
            <template #cell-user="{ row }">
                {{ row.user ?? 'Unknown user' }}
                <div v-if="row.role" class="text-xs text-gray-500">{{ roleLabel(row.role) }}</div>
            </template>
            <template #cell-details="{ row }">
                <div class="max-w-md text-sm text-gray-600 space-y-0.5">
                    <p v-if="row.reason" class="break-words">Reason: {{ row.reason }}</p>
                    <p v-if="row.note" class="break-words">Note: {{ row.note }}</p>
                    <p v-for="c in row.changes" :key="c.field" class="break-words">
                        {{ c.field }}: <span class="text-gray-400 line-through">{{ show(c.from) }}</span> → <span class="font-medium text-gray-800">{{ show(c.to) }}</span>
                    </p>
                    <p v-if="!row.reason && !row.note && !row.changes.length" class="text-gray-400">—</p>
                </div>
            </template>
            <template #empty>
                <p class="py-8 text-center text-sm text-gray-500">No damage report activity yet.</p>
            </template>
        </DataTable>
    </AdminLayout>
</template>
