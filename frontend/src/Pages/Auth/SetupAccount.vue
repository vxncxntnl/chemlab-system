<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    valid: boolean;
    name: string;
    email: string;
    action: string; // the signed link itself — posting back to it keeps the signature check
}>();

const form = useForm({
    password: '',
    password_confirmation: '',
});
const showPassword = ref(false);
const logoError = ref(false);

const submit = () => {
    form.post(props.action, { onFinish: () => form.reset('password', 'password_confirmation') });
};

const inputClass = 'w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition placeholder-gray-400';
</script>

<template>
    <Head title="Set Your Password" />

    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-8" style="background: linear-gradient(135deg, #0a3d1f 0%, #145a32 35%, #1e8449 65%, #0a3d1f 100%);">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <div class="w-20 h-20 mx-auto mb-3 rounded-full flex items-center justify-center overflow-hidden bg-white shadow-md border-[3px] border-green-500">
                    <img v-if="!logoError" src="/images/logo.png" alt="COC Logo" class="w-full h-full object-contain p-1.5" @error="logoError = true" />
                    <span v-else class="text-xl font-bold text-green-600">COC</span>
                </div>
                <h1 class="text-xl font-bold text-white tracking-tight drop-shadow">Science Laboratory Borrowing System</h1>
                <p class="text-sm text-green-200 mt-0.5">Cagayan de Oro College</p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg px-6 py-7 sm:px-8">
                <!-- Valid link: set the password -->
                <template v-if="valid">
                    <h2 class="text-lg font-semibold text-gray-900">Welcome, {{ name }}!</h2>
                    <p class="mt-1 text-sm text-gray-600">Set your password to activate your account.</p>

                    <form @submit.prevent="submit" class="mt-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                            <input :value="email" type="email" disabled autocomplete="username"
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-600" />
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">New Password</label>
                            <div class="relative">
                                <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" required autofocus
                                    autocomplete="new-password" placeholder="At least 8 characters" :class="inputClass + ' pr-16'" />
                                <button type="button" @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-gray-500 hover:text-gray-700">
                                    {{ showPassword ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                            <InputError class="mt-1" :message="form.errors.password" />
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                            <input id="password_confirmation" v-model="form.password_confirmation" :type="showPassword ? 'text' : 'password'" required
                                autocomplete="new-password" placeholder="Type the password again" :class="inputClass" />
                            <InputError class="mt-1" :message="form.errors.password_confirmation" />
                        </div>

                        <button type="submit" :disabled="form.processing"
                            class="w-full py-3 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg shadow transition disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ form.processing ? 'Setting password…' : 'Set Password & Sign In' }}
                        </button>
                    </form>
                </template>

                <!-- Expired or already used -->
                <template v-else>
                    <h2 class="text-lg font-semibold text-gray-900">This link is no longer valid</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        The link has expired or was already used. If you already set your password, just sign in.
                        Otherwise, use <span class="font-medium text-gray-800">Forgot password</span> on the sign-in page,
                        or ask your instructor to send a new link.
                    </p>
                    <Link :href="route('login')"
                        class="mt-5 inline-flex w-full items-center justify-center rounded-lg bg-green-600 py-3 text-sm font-semibold text-white hover:bg-green-700">
                        Go to Sign In
                    </Link>
                </template>
            </div>

            <p class="text-center text-xs text-green-300 mt-5 opacity-80">
                &copy; {{ new Date().getFullYear() }} Cagayan de Oro College — PHINMA Education Network
            </p>
        </div>
    </div>
</template>
