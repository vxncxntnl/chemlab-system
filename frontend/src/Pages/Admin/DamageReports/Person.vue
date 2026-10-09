<script setup lang="ts">
/**
 * One student's or faculty member's damage history at this campus: every
 * damage report where they were the borrower or the faculty in-charge.
 */
import { formatDateTime } from '@/utils/date';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import DataTable from '@/Components/DataTable.vue';
import BackButton from '@/Components/BackButton.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    person: {
        id: number;
        name: string;
        email: string;
        role: 'student' | 'faculty';
        department: string | null;
        id_number: string | null;
        campus: string | null;
    };
    reports: Array<{
        id: number;
        equipment: string | null;
        quantity: number;
        description: string;
        liable_party: string | null;
        status: string;
        created_at: string;
        borrowing_id: number;
        involvement: string;
        borrower: string | null;
        faculty: string | null;
    }>;
    summary: { total: number; open: number; resolved: number; rejected: number; units: number };
}>();

const columns = [
    { key: 'created_at', label: 'Date Reported' },
    { key: 'id', label: 'Report' },
    { key: 'quantity', label: 'Qty' },
    { key: 'involvement', label: 'Involvement' },
    { key: 'liable_party', label: 'Liable Party' },
    { key: 'status', label: 'Status' },
];

const statusVariant = (s: string) =>
    ({ pending: 'warning', assessed: 'primary', resolved: 'success', rejected: 'danger' } as Record<string, any>)[s] ?? 'default';

const liableLabel = (v: string | null) =>
    v ? ({ student: 'Student', faculty: 'Faculty', shared: 'Shared' } as Record<string, string>)[v] ?? v : 'To be determined';
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4 flex-wrap">
                <h1 class="text-xl font-semibold text-gray-800">Damage History — {{ person.name }}</h1>
                <BackButton :href="route('admin.damage-reports.people')">Back to List</BackButton>
            </div>
        </template>

        <div class="space-y-6">
            <Card title="Details">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ person.name }}</dd>
                        <dd class="text-xs text-gray-500">{{ person.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ person.role === 'faculty' ? 'Faculty' : 'Student' }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ person.id_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ person.role === 'faculty' ? 'Department' : 'Course' }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ person.department ?? '—' }}</dd>
                        <dd class="text-xs text-gray-500">{{ person.campus }}</dd>
                    </div>
                </dl>

                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div class="rounded-lg bg-gray-50 px-3 py-2">
                        <p class="text-xs text-gray-500">Damage Reports</p>
                        <p class="text-lg font-semibold text-gray-900">{{ summary.total }}</p>
                    </div>
                    <div class="rounded-lg bg-yellow-50 px-3 py-2">
                        <p class="text-xs text-yellow-700">Open</p>
                        <p class="text-lg font-semibold text-yellow-800">{{ summary.open }}</p>
                    </div>
                    <div class="rounded-lg bg-green-50 px-3 py-2">
                        <p class="text-xs text-green-700">Resolved</p>
                        <p class="text-lg font-semibold text-green-800">{{ summary.resolved }}</p>
                    </div>
                    <div class="rounded-lg bg-red-50 px-3 py-2">
                        <p class="text-xs text-red-700">Rejected</p>
                        <p class="text-lg font-semibold text-red-800">{{ summary.rejected }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2">
                        <p class="text-xs text-gray-500">Damaged Units</p>
                        <p class="text-lg font-semibold text-gray-900">{{ summary.units }}</p>
                    </div>
                </div>
            </Card>

            <DataTable :columns="columns" :data="reports">
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap">{{ formatDateTime(value) }}</span>
                </template>
                <template #cell-id="{ row }">
                    <Link :href="route('admin.damage-reports.show', row.id)" class="font-medium text-green-700 hover:text-green-900">
                        #{{ row.id }} — {{ row.equipment ?? 'Item' }}
                    </Link>
                    <div class="max-w-xs truncate text-xs text-gray-500" :title="row.description">{{ row.description }}</div>
                </template>
                <template #cell-involvement="{ row }">
                    {{ row.involvement }}
                    <div class="text-xs text-gray-500">
                        Borrowing #{{ row.borrowing_id }}<span v-if="row.involvement !== 'Borrower' && row.borrower"> · {{ row.borrower }}</span>
                    </div>
                </template>
                <template #cell-liable_party="{ value }">
                    {{ liableLabel(value) }}
                </template>
                <template #cell-status="{ value }">
                    <Badge :variant="statusVariant(value)" class="capitalize">{{ value }}</Badge>
                </template>
                <template #empty>
                    <p class="py-8 text-center text-sm text-gray-500">No damage reports for this person.</p>
                </template>
            </DataTable>
        </div>
    </AdminLayout>
</template>
