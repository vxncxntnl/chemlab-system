<script setup lang="ts">
import { ref, computed, watch } from 'vue';

type Faculty = { id: number; name: string; email: string; department: string | null };

const props = defineProps<{
    faculties: Faculty[];
    modelValue: string | number;
    // The student's own course (e.g. "Civil Engineering"). When faculty of
    // that department exist, it's used as the department automatically and
    // the department dropdown is skipped.
    department?: string | null;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

// A faculty's `department` field is stored as one combined string, e.g.
// "College of Science – Chemistry" — the dropdown shows just the
// department, not the specific subject/specialization after the dash.
const generalDepartment = (dept: string) => dept.split(/[–—-]/)[0].trim();

const departments = computed(() =>
    [...new Set(props.faculties.map(f => f.department).filter((d): d is string => !!d).map(generalDepartment))].sort()
);

const selectedDepartment = ref('');
const search = ref('');

// If a faculty is already selected (e.g. auto-filled from an activity/QR
// scan), preselect their department too so the picker opens already
// showing the right group instead of looking empty.
const initialFaculty = props.faculties.find(f => String(f.id) === String(props.modelValue));
if (initialFaculty?.department) {
    selectedDepartment.value = generalDepartment(initialFaculty.department);
}

// The student's course is already known — if it matches a department with
// faculty, use it and don't ask. Otherwise (no faculty for that course yet)
// fall back to choosing a department as before.
const ownDepartment = computed(() =>
    props.department && departments.value.includes(generalDepartment(props.department))
        ? generalDepartment(props.department)
        : ''
);
if (ownDepartment.value && !initialFaculty) {
    selectedDepartment.value = ownDepartment.value;
}

// Start collapsed if a faculty is already picked (e.g. preselected from a
// QR scan), otherwise open so the list is immediately usable.
const showList = ref(!initialFaculty);

watch(selectedDepartment, () => {
    search.value = '';
    // Changing department clears the pick unless it still belongs to the
    // newly selected department (covers the preselect-on-mount case above).
    const current = props.faculties.find(f => String(f.id) === String(props.modelValue));
    const stillValid = current?.department && generalDepartment(current.department) === selectedDepartment.value;
    if (!stillValid) emit('update:modelValue', '');
    showList.value = true;
});

const departmentFaculties = computed(() =>
    props.faculties.filter(f => f.department && generalDepartment(f.department) === selectedDepartment.value)
);

const filteredFaculties = computed(() => {
    const term = search.value.trim().toLowerCase();
    const list = term
        ? departmentFaculties.value.filter(f => f.name.toLowerCase().includes(term))
        : departmentFaculties.value;
    return [...list].sort((a, b) => a.name.localeCompare(b.name));
});

const selectedFaculty = computed(() =>
    props.faculties.find(f => String(f.id) === String(props.modelValue)) ?? null
);

const select = (f: Faculty) => {
    emit('update:modelValue', String(f.id));
    showList.value = false;
};

const changeSelection = () => {
    showList.value = true;
    search.value = '';
};
</script>

<template>
    <div class="space-y-2">
        <p v-if="ownDepartment && selectedDepartment === ownDepartment" class="text-xs text-gray-500">
            Showing faculty from your course: <span class="font-medium text-gray-700">{{ ownDepartment }}</span>
        </p>
        <select
            v-else
            v-model="selectedDepartment"
            class="block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
        >
            <option value="">Select department...</option>
            <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
        </select>

        <template v-if="selectedDepartment">
            <template v-if="showList">
                <input
                    type="text"
                    v-model="search"
                    placeholder="Search faculty by name..."
                    autocomplete="off"
                    class="block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                />

                <div class="max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white">
                    <button
                        v-for="f in filteredFaculties"
                        :key="f.id"
                        type="button"
                        @click="select(f)"
                        class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm last:border-b-0 hover:bg-green-50"
                        :class="String(f.id) === String(modelValue) ? 'bg-green-50 font-medium text-green-700' : 'text-gray-700'"
                    >
                        {{ f.name }}
                    </button>
                    <p v-if="filteredFaculties.length === 0" class="px-3 py-3 text-sm text-gray-400">No faculty found.</p>
                </div>
            </template>

            <button
                v-else-if="selectedFaculty"
                type="button"
                @click="changeSelection"
                class="flex w-full items-center justify-between rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-left text-sm hover:bg-green-100"
            >
                <span class="text-green-700">
                    Selected: <span class="font-medium">{{ selectedFaculty.name }}</span>
                </span>
                <span class="text-xs font-medium text-green-700">Change</span>
            </button>
        </template>
    </div>
</template>
