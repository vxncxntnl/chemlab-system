<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import BackButton from '@/Components/BackButton.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    maintenance: {
        id: number;
        equipment: { name: string; serial_number: string };
        performer?: { name: string };
        type: string;
        description: string;
        cost: number;
        maintenance_date: string;
        next_maintenance_date?: string;
        status: string;
        notes?: string;
        created_at: string;
    };
}>();

type BadgeVariant = 'success' | 'warning' | 'info' | 'default' | 'danger' | 'primary';

const getStatusBadge = (status: string): BadgeVariant => {
    const variants: Record<string, BadgeVariant> = {
        scheduled: 'warning',
        in_progress: 'primary',
        completed: 'success',
        cancelled: 'danger',
    };
    return variants[status] || 'default';
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Maintenance Details</h1>
        </template>

        <div class="max-w-3xl">
            <Card>
                <template #header>
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium">Maintenance Log #{{ maintenance.id }}</h3>
                        <Badge :variant="getStatusBadge(maintenance.status)" class="capitalize">{{ maintenance.status?.replace(/_/g, ' ') }}</Badge>
                    </div>
                </template>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Equipment</p>
                        <p class="font-medium">{{ maintenance.equipment.name }}</p>
                        <p class="text-sm text-gray-500">{{ maintenance.equipment.serial_number || 'No S/N' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Type</p>
                        <p class="font-medium capitalize">{{ maintenance.type }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Maintenance Date</p>
                        <p class="font-medium">{{ new Date(maintenance.maintenance_date).toLocaleDateString() }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Next Maintenance</p>
                        <p class="font-medium">
                            {{ maintenance.next_maintenance_date ? new Date(maintenance.next_maintenance_date).toLocaleDateString() : 'Not scheduled' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Cost</p>
                        <p class="font-medium">{{ maintenance.cost ? `₱${parseFloat(String(maintenance.cost)).toLocaleString()}` : 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Performed By</p>
                        <p class="font-medium">{{ maintenance.performer?.name || 'N/A' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-sm text-gray-500">Description</p>
                        <p class="font-medium">{{ maintenance.description }}</p>
                    </div>
                    <div v-if="maintenance.notes" class="col-span-2">
                        <p class="text-sm text-gray-500">Notes</p>
                        <p class="font-medium">{{ maintenance.notes }}</p>
                    </div>
                </div>

                <template #footer>
                    <Link :href="route('admin.maintenance.edit', maintenance.id)" class="text-green-600 hover:text-green-900">
                        Edit
                    </Link>
                </template>
            </Card>

            <div class="mt-4">
                <BackButton :href="route('admin.maintenance.index')">Back to Maintenance Logs</BackButton>
            </div>
        </div>
    </AdminLayout>
</template>
