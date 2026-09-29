<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    category: {
        id: number;
        name: string;
        description: string | null;
        is_active: boolean;
    };
}>();

const form = useForm({
    name: props.category.name,
    description: props.category.description || '',
    is_active: props.category.is_active,
});

const submit = () => {
    form.put(route('admin.categories.update', props.category.id));
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Edit Category</h1>
        </template>

        <Card title="Category Information">
            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <InputLabel for="name" value="Category Name" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        />
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>

                    <div class="flex items-center">
                        <input
                            id="is_active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500"
                        />
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">
                            Active (visible in equipment forms)
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-4">
                    <Link :href="route('admin.categories.index')" class="text-gray-600 hover:text-gray-900">
                        Cancel
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        Update Category
                    </PrimaryButton>
                </div>
            </form>
        </Card>
    </AdminLayout>
</template>
