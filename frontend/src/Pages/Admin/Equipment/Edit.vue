<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Badge from '@/Components/Badge.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    equipment: {
        id: number;
        name: string;
        description: string | null;
        category: string | null;
        serial_number: string | null;
        condition: string;
        is_available: boolean;
        image_path: string | null;
        course_restriction: string | null;
        inventory: Array<{
            id: number;
            campus_id: number;
            location_id: number;
            total_quantity: number;
            available_quantity: number;
            reorder_level: number;
        }>;
    };
    campuses: Array<{
        id: number;
        campus_name: string;
        locations: Array<{
            id: number;
            location_name: string;
        }>;
    }>;
    categories: Array<{
        id: number;
        name: string;
    }>;
}>();

const form = useForm({
    name: props.equipment.name,
    description: props.equipment.description || '',
    category: props.equipment.category || '',
    serial_number: props.equipment.serial_number || '',
    condition: props.equipment.condition,
    is_available: props.equipment.is_available,
    course_restriction: props.equipment.course_restriction || '',
    image: null as File | null,
});

const imagePreview = ref<string | null>(
    props.equipment.image_path ? `/storage/${props.equipment.image_path}` : null
);

const handleImageChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        form.image = file;
        imagePreview.value = URL.createObjectURL(file);
    }
};

const getConditionBadge = (condition: string) => {
    const variants: Record<string, any> = {
        new: 'success',
        good: 'primary',
        fair: 'warning',
        poor: 'danger',
        damaged: 'danger',
    };
    return variants[condition] || 'default';
};

// Sent as POST with _method=put: PHP can't read uploaded files from a real
// multipart PUT request, so the photo was silently dropped before.
const submit = () => {
    form.transform((data) => ({ ...data, _method: 'put' }))
        .post(route('admin.equipment.update', props.equipment.id), {
            forceFormData: true,
        });
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Edit Equipment</h1>
        </template>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Basic Information -->
            <Card title="Equipment Information">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel for="name" value="Equipment Name" />
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
                        <InputLabel for="category" value="Category" />
                        <select
                            id="category"
                            v-model="form.category"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        >
                            <option value="">Select Category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.name">
                                {{ cat.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.category" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="serial_number" value="Serial Number" />
                        <TextInput
                            id="serial_number"
                            v-model="form.serial_number"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.serial_number" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="condition" value="Condition" />
                        <select
                            id="condition"
                            v-model="form.condition"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            required
                        >
                            <option value="new">New</option>
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                            <option value="poor">Poor</option>
                            <option value="damaged">Damaged</option>
                        </select>
                        <InputError :message="form.errors.condition" class="mt-2" />
                    </div>

                    <div class="flex items-center">
                        <input
                            id="is_available"
                            v-model="form.is_available"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500"
                        />
                        <label for="is_available" class="ml-2 block text-sm text-gray-900">
                            Available for borrowing
                        </label>
                    </div>

                    <div>
                        <InputLabel for="image" value="Image" />
                        <input
                            id="image"
                            type="file"
                            accept="image/jpeg,image/jpg,image/png,image/webp"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100"
                            @change="handleImageChange"
                        />
                        <InputError :message="form.errors.image" class="mt-2" />
                        <img v-if="imagePreview" :src="imagePreview" alt="Preview" class="mt-2 h-32 w-32 object-cover rounded-lg" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel for="course_restriction" value="Course Restriction (optional)" />
                        <TextInput
                            id="course_restriction"
                            v-model="form.course_restriction"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., BSED,BSIT (leave blank for all courses)"
                        />
                        <p class="mt-1 text-xs text-gray-500">Comma-separated list of allowed courses. Leave blank to allow all students.</p>
                        <InputError :message="form.errors.course_restriction" class="mt-2" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        />
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>
                </div>
            </Card>

            <!-- Current Inventory (Read Only) -->
            <Card title="Current Inventory">
                <p class="text-sm text-gray-600 mb-4">
                    To modify inventory quantities, use the Inventory Management page.
                </p>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Campus</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Available</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reorder Level</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="inv in equipment.inventory" :key="inv.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                    {{ campuses.find(c => c.id === inv.campus_id)?.campus_name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ campuses.find(c => c.id === inv.campus_id)?.locations.find(l => l.id === inv.location_id)?.location_name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                    {{ inv.total_quantity }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span :class="inv.available_quantity <= inv.reorder_level ? 'text-red-600 font-semibold' : 'text-green-600'">
                                        {{ inv.available_quantity }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ inv.reorder_level }}
                                </td>
                            </tr>
                            <tr v-if="equipment.inventory.length === 0">
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No inventory records found
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <!-- Actions -->
            <div class="flex items-center justify-end space-x-4">
                <Link :href="route('admin.equipment.index')" class="text-gray-600 hover:text-gray-900">
                    Cancel
                </Link>
                <PrimaryButton :disabled="form.processing">
                    Update Equipment
                </PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>
