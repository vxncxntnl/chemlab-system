<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, useForm } from '@inertiajs/vue3';

defineProps<{
    campuses: Array<{
        id: number;
        campus_name: string;
    }>;
}>();

const form = useForm({
    campus_id: '',
    location_name: '',
    description: '',
});

const submit = () => {
    form.post(route('admin.locations.store'));
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Create Location</h1>
        </template>

        <Card title="Location Information">
            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel for="campus_id" value="Campus" />
                        <select
                            id="campus_id"
                            v-model="form.campus_id"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            required
                        >
                            <option value="">Select Campus</option>
                            <option v-for="campus in campuses" :key="campus.id" :value="campus.id">
                                {{ campus.campus_name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.campus_id" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="location_name" value="Location Name" />
                        <TextInput
                            id="location_name"
                            v-model="form.location_name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            placeholder="e.g., Room 101, Lab A, Storage Building"
                        />
                        <InputError :message="form.errors.location_name" class="mt-2" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            placeholder="Additional details about this location"
                        />
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-4">
                    <Link :href="route('admin.locations.index')" class="text-gray-600 hover:text-gray-900">
                        Cancel
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        Create Location
                    </PrimaryButton>
                </div>
            </form>
        </Card>
    </AdminLayout>
</template>
