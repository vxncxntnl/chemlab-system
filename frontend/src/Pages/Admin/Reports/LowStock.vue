<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';

defineProps<{
    items: Array<{
        id: number;
        equipment: { name: string };
        campus: { campus_name: string };
        location: { location_name: string };
        available_quantity: number;
        reorder_level: number;
        total_quantity: number;
    }>;
}>();
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Low Stock Report</h1>
        </template>

        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Equipment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Available</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reorder Level</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="item in items" :key="item.id">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ item.equipment?.name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ item.campus?.campus_name }} - {{ item.location?.location_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ item.available_quantity }} / {{ item.total_quantity }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ item.reorder_level }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <Badge :variant="item.available_quantity === 0 ? 'danger' : 'warning'">
                                    {{ item.available_quantity === 0 ? 'Out of Stock' : 'Low Stock' }}
                                </Badge>
                            </td>
                        </tr>
                        <tr v-if="items.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">
                                All items are well stocked
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AdminLayout>
</template>
