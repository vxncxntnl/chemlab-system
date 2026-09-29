<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    stats: {
        total_items: number;
        chemistry_total: number;
        physics_total: number;
        chemistry_general: number;
        chemistry_pasco: number;
        physics_general: number;
        physics_pasco: number;
        good_condition: number;
        defective: number;
        needs_repair: number;
        transferred_iligan: number;
        transferred_butuan: number;
    };
    recentTransfers: Array<any>;
    lowQuantityItems: Array<any>;
}>();
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold" style="color: #16a34a;">Lab Inventory Dashboard</h1>
                    <p class="text-sm text-gray-500">Max Suniel Carmen, Cagayan de Oro City - S.Y. 2024-2025</p>
                </div>
                <Link
                    :href="route('admin.lab-inventory.index')"
                    class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-90 transition-colors"
                    style="background-color: #16a34a;"
                >
                    View Inventory
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Overview Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-6" style="border-top: 4px solid #16a34a;">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Total Inventory</h3>
                    <p class="text-4xl font-bold" style="color: #16a34a;">{{ stats.total_items }}</p>
                    <p class="text-sm text-gray-500 mt-2">items across both laboratories</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-6 border-t-4 border-blue-500">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Chemistry Lab</h3>
                    <p class="text-4xl font-bold text-blue-600">{{ stats.chemistry_total }}</p>
                    <div class="text-sm text-gray-500 mt-2">
                        <span>General: {{ stats.chemistry_general }}</span>
                        <span class="mx-2">|</span>
                        <span>PASCO: {{ stats.chemistry_pasco }}</span>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-6 border-t-4 border-orange-500">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Physics Lab</h3>
                    <p class="text-4xl font-bold text-orange-600">{{ stats.physics_total }}</p>
                    <div class="text-sm text-gray-500 mt-2">
                        <span>General: {{ stats.physics_general }}</span>
                        <span class="mx-2">|</span>
                        <span>PASCO: {{ stats.physics_pasco }}</span>
                    </div>
                </div>
            </div>

            <!-- Condition Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 uppercase">Good/Working</p>
                            <p class="text-3xl font-bold text-green-600">{{ stats.good_condition }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 uppercase">Needs Repair</p>
                            <p class="text-3xl font-bold text-yellow-600">{{ stats.needs_repair }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-6 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 uppercase">Defective/Broken</p>
                            <p class="text-3xl font-bold text-red-600">{{ stats.defective }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfer Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 uppercase">Transferred to Iligan</p>
                            <p class="text-3xl font-bold text-purple-600">{{ stats.transferred_iligan }}</p>
                            <p class="text-sm text-gray-400 mt-1">items sent to Iligan Campus</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white shadow-sm p-6 border-l-4 border-pink-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 uppercase">Transferred to Butuan</p>
                            <p class="text-3xl font-bold text-pink-600">{{ stats.transferred_butuan }}</p>
                            <p class="text-sm text-gray-400 mt-1">items sent to Butuan Campus</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-pink-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Recent Transfers -->
                <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="px-6 py-4 border-b" style="background-color: #16a34a;">
                        <h3 class="text-lg font-semibold text-white">Recent Transfers</h3>
                    </div>
                    <div class="divide-y">
                        <div v-for="transfer in recentTransfers" :key="transfer.id" class="px-6 py-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-900">{{ transfer.inventory?.description }}</p>
                                    <p class="text-sm text-gray-500">
                                        {{ transfer.quantity }} pcs to {{ transfer.campus === 'iligan' ? 'Iligan' : 'Butuan' }}
                                    </p>
                                </div>
                                <span class="text-xs text-gray-400">{{ transfer.transfer_date }}</span>
                            </div>
                        </div>
                        <div v-if="recentTransfers.length === 0" class="px-6 py-8 text-center text-gray-500">
                            No recent transfers
                        </div>
                    </div>
                </div>

                <!-- Low Quantity Items -->
                <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="px-6 py-4 border-b bg-yellow-500">
                        <h3 class="text-lg font-semibold text-white">Low Quantity Items</h3>
                    </div>
                    <div class="divide-y">
                        <div v-for="item in lowQuantityItems" :key="item.id" class="px-6 py-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-medium text-gray-900">{{ item.description }}</p>
                                    <p class="text-sm text-gray-500">{{ item.lab }} - {{ item.storage_location }}</p>
                                </div>
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-semibold">
                                    {{ item.quantity }} left
                                </span>
                            </div>
                        </div>
                        <div v-if="lowQuantityItems.length === 0" class="px-6 py-8 text-center text-gray-500">
                            No low quantity items
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
