<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    borrowings: {
        data: Array<{
            id: number;
            borrow_date: string;
            return_date: string;
            actual_return_date: string;
            status: string;
            equipment: Array<{
                id: number;
                name: string;
            }>;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
}>();

const columns = [
    { key: 'equipment', label: 'Equipment' },
    { key: 'borrow_date', label: 'Borrow Date' },
    { key: 'actual_return_date', label: 'Return Date' },
];
</script>

<template>
    <UserLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Borrowing History</h1>
        </template>

        <div class="space-y-6">
            <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-4">
                <p class="text-sm text-gray-500">
                    View your completed borrowing transactions. All items shown here have been returned.
                </p>
            </div>

            <DataTable :columns="columns" :data="borrowings.data" :pagination="borrowings">
                <template #cell-equipment="{ row }">
                    <div>
                        <p class="text-sm font-medium text-gray-900">
                            {{ row.equipment?.map((e: any) => e.name).join(', ') || 'Equipment' }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ row.equipment?.length || 0 }} item(s)
                        </p>
                    </div>
                </template>
                <template #cell-borrow_date="{ value }">
                    {{ new Date(value).toLocaleDateString() }}
                </template>
                <template #cell-actual_return_date="{ value }">
                    {{ new Date(value).toLocaleDateString() }}
                </template>
                <template #actions="{ row }">
                    <Link :href="route('faculty.borrowings.show', row.id)" class="text-green-600 hover:text-green-900">
                        View Details
                    </Link>
                </template>
            </DataTable>

            <div v-if="borrowings.data.length === 0" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No borrowing history</h3>
                <p class="mt-1 text-sm text-gray-500">You haven't completed any borrowing transactions yet.</p>
                <div class="mt-6">
                    <Link
                        :href="route('faculty.borrowings.create')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                    >
                        Make Your First Request
                    </Link>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
