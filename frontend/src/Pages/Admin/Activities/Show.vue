<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BackButton from '@/Components/BackButton.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{ activity: any }>();

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
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-semibold text-gray-800">{{ activity.title }}</h1>
                    <BackButton :href="route('admin.activities.index')">Back to Activities</BackButton>
                    <span :class="['px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize', statusBadge[activity.status]]">
                        {{ activity.status }}
                    </span>
                </div>
                <Link v-if="activity.status !== 'expired'" :href="route('admin.activities.edit', activity.id)"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition">
                    Edit
                </Link>
            </div>
        </template>

        <div class="p-6 max-w-4xl mx-auto">
            <!-- Info Card -->
            <div class="bg-white rounded-xl shadow p-6 mb-5">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-wide">Instructor</p>
                        <p class="text-sm font-semibold text-gray-800">{{ activity.instructor?.name ?? '—' }}</p>
                    </div>
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

            <!-- Students -->
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

            <!-- Borrowings -->
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
                            <th class="text-left px-4 py-2 font-semibold text-gray-600">Items</th>
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
                            <td class="px-4 py-2 text-gray-600">{{ b.borrowing_equipment?.length ?? 0 }} items</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-sm text-gray-400">No borrowing requests linked to this activity.</p>
            </div>
        </div>
    </AdminLayout>
</template>
