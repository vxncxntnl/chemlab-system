<script setup lang="ts">
import { formatDateTime } from '@/utils/date';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import BackButton from '@/Components/BackButton.vue';
import DamageReportRejectForm from '@/Components/DamageReportRejectForm.vue';
import DamageReportDecisions from '@/Components/DamageReportDecisions.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    report: {
        id: number;
        borrowing_id: number;
        equipment_id: number;
        reported_by: number;
        description: string;
        status: string;
        date_reported: string;
        resolved_date: string | null;
        created_at: string;
        borrowing: {
            id: number;
            borrow_date: string;
            return_date: string;
            status: string;
            user: {
                id: number;
                name: string;
                email: string;
            };
        };
        equipment: {
            id: number;
            name: string;
            category: string;
        };
        reporter: {
            id: number;
            name: string;
        };
    };
    history: any[];
    rejectReasons: string[];
    canReopen: boolean;
    reopenDays: number;
}>();

const getStatusBadge = (status: string) => {
    const variants: Record<string, 'warning' | 'primary' | 'success' | 'danger'> = {
        pending: 'warning',
        assessed: 'primary',
        resolved: 'success',
        rejected: 'danger',
    };
    return variants[status] || 'default';
};

const showAssessModal = ref(false);
const showResolveModal = ref(false);
const showRejectForm = ref(false);
const showReopenModal = ref(false);
const doReopen = () => { router.post(route('admin.damage-reports.reopen', props.report.id), {}, { preserveScroll: true }); showReopenModal.value = false; };
const doAssess = () => { router.post(route('admin.damage-reports.assess', props.report.id)); showAssessModal.value = false; };
const doResolve = () => { router.post(route('admin.damage-reports.resolve', props.report.id)); showResolveModal.value = false; };
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-semibold text-gray-800">Damage Report #{{ report.id }}</h1>
                    <BackButton :href="route('admin.damage-reports.index')">Back to Damage Reports</BackButton>
                </div>
                <Badge :variant="getStatusBadge(report.status)" class="text-sm capitalize">{{ report.status?.replace(/_/g, ' ') }}</Badge>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Report Details -->
            <Card title="Report Details">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Equipment</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ report.equipment?.name }}</dd>
                        <dd class="text-xs text-gray-500">{{ report.equipment?.category }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Reported By</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ report.reporter?.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Borrower</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ report.borrowing?.user?.name }}</dd>
                        <dd class="text-xs text-gray-500">{{ report.borrowing?.user?.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Faculty In-Charge</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ (report.borrowing as any)?.faculty?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Borrowing Period</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ new Date(report.borrowing?.borrow_date).toLocaleDateString() }} -
                            {{ new Date(report.borrowing?.return_date).toLocaleDateString() }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Damage Description</dt>
                        <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ report.description }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Damage Photo</dt>
                        <dd class="mt-1">
                            <img
                                v-if="(report as any).photo_path"
                                :src="route('admin.damage-reports.photo', report.id)"
                                alt="Damage photo"
                                class="w-full max-w-xs rounded-lg border border-gray-200 shadow-sm"
                            />
                            <p v-else class="text-sm text-gray-500">No damage photo attached.</p>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Date & Time Reported</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(report.created_at) }}</dd>
                    </div>
                    <div v-if="(report as any).liable_party">
                        <dt class="text-sm font-medium text-gray-500">Liable Party</dt>
                        <dd class="mt-1 text-sm font-semibold" :class="{
                            'text-red-600': (report as any).liable_party === 'student',
                            'text-orange-600': (report as any).liable_party === 'faculty',
                            'text-yellow-600': (report as any).liable_party === 'shared',
                        }">
                            {{ ({ student: 'Student (Borrower)', faculty: 'Faculty (Instructor In-Charge)', shared: 'Shared — Student and Faculty' } as any)[(report as any).liable_party] }}
                        </dd>
                    </div>
                    <div v-if="(report as any).liable_reason" class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Reason / Justification</dt>
                        <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ (report as any).liable_reason }}</dd>
                    </div>
                    <div v-if="report.resolved_date">
                        <dt class="text-sm font-medium text-gray-500">Resolved Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(report.resolved_date) }}</dd>
                    </div>
                </dl>
            </Card>

            <!-- Actions -->
            <Card title="Actions">
                <div class="flex flex-wrap gap-3">
                    <button
                        v-if="report.status === 'pending'"
                        @click="showAssessModal = true"
                        class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                    >
                        Assess Damage
                    </button>
                    <button
                        v-if="report.status === 'assessed'"
                        @click="showResolveModal = true"
                        class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                    >
                        Mark as Resolved
                    </button>
                    <button
                        v-if="['pending', 'assessed'].includes(report.status) && !showRejectForm"
                        @click="showRejectForm = true"
                        class="inline-flex items-center px-4 py-2 rounded-lg border border-red-300 bg-white text-sm font-semibold text-red-700 shadow-sm hover:bg-red-50 transition-colors"
                    >
                        Reject Report
                    </button>
                    <button
                        v-if="canReopen"
                        @click="showReopenModal = true"
                        class="inline-flex items-center px-4 py-2 rounded-lg bg-yellow-500 text-sm font-semibold text-white shadow-sm hover:bg-yellow-600 transition-colors"
                    >
                        Reopen Report
                    </button>
                    <Link :href="route('admin.damage-reports.print', report.id)" target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z" />
                        </svg>
                        Print Incident Report
                    </Link>
                </div>
                <p v-if="report.status === 'rejected' && !canReopen" class="mt-3 text-xs text-gray-500">
                    The {{ reopenDays }}-day reopen period for this rejection has passed.
                </p>

                <DamageReportRejectForm
                    v-if="showRejectForm"
                    :action="route('admin.damage-reports.reject', report.id)"
                    :reasons="rejectReasons"
                    @cancel="showRejectForm = false"
                />
            </Card>

            <DamageReportDecisions :report="report" :history="history" />
        </div>
        <ConfirmModal :show="showAssessModal" title="Assess Damage" message="Mark this damage report as assessed?" confirmLabel="Yes, Assess" confirmClass="bg-green-600 hover:bg-green-700" @confirmed="doAssess" @cancelled="showAssessModal = false" />
        <ConfirmModal :show="showResolveModal" title="Resolve Report" message="Mark this damage report as resolved?" confirmLabel="Yes, Resolve" confirmClass="bg-green-600 hover:bg-green-700" @confirmed="doResolve" @cancelled="showResolveModal = false" />
        <ConfirmModal :show="showReopenModal" title="Reopen Report" message="Reopen this rejected damage report? It goes back to its previous status, and any units the rejection returned to stock are taken back out." confirmLabel="Yes, Reopen" confirmClass="bg-yellow-500 hover:bg-yellow-600" @confirmed="doReopen" @cancelled="showReopenModal = false" />
    </AdminLayout>
</template>
