<script setup lang="ts">
import LabLayout from '@/Layouts/LabLayout.vue';
import StatsCard from '@/Components/StatsCard.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    stats: {
        total_equipment: number;
        available: number;
        borrowed: number;
        overdue: number;
        damaged: number;
    };
}>();
</script>

<template>
    <LabLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Lab Staff Dashboard</h1>
        </template>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            <StatsCard
                title="Total Equipment"
                :value="stats.total_equipment"
                color="blue"
                icon="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
            <StatsCard
                title="Available"
                :value="stats.available"
                color="green"
                icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
            />
            <StatsCard
                title="Borrowed"
                :value="stats.borrowed"
                color="purple"
                icon="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"
            />
            <StatsCard
                title="Overdue / Damaged"
                :value="stats.overdue + stats.damaged"
                color="red"
                icon="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                :href="route('lab.borrowings.index')"
                class="group flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200"
            >
                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-green-50 border border-green-100 group-hover:bg-green-100 transition-colors">
                    <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Manage Borrowings</p>
                    <p class="text-xs text-gray-500 mt-0.5">Review and process requests</p>
                </div>
            </Link>

            <Link
                :href="route('lab.damage-reports.index')"
                class="group flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200"
            >
                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-red-50 border border-red-100 group-hover:bg-red-100 transition-colors">
                    <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Damage Reports</p>
                    <p class="text-xs text-gray-500 mt-0.5">Review reported damage</p>
                </div>
            </Link>

            <Link
                :href="route('lab.maintenance.index')"
                class="group flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200"
            >
                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-amber-50 border border-amber-100 group-hover:bg-amber-100 transition-colors">
                    <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Maintenance</p>
                    <p class="text-xs text-gray-500 mt-0.5">Track maintenance records</p>
                </div>
            </Link>
        </div>

        <!-- Stats breakdown -->
        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-green-600">{{ stats.available }}</p>
                <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Available</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ stats.borrowed }}</p>
                <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Borrowed</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-red-500">{{ stats.overdue }}</p>
                <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Overdue</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-amber-500">{{ stats.damaged }}</p>
                <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Damaged</p>
            </div>
        </div>
    </LabLayout>
</template>
