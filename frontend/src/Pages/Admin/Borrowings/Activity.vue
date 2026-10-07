<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import BackButton from '@/Components/BackButton.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { formatDateTime } from '@/utils/date';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useBorrowingStatus } from '@/composables/useBorrowingStatus';

const { formatStatus, statusVariant } = useBorrowingStatus();

// One QR activity's student requests. Each is still its own borrowing:
// approve here, or open it to release / return / record damage.
const props = defineProps<{
    activity: { id: number; title: string; department?: string | null; activity_date: string; return_date?: string | null; status: string; instructor?: string | null };
    requests: Array<any>;
    needApprovalCount: number;
}>();

const formatDate = (d?: string | null) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
const itemsOf = (r: any) => (r.borrowing_equipment ?? []).map((i: any) => `${i.equipment?.name ?? 'Item'} ×${i.quantity}`).join(', ');

// Cancelled requests are hidden by default (nothing to do with them).
const showCancelled = ref(false);
const activeRequests = computed(() => props.requests.filter(r => r.status !== 'cancelled'));
const cancelledCount = computed(() => props.requests.length - activeRequests.value.length);
const shownRequests = computed(() => showCancelled.value ? props.requests : activeRequests.value);

const approving = ref<number | null>(null);
const approveOne = (id: number) => {
    approving.value = id;
    router.post(route('admin.borrowings.approve', id), {}, {
        preserveScroll: true,
        onFinish: () => { approving.value = null; },
    });
};

const showApproveAll = ref(false);
const approvingAll = ref(false);
const approveAll = () => {
    approvingAll.value = true;
    router.post(route('admin.borrowings.approve-activity', props.activity.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            approvingAll.value = false;
            showApproveAll.value = false;
        },
    });
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-xl font-semibold text-gray-800">{{ activity.title }}</h1>
                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">QR Activity</span>
                <BackButton :href="route('admin.borrowings.index')">Back to Borrowings</BackButton>
            </div>
        </template>

        <div class="mb-5 flex flex-col gap-4 rounded-xl bg-white p-5 shadow sm:flex-row sm:items-center sm:justify-between">
            <div class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm sm:grid-cols-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">Instructor</p>
                    <p class="font-semibold text-gray-800">{{ activity.instructor ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">Program</p>
                    <p class="font-semibold text-gray-800">{{ activity.department || '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">Borrow Date</p>
                    <p class="font-semibold text-gray-800">{{ formatDate(activity.activity_date) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">Return Date</p>
                    <p class="font-semibold text-gray-800">{{ formatDate(activity.return_date) }}</p>
                </div>
            </div>
            <button v-if="needApprovalCount > 0" type="button" @click="showApproveAll = true"
                class="w-full rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-700 sm:w-auto">
                Approve all {{ needApprovalCount }}
            </button>
        </div>

        <div class="rounded-xl bg-white shadow">
            <h2 class="border-b border-gray-100 px-5 py-4 text-base font-semibold text-gray-800">
                Student Requests <span class="text-sm font-normal text-gray-400">({{ activeRequests.length }})</span>
            </h2>

            <ul v-if="shownRequests.length" class="divide-y divide-gray-100">
                <li v-for="(r, index) in shownRequests" :key="r.id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900">{{ index + 1 }}. {{ r.user?.name }}</p>
                        <p class="text-sm text-gray-500 break-all">{{ r.user?.email }}</p>
                        <p class="mt-1 text-xs text-gray-500">Requested {{ formatDateTime(r.created_at) }} · {{ itemsOf(r) }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                        <Badge :variant="statusVariant(r.status)">{{ formatStatus(r.status) }}</Badge>
                        <span v-if="r.has_damage_report" class="whitespace-nowrap rounded-full bg-yellow-100 px-2 py-0.5 text-[11px] font-semibold text-yellow-800">⚠ Damage reported</span>
                        <button v-if="r.status === 'pending_admin'" type="button" :disabled="approving === r.id" @click="approveOne(r.id)"
                            class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700 disabled:opacity-50">
                            {{ approving === r.id ? 'Approving…' : 'Approve' }}
                        </button>
                        <Link :href="route('admin.borrowings.show', r.id)"
                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-green-700 hover:bg-gray-50">
                            View
                        </Link>
                    </div>
                </li>
            </ul>
            <p v-else class="px-5 py-6 text-sm text-gray-400">No active student requests for this activity.</p>

            <!-- Cancelled requests need no action: hidden, but kept as history -->
            <div v-if="cancelledCount" class="border-t border-gray-100 px-5 py-3">
                <button type="button" class="text-xs font-semibold text-gray-500 hover:text-gray-700" @click="showCancelled = !showCancelled">
                    {{ showCancelled ? 'Hide cancelled' : `Show cancelled (${cancelledCount})` }}
                </button>
            </div>
        </div>

        <ConfirmModal
            :show="showApproveAll"
            title="Approve All Requests"
            :message="`Approve ${needApprovalCount} request(s) for &quot;${activity.title}&quot;? Each student keeps their own request and claim code, and each one will be notified.`"
            :confirmLabel="`Yes, Approve ${needApprovalCount}`"
            confirmClass="bg-green-600 hover:bg-green-700"
            :processing="approvingAll"
            @confirmed="approveAll"
            @cancelled="showApproveAll = false"
        />
    </AdminLayout>
</template>
