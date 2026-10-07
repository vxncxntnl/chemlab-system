<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    students: {
        data: Array<{
            id: number;
            name: string;
            email: string;
            student_id: string | null;
            course: string | null;
            email_verified_at: string | null;
            created_at: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        last_page: number;
        total: number;
    };
    filters: { search?: string };
}>();

const search = ref(props.filters.search ?? '');
const applySearch = () => router.get(route('faculty.students.index'), { search: search.value }, { preserveState: true, replace: true });
// Email a fresh "Set Your Password" link to a student who hasn't set one yet.
const resendingId = ref<number | null>(null);
const resend = (id: number) => {
    resendingId.value = id;
    router.post(route('faculty.students.resend-setup', id), {}, {
        preserveScroll: true,
        onFinish: () => { resendingId.value = null; },
    });
};

const formatDate = (d: string) => new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <UserLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold text-gray-800">Students</h1>
                <Link :href="route('faculty.students.create')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Add Student
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-4">
            <p class="text-sm text-gray-600">Students you added. Each one gets an email telling them how to set their own password.</p>

            <form @submit.prevent="applySearch" class="flex gap-2">
                <input v-model="search" type="text" placeholder="Search name, email or student ID..."
                    class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500" />
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Search</button>
            </form>

            <!-- Phones: cards -->
            <div class="space-y-3 sm:hidden">
                <div v-for="s in students.data" :key="s.id" class="rounded-xl bg-white p-4 shadow-sm">
                    <p class="font-medium text-gray-900">{{ s.name }}</p>
                    <p class="break-all text-sm text-gray-600">{{ s.email }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ s.student_id }} · {{ s.course }}</p>
                    <span :class="['mt-2 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold', s.email_verified_at ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800']">
                        {{ s.email_verified_at ? 'Active' : 'Waiting to set password' }}
                    </span>
                    <button v-if="!s.email_verified_at" type="button" @click="resend(s.id)" :disabled="resendingId === s.id"
                        class="mt-2 ml-2 text-xs font-semibold text-green-700 hover:text-green-900 disabled:opacity-50">
                        {{ resendingId === s.id ? 'Sending…' : 'Resend link' }}
                    </button>
                </div>
            </div>

            <!-- Larger screens: table -->
            <div class="hidden overflow-hidden rounded-xl bg-white shadow-sm sm:block">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Email</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Student ID</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Course</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Account</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Added</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="s in students.data" :key="s.id">
                            <td class="px-5 py-3 font-medium text-gray-900">{{ s.name }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ s.email }}</td>
                            <td class="px-5 py-3 font-mono text-gray-600">{{ s.student_id }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ s.course }}</td>
                            <td class="px-5 py-3">
                                <span :class="['rounded-full px-2 py-0.5 text-[11px] font-semibold', s.email_verified_at ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800']">
                                    {{ s.email_verified_at ? 'Active' : 'Waiting to set password' }}
                                </span>
                                <button v-if="!s.email_verified_at" type="button" @click="resend(s.id)" :disabled="resendingId === s.id"
                                    class="ml-2 text-xs font-semibold text-green-700 hover:text-green-900 disabled:opacity-50">
                                    {{ resendingId === s.id ? 'Sending…' : 'Resend link' }}
                                </button>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ formatDate(s.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="students.data.length === 0" class="rounded-xl bg-white p-8 text-center text-sm text-gray-500 shadow-sm">
                No students added yet. Click <span class="font-semibold">Add Student</span> to create one.
            </div>

            <div v-if="students.last_page > 1" class="flex flex-wrap justify-end gap-1">
                <template v-for="link in students.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-scroll
                        :class="['inline-flex h-8 min-w-[32px] items-center justify-center rounded-lg border px-2 text-sm', link.active ? 'border-green-600 bg-green-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50']"
                        v-html="link.label" />
                </template>
            </div>
        </div>
    </UserLayout>
</template>
