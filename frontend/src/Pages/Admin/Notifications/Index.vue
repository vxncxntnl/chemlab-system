<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    notifications: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
}>();

const columns = [
    { key: 'title', label: 'Title' },
    { key: 'user.name', label: 'Recipient' },
    { key: 'type', label: 'Type' },
    { key: 'is_read', label: 'Status' },
    { key: 'created_at', label: 'Sent At' },
];

type BadgeVariant = 'success' | 'warning' | 'info' | 'default' | 'danger' | 'primary';

const getTypeBadge = (type: string): BadgeVariant => {
    const variants: Record<string, BadgeVariant> = {
        info: 'primary',
        success: 'success',
        warning: 'warning',
        danger: 'danger',
    };
    return variants[type] || 'default';
};

const showDeleteModal = ref(false);
const deleteTargetId = ref<number | null>(null);
const deleteNotification = (id: number) => { deleteTargetId.value = id; showDeleteModal.value = true; };
const doDelete = () => { router.delete(route('admin.notifications.destroy', deleteTargetId.value!)); showDeleteModal.value = false; };
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Notifications</h1>
                <Link
                    :href="route('admin.notifications.create')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Send Notification
                </Link>
            </div>
        </template>

        <DataTable :columns="columns" :data="notifications.data" :pagination="notifications">
            <template #cell-user.name="{ row }">
                {{ row.user?.name || 'N/A' }}
            </template>
            <template #cell-type="{ value }">
                <Badge :variant="getTypeBadge(value)">{{ value }}</Badge>
            </template>
            <template #cell-is_read="{ value }">
                <Badge :variant="value ? 'success' : 'warning'">
                    {{ value ? 'Read' : 'Unread' }}
                </Badge>
            </template>
            <template #cell-created_at="{ value }">
                {{ new Date(value).toLocaleString() }}
            </template>
            <template #actions="{ row }">
                <button @click="deleteNotification(row.id)" class="text-red-600 hover:text-red-900">
                    Delete
                </button>
            </template>
        </DataTable>
        <ConfirmModal :show="showDeleteModal" title="Delete Notification" message="Are you sure you want to delete this notification?" confirmLabel="Yes, Delete" confirmClass="bg-red-600 hover:bg-red-700" @confirmed="doDelete" @cancelled="showDeleteModal = false" />
    </AdminLayout>
</template>
