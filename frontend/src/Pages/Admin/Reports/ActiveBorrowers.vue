<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import { Link } from '@inertiajs/vue3';
import { useBorrowingStatus } from '@/composables/useBorrowingStatus';

const { formatStatus, statusVariant } = useBorrowingStatus();

defineProps<{
    borrowers: Array<{
        id: number;
        name: string;
        email: string;
        role: string;
        department: string | null;
        active_borrows: number;
        borrowings: Array<{
            id: number;
            status: string;
            borrow_date: string;
            return_date: string;
            equipment: Array<{ name: string }>;
        }>;
    }>;
}>();

const getRoleBadge = (role: string) => {
    const variants: Record<string, 'success' | 'warning' | 'danger' | 'info'> = {
        admin: 'danger',
        faculty: 'info',
        student: 'success',
    };
    return variants[role] || 'default';
};

const getStatusBadge = (status: string) => {
    const variants: Record<string, 'success' | 'warning' | 'danger' | 'info'> = {
        pending: 'warning',
        approved: 'info',
        borrowed: 'success',
        overdue: 'danger',
    };
    return variants[status] || 'default';
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Active Borrowers</h1>
        </template>

        <Card>
            <template #header>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium leading-6 text-gray-900">Users with Active Borrowings</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ borrowers.length }} users currently have active borrowings</p>
                    </div>
                </div>
            </template>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-green-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-white">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-white">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-white">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-white">Active Borrowings</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-white">Borrowing Details</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-white">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="borrower in borrowers" :key="borrower.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-6 py-4">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ borrower.name }}</div>
                                    <div class="text-sm text-gray-500">{{ borrower.email }}</div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <Badge :variant="getRoleBadge(borrower.role)">{{ borrower.role }}</Badge>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ borrower.department || '-' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="borrower.active_borrows > 3 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'">
                                    {{ borrower.active_borrows }} active
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-1">
                                    <div v-for="borrowing in borrower.borrowings" :key="borrowing.id" class="text-xs">
                                        <Badge :variant="statusVariant(borrowing.status)" class="mr-1">{{ formatStatus(borrowing.status) }}</Badge>
                                        <span class="text-gray-500">{{ borrowing.borrow_date }} - {{ borrowing.return_date }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <Link :href="route('admin.users.edit', borrower.id)" class="text-green-600 hover:text-green-900">
                                    View User
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="borrowers.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">
                                No active borrowers found
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AdminLayout>
</template>
