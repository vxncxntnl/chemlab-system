<script setup lang="ts">
/**
 * Reject a damage report (no damage / filed by mistake): pick a reason,
 * optionally add details ("Other" requires them). Used by both the admin and
 * lab staff damage report pages.
 */
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    action: string;
    reasons: string[];
    note?: string;
}>();

const emit = defineEmits<{ cancel: [] }>();

const form = useForm({
    reason: '',
    details: '',
});

const cancel = () => {
    form.reset();
    form.clearErrors();
    emit('cancel');
};

const submit = () => {
    form.post(props.action, {
        preserveScroll: true,
        onSuccess: cancel,
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="mt-4 border-t border-gray-100 pt-4 space-y-4">
        <div class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-800">
            Rejecting means <strong>no damage</strong> — no replacement is required.
            <span v-if="note"> {{ note }}</span>
        </div>
        <div>
            <InputLabel for="reject-reason" value="Reason" />
            <select
                id="reject-reason"
                v-model="form.reason"
                required
                class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-red-500 focus:ring-red-500"
            >
                <option value="" disabled>Select a reason...</option>
                <option v-for="r in reasons" :key="r" :value="r">{{ r }}</option>
            </select>
            <InputError :message="form.errors.reason" class="mt-2" />
        </div>
        <div>
            <InputLabel for="reject-details" :value="form.reason === 'Other' ? 'Details' : 'Details (Optional)'" />
            <textarea
                id="reject-details"
                v-model="form.details"
                rows="3"
                maxlength="500"
                :required="form.reason === 'Other'"
                placeholder="e.g., Inspected the beaker — no cracks, residue only, cleaned."
                class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-red-500 focus:ring-red-500"
            />
            <InputError :message="form.errors.details" class="mt-2" />
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" @click="cancel" class="text-sm text-gray-600 hover:text-gray-900">
                Cancel
            </button>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-red-600 text-sm font-semibold text-white shadow-sm hover:bg-red-700 transition-colors disabled:opacity-50"
            >
                Confirm Rejection
            </button>
        </div>
    </form>
</template>
