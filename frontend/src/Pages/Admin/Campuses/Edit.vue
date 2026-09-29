<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    campus: {
        id: number;
        campus_name: string;
        address: string | null;
        contact_info: string | null;
    };
}>();

const form = useForm({
    campus_name: props.campus.campus_name,
    address: props.campus.address || '',
    contact_info: props.campus.contact_info || '',
});

const submit = () => {
    form.put(route('admin.campuses.update', props.campus.id));
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Edit Campus</h1>
        </template>

        <Card title="Campus Information">
            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel for="campus_name" value="Campus Name" />
                        <TextInput
                            id="campus_name"
                            v-model="form.campus_name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="form.errors.campus_name" class="mt-2" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel for="address" value="Address" />
                        <textarea
                            id="address"
                            v-model="form.address"
                            rows="3"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        />
                        <InputError :message="form.errors.address" class="mt-2" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel for="contact_info" value="Contact Information" />
                        <TextInput
                            id="contact_info"
                            v-model="form.contact_info"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Phone, email, or other contact details"
                        />
                        <InputError :message="form.errors.contact_info" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-4">
                    <Link :href="route('admin.campuses.index')" class="text-gray-600 hover:text-gray-900">
                        Cancel
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        Update Campus
                    </PrimaryButton>
                </div>
            </form>
        </Card>
    </AdminLayout>
</template>
