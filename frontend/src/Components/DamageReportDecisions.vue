<script setup lang="ts">
/**
 * Who decided what on a damage report: the rejection (if rejected) and the
 * full history of actions with names and times. Used by both the admin and
 * lab staff damage report pages.
 */
import Card from '@/Components/Card.vue';
import { formatDateTime } from '@/utils/date';

defineProps<{
    report: any;
    history: Array<{ id: number; action: string; reason: string | null; note?: string | null; changes?: Array<{ field: string; from: any; to: any }>; user: string | null; role: string | null; created_at: string }>;
}>();

/** Readable value for an edited field (liable party codes, empty values). */
const show = (v: any) =>
    v === null || v === undefined || v === ''
        ? '—'
        : ({ student: 'Student', faculty: 'Faculty', shared: 'Shared' } as Record<string, string>)[v] ?? String(v);

const roleLabel = (role: string | null) =>
    ({ admin: 'Admin', lab_staff: 'Lab Staff', faculty: 'Faculty', student: 'Student' } as Record<string, string>)[role ?? ''] ?? '';
</script>

<template>
    <Card v-if="report.status === 'rejected'" title="Rejected — No Replacement Required">
        <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Reason</dt>
                <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ report.rejection_reason }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Rejected By</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ report.rejecter?.name ?? '—' }}
                    <span v-if="report.rejecter?.role" class="text-xs text-gray-500">({{ roleLabel(report.rejecter.role) }})</span>
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Date & Time Rejected</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(report.rejected_at) }}</dd>
            </div>
            <div v-if="report.rejection_restocked_qty > 0" class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Stock</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ report.rejection_restocked_qty }} unit(s) returned to available stock.</dd>
            </div>
        </dl>
    </Card>

    <Card v-if="history.length" title="Decision History">
        <ol class="space-y-3">
            <li v-for="entry in history" :key="entry.id" class="flex gap-3 text-sm">
                <span class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full"
                    :class="/reject/i.test(entry.action) ? 'bg-red-500' : /reopen/i.test(entry.action) ? 'bg-yellow-500' : /edit/i.test(entry.action) ? 'bg-blue-500' : 'bg-green-500'" />
                <div class="min-w-0">
                    <p class="text-gray-900">
                        <span class="font-medium">{{ entry.action }}</span>
                        — {{ entry.user ?? 'Unknown user' }}<span v-if="entry.role" class="text-gray-500"> ({{ roleLabel(entry.role) }})</span>
                    </p>
                    <p v-if="entry.reason" class="text-gray-600 break-words">Reason: {{ entry.reason }}</p>
                    <p v-if="entry.note" class="text-gray-600 break-words">Note: {{ entry.note }}</p>
                    <ul v-if="entry.changes?.length" class="mt-1 space-y-0.5 text-gray-600">
                        <li v-for="c in entry.changes" :key="c.field" class="break-words">
                            {{ c.field }}: <span class="text-gray-400 line-through">{{ show(c.from) }}</span> → <span class="font-medium text-gray-800">{{ show(c.to) }}</span>
                        </li>
                    </ul>
                    <p class="text-xs text-gray-500">{{ formatDateTime(entry.created_at) }}</p>
                </div>
            </li>
        </ol>
    </Card>
</template>
