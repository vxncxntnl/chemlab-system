<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps<{
    users: Array<{
        id: number;
        name: string;
        email: string;
        role: string;
        department: string | null; // department (faculty) / course (student)
        id_number: string | null;
    }>;
}>();

const form = useForm({
    user_ids: [] as number[],
    title: '',
    message: '',
    type: 'info',
});

const selectAll = ref(false);
const roleFilter = ref('');
const departmentFilter = ref('');
const search = ref('');

const departments = computed(() =>
    [...new Set(props.users.map(u => u.department).filter((d): d is string => !!d))].sort()
);

// Search ignores upper/lower case: name, email or ID.
const filteredUsers = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.users.filter(u =>
        (!roleFilter.value || u.role === roleFilter.value)
        && (!departmentFilter.value || u.department === departmentFilter.value)
        && (!term || [u.name, u.email, u.id_number ?? ''].some(v => v.toLowerCase().includes(term)))
    );
});

const roleLabel = (role: string) =>
    ({ admin: 'Admin', lab_staff: 'Lab Staff', faculty: 'Faculty', student: 'Student' } as Record<string, string>)[role] ?? role;

// Select All picks only the people currently shown by the filters.
const toggleSelectAll = () => {
    const shown = filteredUsers.value.map(u => u.id);
    form.user_ids = selectAll.value
        ? [...new Set([...form.user_ids, ...shown])]
        : form.user_ids.filter(id => !shown.includes(id));
};

const submit = () => {
    form.post(route('admin.notifications.store'));
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">Send Notification</h1>
        </template>

        <div class="max-w-4xl">
            <form @submit.prevent="submit" class="space-y-6 bg-white p-6 rounded-lg shadow">
                <div>
                    <InputLabel value="Notification Type" />
                    <select
                        v-model="form.type"
                        class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                    >
                        <option value="info">Info</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="danger">Danger</option>
                    </select>
                </div>

                <div>
                    <InputLabel value="Title" />
                    <TextInput v-model="form.title" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.title" class="mt-2" />
                </div>

                <div>
                    <InputLabel value="Message" />
                    <textarea
                        v-model="form.message"
                        rows="4"
                        class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        required
                    ></textarea>
                    <InputError :message="form.errors.message" class="mt-2" />
                </div>

                <div>
                    <InputLabel value="Recipients" />
                    <div class="mt-2 mb-4 flex flex-wrap items-center gap-3">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search name, email or ID..."
                            class="rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500 sm:w-64"
                            @input="selectAll = false"
                        />
                        <select
                            v-model="roleFilter"
                            class="rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            @change="selectAll = false"
                        >
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="lab_staff">Lab Staff</option>
                            <option value="faculty">Faculty</option>
                            <option value="student">Student</option>
                        </select>
                        <select
                            v-model="departmentFilter"
                            class="rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            @change="selectAll = false"
                        >
                            <option value="">All Departments / Courses</option>
                            <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                        </select>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                v-model="selectAll"
                                @change="toggleSelectAll"
                                class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500"
                            />
                            <span class="ml-2 text-sm text-gray-600">Select All</span>
                        </label>
                    </div>
                    <div class="max-h-60 overflow-y-auto border rounded-md p-3 space-y-2">
                        <label
                            v-for="user in filteredUsers"
                            :key="user.id"
                            class="flex items-center"
                        >
                            <input
                                type="checkbox"
                                :value="user.id"
                                v-model="form.user_ids"
                                class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500"
                            />
                            <span class="ml-2 text-sm">
                                {{ user.name }} ({{ user.email }}) — {{ roleLabel(user.role) }}<span v-if="user.department" class="text-gray-500"> · {{ user.department }}</span>
                            </span>
                        </label>
                        <p v-if="filteredUsers.length === 0" class="text-sm text-gray-400">No one matches the search.</p>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">{{ form.user_ids.length }} selected</p>
                    <InputError :message="form.errors.user_ids" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <PrimaryButton :disabled="form.processing">
                        Send Notification
                    </PrimaryButton>
                    <Link :href="route('admin.notifications.index')" class="text-gray-600 hover:text-gray-900">
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
