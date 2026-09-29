<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    items: Array<{
        borrowing_id: number | null;
        borrower_name: string;
        borrower_id: string;
        borrower_role: string | null;
        equipment_name: string;
        quantity: number;
        borrow_date: string | null;
        return_date: string | null;
        status: string | null;
        is_overdue: boolean;
        days_overdue: number;
    }>;
    campuses: Array<{ id: number; campus_name: string }>;
    filters: { campus_id?: string };
}>();

const overdueCount = computed(() => props.items.filter(i => i.is_overdue).length);

const formatDate = (date: string | null) => date ? new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

const applyCampusFilter = (campusId: string) => {
    router.get(route('admin.reports.unreturned-items'), { campus_id: campusId || undefined }, { preserveState: true });
};

const borrowingShowRoute = (item: (typeof props.items)[number]) => {
    if (!item.borrowing_id) return null;
    return route('admin.borrowings.show', item.borrowing_id);
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Unreturned Items</h1>
        </template>

        <!-- Stats -->
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-gray-800">{{ items.length }}</p>
                <p class="mt-1 text-xs uppercase tracking-wide text-gray-400">Currently Out</p>
            </div>
            <div class="rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold" :class="overdueCount > 0 ? 'text-red-600' : 'text-gray-800'">{{ overdueCount }}</p>
                <p class="mt-1 text-xs uppercase tracking-wide text-gray-400">Overdue</p>
            </div>
            <div class="col-span-2 sm:col-span-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">Filter by Campus</label>
                <select
                    :value="filters.campus_id ?? ''"
                    @change="applyCampusFilter(($event.target as HTMLSelectElement).value)"
                    class="w-full rounded-lg border-gray-200 text-sm shadow-sm focus:border-green-500 focus:ring-green-500"
                >
                    <option value="">All Campuses</option>
                    <option v-for="c in campuses" :key="c.id" :value="c.id">{{ c.campus_name }}</option>
                </select>
            </div>
        </div>

        <Card>
            <p class="sm:hidden mb-2 text-xs text-gray-400">◂ Swipe to see more ▸</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Borrower</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Item</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Qty</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Borrow Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Expected Return</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="(item, idx) in items"
                            :key="idx"
                            :class="item.is_overdue ? 'bg-red-50' : ''"
                            class="hover:bg-gray-50"
                        >
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <Link v-if="borrowingShowRoute(item)" :href="borrowingShowRoute(item)!" class="text-green-700 hover:underline">
                                    {{ item.borrower_name }}
                                </Link>
                                <span v-else>{{ item.borrower_name }}</span>
                                <span class="ml-1 text-xs capitalize text-gray-400">({{ item.borrower_role ?? '—' }})</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">{{ item.borrower_id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ item.equipment_name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ item.quantity }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(item.borrow_date) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(item.return_date) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <Badge v-if="item.is_overdue" variant="danger">
                                    Overdue{{ item.days_overdue > 0 ? ` (${item.days_overdue}d)` : '' }}
                                </Badge>
                                <Badge v-else variant="info">Borrowed</Badge>
                            </td>
                        </tr>
                        <tr v-if="items.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">
                                Nothing is currently out — every borrowed item has been returned.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AdminLayout>
</template>
