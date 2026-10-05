<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    settings: Record<string, Array<{
        id: number;
        key: string;
        value: string;
        group: string;
        type: string;
        description?: string;
    }>>;
    mail: {
        mailer: string;
        host: string | null;
        port: number | string | null;
        from: string | null;
    };
}>();

const testEmail = useForm({});

const sendTestEmail = () => {
    testEmail.post(route('admin.settings.test-email'), { preserveScroll: true });
};

const settingsArray = computed(() => {
    const result: Array<any> = [];
    Object.values(props.settings).forEach(group => {
        group.forEach(setting => result.push(setting));
    });
    return result;
});

const form = useForm({
    settings: settingsArray.value.map(s => ({
        key: s.key,
        value: s.value,
    })),
});

const getSettingValue = (key: string) => {
    const setting = form.settings.find(s => s.key === key);
    return setting?.value || '';
};

const setSettingValue = (key: string, value: string | number) => {
    const setting = form.settings.find(s => s.key === key);
    if (setting) {
        setting.value = String(value);
    }
};

const submit = () => {
    form.put(route('admin.settings.update'));
};

const groupLabels: Record<string, string> = {
    general: 'General Settings',
    borrowing: 'Borrowing Settings',
    notifications: 'Notification Settings',
    system: 'System Settings',
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-800">System Settings</h1>
        </template>

        <form @submit.prevent="submit" class="max-w-3xl space-y-6">
            <Card v-for="(groupSettings, groupName) in settings" :key="groupName">
                <template #header>
                    <h3 class="text-lg font-medium">{{ groupLabels[groupName as string] || groupName }}</h3>
                </template>

                <div class="space-y-4">
                    <div v-for="setting in groupSettings" :key="setting.key">
                        <InputLabel :value="setting.key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())" />
                        <p v-if="setting.description" class="text-sm text-gray-500 mb-1">{{ setting.description }}</p>

                        <template v-if="setting.type === 'boolean'">
                            <select
                                :value="getSettingValue(setting.key)"
                                @change="setSettingValue(setting.key, ($event.target as HTMLSelectElement).value)"
                                class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500"
                            >
                                <option value="true">Yes</option>
                                <option value="false">No</option>
                            </select>
                        </template>
                        <template v-else-if="setting.type === 'number'">
                            <TextInput
                                type="number"
                                :model-value="getSettingValue(setting.key)"
                                @update:model-value="setSettingValue(setting.key, $event)"
                                class="mt-1 block w-full"
                            />
                        </template>
                        <template v-else>
                            <TextInput
                                :model-value="getSettingValue(setting.key)"
                                @update:model-value="setSettingValue(setting.key, $event)"
                                class="mt-1 block w-full"
                            />
                        </template>
                    </div>
                </div>
            </Card>

            <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">
                    Save Settings
                </PrimaryButton>
            </div>
        </form>

        <Card class="mt-6 max-w-3xl">
            <template #header>
                <h3 class="text-lg font-medium">Email Delivery</h3>
            </template>

            <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">Mailer</dt><dd class="font-medium text-gray-800">{{ mail.mailer }}</dd></div>
                <div><dt class="text-gray-500">Mail server</dt><dd class="font-medium text-gray-800 break-all">{{ mail.host || '—' }}{{ mail.port ? ':' + mail.port : '' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-gray-500">Sender address</dt><dd class="font-medium text-gray-800 break-all">{{ mail.from || '—' }}</dd></div>
            </dl>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500">Sends a sample notification to your own email address and shows whether this server delivered it.</p>
                <PrimaryButton type="button" :disabled="testEmail.processing" @click="sendTestEmail" class="shrink-0">
                    {{ testEmail.processing ? 'Sending…' : 'Send Test Email' }}
                </PrimaryButton>
            </div>
        </Card>
    </AdminLayout>
</template>
