<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    activities: Array<any>;
}>();

const statusBadge: Record<string, string> = {
    upcoming: 'bg-blue-100 text-blue-800',
    ongoing: 'bg-green-100 text-green-800',
    completed: 'bg-gray-100 text-gray-700',
    cancelled: 'bg-red-100 text-red-800',
    expired: 'bg-orange-100 text-orange-800',
};

const formatDate = (d: string) => new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <UserLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">My Laboratory Activities</h1>
        </template>

        <div>
            <div v-if="activities.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="activity in activities" :key="activity.id"
                    class="rounded-xl border border-gray-100 bg-white shadow-sm p-5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
                    <div class="flex items-start justify-between mb-3">
                        <h2 class="text-base font-semibold text-gray-800 leading-tight">{{ activity.title }}</h2>
                        <span :class="['ml-2 px-2 py-0.5 rounded-full text-xs font-semibold capitalize flex-shrink-0', statusBadge[activity.status]]">
                            {{ activity.status }}
                        </span>
                    </div>

                    <div class="space-y-1.5 mb-4">
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span>{{ activity.instructor?.name ?? '—' }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <span>{{ activity.department }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>{{ formatDate(activity.activity_date) }}</span>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Link :href="route('student.activities.show', activity.id)"
                            class="flex-1 text-center py-2 text-sm font-semibold text-green-700 bg-green-50 hover:bg-green-100 rounded-lg transition">
                            View Details
                        </Link>
                        <Link v-if="activity.status === 'upcoming' || activity.status === 'ongoing'"
                            :href="route('student.borrowings.create') + '?activity_id=' + activity.id"
                            class="flex-1 text-center py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg transition">
                            Borrow Equipment
                        </Link>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-xl border border-gray-100 bg-white shadow-sm p-12 text-center">
                <svg class="w-14 h-14 text-gray-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-gray-500 font-medium">No activities yet</p>
                <p class="text-sm text-gray-400 mt-1">Your instructor will enroll you in laboratory activities.</p>
            </div>
        </div>
    </UserLayout>
</template>
