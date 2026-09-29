<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const logoError = ref(false);
const showPassword = ref(false);

// The "wrong door" message from AuthenticatedSessionController — a student/faculty
// account tried the staff password form. Detected by content, not exact match, so
// it still shows the friendly callout even if the backend message wording changes.
const wrongDoorError = computed(() => {
    const message = form.errors.email;
    return message && message.includes('Google account') ? message : null;
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen flex flex-col items-center justify-center relative overflow-hidden" style="background: linear-gradient(135deg, #0a3d1f 0%, #145a32 35%, #1e8449 65%, #0a3d1f 100%);">
        <!-- Decorative blurred circles -->
        <div class="absolute top-[-80px] left-[-80px] w-72 h-72 rounded-full opacity-20" style="background: radial-gradient(circle, #f9e94e, transparent 70%);"></div>
        <div class="absolute bottom-[-60px] right-[-60px] w-80 h-80 rounded-full opacity-15" style="background: radial-gradient(circle, #f9e94e, transparent 70%);"></div>
        <div class="absolute top-1/2 left-[-120px] w-56 h-56 rounded-full opacity-10" style="background: radial-gradient(circle, #a8e6cf, transparent 70%);"></div>
        <div class="absolute top-[10%] right-[8%] w-40 h-40 rounded-full opacity-10" style="background: radial-gradient(circle, #52be80, transparent 70%);"></div>
        <!-- Grid dot pattern overlay -->
        <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 32px 32px;"></div>
        <div class="w-full max-w-md px-4 relative z-10">

            <!-- Logo + Title -->
            <div class="text-center mb-6">
                <div class="w-24 h-24 mx-auto mb-4 rounded-full flex items-center justify-center overflow-hidden bg-white shadow-md border-[3px] border-green-500">
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
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-8 py-7">

                    <!-- Status message -->
                    <div v-if="status" class="mb-5 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800 text-center">
                        {{ status }}
                    </div>

                    <!-- Wrong-door callout: a student/faculty account tried the staff form below -->
                    <div v-if="wrongDoorError" class="mb-5 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 flex gap-2.5">
                        <svg class="w-5 h-5 flex-shrink-0 text-blue-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm text-blue-800">
                            Looks like you're a student or faculty member — please use
                            <span class="font-semibold">Sign in with Google</span> above instead.
                        </p>
                    </div>

                    <!-- Google Sign-In — Primary for Students & Faculty -->
                    <div class="mb-6">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 text-center">Students & Faculty</p>
                        <a
                            :href="route('auth.google')"
                            class="w-full flex items-center justify-center gap-3 py-3.5 px-4 rounded-lg border-2 border-green-500 bg-green-50 hover:bg-green-100 text-base font-semibold text-green-800 shadow-sm transition focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                        >
                            <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                            Sign in with Google
                        </a>
                        <p class="mt-2 text-center text-xs text-gray-400">Use your <span class="font-medium text-gray-500">@phinmaed.com</span> school account</p>
                    </div>

                    <!-- Divider -->
                    <div class="flex items-center mb-6">
                        <div class="flex-1 border-t border-gray-200"></div>
                        <span class="px-3 text-xs text-gray-400 font-medium uppercase tracking-wider">Admin / Staff login</span>
                        <div class="flex-1 border-t border-gray-200"></div>
                    </div>

                    <!-- Email/Password — For Admin & Lab Staff only -->
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
                            <input
                                id="email"
                                type="email"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="username"
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition placeholder-gray-400"
                                placeholder="Enter your email"
                            />
                            <InputError v-if="!wrongDoorError" class="mt-1" :message="form.errors.email" />
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                            <div class="relative">
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    v-model="form.password"
                                    required
                                    autocomplete="current-password"
                                    class="w-full px-4 py-2.5 pr-10 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition placeholder-gray-400"
                                    placeholder="Enter your password"
                                />
                                <button type="button" @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-500">
                                    <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                </button>
                            </div>
                            <InputError class="mt-1" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm text-gray-600 select-none">
                                <input
                                    type="checkbox"
                                    v-model="form.remember"
                                    class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500"
                                />
                                Remember me
                            </label>
                            <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-green-600 hover:text-green-800 font-medium">
                                Forgot password?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3 bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold rounded-lg shadow focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? 'Signing in...' : 'Sign In' }}
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-xs text-green-300 mt-5 opacity-80">
                &copy; {{ new Date().getFullYear() }} Cagayan de Oro College — PHINMA Education Network
            </p>
        </div>
    </div>
</template>
