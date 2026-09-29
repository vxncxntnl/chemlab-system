<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import Badge from '@/Components/Badge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import BackButton from '@/Components/BackButton.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    reservation: {
        id: number;
        user: { name: string; email: string };
        equipment: { name: string; serial_number: string };
        reserved_date: string;
        reserved_until: string;
        purpose: string;
        status: string;
        approver?: { name: string };
        remarks?: string;
        created_at: string;
    };
}>();

const showRejectModal = ref(false);
const rejectForm = useForm({
    remarks: '',
});

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

const approve = () => {
    router.post(route('admin.reservations.approve', props.reservation.id));
};

const submitReject = () => {
    rejectForm.post(route('admin.reservations.reject', props.reservation.id), {
        onSuccess: () => {
            showRejectModal.value = false;
        },
    });
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Reservation Details</h1>
        </template>

        <div class="max-w-3xl">
            <Card>
                <template #header>
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium">Reservation #{{ reservation.id }}</h3>
                        <Badge :variant="getStatusBadge(reservation.status)" class="capitalize">{{ reservation.status?.replace(/_/g, ' ') }}</Badge>
                    </div>
                </template>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Requested By</p>
                        <p class="font-medium">{{ reservation.user.name }}</p>
                        <p class="text-sm text-gray-500">{{ reservation.user.email }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Equipment</p>
                        <p class="font-medium">{{ reservation.equipment.name }}</p>
                        <p class="text-sm text-gray-500">{{ reservation.equipment.serial_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Reserved From</p>
                        <p class="font-medium">{{ new Date(reservation.reserved_date).toLocaleDateString() }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Reserved Until</p>
                        <p class="font-medium">{{ new Date(reservation.reserved_until).toLocaleDateString() }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-sm text-gray-500">Purpose</p>
                        <p class="font-medium">{{ reservation.purpose || 'Not specified' }}</p>
                    </div>
                    <div v-if="reservation.remarks" class="col-span-2">
                        <p class="text-sm text-gray-500">Remarks</p>
                        <p class="font-medium">{{ reservation.remarks }}</p>
                    </div>
                </div>

                <template #footer v-if="reservation.status === 'pending'">
                    <div class="flex gap-4">
                        <PrimaryButton @click="approve">Approve</PrimaryButton>
                        <DangerButton @click="showRejectModal = true">Reject</DangerButton>
                    </div>
                </template>
            </Card>

            <div class="mt-4">
                <BackButton :href="route('admin.reservations.index')">Back to Reservations</BackButton>
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-medium mb-4">Reject Reservation</h3>
                <form @submit.prevent="submitReject">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for rejection</label>
                        <textarea
                            v-model="rejectForm.remarks"
                            rows="3"
                            class="w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            required
                        ></textarea>
                    </div>
                    <div class="flex justify-end gap-4">
                        <button type="button" @click="showRejectModal = false" class="text-gray-600">Cancel</button>
                        <DangerButton :disabled="rejectForm.processing">Reject</DangerButton>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
