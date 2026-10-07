<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps<{
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
    name: '',
    description: '',
    category: '',
    serial_number: '',
    condition: 'good',
    course_restriction: '',
    image: null as File | null,
    inventory: [
        {
            campus_id: '',
            location_id: '',
            total_quantity: 1,
            reorder_level: 5,
        },
    ],
});

const imagePreview = ref<string | null>(null);

const handleImageChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        form.image = file;
        imagePreview.value = URL.createObjectURL(file);
    }
};

const getLocationsForCampus = (campusId: string | number) => {
    const campus = props.campuses.find(c => c.id === Number(campusId));
    return campus?.locations || [];
};

const addInventoryRow = () => {
    form.inventory.push({
        campus_id: '',
        location_id: '',
        total_quantity: 1,
        reorder_level: 5,
    });
};

const removeInventoryRow = (index: number) => {
    if (form.inventory.length > 1) {
        form.inventory.splice(index, 1);
    }
};

const submit = () => {
    form.post(route('admin.equipment.store'), {
        forceFormData: true,
    });
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Add Equipment</h1>
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
                            placeholder="e.g., Projector, Laptop, Microscope"
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
                            placeholder="Optional"
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
                        </select>
                        <InputError :message="form.errors.condition" class="mt-2" />
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
                            placeholder="Equipment specifications, features, etc."
                        />
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>
                </div>
            </Card>

            <!-- Inventory -->
            <Card title="Inventory Locations">
                <p class="text-sm text-gray-600 mb-4">
                    Add equipment quantities at different campus locations.
                </p>

                <div class="space-y-4">
                    <div
                        v-for="(inv, index) in form.inventory"
                        :key="index"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-5 p-4 bg-gray-50 rounded-lg"
                    >
                        <div>
                            <InputLabel :for="'campus_' + index" value="Campus" />
                            <select
                                :id="'campus_' + index"
                                v-model="inv.campus_id"
                                class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                                required
                                @change="inv.location_id = ''"
                            >
                                <option value="">Select Campus</option>
                                <option v-for="campus in campuses" :key="campus.id" :value="campus.id">
                                    {{ campus.campus_name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <InputLabel :for="'location_' + index" value="Location" />
                            <select
                                :id="'location_' + index"
                                v-model="inv.location_id"
                                class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                                required
                                :disabled="!inv.campus_id"
                            >
                                <option value="">Select Location</option>
                                <option v-for="loc in getLocationsForCampus(inv.campus_id)" :key="loc.id" :value="loc.id">
                                    {{ loc.location_name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <InputLabel :for="'quantity_' + index" value="Quantity" />
                            <TextInput
                                :id="'quantity_' + index"
                                v-model.number="inv.total_quantity"
                                type="number"
                                min="1"
                                class="mt-1 block w-full"
                                required
                            />
                        </div>

                        <div>
                            <InputLabel :for="'reorder_' + index" value="Reorder Level" />
                            <TextInput
                                :id="'reorder_' + index"
                                v-model.number="inv.reorder_level"
                                type="number"
                                min="0"
                                class="mt-1 block w-full"
                                required
                            />
                        </div>

                        <div class="flex items-end">
                            <button
                                v-if="form.inventory.length > 1"
                                type="button"
                                @click="removeInventoryRow(index)"
                                class="inline-flex items-center px-3 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    @click="addInventoryRow"
                    class="mt-4 inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                >
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Another Location
                </button>

                <InputError :message="form.errors.inventory" class="mt-2" />
            </Card>

            <!-- Actions -->
            <div class="flex items-center justify-end space-x-4">
                <Link :href="route('admin.equipment.index')" class="text-gray-600 hover:text-gray-900">
                    Cancel
                </Link>
                <PrimaryButton :disabled="form.processing">
                    Create Equipment
                </PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>
