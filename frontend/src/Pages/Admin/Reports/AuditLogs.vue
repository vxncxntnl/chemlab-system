<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    logs: {
        data: Array<{
            id: number;
            user_id: number;
            action: string;
            table_affected: string | null;
            record_id: number | null;
            created_at: string;
            department: string | null; // department (faculty) / course (student)
            user: {
                id: number;
                name: string;
            } | null;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
    users: Array<{
        id: number;
        name: string;
        department: string | null;
    }>;
    departments: string[];
    filters: {
        action?: string;
        user_id?: string;
        department?: string;
    };
}>();

const actionFilter = ref(props.filters.action || '');
const userFilter = ref(props.filters.user_id || '');
const departmentFilter = ref(props.filters.department || '');

const columns = [
    { key: 'created_at', label: 'Date/Time' },
    { key: 'user', label: 'User' },
    { key: 'department', label: 'Department' },
    { key: 'action', label: 'Action' },
    { key: 'table_affected', label: 'Table' },
    { key: 'record_id', label: 'Record ID' },
];

const applyFilters = () => {
    router.get(route('admin.reports.audit-logs'), {
        action: actionFilter.value,
        user_id: userFilter.value,
        department: departmentFilter.value,
    }, { preserveState: true });
};

const clearFilters = () => {
    actionFilter.value = '';
    userFilter.value = '';
    departmentFilter.value = '';
    router.get(route('admin.reports.audit-logs'));
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Audit Logs</h1>
        </template>

        <div class="space-y-6">
            <!-- Filters -->
            <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-4">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <label for="action" class="block text-sm font-medium text-gray-700 mb-1">Search Action</label>
                        <input
                            id="action"
                            v-model="actionFilter"
                            type="text"
                            placeholder="e.g., created, updated, deleted (any case)"
                            class="w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <div class="flex-1">
                        <label for="user" class="block text-sm font-medium text-gray-700 mb-1">User</label>
                        <select
                            id="user"
                            v-model="userFilter"
                            class="w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            @change="applyFilters"
                        >
                            <option value="">All Users</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}{{ user.department ? ` — ${user.department}` : '' }}
                            </option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department / Course</label>
                        <select
                            id="department"
                            v-model="departmentFilter"
                            class="w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            @change="applyFilters"
                        >
                            <option value="">All Departments</option>
                            <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button
                            @click="applyFilters"
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm font-medium"
                        >
                            Filter
                        </button>
                        <button
                            @click="clearFilters"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm font-medium"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Logs Table -->
            <DataTable :columns="columns" :data="logs.data" :pagination="logs">
                <template #cell-created_at="{ value }">
                    <div class="text-sm">
                        <div class="text-gray-900">{{ new Date(value).toLocaleDateString() }}</div>
                        <div class="text-gray-500">{{ new Date(value).toLocaleTimeString() }}</div>
                    </div>
                </template>
                <template #cell-user="{ row }">
                    <span class="font-medium text-gray-900">{{ row.user?.name || 'System' }}</span>
                </template>
                <template #cell-department="{ value }">
                    <span class="text-gray-600">{{ value || '—' }}</span>
                </template>
                <template #cell-action="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="{
                            'bg-green-100 text-green-800': value.toLowerCase().includes('created'),
                            'bg-blue-100 text-blue-800': value.toLowerCase().includes('updated'),
                            'bg-red-100 text-red-800': value.toLowerCase().includes('deleted'),
                            'bg-yellow-100 text-yellow-800': value.toLowerCase().includes('approved') || value.toLowerCase().includes('rejected'),
                            'bg-gray-100 text-gray-800': !value.toLowerCase().includes('created') && !value.toLowerCase().includes('updated') && !value.toLowerCase().includes('deleted') && !value.toLowerCase().includes('approved') && !value.toLowerCase().includes('rejected'),
                        }"
                    >
                        {{ value }}
                    </span>
                </template>
                <template #cell-table_affected="{ value }">
                    <span class="text-gray-600">{{ value || '-' }}</span>
                </template>
                <template #cell-record_id="{ value }">
                    <span class="text-gray-600">{{ value || '-' }}</span>
                </template>
            </DataTable>
        </div>
    </AdminLayout>
</template>
