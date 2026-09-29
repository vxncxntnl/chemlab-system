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

const filteredUsers = computed(() => {
    if (!roleFilter.value) return props.users;
    return props.users.filter(u => u.role === roleFilter.value);
});

const toggleSelectAll = () => {
    if (selectAll.value) {
        form.user_ids = filteredUsers.value.map(u => u.id);
    } else {
        form.user_ids = [];
    }
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
                    <div class="mt-2 flex items-center gap-4 mb-4">
                        <select
                            v-model="roleFilter"
                            class="rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                        >
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="faculty">Faculty</option>
                            <option value="student">Student</option>
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
                            <span class="ml-2 text-sm">{{ user.name }} ({{ user.email }}) - {{ user.role }}</span>
                        </label>
                    </div>
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
