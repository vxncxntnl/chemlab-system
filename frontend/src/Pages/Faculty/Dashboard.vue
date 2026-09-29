<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue';
import StatsCard from '@/Components/StatsCard.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { useBorrowingStatus } from '@/composables/useBorrowingStatus';

ChartJS.register(ArcElement, Tooltip, Legend);

const { formatStatus, statusVariant } = useBorrowingStatus();

const props = defineProps<{
    stats: {
        active_borrowings: number;
        pending_requests: number;
        overdue_items: number;
    };
    recentBorrowings: Array<any>;
}>();

const statusChartData = computed(() => ({
    labels: ['Active', 'Pending', 'Overdue'],
    datasets: [{
        data: [props.stats.active_borrowings, props.stats.pending_requests, props.stats.overdue_items],
        backgroundColor: ['#16a34a', '#f59e0b', '#ef4444'],
        borderWidth: 0,
    }],
}));

const statusChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' as const } },
    cutout: '60%',
};
</script>

<template>
    <UserLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Dashboard</h1>
        </template>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
            <StatsCard
                title="Active Borrowings"
                :value="stats.active_borrowings"
                color="green"
                icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
            />
            <StatsCard
                title="Pending Requests"
                :value="stats.pending_requests"
                color="yellow"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
            />
            <StatsCard
                title="Overdue Items"
                :value="stats.overdue_items"
                color="red"
                icon="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
        </div>

        <!-- Chart Row -->
        <div class="mb-8">
            <Card title="My Borrowing Summary">
                <div class="flex flex-col sm:flex-row items-center gap-6">
                    <div class="w-48 h-48 flex-shrink-0">
                        <Doughnut :data="statusChartData" :options="statusChartOptions" />
                    </div>
                    <div class="grid grid-cols-3 gap-4 flex-1 text-center">
                        <div class="p-4 rounded-lg bg-green-50">
                            <p class="text-2xl font-bold text-green-600">{{ stats.active_borrowings }}</p>
                            <p class="text-xs text-gray-500 mt-1">Active</p>
                        </div>
                        <div class="p-4 rounded-lg bg-yellow-50">
                            <p class="text-2xl font-bold text-yellow-500">{{ stats.pending_requests }}</p>
                            <p class="text-xs text-gray-500 mt-1">Pending</p>
                        </div>
                        <div class="p-4 rounded-lg bg-red-50">
                            <p class="text-2xl font-bold text-red-500">{{ stats.overdue_items }}</p>
                            <p class="text-xs text-gray-500 mt-1">Overdue</p>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Card title="Recent Borrowings">
                <ul role="list" class="-my-5 divide-y divide-gray-200">
                    <li v-for="borrowing in recentBorrowings" :key="borrowing.id" class="py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ borrowing.equipment?.map((e: any) => e.name).join(', ') || 'Equipment' }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ new Date(borrowing.borrow_date).toLocaleDateString() }} -
                                    {{ new Date(borrowing.return_date).toLocaleDateString() }}
                                </p>
                            </div>
                            <Badge :variant="statusVariant(borrowing.status)">{{ formatStatus(borrowing.status) }}</Badge>
                        </div>
                    </li>
                    <li v-if="recentBorrowings.length === 0">
                        <EmptyState icon="clipboard" title="No borrowings yet" description="You haven't made any borrowing requests yet." actionLabel="New Request" :actionHref="route('faculty.borrowings.create')" />
                    </li>
                </ul>
                <template #footer>
                    <Link :href="route('faculty.borrowings.index')" class="text-sm font-medium text-green-600 hover:text-green-500">
                        View all borrowings
                    </Link>
                </template>
            </Card>

            <Card title="Quick Actions">
                <div class="space-y-3">
                    <Link
                        :href="route('faculty.borrowings.create')"
                        class="group flex items-center gap-4 rounded-xl border border-gray-100 p-4 hover:bg-green-50/50 hover:border-green-200 transition-all duration-150"
                    >
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-green-50 border border-green-100 group-hover:bg-green-100 transition-colors">
                            <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">New Borrowing Request</p>
                            <p class="text-xs text-gray-500 mt-0.5">Request equipment for your needs</p>
                        </div>
                    </Link>
                    <Link
                        :href="route('faculty.equipment')"
                        class="group flex items-center gap-4 rounded-xl border border-gray-100 p-4 hover:bg-blue-50/50 hover:border-blue-200 transition-all duration-150"
                    >
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 border border-blue-100 group-hover:bg-blue-100 transition-colors">
                            <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Browse Equipment</p>
                            <p class="text-xs text-gray-500 mt-0.5">View available equipment catalog</p>
                        </div>
                    </Link>
                </div>
            </Card>
        </div>
    </UserLayout>
</template>
