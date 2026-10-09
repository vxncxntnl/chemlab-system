<script setup lang="ts">
/**
 * Borrower's and Faculty In-Charge's email, campus and course/department on
 * the damage report details — shared by the admin and lab staff pages. Goes
 * inside the details grid: borrower on the left, faculty on the right.
 */
type Affiliation = { email: string | null; campus: string | null; department_label: string; department: string | null } | null;

defineProps<{
    affiliations: { borrower: Affiliation; faculty: Affiliation };
}>();

const shown = (v: string | null | undefined) => v || 'Not provided';
</script>

<template>
    <div>
        <dt class="text-sm font-medium text-gray-500">Borrower Email</dt>
        <dd class="mt-1 text-sm text-gray-900 break-all">{{ shown(affiliations.borrower?.email) }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500">Faculty Email</dt>
        <dd class="mt-1 text-sm text-gray-900 break-all">{{ affiliations.faculty ? shown(affiliations.faculty.email) : '—' }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500">Borrower's College / Campus</dt>
        <dd class="mt-1 text-sm text-gray-900">{{ shown(affiliations.borrower?.campus) }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500">Faculty's College / Campus</dt>
        <dd class="mt-1 text-sm text-gray-900">{{ affiliations.faculty ? shown(affiliations.faculty.campus) : '—' }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500">Borrower's {{ affiliations.borrower?.department_label ?? 'Course' }}</dt>
        <dd class="mt-1 text-sm text-gray-900">{{ shown(affiliations.borrower?.department) }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500">Faculty's Department</dt>
        <dd class="mt-1 text-sm text-gray-900">{{ affiliations.faculty ? shown(affiliations.faculty.department) : '—' }}</dd>
    </div>
</template>
