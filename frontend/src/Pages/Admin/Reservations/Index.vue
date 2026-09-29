<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    reservations: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
}>();

const columns = [
    { key: 'user.name', label: 'Requested By' },
    { key: 'equipment.name', label: 'Equipment' },
    { key: 'reserved_date', label: 'From' },
    { key: 'reserved_until', label: 'Until' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Requested At' },
];

type BadgeVariant = 'success' | 'warning' | 'info' | 'default' | 'danger' | 'primary';

const getStatusBadge = (status: string): BadgeVariant => {
    const variants: Record<string, BadgeVariant> = {
        pending: 'warning',
        approved: 'success',
        rejected: 'danger',
        cancelled: 'default',
        converted: 'primary',
    };
    return variants[status] || 'default';
};

const approve = (id: number) => {
    router.post(route('admin.reservations.approve', id));
};

const showDeleteModal = ref(false);
const deleteTargetId = ref<number | null>(null);
const deleteReservation = (id: number) => { deleteTargetId.value = id; showDeleteModal.value = true; };
const doDelete = () => { router.delete(route('admin.reservations.destroy', deleteTargetId.value!)); showDeleteModal.value = false; };
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Reservations</h1>
        </template>

        <DataTable :columns="columns" :data="reservations.data" :pagination="reservations">
            <template #cell-user.name="{ row }">
                {{ row.user?.name || 'N/A' }}
            </template>
            <template #cell-equipment.name="{ row }">
                {{ row.equipment?.name || 'N/A' }}
            </template>
            <template #cell-reserved_date="{ value }">
                {{ new Date(value).toLocaleDateString() }}
            </template>
            <template #cell-reserved_until="{ value }">
                {{ new Date(value).toLocaleDateString() }}
            </template>
            <template #cell-status="{ value }">
                <Badge :variant="getStatusBadge(value)" class="capitalize">{{ value?.replace(/_/g, ' ') }}</Badge>
            </template>
            <template #cell-created_at="{ value }">
                {{ new Date(value).toLocaleDateString() }}
            </template>
            <template #actions="{ row }">
                <div class="flex space-x-2">
                    <Link :href="route('admin.reservations.show', row.id)" class="text-green-600 hover:text-green-900">
                        View
                    </Link>
                    <button
                        v-if="row.status === 'pending'"
                        @click="approve(row.id)"
                        class="text-green-600 hover:text-green-900"
                    >
                        Approve
                    </button>
                    <button @click="deleteReservation(row.id)" class="text-red-600 hover:text-red-900">
                        Delete
                    </button>
                </div>
            </template>
        </DataTable>
        <ConfirmModal :show="showDeleteModal" title="Delete Reservation" message="Are you sure you want to delete this reservation?" confirmLabel="Yes, Delete" confirmClass="bg-red-600 hover:bg-red-700" @confirmed="doDelete" @cancelled="showDeleteModal = false" />
    </AdminLayout>
</template>
