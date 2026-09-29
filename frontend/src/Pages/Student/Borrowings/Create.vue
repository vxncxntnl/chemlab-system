<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue';
import Card from '@/Components/Card.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import FacultyCombobox from '@/Components/FacultyCombobox.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';

const props = defineProps<{
    campuses: Array<{
        id: number;
        campus_name: string;
        locations: Array<{ id: number; location_name: string }>;
    }>;
    availableInventory: Array<{
        id: number;
        equipment_id: number;
        campus_id: number;
        location_id: number;
        available_quantity: number;
        equipment: { id: number; name: string; category: string };
        campus: { campus_name: string };
        location: { location_name: string };
    }>;
    faculties: Array<{ id: number; name: string; email: string; department: string | null }>;
    activities: Array<{ id: number; title: string; subject?: string; department: string; activity_date: string; instructor: { id: number; name: string } }>;
    preselectedInventory?: {
        id: number;
        equipment_id: number;
        campus_id: number;
        available_quantity: number;
        equipment: { id: number; name: string; category: string };
        campus: { campus_name: string };
        location: { location_name: string };
    } | null;
    preselectedActivityId?: number | null;
}>();

const initialEquipment = props.preselectedInventory
    ? [{ equipment_id: props.preselectedInventory.equipment_id, inventory_id: props.preselectedInventory.id, quantity: 1 }]
    : [];

const form = useForm({
    borrow_date: '',
    return_date: '',
    purpose: '',
    subject: '',
    faculty_id: '',
    activity_id: (props.preselectedActivityId ?? '') as string | number,
    equipment: initialEquipment as Array<{ equipment_id: number; inventory_id: number; quantity: number }>,
});

const onActivityChange = () => {
    if (!form.activity_id) {
        form.subject = '';
        form.faculty_id = '';
        return;
    }
    const activity = props.activities.find(a => a.id === Number(form.activity_id));
    if (activity) {
        form.faculty_id = String(activity.instructor.id);
        // Use subject field if available, otherwise fall back to title
        form.subject = activity.subject ?? activity.title;
    }
};

// Came here via a QR scan with an activity preselected — auto-fill instructor & subject
onMounted(() => {
    if (props.preselectedActivityId) onActivityChange();
});


const selectedCampus = ref<number | null>(props.preselectedInventory?.campus_id ?? null);
const selectedEquipment = ref<number | null>(null);
const quantity = ref(1);

// Fix 3: picker always starts closed — user clicks "Choose equipment" to open
const showAddMore = ref(false);

// Fix 4: toast after adding
const addedToast = ref(false);
let toastTimer: ReturnType<typeof setTimeout> | null = null;

const addedInventoryIds = computed(() => new Set(form.equipment.map(e => e.inventory_id)));

// Fix 5: max qty = selected item's available quantity
const selectedEquipmentMax = computed(() =>
    props.availableInventory.find(i => i.id === selectedEquipment.value)?.available_quantity ?? 99
);

// Reset qty to 1 when a different equipment is selected
watch(selectedEquipment, () => { quantity.value = 1; });

// Clamp qty if user types over the max
const clampQty = () => {
    if (quantity.value < 1) quantity.value = 1;
    if (quantity.value > selectedEquipmentMax.value) quantity.value = selectedEquipmentMax.value;
};

// Fix 6: show ALL items in dropdown but mark already-added ones as disabled
const dropdownInventory = computed(() => {
    return props.availableInventory
        .filter(i => !selectedCampus.value || i.campus_id === selectedCampus.value)
        .map(i => ({ ...i, alreadyAdded: addedInventoryIds.value.has(i.id) }));
});

const getInventoryInfo = (inventoryId: number) =>
    props.availableInventory.find(i => i.id === inventoryId);

const getRemainingQty = (inventoryId: number, currentQty: number) =>
    (getInventoryInfo(inventoryId)?.available_quantity ?? 0) - currentQty;

const incrementQty = (index: number) => {
    const item = form.equipment[index];
    const max = getInventoryInfo(item.inventory_id)?.available_quantity ?? 99;
    if (item.quantity < max) item.quantity++;
};

const decrementQty = (index: number) => {
    if (form.equipment[index].quantity > 1) form.equipment[index].quantity--;
};

const addEquipment = () => {
    if (!selectedEquipment.value) return;
    const inventory = props.availableInventory.find(i => i.id === selectedEquipment.value);
    if (!inventory) return;

    const existing = form.equipment.find(e => e.inventory_id === selectedEquipment.value);
    if (existing) {
        existing.quantity = Math.min(existing.quantity + quantity.value, inventory.available_quantity);
    } else {
        form.equipment.push({
            equipment_id: inventory.equipment_id,
            inventory_id: selectedEquipment.value,
            quantity: Math.min(quantity.value, inventory.available_quantity),
        });
    }

    // Fix 4: show "Item added" toast for 2 seconds
    addedToast.value = true;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { addedToast.value = false; }, 2000);

    selectedEquipment.value = null;
    quantity.value = 1;
    showAddMore.value = false;
};

const removeEquipment = (index: number) => {
    form.equipment.splice(index, 1);
};

const showEquipmentWarning = ref(false);

const submit = () => {
    if (selectedEquipment.value) addEquipment();
    if (form.equipment.length === 0) {
        showEquipmentWarning.value = true;
        return;
    }
    showEquipmentWarning.value = false;
    form.post(route('student.borrowings.store'));
};
</script>

<template>
    <UserLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">New Borrowing Request</h1>
        </template>

        <!-- Pre-selected equipment banner -->
        <div v-if="preselectedInventory && form.equipment.some(e => e.inventory_id === preselectedInventory!.id)"
            class="mb-4 rounded-md bg-green-50 border border-green-200 p-4 flex items-center gap-3">
            <svg class="h-5 w-5 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <div>
                <p class="text-sm font-semibold text-green-800">{{ preselectedInventory.equipment?.name }}</p>
                <p class="text-xs text-green-600">{{ preselectedInventory.campus?.campus_name }} — {{ preselectedInventory.location?.location_name }}</p>
                <p v-if="preselectedActivityId" class="mt-1 text-xs text-green-700">
                    Scanned from a QR code — this request will be linked to
                    <span class="font-semibold">{{ activities.find(a => a.id === preselectedActivityId)?.title ?? 'your activity' }}</span>
                    and routed straight to lab approval.
                </p>
            </div>
        </div>

        <!-- Fix 4: Item added toast -->
        <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 translate-y-1" enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="addedToast" class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 flex items-center gap-2 text-sm text-green-700">
                <svg class="h-4 w-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Item added to your request.
            </div>
        </transition>

        <form @submit.prevent="submit" class="space-y-6">
            <Card title="Borrowing Details">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel for="borrow_date" value="Borrow Date" />
                        <TextInput id="borrow_date" v-model="form.borrow_date" type="date" class="mt-1 block w-full"
                            :min="new Date().toISOString().split('T')[0]" required />
                        <InputError :message="form.errors.borrow_date" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="return_date" value="Return Date" />
                        <TextInput id="return_date" v-model="form.return_date" type="date" class="mt-1 block w-full"
                            :min="form.borrow_date || new Date().toISOString().split('T')[0]" required />
                        <InputError :message="form.errors.return_date" class="mt-2" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel for="purpose" value="Purpose" />
                        <textarea id="purpose" v-model="form.purpose" rows="3"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            placeholder="Describe the purpose of borrowing..."></textarea>
                        <InputError :message="form.errors.purpose" class="mt-2" />
                    </div>

                    <div class="sm:col-span-2" v-if="activities.length">
                        <InputLabel for="activity_id" value="Link to Laboratory Activity (Optional)" />
                        <select id="activity_id" v-model="form.activity_id" @change="onActivityChange"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="">No activity / borrow independently</option>
                            <option v-for="a in activities" :key="a.id" :value="a.id">
                                {{ a.title }} — {{ a.department }} ({{ new Date(a.activity_date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) }})
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Selecting an activity will auto-fill the instructor and subject.</p>
                        <InputError :message="form.errors.activity_id" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="subject" value="Activity / Laboratory Subject" />
                        <TextInput id="subject" v-model="form.subject" type="text" class="mt-1 block w-full"
                            placeholder="e.g., Organic Chemistry Laboratory" required />
                        <InputError :message="form.errors.subject" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="faculty_id" value="Assigned Faculty In-Charge" />
                        <FacultyCombobox
                            v-model="form.faculty_id"
                            :faculties="faculties"
                        />
                        <InputError :message="form.errors.faculty_id" class="mt-2" />
                    </div>
                </div>
            </Card>

            <Card title="Equipment">
                <div class="space-y-4">

                    <!-- Selected items list -->
                    <div v-if="form.equipment.length > 0">
                        <ul class="divide-y divide-gray-200 border rounded-md">
                            <li v-for="(item, index) in form.equipment" :key="index"
                                class="p-3 flex items-center justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <span class="font-medium text-gray-900">{{ getInventoryInfo(item.inventory_id)?.equipment?.name }}</span>
                                    <span class="text-sm text-gray-500 ml-2">({{ getInventoryInfo(item.inventory_id)?.campus?.campus_name }})</span>
                                    <span class="ml-2 text-xs"
                                        :class="getRemainingQty(item.inventory_id, item.quantity) === 0 ? 'text-red-500 font-medium' : 'text-gray-400'">
                                        {{ getRemainingQty(item.inventory_id, item.quantity) }} of {{ getInventoryInfo(item.inventory_id)?.available_quantity }} remaining
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <button type="button" @click="decrementQty(index)"
                                        class="w-7 h-7 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 disabled:opacity-40"
                                        :disabled="item.quantity <= 1">−</button>
                                    <span class="w-6 text-center text-sm font-medium">{{ item.quantity }}</span>
                                    <button type="button" @click="incrementQty(index)"
                                        class="w-7 h-7 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 disabled:opacity-40"
                                        :disabled="item.quantity >= (getInventoryInfo(item.inventory_id)?.available_quantity ?? 99)">+</button>
                                    <button type="button" @click="removeEquipment(index)"
                                        class="text-red-500 hover:text-red-700 text-sm ml-2">Remove</button>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div v-if="showEquipmentWarning" class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                        Please add at least one equipment item before submitting.
                    </div>
                    <InputError :message="form.errors.equipment" class="mt-2" />

                    <!-- Add / Add another button -->
                    <button v-if="!showAddMore" type="button" @click="showAddMore = true"
                        class="flex items-center gap-1 text-sm text-green-600 hover:text-green-800 font-medium">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ form.equipment.length > 0 ? 'Add another item' : 'Choose equipment' }}
                    </button>

                    <!-- Picker panel -->
                    <div v-if="showAddMore" class="border rounded-md p-4 bg-gray-50 space-y-3">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                            <div v-if="!preselectedInventory">
                                <InputLabel value="Campus (Optional)" />
                                <select v-model="selectedCampus"
                                    class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                                    <option :value="null">All Campuses</option>
                                    <option v-for="campus in campuses" :key="campus.id" :value="campus.id">{{ campus.campus_name }}</option>
                                </select>
                            </div>

                            <div :class="preselectedInventory ? 'sm:col-span-3' : 'sm:col-span-2'">
                                <InputLabel value="Equipment" />
                                <select v-model="selectedEquipment"
                                    class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                                    <option :value="null">Select equipment...</option>
                                    <!-- Fix 6: show all items, disable already-added ones -->
                                    <option v-for="item in dropdownInventory" :key="item.id" :value="item.id"
                                        :disabled="item.alreadyAdded || item.available_quantity === 0">
                                        {{ item.equipment?.name }} ({{ item.location?.location_name }})
                                        — {{ item.alreadyAdded ? 'Already in list' : item.available_quantity === 0 ? 'Out of stock' : item.available_quantity + ' available' }}
                                    </option>
                                </select>
                            </div>

                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <InputLabel value="Qty" />
                                    <!-- Fix 5: min=1, max=selected item's available qty -->
                                    <TextInput v-model="quantity" type="number"
                                        min="1" :max="selectedEquipmentMax"
                                        @blur="clampQty" @change="clampQty"
                                        class="mt-1 block w-full"
                                        :disabled="!selectedEquipment" />
                                </div>
                                <button type="button" @click="addEquipment" :disabled="!selectedEquipment"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 text-sm">
                                    Add
                                </button>
                            </div>
                        </div>
                        <!-- Fix 2: Cancel only shows when list already has items -->
                        <button v-if="form.equipment.length > 0" type="button"
                            @click="showAddMore = false; selectedEquipment = null; quantity = 1;"
                            class="text-xs text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>

                </div>
            </Card>

            <div class="flex items-center justify-end space-x-4">
                <Link :href="route('student.borrowings.index')" class="text-gray-600 hover:text-gray-900">Cancel</Link>
                <PrimaryButton :disabled="form.processing">Submit Request</PrimaryButton>
            </div>
        </form>
    </UserLayout>
</template>
