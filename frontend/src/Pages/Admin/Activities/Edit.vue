<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps<{
    activity: any;
    instructors: Array<any>;
    students: Array<any>;
}>();

const form = useForm({
    title: props.activity.title,
    instructor_id: props.activity.instructor_id,
    department: props.activity.department,
    activity_date: props.activity.activity_date,
    status: props.activity.status,
    description: props.activity.description ?? '',
    student_ids: props.activity.students?.map((s: any) => s.id) ?? [] as number[],
});

const studentSearch = ref('');
const filteredStudents = computed(() =>
    props.students.filter(s =>
        s.name.toLowerCase().includes(studentSearch.value.toLowerCase()) ||
        s.email.toLowerCase().includes(studentSearch.value.toLowerCase())
    )
);

const toggleStudent = (id: number) => {
    const idx = form.student_ids.indexOf(id);
    if (idx === -1) form.student_ids.push(id);
    else form.student_ids.splice(idx, 1);
};

const submit = () => form.put(route('admin.activities.update', props.activity.id));
</script>

<template>
    <AdminLayout>
        <div class="p-6 max-w-3xl mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('admin.activities.index')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-800">Edit Activity</h1>
            </div>

            <form @submit.prevent="submit" class="bg-white rounded-xl shadow p-6 space-y-5">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Activity Title <span class="text-red-500">*</span></label>
                    <input v-model="form.title" type="text" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instructor <span class="text-red-500">*</span></label>
                        <select v-model="form.instructor_id" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Select instructor</option>
                            <option v-for="inst in instructors" :key="inst.id" :value="inst.id">{{ inst.name }}</option>
                        </select>
                        <InputError :message="form.errors.instructor_id" class="mt-1" />
                    </div>
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
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Activity Date <span class="text-red-500">*</span></label>
                        <input v-model="form.activity_date" type="date" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                        <InputError :message="form.errors.activity_date" class="mt-1" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea v-model="form.description" rows="3"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Enrolled Students
                        <span class="ml-1 text-xs text-gray-400">({{ form.student_ids.length }} selected)</span>
                    </label>
                    <input v-model="studentSearch" type="text" placeholder="Search students..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 mb-2" />
                    <div class="border border-gray-200 rounded-lg max-h-48 overflow-y-auto divide-y divide-gray-100">
                        <label v-for="student in filteredStudents" :key="student.id"
                            class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" :value="student.id" :checked="form.student_ids.includes(student.id)"
                                @change="toggleStudent(student.id)"
                                class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
                            <span class="text-sm text-gray-700">{{ student.name }}</span>
                            <span class="text-xs text-gray-400 ml-auto">{{ student.email }}</span>
                        </label>
                        <p v-if="filteredStudents.length === 0" class="px-4 py-3 text-sm text-gray-400 text-center">No students found.</p>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                    <Link :href="route('admin.activities.show', activity.id)"
                        class="px-6 py-2.5 border border-gray-300 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
