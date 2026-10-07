<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue';
import BackButton from '@/Components/BackButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Link, useForm } from '@inertiajs/vue3';

// The student gets the faculty's own campus and program automatically.
defineProps<{
    course: string | null;
    campus: { id: number; campus_name: string } | null;
}>();

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    student_id: '',
});

const submit = () => form.post(route('faculty.students.store'));

const inputClass = 'mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500';
</script>

<template>
    <UserLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2 sm:gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Add Student</h1>
                <BackButton :href="route('faculty.students.index')" />
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="space-y-5 rounded-xl bg-white p-5 shadow-sm sm:p-7">
                <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    The student will get an email with instructions to set <strong>their own password</strong>
                    (using Forgot password) or to sign in with Google. You won't see or set their password.
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="first_name" value="First Name" />
                        <input id="first_name" v-model="form.first_name" type="text" required maxlength="100" :class="inputClass" />
                        <InputError :message="form.errors.first_name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="last_name" value="Last Name" />
                        <input id="last_name" v-model="form.last_name" type="text" required maxlength="100" :class="inputClass" />
                        <InputError :message="form.errors.last_name" class="mt-1" />
                    </div>
                </div>

                <div>
                    <InputLabel for="email" value="PHINMA Email" />
                    <input id="email" v-model="form.email" type="email" required placeholder="name.surname.coc@phinmaed.com" :class="inputClass" />
                    <InputError :message="form.errors.email" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="student_id" value="Student ID" />
                    <input id="student_id" v-model="form.student_id" type="text" required maxlength="50" placeholder="e.g. 02-2324-11526" :class="inputClass" />
                    <InputError :message="form.errors.student_id" class="mt-1" />
                </div>

                <!-- Set automatically from the faculty's account -->
                <dl class="grid grid-cols-1 gap-3 rounded-lg bg-gray-50 px-4 py-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-500">Course</dt>
                        <dd class="font-medium text-gray-900">{{ course ?? 'Not set — ask the administrator to set your department' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Campus</dt>
                        <dd class="font-medium text-gray-900">{{ campus?.campus_name ?? 'Not set — ask the administrator to set your campus' }}</dd>
                    </div>
                    <p class="text-xs text-gray-500 sm:col-span-2">The student is added to your program and campus.</p>
                </dl>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Link :href="route('faculty.students.index')"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing || !campus || !course"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50 sm:w-auto">
                        {{ form.processing ? 'Creating…' : 'Create Student Account' }}
                    </button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
