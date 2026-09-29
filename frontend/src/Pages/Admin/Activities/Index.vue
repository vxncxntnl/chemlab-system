<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    activities: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        total: number;
        links: Array<any>;
    };
    filters: { search?: string; status?: string };
}>();

const search = ref('');
const statusFilter = ref('');
const showDeleteModal = ref(false);
const deleteTargetId = ref<number | null>(null);
const doDelete = () => { router.delete(route('admin.activities.destroy', deleteTargetId.value!)); showDeleteModal.value = false; };

const applyFilters = () => {
    router.get(route('admin.activities.index'), {
        search: search.value,
        status: statusFilter.value,
    }, { preserveState: true, replace: true });
};

const statusBadge: Record<string, string> = {
    upcoming: 'bg-blue-100 text-blue-800',
    ongoing: 'bg-green-100 text-green-800',
    completed: 'bg-gray-100 text-gray-700',
    cancelled: 'bg-red-100 text-red-800',
    expired: 'bg-orange-100 text-orange-800',
};

const formatDate = (d: string) => new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Laboratory Activities</h1>
                <Link :href="route('admin.activities.create')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    New Activity
                </Link>
            </div>
        </template>

        <div>
            <!-- Filters -->
            <div class="mb-5 flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1 max-w-sm">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input v-model="search" @keyup.enter="applyFilters" type="text"
                        placeholder="Search title or department..."
                        class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20" />
                </div>
                <select v-model="statusFilter" @change="applyFilters"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                    <option value="">All Status</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="expired">Expired</option>
                </select>
            </div>

            <!-- Table -->
            <div class="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                <p class="sm:hidden px-5 pt-3 text-xs text-gray-400">◂ Swipe to see more ▸</p>
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Title</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Instructor</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Department</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Date</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Students</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Status</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="activity in activities.data" :key="activity.id"
                            class="hover:bg-green-50/30 transition-colors duration-100">
                            <td class="px-5 py-3 font-medium text-gray-800">{{ activity.title }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ activity.instructor?.name ?? '—' }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ activity.department }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ formatDate(activity.activity_date) }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ activity.students?.length ?? 0 }}</td>
                            <td class="px-5 py-3">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold capitalize', statusBadge[activity.status]]">
                                    {{ activity.status }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <Link :href="route('admin.activities.show', activity.id)"
                                        class="text-xs text-green-600 hover:text-green-800 font-medium">View</Link>
                                    <Link v-if="activity.status !== 'expired'" :href="route('admin.activities.edit', activity.id)"
                                        class="text-xs text-blue-600 hover:text-blue-800 font-medium">Edit</Link>
                                    <button
                                        class="text-xs text-red-600 hover:text-red-800 font-medium"
                                        @click="deleteTargetId = activity.id; showDeleteModal = true">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="activities.data.length === 0">
                            <td colspan="7" class="text-center py-10 text-gray-400">No activities found.</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="activities.last_page > 1" class="flex gap-1 mt-4 justify-end">
                <template v-for="link in activities.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url"
                        :class="['inline-flex items-center justify-center min-w-[32px] h-8 rounded-lg text-sm border transition-colors', link.active ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50']"
                        v-html="link.label" />
                    <span v-else class="inline-flex items-center justify-center min-w-[32px] h-8 rounded-lg text-sm border border-gray-100 text-gray-400" v-html="link.label" />
                </template>
            </div>
        </div>
        <ConfirmModal :show="showDeleteModal" title="Delete Activity" message="Are you sure you want to delete this activity?" confirmLabel="Yes, Delete" confirmClass="bg-red-600 hover:bg-red-700" @confirmed="doDelete" @cancelled="showDeleteModal = false" />
    </AdminLayout>
</template>
