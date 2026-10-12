<script setup lang="ts">
/**
 * ID check at release (lab staff and admin): shows who the equipment is
 * for, and the releaser types the student number from the borrower's school
 * ID (not filled in for them) and confirms they checked the ID.
 */
import InputError from '@/Components/InputError.vue';

defineProps<{
    borrower: { name?: string; student_id?: string | null; employee_id?: string | null; role?: string; course?: string | null; department?: string | null; campus?: { campus_name?: string } | null } | null;
    activityTitle?: string | null;
    form: { student_id: string; id_checked: boolean; errors: Record<string, string | undefined> };
}>();
</script>

<template>
    <div class="rounded-lg border-2 border-yellow-300 bg-yellow-50 p-4">
        <p class="text-sm font-bold uppercase tracking-wide text-yellow-900">Check the borrower's school ID</p>
        <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Name</dt><dd class="font-semibold text-gray-900">{{ borrower?.name ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">{{ borrower?.role === 'faculty' ? 'Employee No.' : 'Student Number' }}</dt>
                <dd class="font-mono font-semibold text-gray-900">{{ borrower?.student_id ?? borrower?.employee_id ?? 'Not provided' }}</dd></div>
            <div><dt class="text-gray-500">{{ borrower?.role === 'faculty' ? 'Department' : 'Course' }}</dt>
                <dd class="text-gray-900">{{ (borrower?.role === 'faculty' ? borrower?.department : borrower?.course) || 'Not provided' }}</dd></div>
            <div><dt class="text-gray-500">Campus</dt><dd class="text-gray-900">{{ borrower?.campus?.campus_name ?? 'Not provided' }}</dd></div>
            <div v-if="activityTitle" class="sm:col-span-2"><dt class="text-gray-500">Activity</dt><dd class="text-gray-900">{{ activityTitle }}</dd></div>
        </dl>

        <div class="mt-4">
            <label for="release_student_id" class="block text-sm font-medium text-gray-800">Type the number from their school ID</label>
            <input id="release_student_id" v-model="form.student_id" type="text" autocomplete="off" required
                class="mt-1 block w-full max-w-xs rounded-lg border-gray-300 font-mono shadow-sm focus:border-green-500 focus:ring-green-500" />
            <InputError :message="form.errors.student_id" class="mt-1" />
        </div>
        <label class="mt-3 flex items-center gap-2 text-sm text-gray-800">
            <input v-model="form.id_checked" type="checkbox" class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
            I checked the borrower's school ID and it matches.
        </label>
        <InputError :message="form.errors.id_checked" class="mt-1" />
    </div>
</template>
