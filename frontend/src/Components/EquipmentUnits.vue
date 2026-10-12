<script setup lang="ts">
/**
 * Units of a serial-tracked equipment item at this campus: each unit's tag,
 * serial number (editable), and whether it's under maintenance right now.
 */
import Card from '@/Components/Card.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputError from '@/Components/InputError.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Unit = {
    id: number;
    unit_code: string;
    serial_number: string | null;
    notes: string | null;
    maintenance: { id: number; type: string; status: string; maintenance_date: string } | null;
};

const props = defineProps<{ equipmentId: number; units: Unit[]; inStock: number }>();

const underMaintenance = computed(() => props.units.filter(u => u.maintenance).length);
const missingSerial = computed(() => props.units.filter(u => !u.serial_number).length);

// One row edited at a time.
const editingId = ref<number | null>(null);
const editForm = useForm({ serial_number: '', notes: '' });
const startEdit = (u: Unit) => {
    editingId.value = u.id;
    editForm.clearErrors();
    editForm.serial_number = u.serial_number ?? '';
    editForm.notes = u.notes ?? '';
};
const saveEdit = (u: Unit) => {
    editForm.put(route('admin.equipment.units.update', u.id), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
};

const addForm = useForm({ serial_number: '', notes: '' });
const showAdd = ref(false);
const addUnit = () => {
    addForm.post(route('admin.equipment.units.store', props.equipmentId), {
        preserveScroll: true,
        onSuccess: () => { addForm.reset(); showAdd.value = false; },
    });
};

const removing = ref<Unit | null>(null);
const doRemove = () => {
    if (removing.value) router.delete(route('admin.equipment.units.destroy', removing.value.id), { preserveScroll: true });
    removing.value = null;
};

const inputClass = 'block w-full rounded-lg border-gray-200 text-sm shadow-sm focus:border-green-500 focus:ring-green-500';
const typeLabel = (t: string) => t.charAt(0).toUpperCase() + t.slice(1);
</script>

<template>
    <Card title="Units & Serial Numbers">
        <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-gray-700">{{ units.length }} unit(s)</span>
            <span class="rounded-full bg-green-50 px-2.5 py-0.5 text-green-700">{{ units.length - underMaintenance }} available</span>
            <span v-if="underMaintenance" class="rounded-full bg-orange-100 px-2.5 py-0.5 font-semibold text-orange-800">{{ underMaintenance }} under maintenance</span>
            <span v-if="missingSerial" class="rounded-full bg-yellow-50 px-2.5 py-0.5 text-yellow-800">{{ missingSerial }} without serial number yet</span>
            <span v-if="units.length !== inStock" class="text-xs text-gray-500">(stock count here: {{ inStock }})</span>
            <button v-if="!showAdd" type="button" @click="showAdd = true"
                class="ml-auto rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 print:hidden">+ Add Unit</button>
        </div>

        <form v-if="showAdd" @submit.prevent="addUnit" class="mb-4 grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 sm:grid-cols-3 print:hidden">
            <div>
                <input v-model="addForm.serial_number" type="text" maxlength="100" placeholder="Serial number (optional)" :class="inputClass" />
                <InputError :message="addForm.errors.serial_number" class="mt-1" />
            </div>
            <input v-model="addForm.notes" type="text" maxlength="255" placeholder="Notes (optional)" :class="inputClass" />
            <div class="flex items-center gap-3">
                <button type="submit" :disabled="addForm.processing" class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-green-700">Add</button>
                <button type="button" @click="showAdd = false" class="text-sm text-gray-600 hover:text-gray-900">Cancel</button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="px-3 py-2">Unit</th>
                        <th class="px-3 py-2">Serial Number</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Notes</th>
                        <th class="px-3 py-2 text-right print:hidden">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="u in units" :key="u.id" :class="u.maintenance ? 'bg-orange-50' : ''">
                        <td class="whitespace-nowrap px-3 py-2 font-mono text-gray-800">{{ u.unit_code }}</td>
                        <template v-if="editingId === u.id">
                            <td class="px-3 py-2">
                                <input v-model="editForm.serial_number" type="text" maxlength="100" placeholder="Serial number" :class="inputClass" />
                                <InputError :message="editForm.errors.serial_number" class="mt-1" />
                            </td>
                            <td class="px-3 py-2 text-gray-500">—</td>
                            <td class="px-3 py-2"><input v-model="editForm.notes" type="text" maxlength="255" placeholder="Notes" :class="inputClass" /></td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                <button type="button" @click="saveEdit(u)" :disabled="editForm.processing" class="font-semibold text-green-700 hover:text-green-900">Save</button>
                                <button type="button" @click="editingId = null" class="ml-3 text-gray-500 hover:text-gray-800">Cancel</button>
                            </td>
                        </template>
                        <template v-else>
                            <td class="px-3 py-2 font-mono" :class="u.serial_number ? 'text-gray-900' : 'italic text-gray-400'">{{ u.serial_number || 'Not recorded' }}</td>
                            <td class="px-3 py-2">
                                <Link v-if="u.maintenance" :href="route('admin.maintenance.show', u.maintenance.id)"
                                    class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-semibold text-orange-800 hover:bg-orange-200">
                                    🔧 Under Maintenance — {{ typeLabel(u.maintenance.type) }}
                                </Link>
                                <span v-else class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700 ring-1 ring-green-200">Available</span>
                            </td>
                            <td class="px-3 py-2 text-gray-600">{{ u.notes || '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right print:hidden">
                                <button type="button" @click="startEdit(u)" class="font-semibold text-green-700 hover:text-green-900">Edit</button>
                                <button type="button" @click="removing = u" class="ml-3 text-red-600 hover:text-red-800">Remove</button>
                            </td>
                        </template>
                    </tr>
                    <tr v-if="!units.length">
                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">No units at your campus yet. Use “Add Unit”.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ConfirmModal :show="!!removing" title="Remove Unit"
            :message="`Remove unit ${removing?.unit_code ?? ''}? Use this for a unit that was disposed of. Its past maintenance records are kept.`"
            confirmLabel="Yes, Remove" confirmClass="bg-red-600 hover:bg-red-700" @confirmed="doRemove" @cancelled="removing = null" />
    </Card>
</template>
