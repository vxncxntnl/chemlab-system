<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatsCard from '@/Components/StatsCard.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Doughnut, Bar } from 'vue-chartjs';
import { useBorrowingStatus } from '@/composables/useBorrowingStatus';
import {
    Chart as ChartJS,
    ArcElement,
    Tooltip,
    Legend,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
} from 'chart.js';

ChartJS.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, Title);

const { formatStatus, statusVariant } = useBorrowingStatus();

const props = defineProps<{
    stats: {
        total_users: number;
        total_equipment: number;
        pending_requests: number;
        active_borrowings: number;
        overdue_borrowings: number;
        pending_damage_reports: number;
    };
    inventoryStats: {
        total_equipment: number;
        total_inventory: number;
        available_inventory: number;
        borrowed_inventory: number;
        low_stock_count: number;
        utilization_rate: number;
    };
    recentBorrowings: Array<any>;
    lowStockItems: Array<any>;
}>();

const inventoryChartData = computed(() => ({
    labels: ['Available', 'Borrowed', 'Low Stock'],
    datasets: [{
        data: [
            props.inventoryStats.available_inventory,
            props.inventoryStats.borrowed_inventory,
            props.inventoryStats.low_stock_count,
        ],
        backgroundColor: ['#16a34a', '#2563eb', '#f59e0b'],
        borderWidth: 0,
    }],
}));

const inventoryChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' as const },
    },
    cutout: '65%',
};

const borrowingChartData = computed(() => ({
    labels: ['Pending', 'Active', 'Overdue'],
    datasets: [{
        label: 'Borrowings',
        data: [
            props.stats.pending_requests,
            props.stats.active_borrowings,
            props.stats.overdue_borrowings,
        ],
        backgroundColor: ['#fbbf24', '#16a34a', '#ef4444'],
        borderRadius: 6,
        borderWidth: 0,
    }],
}));

const borrowingChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
            grid: { color: '#f3f4f6' },
        },
        x: {
            grid: { display: false },
        },
    },
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Dashboard</h1>
        </template>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 mb-8">
            <StatsCard
                title="Total Users"
                :value="stats.total_users"
                color="green"
                icon="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"
            />
            <StatsCard
                title="Total Equipment"
                :value="stats.total_equipment"
                color="blue"
                icon="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
            <StatsCard
                title="Pending Requests"
                :value="stats.pending_requests"
                color="yellow"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
            />
            <StatsCard
                title="Active Borrowings"
                :value="stats.active_borrowings"
                color="green"
                icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
            />
            <StatsCard
                title="Overdue Items"
                :value="stats.overdue_borrowings"
                color="red"
                icon="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
            <StatsCard
                title="Utilization Rate"
                :value="`${inventoryStats.utilization_rate}%`"
                color="green"
                icon="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
            />
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 mb-8">
            <Card title="Inventory Overview">
                <div class="h-56 flex items-center justify-center">
                    <Doughnut :data="inventoryChartData" :options="inventoryChartOptions" />
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs text-gray-500">
                    <div>
                        <p class="text-lg font-bold text-green-600">{{ inventoryStats.available_inventory }}</p>
                        <p>Available</p>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-blue-600">{{ inventoryStats.borrowed_inventory }}</p>
                        <p>Borrowed</p>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-yellow-500">{{ inventoryStats.low_stock_count }}</p>
                        <p>Low Stock</p>
                    </div>
                </div>
            </Card>

            <Card title="Borrowing Status Overview">
                <div class="h-56">
                    <Bar :data="borrowingChartData" :options="borrowingChartOptions" />
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs text-gray-500">
                    <div>
                        <p class="text-lg font-bold text-yellow-500">{{ stats.pending_requests }}</p>
                        <p>Pending</p>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-green-600">{{ stats.active_borrowings }}</p>
                        <p>Active</p>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-red-500">{{ stats.overdue_borrowings }}</p>
                        <p>Overdue</p>
                    </div>
                </div>
            </Card>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <!-- Recent Borrowings -->
            <Card title="Recent Borrowing Requests">
                <div class="flow-root">
                    <ul role="list" class="-my-5 divide-y divide-gray-200">
                        <li v-for="borrowing in recentBorrowings" :key="borrowing.id" class="py-4">
                            <div class="flex items-center space-x-4">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ borrowing.user?.name }}</p>
                                    <p class="truncate text-sm text-gray-500">
                                        {{ borrowing.equipment?.map((e: any) => e.name).join(', ') || 'Multiple items' }}
                                    </p>
                                </div>
                                <Badge :variant="statusVariant(borrowing.status)">{{ formatStatus(borrowing.status) }}</Badge>
                            </div>
                        </li>
                        <li v-if="recentBorrowings.length === 0">
                            <EmptyState icon="clipboard" title="No recent borrowings" description="No borrowing requests have been made yet." actionLabel="View All Borrowings" :actionHref="route('admin.borrowings.index')" />
                        </li>
                    </ul>
                </div>
                <template #footer>
                    <Link :href="route('admin.borrowings.index')" class="text-sm font-medium text-green-600 hover:text-green-500">
                        View all borrowings
                    </Link>
                </template>
            </Card>

            <!-- Low Stock Alerts -->
            <Card title="Low Stock Alerts">
                <div class="flow-root">
                    <ul role="list" class="-my-5 divide-y divide-gray-200">
                        <li v-for="item in lowStockItems" :key="item.id" class="py-4">
                            <div class="flex items-center space-x-4">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ item.equipment?.name }}</p>
                                    <p class="truncate text-sm text-gray-500">
                                        {{ item.campus?.campus_name }} - {{ item.location?.location_name }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-red-600">{{ item.available_quantity }} left</p>
                                    <p class="text-xs text-gray-500">Reorder: {{ item.reorder_level }}</p>
                                </div>
                            </div>
                        </li>
                        <li v-if="lowStockItems.length === 0">
                            <EmptyState icon="equipment" title="All items well stocked" description="No low stock alerts at this time." />
                        </li>
                    </ul>
                </div>
                <template #footer>
                    <Link :href="route('admin.reports.low-stock')" class="text-sm font-medium text-green-600 hover:text-green-500">
                        View all low stock items
                    </Link>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
