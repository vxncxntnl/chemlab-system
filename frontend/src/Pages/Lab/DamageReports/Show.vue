<script setup lang="ts">
import { formatDateTime } from '@/utils/date';
import LabLayout from '@/Layouts/LabLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import BackButton from '@/Components/BackButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DamageReportRejectForm from '@/Components/DamageReportRejectForm.vue';
import DamageReportEditForm from '@/Components/DamageReportEditForm.vue';
import DamageReportDecisions from '@/Components/DamageReportDecisions.vue';
import DamageReportAffiliations from '@/Components/DamageReportAffiliations.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    report: any;
    history: any[];
    // Borrower's and faculty's email, campus, course/department.
    affiliations: any;
    rejectReasons: string[];
    reopenDays: number;
}>();

const showRejectForm = ref(false);
const showEditForm = ref(false);

const getStatusBadge = (status: string) => {
    const variants: Record<string, any> = {
        pending: 'warning',
        assessed: 'primary',
        resolved: 'success',
        rejected: 'danger',
    };
    return variants[status] || 'default';
};

const showAssessForm = ref(false);
const showResolveModal = ref(false);

const assessForm = useForm({
    photo: null as File | null,
});

const photoPreview = ref<string | null>(null);
const handlePhotoChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
        assessForm.photo = file;
        photoPreview.value = URL.createObjectURL(file);
    }
};

const cancelAssess = () => {
    showAssessForm.value = false;
    assessForm.reset();
    assessForm.clearErrors();
    photoPreview.value = null;
};

const submitAssess = () => {
    assessForm.post(route('lab.damage-reports.assess', props.report.id), {
        forceFormData: true,
        onSuccess: cancelAssess,
    });
};

const doResolve = () => { router.post(route('lab.damage-reports.resolve', props.report.id)); showResolveModal.value = false; };
</script>

<template>
    <LabLayout>
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-semibold text-gray-800">
                        Damage Report #{{ report.id }}
                    </h1>
                    <BackButton :href="route('lab.damage-reports.index')">Back to Damage Reports</BackButton>
                </div>
                <Badge :variant="getStatusBadge(report.status)" class="text-sm capitalize">{{ report.status?.replace(/_/g, ' ') }}</Badge>
            </div>
        </template>

        <div class="space-y-6">
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
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Faculty In-Charge</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ report.borrowing?.faculty?.name ?? '—' }}</dd>
                    </div>
                    <DamageReportAffiliations :affiliations="affiliations" />
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Borrowing Period</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ new Date(report.borrowing?.borrow_date).toLocaleDateString() }} -
                            {{ new Date(report.borrowing?.return_date).toLocaleDateString() }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Date & Time Reported</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(report.created_at) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Damage Description</dt>
                        <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ report.description }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Damage Photo</dt>
                        <dd class="mt-1">
                            <img
                                v-if="report.photo_path"
                                :src="route('lab.damage-reports.photo', report.id)"
                                alt="Damage photo"
                                class="w-full max-w-xs rounded-lg border border-gray-200 shadow-sm"
                            />
                            <p v-else class="text-sm text-gray-500">No damage photo attached.</p>
                        </dd>
                    </div>
                    <div v-if="report.liable_party">
                        <dt class="text-sm font-medium text-gray-500">Liable Party</dt>
                        <dd class="mt-1 text-sm font-semibold" :class="{
                            'text-red-600': report.liable_party === 'student',
                            'text-orange-600': report.liable_party === 'faculty',
                            'text-yellow-600': report.liable_party === 'shared',
                        }">
                            {{ ({ student: 'Student (Borrower)', faculty: 'Faculty (Instructor In-Charge)', shared: 'Shared — Student and Faculty' } as Record<string, string>)[report.liable_party] }}
                        </dd>
                    </div>
                    <div v-if="report.liable_reason" class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Reason / Justification</dt>
                        <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ report.liable_reason }}</dd>
                    </div>
                </dl>
            </Card>

            <Card title="Actions">
                <div class="flex flex-wrap gap-3">
                    <button
                        v-if="report.status === 'pending' && !showAssessForm && !showRejectForm && !showEditForm"
                        @click="showAssessForm = true"
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
                        v-if="['pending', 'assessed'].includes(report.status) && !showRejectForm && !showEditForm && !showAssessForm"
                        @click="showEditForm = true"
                        class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors"
                    >
                        Edit Report
                    </button>
                    <button
                        v-if="['pending', 'assessed'].includes(report.status) && !showRejectForm && !showEditForm && !showAssessForm"
                        @click="showRejectForm = true"
                        class="inline-flex items-center px-4 py-2 rounded-lg border border-red-300 bg-white text-sm font-semibold text-red-700 shadow-sm hover:bg-red-50 transition-colors"
                    >
                        Reject Report
                    </button>
                    <Link :href="route('lab.damage-reports.print', report.id)" target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z" />
                        </svg>
                        Print Incident Report
                    </Link>
                </div>

                <form v-if="showAssessForm" @submit.prevent="submitAssess" class="mt-4 border-t border-gray-100 pt-4 space-y-4">
                    <div>
                        <InputLabel for="photo" :value="report.photo_path ? 'Replace Damage Photo (Optional)' : 'Damage Photo (Optional)'" />
                        <input
                            id="photo"
                            type="file"
                            accept="image/jpeg,image/jpg,image/png,image/webp"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100"
                            @change="handlePhotoChange"
                        />
                        <p class="mt-1 text-xs text-gray-500">Maximum size: 10 MB. Accepted formats: JPG, JPEG, PNG, WebP. On a phone you can take the photo directly.</p>
                        <InputError :message="assessForm.errors.photo" class="mt-2" />
                        <img v-if="photoPreview" :src="photoPreview" alt="Preview" class="mt-2 h-32 w-32 object-cover rounded-lg" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="cancelAssess" class="text-sm text-gray-600 hover:text-gray-900">
                            Cancel
                        </button>
                        <PrimaryButton :disabled="assessForm.processing">Confirm Assessment</PrimaryButton>
                    </div>
                </form>

                <DamageReportEditForm
                    v-if="showEditForm"
                    :action="route('lab.damage-reports.update', report.id)"
                    :report="report"
                    note="The admin will be notified, and the change is recorded in the Decision History."
                    @done="showEditForm = false"
                />

                <DamageReportRejectForm
                    v-if="showRejectForm"
                    :action="route('lab.damage-reports.reject', report.id)"
                    :reasons="rejectReasons"
                    :note="`The admin will be notified and can reopen it within ${reopenDays} days.`"
                    @cancel="showRejectForm = false"
                />
            </Card>

            <DamageReportDecisions :report="report" :history="history" />
        </div>
        <ConfirmModal :show="showResolveModal" title="Resolve Report" message="Mark this damage report as resolved?" confirmLabel="Yes, Resolve" confirmClass="bg-green-600 hover:bg-green-700" @confirmed="doResolve" @cancelled="showResolveModal = false" />
    </LabLayout>
</template>

