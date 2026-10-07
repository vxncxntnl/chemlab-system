<script setup lang="ts">
/**
 * Lab staff / admin: record a verified damage report for one borrowed item,
 * with an optional photo as evidence. Used after a student reports possible
 * damage and brings the item to the laboratory.
 */
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps<{
    // The borrowed line being reported, or null when closed.
    item: { id: number; name: string; quantity: number } | null;
    borrowingId: number;
    routeName: string; // 'lab.borrowings.record-damage' or 'admin.borrowings.record-damage'
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const form = useForm({
    borrowing_equipment_id: 0,
    quantity: 1,
    description: '',
    photo: null as File | null,
});
const preview = ref<string | null>(null);

watch(() => props.item, (item) => {
    form.reset();
    form.clearErrors();
    preview.value = null;
    if (item) form.borrowing_equipment_id = item.id;
});

const onPhoto = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.photo = file;
    preview.value = file ? URL.createObjectURL(file) : null;
};

const submit = () => {
    form.post(route(props.routeName, props.borrowingId), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <Teleport to="body">
        <div v-if="item" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true">
            <form @submit.prevent="submit"
                class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:max-w-lg sm:rounded-xl sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Record Damage</h2>
                <p class="mt-1 text-sm text-gray-600">
                    <span class="font-medium text-gray-900">{{ item.name }}</span> — {{ item.quantity }} borrowed.
                    Record this after checking the item.
                </p>

                <div class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="damage-qty" value="Damaged Quantity" />
                        <input id="damage-qty" v-model.number="form.quantity" type="number" min="1" :max="item.quantity" required
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500" />
                        <InputError :message="form.errors.quantity || form.errors.borrowing_equipment_id" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="damage-desc" value="Description of Damage" />
                        <textarea id="damage-desc" v-model="form.description" rows="3" maxlength="1000" required
                            placeholder="e.g. Crack along the base, verified with the student."
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500" />
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="damage-photo" value="Photo of the Damage (Optional)" />
                        <input id="damage-photo" type="file" accept="image/jpeg,image/jpg,image/png,image/webp" @change="onPhoto"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-green-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-green-700 hover:file:bg-green-100" />
                        <p class="mt-1 text-xs text-gray-500">JPG, JPEG, PNG or WebP, up to 10 MB. On a phone you can take the photo directly.</p>
                        <InputError :message="form.errors.photo" class="mt-1" />
                        <img v-if="preview" :src="preview" alt="Photo preview" class="mt-2 h-32 w-32 rounded-lg object-cover" />
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" @click="emit('close')"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto">
                        Cancel
                    </button>
                    <button type="submit" :disabled="form.processing"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50 sm:w-auto">
                        {{ form.processing ? 'Saving…' : 'Record Damage' }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>
