<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import LabLayout from '@/Layouts/LabLayout.vue';
import UserLayout from '@/Layouts/UserLayout.vue';
import BackButton from '@/Components/BackButton.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
    isGoogleUser?: boolean;
}>();

// Same layout (sidebar, branding) as the rest of the system for this role.
const role = computed(() => (usePage().props.auth as any)?.user?.role as string | undefined);
const layout = computed(() => {
    if (role.value === 'admin') return AdminLayout;
    if (role.value === 'lab_staff') return LabLayout;
    return UserLayout;
});

// Staff accounts are removed by an administrator from Users management
// (which keeps their history), not self-deleted here.
const canSelfDelete = computed(() => !['admin', 'lab_staff'].includes(role.value ?? ''));
</script>

<template>
    <Head title="Profile" />

    <component :is="layout">
        <template #header>
            <div class="flex flex-wrap items-center gap-2 sm:gap-4">
                <h1 class="text-xl font-semibold text-gray-800">Profile</h1>
                <BackButton :href="route('dashboard')" />
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <UpdatePasswordForm :is-google-user="isGoogleUser" class="max-w-xl" />
            </div>

            <div v-if="canSelfDelete" class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </component>
</template>
