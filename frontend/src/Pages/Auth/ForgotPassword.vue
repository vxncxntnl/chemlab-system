<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{ status?: string }>();

const logoError = ref(false);
const form = useForm({ email: '' });
const submit = () => form.post(route('password.email'));
</script>

<template>
    <Head title="Forgot Password" />

    <div class="min-h-screen flex flex-col items-center justify-center relative overflow-hidden" style="background: linear-gradient(135deg, #0a3d1f 0%, #145a32 35%, #1e8449 65%, #0a3d1f 100%);">
        <div class="absolute top-[-80px] left-[-80px] w-72 h-72 rounded-full opacity-20" style="background: radial-gradient(circle, #f9e94e, transparent 70%);"></div>
        <div class="absolute bottom-[-60px] right-[-60px] w-80 h-80 rounded-full opacity-15" style="background: radial-gradient(circle, #f9e94e, transparent 70%);"></div>
        <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 32px 32px;"></div>

        <div class="w-full max-w-md px-4 relative z-10">
            <div class="text-center mb-6">
                <div class="w-24 h-24 mx-auto mb-4 rounded-full flex items-center justify-center overflow-hidden bg-white shadow-md border-[3px] border-green-500">
                    <img v-if="!logoError" src="/images/logo.png" alt="COC Logo" class="w-full h-full object-contain p-1.5" @error="logoError = true" />
                    <span v-else class="text-2xl font-bold text-green-600">COC</span>
                </div>
                <h1 class="text-xl font-bold text-white tracking-tight drop-shadow">Science Laboratory Borrowing System</h1>
                <p class="text-sm text-green-200 mt-0.5">Cagayan de Oro College</p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-8 py-7">
                    <h2 class="text-lg font-bold text-gray-800 mb-1">Forgot Password</h2>
                    <p class="text-sm text-gray-500 mb-6">Enter your email and we'll send a 6-digit code to reset your password.</p>

                    <div v-if="status" class="mb-5 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800 text-center">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
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
                            <InputError class="mt-1" :message="form.errors.email" />
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg shadow focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? 'Sending...' : 'Send Reset Code' }}
                        </button>

                        <div class="text-center">
                            <a :href="route('login')" class="text-sm text-green-600 hover:text-green-800 font-medium">Back to Login</a>
                        </div>
                    </form>
                </div>
            </div>

            <p class="text-center text-xs text-green-300 mt-5 opacity-80">
                &copy; {{ new Date().getFullYear() }} Cagayan de Oro College — PHINMA Education Network
            </p>
        </div>
    </div>
</template>
