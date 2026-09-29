<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const logoError = ref(false);
const showPassword = ref(false);
const showConfirm = ref(false);

const passwordsMatch = computed(() =>
    !form.password_confirmation || form.password === form.password_confirmation
);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register" />

    <div class="min-h-screen flex flex-col items-center justify-center relative overflow-hidden py-8"
        style="background: linear-gradient(135deg, #0a3d1f 0%, #145a32 35%, #1e8449 65%, #0a3d1f 100%);">

        <!-- Decorative blurred circles -->
        <div class="absolute top-[-80px] left-[-80px] w-72 h-72 rounded-full opacity-20"
            style="background: radial-gradient(circle, #f9e94e, transparent 70%);"></div>
        <div class="absolute bottom-[-60px] right-[-60px] w-80 h-80 rounded-full opacity-15"
            style="background: radial-gradient(circle, #f9e94e, transparent 70%);"></div>
        <div class="absolute top-1/2 left-[-120px] w-56 h-56 rounded-full opacity-10"
            style="background: radial-gradient(circle, #a8e6cf, transparent 70%);"></div>
        <div class="absolute top-[10%] right-[8%] w-40 h-40 rounded-full opacity-10"
            style="background: radial-gradient(circle, #52be80, transparent 70%);"></div>
        <!-- Dot grid -->
        <div class="absolute inset-0 opacity-5"
            style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 32px 32px;"></div>

        <div class="w-full max-w-lg px-4 relative z-10">

            <!-- Logo + Title -->
            <div class="text-center mb-6">
                <div class="w-24 h-24 mx-auto mb-4 rounded-full flex items-center justify-center overflow-hidden bg-white shadow-md border-[3px] border-green-400">
                    <img
                        v-if="!logoError"
                        src="/images/logo.png"
                        alt="COC Logo"
                        class="w-full h-full object-contain p-1.5"
                        @error="logoError = true"
                    />
                    <span v-else class="text-2xl font-bold text-green-600">COC</span>
                </div>
                <h1 class="text-xl font-bold text-white tracking-tight drop-shadow">Science Laboratory Borrowing System</h1>
                <p class="text-sm text-green-200 mt-0.5">Cagayan de Oro College</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">

                <!-- Tabs -->
                <div class="flex border-b border-gray-200">
                    <Link
                        :href="route('login')"
                        class="flex-1 py-4 text-sm font-semibold text-center border-b-2 border-transparent text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        Login
                    </Link>
                    <Link
                        :href="route('register')"
                        class="flex-1 py-4 text-sm font-semibold text-center border-b-2 border-green-600 text-green-700 transition-colors"
                    >
                        Register
                    </Link>
                </div>

                <div class="px-8 py-7">
                    <form @submit.prevent="submit" class="space-y-4">

                        <!-- First Name + Last Name -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1.5">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="first_name"
                                    type="text"
                                    v-model="form.first_name"
                                    required
                                    autofocus
                                    autocomplete="given-name"
                                    placeholder="Juan"
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition placeholder-gray-400"
                                />
                                <InputError class="mt-1" :message="form.errors.first_name" />
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Last Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="last_name"
                                    type="text"
                                    v-model="form.last_name"
                                    required
                                    autocomplete="family-name"
                                    placeholder="dela Cruz"
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition placeholder-gray-400"
                                />
                                <InputError class="mt-1" :message="form.errors.last_name" />
                            </div>
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="email"
                                type="email"
                                v-model="form.email"
                                required
                                autocomplete="username"
                                placeholder="you@phinmaed.com"
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition placeholder-gray-400"
                            />
                            <p class="mt-1 text-xs text-gray-400">Use your PHINMA school email (@phinmaed.com)</p>
                            <InputError class="mt-1" :message="form.errors.email" />
                        </div>

                        <!-- Password + Confirm side by side -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Password <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        id="password"
                                        :type="showPassword ? 'text' : 'password'"
                                        v-model="form.password"
                                        required
                                        autocomplete="new-password"
                                        placeholder="Min. 6 characters"
                                        class="w-full px-4 py-2.5 pr-9 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition placeholder-gray-400"
                                    />
                                    <button type="button" @click="showPassword = !showPassword"
                                        class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600">
                                        <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                        </svg>
                                    </button>
                                </div>
                                <InputError class="mt-1" :message="form.errors.password" />
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Confirm Password <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        id="password_confirmation"
                                        :type="showConfirm ? 'text' : 'password'"
                                        v-model="form.password_confirmation"
                                        required
                                        autocomplete="new-password"
                                        placeholder="Re-enter password"
                                        :class="[
                                            'w-full px-4 py-2.5 pr-9 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition placeholder-gray-400',
                                            !passwordsMatch ? 'border-red-400 bg-red-50' : 'border-gray-300'
                                        ]"
                                    />
                                    <button type="button" @click="showConfirm = !showConfirm"
                                        class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600">
                                        <svg v-if="!showConfirm" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                        </svg>
                                    </button>
                                </div>
                                <p v-if="!passwordsMatch" class="mt-1 text-xs text-red-600">Passwords do not match.</p>
                                <InputError class="mt-1" :message="form.errors.password_confirmation" />
                            </div>
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg shadow focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed mt-2"
                        >
                            {{ form.processing ? 'Creating account...' : 'Create Account' }}
                        </button>

                        <p class="text-center text-sm text-gray-500 pt-1">
                            Already have an account?
                            <Link :href="route('login')" class="text-green-600 hover:text-green-800 font-semibold">
                                Login here
                            </Link>
                        </p>
                    </form>
                </div>
            </div>

            <p class="text-center text-xs text-green-300 mt-5 opacity-80">
                &copy; {{ new Date().getFullYear() }} Cagayan de Oro College — PHINMA Education Network
            </p>
        </div>
    </div>
</template>
