<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue';
import QrCode from '@/Components/QrCode.vue';
import BackButton from '@/Components/BackButton.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps<{ activity: any; qrUrl: string }>();

// What each joined student has done for this activity: their latest
// request's status, or nothing yet.
const requestStatusLabel: Record<string, string> = {
    pending_faculty: 'Waiting for Faculty',
    pending_admin: 'Waiting for Admin',
    approved: 'Ready for Release',
    borrowed: 'Borrowed',
    overdue: 'Overdue',
    returned: 'Returned',
    cancelled: 'Cancelled',
};
const requestStatusClass: Record<string, string> = {
    pending_faculty: 'bg-yellow-100 text-yellow-800',
    pending_admin: 'bg-purple-100 text-purple-800',
    approved: 'bg-blue-100 text-blue-800',
    borrowed: 'bg-green-100 text-green-800',
    overdue: 'bg-red-100 text-red-800',
    returned: 'bg-gray-200 text-gray-700',
    cancelled: 'bg-red-50 text-red-700',
};
const latestRequestFor = (studentId: number) =>
    (props.activity.borrowings ?? [])
        .filter((b: any) => b.user_id === studentId)
        .sort((a: any, b: any) => b.id - a.id)[0];

const showCancelModal = ref(false);
const doCancel = () => {
    router.post(route('faculty.activities.cancel', props.activity.id));
    showCancelModal.value = false;
};
const canCancel = computed(() => !['cancelled', 'completed', 'expired'].includes(props.activity.status));

const statusBadge: Record<string, string> = {
    upcoming: 'bg-blue-100 text-blue-800',
    ongoing: 'bg-green-100 text-green-800',
    completed: 'bg-gray-100 text-gray-700',
    cancelled: 'bg-red-100 text-red-800',
    expired: 'bg-orange-100 text-orange-800',
};

const borrowingStatusBadge: Record<string, string> = {
    pending_faculty: 'bg-yellow-100 text-yellow-800',
    pending_admin: 'bg-purple-100 text-purple-800',
    approved: 'bg-blue-100 text-blue-800',
    borrowed: 'bg-green-100 text-green-800',
    returned: 'bg-gray-100 text-gray-700',
    cancelled: 'bg-red-100 text-red-800',
};

const formatDate = (d: string) => new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <UserLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2 sm:gap-4 min-w-0">
                    <h1 class="text-xl font-semibold text-gray-800 break-words">{{ activity.title }}</h1>
                    <BackButton :href="route('faculty.activities.index')">Back to Activities</BackButton>
                    <span :class="['px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize', statusBadge[activity.status]]">
                        {{ activity.status }}
                    </span>
                </div>
                <div class="flex w-full items-center gap-2 sm:w-auto">
                    <button v-if="canCancel" type="button" @click="showCancelModal = true"
                        class="flex-1 sm:flex-none text-center px-4 py-2.5 bg-white border border-red-300 text-red-600 hover:bg-red-50 text-sm font-semibold rounded-lg transition">
                        Cancel Activity
                    </button>
                    <Link v-if="!['expired', 'cancelled'].includes(activity.status)" :href="route('faculty.activities.edit', activity.id)"
                        class="flex-1 sm:flex-none text-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition">
                        Edit
                    </Link>
                </div>
            </div>
        </template>

        <div class="p-6 max-w-4xl mx-auto">
            <div class="bg-white rounded-xl shadow p-6 mb-5">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-6">
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-wide">Department</p>
                        <p class="text-sm font-semibold text-gray-800">{{ activity.department }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-wide">Borrow Date</p>
                        <p class="text-sm font-semibold text-gray-800">{{ formatDate(activity.activity_date) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-wide">Return Date</p>
                        <p class="text-sm font-semibold text-gray-800">{{ activity.return_date ? formatDate(activity.return_date) : 'Not set' }}</p>
                    </div>
                </div>
                <div v-if="activity.description" class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400 mb-1 uppercase tracking-wide">Description</p>
                    <p class="text-sm text-gray-700">{{ activity.description }}</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow p-6 mb-5">
                <h2 class="text-base font-semibold text-gray-800 mb-4">
                    Students Joined
                    <span class="ml-1 text-xs text-gray-400 font-normal">({{ activity.students?.length ?? 0 }})</span>
                </h2>
                <div v-if="activity.students?.length" class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <div v-for="student in activity.students" :key="student.id"
                        class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg text-sm text-gray-700">
                        <span class="w-7 h-7 rounded-full bg-green-100 text-green-700 font-bold text-xs flex items-center justify-center flex-shrink-0">
                            {{ student.name.charAt(0).toUpperCase() }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate">{{ student.name }}</p>
                            <span v-if="latestRequestFor(student.id)"
                                :class="['inline-block mt-0.5 px-2 py-0.5 rounded-full text-[11px] font-semibold', requestStatusClass[latestRequestFor(student.id).status] ?? 'bg-gray-100 text-gray-600']">
                                {{ requestStatusLabel[latestRequestFor(student.id).status] ?? latestRequestFor(student.id).status }}
                            </span>
                            <span v-else class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-500">
                                Not yet requested
                            </span>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">No students have joined yet.</p>
            </div>

            <div class="bg-white rounded-xl shadow p-6 mb-5">
                <h2 class="text-base font-semibold text-gray-800 mb-1">Activity QR Code</h2>
                <p class="text-xs text-gray-400 mb-4">
                    Share this ONE QR code in your class group chat. Students who scan it get a borrow request already filled with all the equipment below, linked to this activity. Each student's request is separate.
                </p>
                <div v-if="activity.equipment_qr_codes?.length" class="flex flex-col items-center gap-6 sm:flex-row sm:items-start">
                    <div class="flex flex-col items-center rounded-lg border border-gray-200 p-4 text-center">
                        <QrCode :value="qrUrl" :size="180" :label="activity.title" />
                        <p class="mt-1 text-[11px] text-gray-400">{{ activity.qr_scan_count ?? 0 }} scan(s)</p>
                    </div>
                    <div class="w-full flex-1">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Equipment included ({{ activity.equipment_qr_codes.length }})
                        </p>
                        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                            <li v-for="qr in activity.equipment_qr_codes" :key="qr.id" class="px-4 py-2.5">
                                <p class="text-sm font-medium text-gray-800">{{ qr.inventory?.equipment?.name }}</p>
                                <p class="text-xs text-gray-400">{{ qr.inventory?.campus?.campus_name }} — {{ qr.inventory?.location?.location_name }}</p>
                            </li>
                        </ul>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">No equipment selected yet. Edit this activity to choose the equipment — the QR code appears here once equipment is selected.</p>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-4">
                    Borrowing Requests
                    <span class="ml-1 text-xs text-gray-400 font-normal">({{ activity.borrowings?.length ?? 0 }})</span>
                </h2>
                <table v-if="activity.borrowings?.length" class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-4 py-2 font-semibold text-gray-600">Student</th>
                            <th class="text-left px-4 py-2 font-semibold text-gray-600">Borrow Date</th>
                            <th class="text-left px-4 py-2 font-semibold text-gray-600">Return Date</th>
                            <th class="text-left px-4 py-2 font-semibold text-gray-600">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in activity.borrowings" :key="b.id"
                            class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-2">{{ b.user?.name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ formatDate(b.borrow_date) }}</td>
                            <td class="px-4 py-2">{{ formatDate(b.return_date) }}</td>
                            <td class="px-4 py-2">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold capitalize', borrowingStatusBadge[b.status] ?? 'bg-gray-100 text-gray-700']">
                                    {{ b.status.replace(/_/g, ' ') }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-sm text-gray-400">No borrowing requests linked to this activity.</p>
            </div>
        </div>
        <ConfirmModal :show="showCancelModal" title="Cancel Activity" message="Requests that haven't been released yet will be cancelled, the equipment goes back to stock, and the students will be notified. The QR codes will stop working. The activity's record is kept." confirmLabel="Yes, Cancel Activity" confirmClass="bg-red-600 hover:bg-red-700" @confirmed="doCancel" @cancelled="showCancelModal = false" />
    </UserLayout>
</template>
