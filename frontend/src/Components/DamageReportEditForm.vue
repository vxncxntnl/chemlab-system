<script setup lang="ts">
/**
 * Edit a Pending/Assessed damage report — the same form and rules for the
 * admin and lab staff pages. A short note explaining the edit is required;
 * every change is recorded in the report's Decision History.
 */
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    action: string;
    report: any;
    note?: string;
}>();

const emit = defineEmits<{ done: [] }>();

const form = useForm({
    description: props.report.description ?? '',
    quantity: props.report.quantity ?? 1,
    liable_party: props.report.liable_party ?? '',
    liable_reason: props.report.liable_reason ?? '',
    photo: null as File | null,
    note: '',
});

const photoPreview = ref<string | null>(null);
const onPhoto = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.photo = file;
    photoPreview.value = file ? URL.createObjectURL(file) : null;
};

const cancel = () => {
    form.reset();
    form.clearErrors();
    photoPreview.value = null;
    emit('done');
};

const submit = () => {
    form.transform(data => ({ ...data, liable_party: data.liable_party || null }))
        .post(props.action, { forceFormData: true, preserveScroll: true, onSuccess: cancel });
};

const inputClass = 'mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500';
</script>

<template>
    <form @submit.prevent="submit" class="mt-4 border-t border-gray-100 pt-4 space-y-4">
        <p v-if="note" class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm text-blue-800">{{ note }}</p>

        <div>
            <InputLabel for="edit-description" value="Damage Description" />
            <textarea id="edit-description" v-model="form.description" rows="3" maxlength="1000" required :class="inputClass" />
            <InputError :message="form.errors.description" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="edit-quantity" value="Damaged Quantity" />
                <TextInput id="edit-quantity" v-model.number="form.quantity" type="number" min="1" class="mt-1 block w-full" required />
                <InputError :message="form.errors.quantity" class="mt-2" />
            </div>
            <div>
                <InputLabel for="edit-liable" value="Liable Party" />
                <select id="edit-liable" v-model="form.liable_party" :class="inputClass">
                    <option value="">To be determined</option>
                    <option value="student">Student (Borrower)</option>
                    <option value="faculty">Faculty (Instructor In-Charge)</option>
                    <option value="shared">Shared — Student and Faculty</option>
                </select>
                <InputError :message="form.errors.liable_party" class="mt-2" />
            </div>
        </div>

        <div>
            <InputLabel for="edit-liable-reason" value="Reason / Justification (Optional)" />
            <textarea id="edit-liable-reason" v-model="form.liable_reason" rows="2" maxlength="1000" :class="inputClass" />
            <InputError :message="form.errors.liable_reason" class="mt-2" />
        </div>

        <div>
            <InputLabel for="edit-photo" :value="report.photo_path ? 'Replace Damage Photo (Optional)' : 'Damage Photo (Optional)'" />
            <input
                id="edit-photo"
                type="file"
                accept="image/jpeg,image/jpg,image/png,image/webp"
                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100"
                @change="onPhoto"
            />
            <InputError :message="form.errors.photo" class="mt-2" />
            <img v-if="photoPreview" :src="photoPreview" alt="Preview" class="mt-2 h-32 w-32 object-cover rounded-lg" />
        </div>

        <div>
            <InputLabel for="edit-note" value="Why are you editing this report?" />
            <TextInput id="edit-note" v-model="form.note" type="text" maxlength="500" class="mt-1 block w-full" placeholder="e.g., Rechecked — only one beaker is cracked." required />
            <InputError :message="form.errors.note" class="mt-2" />
        </div>

        <div class="flex justify-end gap-3">
            <button type="button" @click="cancel" class="text-sm text-gray-600 hover:text-gray-900">Cancel</button>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors disabled:opacity-50"
            >
                Save Changes
            </button>
        </div>
    </form>
</template>
