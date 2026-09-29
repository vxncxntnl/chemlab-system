<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    data: Array<{
        month: string;
        total_transactions: number;
        completed: number;
        overdue: number;
    }>;
    year: string;
    years: Array<string>;
}>();

const selectedYear = ref(props.year);

const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

const getMonthName = (monthNum: string) => {
    return monthNames[parseInt(monthNum) - 1] || monthNum;
};

const changeYear = () => {
    router.get(route('admin.reports.monthly'), {
        year: selectedYear.value,
    }, { preserveState: true });
};

const totalTransactions = props.data.reduce((sum, item) => sum + item.total_transactions, 0);
const totalCompleted = props.data.reduce((sum, item) => sum + item.completed, 0);
const totalOverdue = props.data.reduce((sum, item) => sum + item.overdue, 0);
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Monthly Report</h1>
        </template>

        <div class="space-y-6">
            <!-- Year Filter -->
            <div class="flex items-center gap-4">
                <label for="year" class="text-sm font-medium text-gray-700">Year:</label>
                <select
                    id="year"
                    v-model="selectedYear"
                    class="rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                    @change="changeYear"
                >
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <div class="text-center">
                        <p class="text-sm font-medium text-gray-500">Total Transactions</p>
                        <p class="mt-1 text-3xl font-semibold text-gray-900">{{ totalTransactions }}</p>
                    </div>
                </Card>
                <Card>
                    <div class="text-center">
                        <p class="text-sm font-medium text-gray-500">Completed</p>
                        <p class="mt-1 text-3xl font-semibold text-green-600">{{ totalCompleted }}</p>
                    </div>
                </Card>
                <Card>
                    <div class="text-center">
                        <p class="text-sm font-medium text-gray-500">Overdue</p>
                        <p class="mt-1 text-3xl font-semibold text-red-600">{{ totalOverdue }}</p>
                    </div>
                </Card>
            </div>

            <!-- Monthly Data Table -->
            <Card title="Monthly Breakdown">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Month</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Transactions</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Completed</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Overdue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="item in data" :key="item.month" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ getMonthName(item.month) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                    {{ item.total_transactions }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-green-600">
                                    {{ item.completed }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-red-600">
                                    {{ item.overdue }}
                                </td>
                            </tr>
                            <tr v-if="data.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">
                                    No data available for this year
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr class="font-semibold">
                                <td class="px-6 py-3 text-sm text-gray-900">Total</td>
                                <td class="px-6 py-3 text-sm text-gray-900">{{ totalTransactions }}</td>
                                <td class="px-6 py-3 text-sm text-green-600">{{ totalCompleted }}</td>
                                <td class="px-6 py-3 text-sm text-red-600">{{ totalOverdue }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
