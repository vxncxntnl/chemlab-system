<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, useForm } from '@inertiajs/vue3';

// Same list of programs shown to students on the self-registration page
// (Complete Your Profile), so an admin-edited faculty account is
// assigned a department from the same set.
const facultyDepartments = [
    'Medical Technology (MedTech)',
    'Pharmacy',
    'Criminology',
    'Nursing',
    'Psychology',
    'Civil Engineering',
    'Electrical Engineering',
    'Mechanical Engineering',
    'Computer Engineering',
];

// A student's "Course" is its own field (not Department — that's
// faculty-only, see CompleteProfileController::store()), grouped here the
// same way the self-registration page groups them.
const departmentCourses: Record<string, string[]> = {
    'Health Sciences': ['Medical Technology (MedTech)', 'Pharmacy', 'Nursing'],
    'Criminal Justice Education': ['Criminology'],
    'Arts and Sciences': ['Psychology'],
    'Engineering': ['Civil Engineering', 'Electrical Engineering', 'Mechanical Engineering', 'Computer Engineering'],
};

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
        department: string | null;
        course: string | null;
        student_id: string | null;
        employee_id: string | null;
        contact_number: string | null;
        campus_id: number | null;
    };
    campuses: { id: number; campus_name: string }[];
    // One admin system-wide; one active lab staff per campus (checked again
    // on the server). Both exclude the account being edited.
    existingAdmin: { name: string; email: string } | null;
    labStaffByCampus: Record<number, string>;
}>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role: props.user.role,
    department: props.user.department || '',
    course: props.user.course || '',
    student_id: props.user.student_id || '',
    employee_id: props.user.employee_id || '',
    contact_number: props.user.contact_number || '',
    campus_id: props.user.campus_id ?? ('' as number | ''),
});

const campusTaken = (campusId: number) => form.role === 'lab_staff' && !!props.labStaffByCampus[campusId];

const submit = () => {
    form.put(route('admin.users.update', props.user.id));
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Edit User</h1>
        </template>

        <Card title="User Information">
            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel for="name" value="Full Name" />
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
                        <InputLabel for="email" value="Email" />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="form.errors.email" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="password" value="New Password (leave blank to keep current)" />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.password" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="password_confirmation" value="Confirm New Password" />
                        <TextInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                        />
                    </div>

                    <div>
                        <InputLabel for="role" value="Role" />
                        <select
                            id="role"
                            v-model="form.role"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        >
                            <option value="admin" :disabled="!!existingAdmin">Admin{{ existingAdmin ? ' (only one allowed)' : '' }}</option>
                            <option value="lab_staff">Lab Staff</option>
                            <option value="faculty">Faculty</option>
                            <option value="student">Student</option>
                        </select>
                        <p v-if="existingAdmin && form.role !== 'admin'" class="mt-1 text-xs text-gray-500">The system already has its admin: {{ existingAdmin.name }}.</p>
                        <InputError :message="form.errors.role" class="mt-2" />
                    </div>

                    <div v-if="form.role !== 'admin'">
                        <InputLabel for="campus_id" value="Campus" />
                        <select
                            id="campus_id"
                            v-model="form.campus_id"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        >
                            <option value="" disabled>Select campus</option>
                            <option v-for="campus in campuses" :key="campus.id" :value="campus.id" :disabled="campusTaken(campus.id)">
                                {{ campus.campus_name }}{{ campusTaken(campus.id) ? ` — has lab staff (${labStaffByCampus[campus.id]})` : '' }}
                            </option>
                        </select>
                        <p v-if="form.role === 'lab_staff'" class="mt-1 text-xs text-gray-500">Each campus can have one active lab staff account.</p>
                        <InputError :message="form.errors.campus_id" class="mt-2" />
                    </div>

                    <div v-if="form.role === 'student'">
                        <InputLabel for="course" value="Course / Program" />
                        <select
                            id="course"
                            v-model="form.course"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        >
                            <option value="" disabled>Select course</option>
                            <optgroup v-for="(courseList, dept) in departmentCourses" :key="dept" :label="dept">
                                <option v-for="course in courseList" :key="course" :value="course">{{ course }}</option>
                            </optgroup>
                        </select>
                        <InputError :message="form.errors.course" class="mt-2" />
                    </div>

                    <div v-else>
                        <InputLabel for="department" value="Department" />
                        <select
                            v-if="form.role === 'faculty'"
                            id="department"
                            v-model="form.department"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        >
                            <option value="" disabled>Select department</option>
                            <option v-for="dept in facultyDepartments" :key="dept" :value="dept">{{ dept }}</option>
                        </select>
                        <TextInput
                            v-else
                            id="department"
                            v-model="form.department"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.department" class="mt-2" />
                    </div>

                    <div v-if="form.role === 'student'">
                        <InputLabel for="student_id" value="Student ID" />
                        <TextInput
                            id="student_id"
                            v-model="form.student_id"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.student_id" class="mt-2" />
                    </div>

                    <div v-if="form.role !== 'student'">
                        <InputLabel for="employee_id" value="Employee ID" />
                        <TextInput
                            id="employee_id"
                            v-model="form.employee_id"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.employee_id" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="contact_number" value="Contact Number" />
                        <TextInput
                            id="contact_number"
                            v-model="form.contact_number"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.contact_number" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-4">
                    <Link :href="route('admin.users.index')" class="text-gray-600 hover:text-gray-900">
                        Cancel
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        Update User
                    </PrimaryButton>
                </div>
            </form>
        </Card>
    </AdminLayout>
</template>
