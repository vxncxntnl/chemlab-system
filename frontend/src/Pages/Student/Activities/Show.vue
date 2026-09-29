<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{ activity: any }>();

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
        <div class="p-6 max-w-3xl mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('student.activities.index')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </Link>
                <h1 class="text-xl font-bold text-gray-800">{{ activity.title }}</h1>
                <span :class="['ml-2 px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize', statusBadge[activity.status]]">
                    {{ activity.status }}
                </span>
            </div>

            <!-- Info -->
            <div class="bg-white rounded-xl shadow p-6 mb-5">
                <div class="grid grid-cols-2 gap-5">
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-wide">Instructor</p>
                        <p class="text-sm font-semibold text-gray-800">{{ activity.instructor?.name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-wide">Department</p>
                        <p class="text-sm font-semibold text-gray-800">{{ activity.department }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-wide">Activity Date</p>
                        <p class="text-sm font-semibold text-gray-800">{{ formatDate(activity.activity_date) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-wide">Enrolled Students</p>
                        <p class="text-sm font-semibold text-gray-800">{{ activity.students?.length ?? 0 }}</p>
                    </div>
                </div>
                <div v-if="activity.description" class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400 mb-1 uppercase tracking-wide">Description</p>
                    <p class="text-sm text-gray-700">{{ activity.description }}</p>
                </div>
            </div>

            <!-- Borrow Equipment Button -->
            <div v-if="activity.status === 'upcoming' || activity.status === 'ongoing'"
                class="bg-green-50 border border-green-200 rounded-xl p-5 mb-5 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-green-800">Need equipment for this activity?</p>
                    <p class="text-xs text-green-600 mt-0.5">Submit a borrowing request linked to this activity.</p>
                </div>
                <Link :href="route('student.borrowings.create') + '?activity_id=' + activity.id"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition">
                    Borrow Equipment
                </Link>
            </div>

            <!-- My Borrowings for this activity -->
            <div class="bg-white rounded-xl shadow p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-4">My Borrowing Requests</h2>
                <table v-if="activity.borrowings?.length" class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-4 py-2 font-semibold text-gray-600">Borrow Date</th>
                            <th class="text-left px-4 py-2 font-semibold text-gray-600">Return Date</th>
                            <th class="text-left px-4 py-2 font-semibold text-gray-600">Status</th>
                            <th class="text-left px-4 py-2 font-semibold text-gray-600">Items</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in activity.borrowings" :key="b.id"
                            class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-2">{{ formatDate(b.borrow_date) }}</td>
                            <td class="px-4 py-2">{{ formatDate(b.return_date) }}</td>
                            <td class="px-4 py-2">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold capitalize', borrowingStatusBadge[b.status] ?? 'bg-gray-100 text-gray-700']">
                                    {{ b.status.replace(/_/g, ' ') }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-600">{{ b.borrowing_equipment?.length ?? 0 }} items</td>
                            <td class="px-4 py-2">
                                <Link :href="route('student.borrowings.show', b.id)"
                                    class="text-xs text-green-600 hover:text-green-800 font-medium">View</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-sm text-gray-400">You have no borrowing requests for this activity yet.</p>
            </div>
        </div>
    </UserLayout>
</template>
