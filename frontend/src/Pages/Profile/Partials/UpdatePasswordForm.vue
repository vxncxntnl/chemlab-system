<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    isGoogleUser?: boolean;
}>();

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

// Form for regular users (requires current password)
const updateForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

// Form for Google users (no current password needed)
const setForm = useForm({
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    updateForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => updateForm.reset(),
        onError: () => {
            if (updateForm.errors.password) {
                updateForm.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (updateForm.errors.current_password) {
                updateForm.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};

const setPassword = () => {
    setForm.post(route('password.set'), {
        preserveScroll: true,
        onSuccess: () => setForm.reset(),
        onError: () => {
            if (setForm.errors.password) {
                setForm.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                {{ isGoogleUser ? 'Set a Password' : 'Update Password' }}
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                {{ isGoogleUser
                    ? 'Set a password so you can also log in with your email and password — in addition to Google Sign-In.'
                    : 'Ensure your account is using a long, random password to stay secure.'
                }}
            </p>
        </header>

        <!-- Google User — Set Password (no current password required) -->
        <form v-if="isGoogleUser" @submit.prevent="setPassword" class="mt-6 space-y-6">
            <div>
                <InputLabel for="set_password" value="New Password" />
                <TextInput
                    id="set_password"
                    ref="passwordInput"
                    v-model="setForm.password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Min. 8 characters"
                />
                <InputError :message="setForm.errors.password" class="mt-2" />
            </div>

            <div>
                <InputLabel for="set_password_confirmation" value="Confirm Password" />
                <TextInput
                    id="set_password_confirmation"
                    v-model="setForm.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Re-enter password"
                />
                <InputError :message="setForm.errors.password_confirmation" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="setForm.processing">Set Password</PrimaryButton>
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="setForm.recentlySuccessful" class="text-sm text-green-600 font-medium">
                        Password set! You can now log in with email and password.
                    </p>
                </Transition>
            </div>
        </form>

        <!-- Regular User — Update Password (requires current password) -->
        <form v-else @submit.prevent="updatePassword" class="mt-6 space-y-6">
            <div>
                <InputLabel for="current_password" value="Current Password" />
                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="updateForm.current_password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                />
                <InputError :message="updateForm.errors.current_password" class="mt-2" />
            </div>

            <div>
                <InputLabel for="password" value="New Password" />
                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="updateForm.password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                />
                <InputError :message="updateForm.errors.password" class="mt-2" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirm Password" />
                <TextInput
                    id="password_confirmation"
                    v-model="updateForm.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                />
                <InputError :message="updateForm.errors.password_confirmation" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="updateForm.processing">Save</PrimaryButton>
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="updateForm.recentlySuccessful" class="text-sm text-gray-600">
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
