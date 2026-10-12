<script setup lang="ts">
import { formatDateTime } from '@/utils/date';
import { useLiveSearch } from '@/composables/useLiveSearch';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    users: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
    filters: {
        search?: string;
        role?: string;
        status?: string;
    };
}>();

const search = ref(props.filters?.search || '');
const roleFilter = ref('');
const statusFilter = ref('');

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Role' },
    { key: 'status', label: 'Status' },
    { key: 'campus', label: 'Campus' },
    { key: 'created_at', label: 'Joined' },
];

type BadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'primary' | 'default';

const getRoleBadge = (role: string): BadgeVariant => {
    const map: Record<string, BadgeVariant> = {
        admin: 'danger',
        lab_staff: 'warning',
        faculty: 'primary',
        student: 'success',
    };
    return map[role] || 'default';
};

const getStatusBadge = (status: string): BadgeVariant => {
    const map: Record<string, BadgeVariant> = {
        active: 'success',
        pending: 'warning',
        suspended: 'danger',
    };
    return map[status] || 'default';
};

const applyFilters = () => {
    router.get(route('admin.users.index'), {
        search: search.value,
        role: roleFilter.value,
        status: statusFilter.value,
    }, { preserveState: true });
};

const approveUser = (id: number) => {
    router.post(route('admin.users.approve', id));
};

const showSuspendModal = ref(false);
const showDeleteModal = ref(false);
const actionTargetId = ref<number | null>(null);

const suspendUser = (id: number) => { actionTargetId.value = id; showSuspendModal.value = true; };
const doSuspend = () => { router.post(route('admin.users.suspend', actionTargetId.value!)); showSuspendModal.value = false; };

const deleteUser = (id: number) => { actionTargetId.value = id; showDeleteModal.value = true; };
const doDelete = () => { router.delete(route('admin.users.destroy', actionTargetId.value!)); showDeleteModal.value = false; };

// Search as you type — clearing the box shows everything again.
useLiveSearch(search, () => applyFilters());
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Users</h1>
                <Link
                    :href="route('admin.users.create')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Add User
                </Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="mb-6 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email..."
                    class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                    @keyup.enter="applyFilters"
                />
            </div>
            <select
                v-model="roleFilter"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                @change="applyFilters"
            >
                <option value="">All Roles</option>
                <option value="admin">Admin</option>
                <option value="lab_staff">Lab Staff</option>
                <option value="faculty">Faculty</option>
                <option value="student">Student</option>
            </select>
            <select
                v-model="statusFilter"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                @change="applyFilters"
            >
                <option value="">All Statuses</option>
                <option value="pending">Pending Approval</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
            </select>
        </div>

        <DataTable :columns="columns" :data="users.data" :pagination="users">
            <template #cell-name="{ row }">
                <div>
                    <p class="font-medium text-gray-900">
                        {{ row.full_name || row.name }}
                    </p>
                    <p v-if="row.student_id" class="text-xs text-gray-400">{{ row.student_id }}</p>
                    <p v-else-if="row.employee_id" class="text-xs text-gray-400">{{ row.employee_id }}</p>
                    <p v-if="row.course" class="text-xs text-green-600 font-medium mt-0.5">{{ row.course }}</p>
                </div>
            </template>

            <template #cell-role="{ value }">
                <Badge :variant="getRoleBadge(value)" class="capitalize">{{ value?.replace('_', ' ') }}</Badge>
            </template>

            <template #cell-status="{ value }">
                <Badge :variant="getStatusBadge(value)" class="capitalize">{{ value }}</Badge>
            </template>

            <template #cell-campus="{ row }">
                <span class="text-sm text-gray-600">{{ row.campus?.campus_name ?? '—' }}</span>
            </template>

            <template #cell-created_at="{ value }">
                <span class="whitespace-nowrap">{{ formatDateTime(value) }}</span>
            </template>

            <template #actions="{ row }">
                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Approve button for pending users -->
                    <button
                        v-if="row.status === 'pending'"
                        @click="approveUser(row.id)"
                        class="inline-flex items-center px-2 py-1 text-xs bg-green-100 text-green-800 rounded hover:bg-green-200 font-medium"
                    >
                        ✓ Approve
                    </button>

                    <!-- Suspend button for active users -->
                    <button
                        v-if="row.status === 'active'"
                        @click="suspendUser(row.id)"
                        class="inline-flex items-center px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded hover:bg-yellow-200 font-medium"
                    >
                        Suspend
                    </button>

                    <!-- Re-activate suspended users -->
                    <button
                        v-if="row.status === 'suspended'"
                        @click="approveUser(row.id)"
                        class="inline-flex items-center px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded hover:bg-blue-200 font-medium"
                    >
                        Re-activate
                    </button>

                    <Link :href="route('admin.users.edit', row.id)" class="text-green-600 hover:text-green-900 text-sm">
                        Edit
                    </Link>
                    <button @click="deleteUser(row.id)" class="text-red-600 hover:text-red-900 text-sm">
                        Delete
                    </button>
                </div>
            </template>
        </DataTable>
        <ConfirmModal
            :show="showSuspendModal"
            title="Suspend User"
            message="Suspend this user? They will not be able to log in until re-activated."
            confirmLabel="Yes, Suspend"
            confirmClass="bg-yellow-600 hover:bg-yellow-700"
            @confirmed="doSuspend"
            @cancelled="showSuspendModal = false"
        />
        <ConfirmModal
            :show="showDeleteModal"
            title="Delete User"
            message="Permanently delete this user? This action cannot be undone."
            confirmLabel="Yes, Delete"
            confirmClass="bg-red-600 hover:bg-red-700"
            @confirmed="doDelete"
            @cancelled="showDeleteModal = false"
        />
    </AdminLayout>
</template>
