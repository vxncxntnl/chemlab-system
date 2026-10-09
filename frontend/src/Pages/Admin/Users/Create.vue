<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, useForm } from '@inertiajs/vue3';

// Same list of programs shown to students on the self-registration page
// (Complete Your Profile), so an admin-created faculty account is
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
    campuses: { id: number; campus_name: string }[];
    // Each campus: one active admin and one active lab staff (checked again on
    // the server). Only the main campus (Carmen) admin creates admins, and for
    // other campuses only Admin/Lab Staff accounts.
    isMainAdmin: boolean;
    ownCampusId: number;
    adminByCampus: Record<number, string>;
    labStaffByCampus: Record<number, string>;
}>();

/** Why a campus can't be picked for the chosen role ('' = it can). */
const campusBlock = (campusId: number): string => {
    if (form.role === 'admin' && props.adminByCampus[campusId]) return `has admin (${props.adminByCampus[campusId]})`;
    if (form.role === 'lab_staff' && props.labStaffByCampus[campusId]) return `has lab staff (${props.labStaffByCampus[campusId]})`;
    if (props.isMainAdmin && ['faculty', 'student'].includes(form.role) && campusId !== props.ownCampusId) return "managed by that campus's admin";
    return '';
};

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'student',
    department: '',
    course: '',
    student_id: '',
    employee_id: '',
    contact_number: '',
    // A campus admin can only pick their own campus — preselect it.
    campus_id: (props.campuses.length === 1 ? props.campuses[0].id : '') as number | '',
});

const submit = () => {
    form.post(route('admin.users.store'));
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Create User</h1>
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
                        <InputLabel for="password" value="Password" />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="form.errors.password" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="password_confirmation" value="Confirm Password" />
                        <TextInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            required
                        />
                    </div>

                    <div>
                        <InputLabel for="role" value="Role" />
                        <select
                            id="role"
                            v-model="form.role"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        >
                            <option v-if="isMainAdmin" value="admin">Admin</option>
                            <option value="lab_staff">Lab Staff</option>
                            <option value="faculty">Faculty</option>
                            <option value="student">Student</option>
                        </select>
                        <InputError :message="form.errors.role" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="campus_id" value="Campus" />
                        <select
                            id="campus_id"
                            v-model="form.campus_id"
                            class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        >
                            <option value="" disabled>Select campus</option>
                            <option v-for="campus in campuses" :key="campus.id" :value="campus.id" :disabled="!!campusBlock(campus.id)">
                                {{ campus.campus_name }}{{ campusBlock(campus.id) ? ` — ${campusBlock(campus.id)}` : '' }}
                            </option>
                        </select>
                        <p v-if="['admin', 'lab_staff'].includes(form.role)" class="mt-1 text-xs text-gray-500">Each campus can have one active {{ form.role === 'admin' ? 'admin' : 'lab staff' }} account.</p>
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
                        Create User
                    </PrimaryButton>
                </div>
            </form>
        </Card>
    </AdminLayout>
</template>
