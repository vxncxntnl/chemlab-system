<script setup lang="ts">
defineProps<{
    show: boolean;
    title?: string;
    message?: string;
    confirmLabel?: string;
    confirmClass?: string;
    cancelLabel?: string;
    processing?: boolean;
}>();

const emit = defineEmits<{
    confirmed: [];
    cancelled: [];
}>();
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-60 transition-opacity" @click="emit('cancelled')" />

                <!-- Modal -->
                <div class="flex min-h-full items-center justify-center p-4">
                    <Transition
                        enter-active-class="ease-out duration-200"
                        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-active-class="ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <div v-if="show" class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                            <!-- Icon -->
                            <div class="flex items-center gap-4 mb-4">
                                <div :class="[
                                    'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center',
                                    (confirmClass || '').includes('red') ? 'bg-red-100' : 'bg-yellow-100'
                                ]">
                                    <svg :class="[
                                        'w-5 h-5',
                                        (confirmClass || '').includes('red') ? 'text-red-600' : 'text-yellow-600'
                                    ]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">{{ title || 'Confirm Action' }}</h3>
                                </div>
                            </div>

                            <p class="text-sm text-gray-500 mb-2 ml-14">{{ message || 'Are you sure you want to proceed?' }}</p>

                            <div v-if="$slots.default" class="mb-6 ml-14">
                                <slot />
                            </div>
                            <div v-else class="mb-6"></div>

                            <div class="flex justify-end gap-3">
                                <button
                                    @click="emit('cancelled')"
                                    :disabled="processing"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition disabled:opacity-50"
                                >
                                    {{ cancelLabel || 'Cancel' }}
                                </button>
                                <button
                                    @click="emit('confirmed')"
                                    :disabled="processing"
                                    :class="[
                                        'px-4 py-2 text-sm font-medium text-white rounded-lg transition disabled:opacity-50',
                                        confirmClass || 'bg-red-600 hover:bg-red-700'
                                    ]"
                                >
                                    {{ processing ? 'Processing...' : (confirmLabel || 'Confirm') }}
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
