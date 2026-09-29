<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/Components/EmptyState.vue';

defineProps<{
    columns: Array<{ key: string; label: string; sortable?: boolean }>;
    data: Array<Record<string, any>>;
    pagination?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();
</script>

<template>
    <div class="overflow-hidden bg-white rounded-xl border border-gray-100 shadow-sm">
        <!-- The table below already scrolls horizontally on its own (overflow-x-auto) rather than
             forcing the whole page to scroll sideways — but on a narrow screen nothing signals that
             there's more to see off-screen, so a first-time mobile user can miss columns entirely. -->
        <p v-if="data.length > 0" class="sm:hidden px-5 pt-3 text-xs text-gray-400">◂ Swipe to see more ▸</p>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-400"
                        >
                            {{ column.label }}
                        </th>
                        <th v-if="$slots.actions" scope="col" class="relative px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-400">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 bg-white">
                    <tr
                        v-for="(row, index) in data"
                        :key="index"
                        class="hover:bg-green-50/30 transition-colors duration-100"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-5 py-3.5 text-sm text-gray-700"
                        >
                            <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                                {{ row[column.key] }}
                            </slot>
                        </td>
                        <td v-if="$slots.actions" class="px-5 py-3.5 text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-3">
                                <slot name="actions" :row="row" />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="data.length === 0">
                        <td :colspan="columns.length + ($slots.actions ? 1 : 0)">
                            <slot name="empty">
                                <EmptyState />
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="border-t border-gray-100 bg-gray-50/50 px-5 py-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-gray-500">
                    Showing
                    <span class="font-semibold text-gray-700">{{ (pagination.current_page - 1) * pagination.per_page + 1 }}</span>
                    –
                    <span class="font-semibold text-gray-700">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}</span>
                    of
                    <span class="font-semibold text-gray-700">{{ pagination.total }}</span>
                    results
                </p>
                <div class="flex items-center gap-1">
                    <template v-for="(link, index) in pagination.links" :key="index">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="[
                                'inline-flex items-center justify-center min-w-[32px] h-8 px-2.5 text-xs font-medium rounded-lg transition-colors',
                                link.active
                                    ? 'bg-green-600 text-white shadow-sm'
                                    : 'text-gray-500 hover:bg-gray-100'
                            ]"
                            v-html="link.label"
                            preserve-scroll
                        />
                        <span
                            v-else
                            class="inline-flex items-center justify-center min-w-[32px] h-8 px-2.5 text-xs font-medium text-gray-300"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
