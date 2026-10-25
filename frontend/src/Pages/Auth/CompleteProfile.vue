<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    // open = the campus has an admin (or is the main campus), so students can join it
    campuses: { id: number; campus_name: string; open: boolean }[];
    user: any;
}>();

const logoError = ref(false);
const selectedRole = ref<'student' | 'faculty'>('student');

// Single source of truth: every student course belongs to exactly one
// faculty department — previously these were two unrelated hardcoded
// lists with no connection between a student's specific program and the
// broader department a faculty member selects. Only departments that
// actually use the science laboratory (and so have equipment to borrow)
// are listed — Science and Technology, Business Administration, and
// General Education were removed since those departments don't use the
// lab at all.
const departmentCourses: Record<string, string[]> = {
    'Health Sciences': ['Medical Technology (MedTech)', 'Pharmacy', 'Nursing'],
    'Criminal Justice Education': ['Criminology'],
    'Arts and Sciences': ['Psychology'],
    'Engineering': ['Civil Engineering', 'Electrical Engineering', 'Mechanical Engineering', 'Computer Engineering'],
};

const departments = Object.keys(departmentCourses);

// Only the departments that actually have a course listed are worth
// showing as a group in the student's dropdown.
const departmentsWithCourses = Object.entries(departmentCourses).filter(([, courseList]) => courseList.length > 0);

const form = useForm({
    role:       'student' as 'student' | 'faculty',
    campus_id:  '',
    student_id: '',
    course:     '',
    department: '',
});

watch(selectedRole, (val) => {
    form.role = val;
    form.student_id = '';
    form.course = '';
    form.department = '';
    form.clearErrors();
    // A faculty member may pick a campus that isn't open to students yet
    if (val === 'student' && isCampusClosed(form.campus_id)) {
        form.campus_id = '';
    }
});

// Closed campuses stay visible for students, just not selectable
const isCampusClosed = (campusId: number | string) =>
    props.campuses.some((c) => c.id === Number(campusId) && !c.open);

const submit = () => form.post(route('profile.complete.store'));
</script>

<template>
    <Head title="Complete Your Profile" />

    <div class="min-h-screen flex flex-col items-center justify-center relative overflow-hidden"
        style="background: linear-gradient(135deg, #0a3d1f 0%, #145a32 35%, #1e8449 65%, #0a3d1f 100%);">
        <div class="absolute top-[-80px] left-[-80px] w-72 h-72 rounded-full opacity-20"
            style="background: radial-gradient(circle, #f9e94e, transparent 70%);"></div>
        <div class="absolute bottom-[-60px] right-[-60px] w-80 h-80 rounded-full opacity-15"
            style="background: radial-gradient(circle, #f9e94e, transparent 70%);"></div>
        <div class="absolute inset-0 opacity-5"
            style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 32px 32px;"></div>

        <div class="w-full max-w-md px-4 relative z-10">
            <!-- Logo -->
            <div class="text-center mb-6">
                <div class="w-24 h-24 mx-auto mb-4 rounded-full flex items-center justify-center overflow-hidden bg-white shadow-md border-[3px] border-green-500">
                    <img v-if="!logoError" src="/images/logo.png" alt="COC Logo"
                        class="w-full h-full object-contain p-1.5" @error="logoError = true" />
                    <span v-else class="text-2xl font-bold text-green-600">COC</span>
                </div>
                <h1 class="text-xl font-bold text-white tracking-tight drop-shadow">Science Laboratory Borrowing System</h1>
                <p class="text-sm text-green-200 mt-0.5">Cagayan de Oro College</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-8 py-7">
                    <h2 class="text-lg font-bold text-gray-800 mb-1">Complete Your Profile</h2>
                    <p class="text-sm text-gray-500 mb-5">
                        Welcome, <span class="font-semibold text-green-700">{{ user.name }}</span>!
                        Please fill in the details below to finish setting up your account.
                    </p>

                    <!-- Role Selection -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">I am a...</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="selectedRole = 'student'"
                                :class="[
                                    'flex flex-col items-center gap-1.5 px-4 py-3 rounded-xl border-2 text-sm font-semibold transition-all',
                                    selectedRole === 'student'
                                        ? 'border-green-500 bg-green-50 text-green-700'
                                        : 'border-gray-200 text-gray-500 hover:border-gray-300'
                                ]"
                            >
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                </svg>
                                Student
                            </button>
                            <button
                                type="button"
                                @click="selectedRole = 'faculty'"
                                :class="[
                                    'flex flex-col items-center gap-1.5 px-4 py-3 rounded-xl border-2 text-sm font-semibold transition-all',
                                    selectedRole === 'faculty'
                                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                                        : 'border-gray-200 text-gray-500 hover:border-gray-300'
                                ]"
                            >
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Faculty / Instructor
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Campus (both roles) -->
                        <div>
                            <label for="campus_id" class="block text-sm font-medium text-gray-700 mb-1.5">Campus</label>
                            <select
                                id="campus_id"
                                v-model="form.campus_id"
                                required
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition bg-white"
                            >
                                <option value="" disabled>Select your campus</option>
                                <option
                                    v-for="campus in campuses"
                                    :key="campus.id"
                                    :value="campus.id"
                                    :disabled="selectedRole === 'student' && !campus.open"
                                >
                                    {{ campus.campus_name }}{{ selectedRole === 'student' && !campus.open ? ' (not available yet)' : '' }}
                                </option>
                            </select>
                            <p
                                v-if="selectedRole === 'student' && campuses.some((c) => !c.open)"
                                class="mt-1 text-xs text-gray-500"
                            >
                                Campuses marked "not available yet" have no administrator yet.
                            </p>
                            <InputError class="mt-1" :message="form.errors.campus_id" />
                        </div>

                        <!-- STUDENT fields -->
                        <template v-if="selectedRole === 'student'">
                            <div>
                                <label for="course" class="block text-sm font-medium text-gray-700 mb-1.5">Course / Program</label>
                                <select
                                    id="course"
                                    v-model="form.course"
                                    required
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition bg-white"
                                >
                                    <option value="" disabled>Select your course</option>
                                    <optgroup
                                        v-for="[dept, courseList] in departmentsWithCourses"
                                        :key="dept"
                                        :label="dept"
                                    >
                                        <option v-for="course in courseList" :key="course" :value="course">{{ course }}</option>
                                    </optgroup>
                                </select>
                                <InputError class="mt-1" :message="form.errors.course" />
                            </div>
                            <div>
                                <label for="student_id" class="block text-sm font-medium text-gray-700 mb-1.5">Student ID</label>
                                <input
                                    id="student_id"
                                    type="text"
                                    v-model="form.student_id"
                                    required
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition placeholder-gray-400"
                                    placeholder="e.g. 2024-00001"
                                />
                                <InputError class="mt-1" :message="form.errors.student_id" />
                            </div>
                            <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-xs text-green-800">
                                Your account will be activated immediately. You can start browsing and requesting equipment right away.
                            </div>
                        </template>

                        <!-- FACULTY fields -->
                        <template v-if="selectedRole === 'faculty'">
                            <div>
                                <label for="department" class="block text-sm font-medium text-gray-700 mb-1.5">Department</label>
                                <select
                                    id="department"
                                    v-model="form.department"
                                    required
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-white"
                                >
                                    <option value="" disabled>Select your department</option>
                                    <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.department" />
                            </div>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-xs text-blue-800">
                                Your account will be activated immediately. The administrator will be notified to verify your registration.
                            </div>
                        </template>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            :class="[
                                'w-full py-3 text-white text-sm font-semibold rounded-lg shadow focus:outline-none focus:ring-2 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed',
                                selectedRole === 'faculty'
                                    ? 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'
                                    : 'bg-green-600 hover:bg-green-700 focus:ring-green-500'
                            ]"
                        >
                            {{ form.processing ? 'Saving...' : 'Complete Profile' }}
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-xs text-green-300 mt-5 opacity-80">
                &copy; {{ new Date().getFullYear() }} Cagayan de Oro College — PHINMA Education Network
            </p>
        </div>
    </div>
</template>
