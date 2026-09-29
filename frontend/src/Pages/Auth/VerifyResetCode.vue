<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{ email: string }>();

const logoError = ref(false);
const form = useForm({ email: props.email, code: '' });
const submit = () => form.post(route('password.verify-code.store'));
</script>

<template>
    <Head title="Enter Reset Code" />

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
                    <h2 class="text-lg font-bold text-gray-800 mb-1">Enter Reset Code</h2>
                    <p class="text-sm text-gray-500 mb-1">We sent a 6-digit code to:</p>
                    <p class="text-sm font-semibold text-green-700 mb-6">{{ email }}</p>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700 mb-1.5">6-Digit Code</label>
                            <input
                                id="code"
                                type="text"
                                v-model="form.code"
                                required
                                autofocus
                                maxlength="6"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 text-2xl font-bold text-center tracking-[0.5em] focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition placeholder-gray-300"
                                placeholder="000000"
                            />
                            <InputError class="mt-1" :message="form.errors.code" />
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg shadow focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? 'Verifying...' : 'Verify Code' }}
                        </button>

                        <div class="text-center">
                            <a :href="route('password.request')" class="text-sm text-green-600 hover:text-green-800 font-medium">Resend code</a>
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
