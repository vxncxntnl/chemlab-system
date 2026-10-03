<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue';
import InputError from '@/Components/InputError.vue';
import BackButton from '@/Components/BackButton.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps<{
    inventoryItems: Array<any>;
}>();

const form = useForm({
    title: '',
    department: '',
    activity_date: '',
    status: 'upcoming',
    description: '',
    equipment_inventory_ids: [] as number[],
});

const equipmentSearch = ref('');
const equipmentCampus = ref('');
const campusOptions = computed(() => {
    const names = props.inventoryItems.map(i => i.campus?.campus_name).filter(Boolean);
    return [...new Set(names)].sort();
});
const filteredEquipment = computed(() =>
    props.inventoryItems.filter(i =>
        (!equipmentCampus.value || i.campus?.campus_name === equipmentCampus.value) &&
        ((i.equipment?.name ?? '').toLowerCase().includes(equipmentSearch.value.toLowerCase()) ||
         (i.campus?.campus_name ?? '').toLowerCase().includes(equipmentSearch.value.toLowerCase()))
    )
);

const toggleEquipment = (id: number) => {
    const idx = form.equipment_inventory_ids.indexOf(id);
    if (idx === -1) form.equipment_inventory_ids.push(id);
    else form.equipment_inventory_ids.splice(idx, 1);
};

const submit = () => form.post(route('faculty.activities.store'));
</script>

<template>
    <UserLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold text-gray-800">New Laboratory Activity</h1>
                <BackButton :href="route('faculty.activities.index')">Back to Activities</BackButton>
            </div>
        </template>

        <div class="p-6 max-w-3xl mx-auto">

            <form @submit.prevent="submit" class="bg-white rounded-xl shadow p-6 space-y-5">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Activity Title <span class="text-red-500">*</span></label>
                    <input v-model="form.title" type="text" required placeholder="e.g. Acid-Base Titration Lab"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
                        <select v-model="form.department" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Select Department</option>
                            <option>Medical Technology (MedTech)</option>
                            <option>Pharmacy</option>
                            <option>Criminology</option>
                            <option>Nursing</option>
                            <option>Psychology</option>
                            <option>Civil Engineering</option>
                            <option>Electrical Engineering</option>
                            <option>Mechanical Engineering</option>
                            <option>Computer Engineering</option>
                        </select>
                        <InputError :message="form.errors.department" class="mt-1" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Activity Date <span class="text-red-500">*</span></label>
                        <input v-model="form.activity_date" type="date" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                        <InputError :message="form.errors.activity_date" class="mt-1" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea v-model="form.description" rows="3" placeholder="Optional details about this activity..."
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Equipment for this Activity
                        <span class="ml-1 text-xs text-gray-400">({{ form.equipment_inventory_ids.length }} selected — a QR code will be generated for each)</span>
                    </label>
                    <div class="flex gap-2 mb-2">
                        <input v-model="equipmentSearch" type="text" placeholder="Search equipment..."
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                        <select v-model="equipmentCampus"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">All Campuses</option>
                            <option v-for="campus in campusOptions" :key="campus" :value="campus">{{ campus }}</option>
                        </select>
                    </div>
                    <div class="border border-gray-200 rounded-lg max-h-48 overflow-y-auto divide-y divide-gray-100">
                        <label v-for="item in filteredEquipment" :key="item.id"
                            class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" :value="item.id" :checked="form.equipment_inventory_ids.includes(item.id)"
                                @change="toggleEquipment(item.id)"
                                class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
                            <span class="text-sm text-gray-700">{{ item.equipment?.name }}</span>
                            <span class="text-xs text-gray-400 ml-auto">{{ item.campus?.campus_name }} — {{ item.location?.location_name }}</span>
                        </label>
                        <p v-if="filteredEquipment.length === 0" class="px-4 py-3 text-sm text-gray-400 text-center">No equipment found.</p>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-400">Students can scan the generated QR codes to borrow these items directly for this activity — no need to add them manually.</p>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition disabled:opacity-50">
                        {{ form.processing ? 'Creating...' : 'Create Activity' }}
                    </button>
                    <Link :href="route('faculty.activities.index')"
                        class="px-6 py-2.5 border border-gray-300 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
