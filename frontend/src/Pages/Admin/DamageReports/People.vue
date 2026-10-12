<script setup lang="ts">
/**
 * Damage history by person: students and faculty of this campus with damage
 * reports (as borrower or faculty in-charge), with department/course.
 */
import { formatDateTime } from '@/utils/date';
import { useLiveSearch } from '@/composables/useLiveSearch';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import BackButton from '@/Components/BackButton.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    people: Array<{
        id: number;
        name: string;
        role: 'student' | 'faculty';
        department: string | null;
        id_number: string | null;
        total: number;
        open: number;
        resolved: number;
        rejected: number;
        last_reported_at: string | null;
    }>;
    departments: string[];
    filters: { search?: string; role?: string; department?: string };
}>();

const search = ref(props.filters.search || '');
const role = ref(props.filters.role || '');
const department = ref(props.filters.department || '');

const apply = () => {
    router.get(route('admin.damage-reports.people'), {
        search: search.value || undefined,
        role: role.value || undefined,
        department: department.value || undefined,
    }, { preserveState: true, replace: true });
};

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'department', label: 'Department / Course' },
    { key: 'total', label: 'Damage Reports' },
    { key: 'last_reported_at', label: 'Last Report' },
];

const selectClass = 'rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20';

// Search as you type — clearing the box shows everything again.
useLiveSearch(search, () => apply());
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4 flex-wrap">
                <h1 class="text-xl font-semibold text-gray-800">Damage History by Student / Faculty</h1>
                <BackButton :href="route('admin.damage-reports.index')">Back to Damage Reports</BackButton>
            </div>
        </template>

        <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-3">
            <input
                v-model="search"
                type="text"
                placeholder="Search name or ID..."
                :class="selectClass"
                class="sm:w-64"
                @keyup.enter="apply"
            />
            <select v-model="role" :class="selectClass" @change="apply">
                <option value="">Students and Faculty</option>
                <option value="student">Students</option>
                <option value="faculty">Faculty</option>
            </select>
            <select v-model="department" :class="selectClass" @change="apply">
                <option value="">All Departments / Courses</option>
                <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
            </select>
        </div>

        <DataTable :columns="columns" :data="people">
            <template #cell-name="{ row }">
                <Link :href="route('admin.damage-reports.person', row.id)" class="font-medium text-green-700 hover:text-green-900">
                    {{ row.name }}
                </Link>
                <div class="text-xs text-gray-500">
                    {{ row.role === 'faculty' ? 'Faculty' : 'Student' }}<span v-if="row.id_number"> · {{ row.id_number }}</span>
                </div>
            </template>
            <template #cell-department="{ value }">
                {{ value ?? '—' }}
            </template>
            <template #cell-total="{ row }">
                <span class="font-semibold text-gray-900">{{ row.total }}</span>
                <div class="text-xs text-gray-500">
                    <span v-if="row.open" class="text-yellow-700">{{ row.open }} open</span><span v-if="row.open && (row.resolved || row.rejected)"> · </span>
                    <span v-if="row.resolved" class="text-green-700">{{ row.resolved }} resolved</span><span v-if="row.resolved && row.rejected"> · </span>
                    <span v-if="row.rejected" class="text-red-700">{{ row.rejected }} rejected</span>
                </div>
            </template>
            <template #cell-last_reported_at="{ value }">
                <span class="whitespace-nowrap">{{ value ? formatDateTime(value) : '—' }}</span>
            </template>
            <template #empty>
                <p class="py-8 text-center text-sm text-gray-500">No students or faculty with damage reports.</p>
            </template>
        </DataTable>
    </AdminLayout>
</template>
