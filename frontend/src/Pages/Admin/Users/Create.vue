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

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'student',
    department: '',
    student_id: '',
    employee_id: '',
    contact_number: '',
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
                            <option value="admin">Admin</option>
                            <option value="faculty">Faculty</option>
                            <option value="student">Student</option>
                        </select>
                        <InputError :message="form.errors.role" class="mt-2" />
                    </div>

                    <div>
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
