<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    status: string;
    rejectedAt?: string | null;
    cancelledAt?: string | null;
}>();

type Step = {
    key: string;
    label: string;
    description: string;
};

const steps: Step[] = [
    { key: 'pending_faculty', label: 'Faculty Review', description: 'Waiting for faculty approval' },
    { key: 'pending_admin',   label: 'Admin Review',   description: 'Waiting for admin approval' },
    { key: 'approved',        label: 'Approved',       description: 'Request approved, ready for pickup' },
    { key: 'borrowed',        label: 'Released',       description: 'Equipment released to borrower' },
    { key: 'returned',        label: 'Returned',       description: 'Equipment successfully returned' },
];

const stepOrder = steps.map(s => s.key);

const isTerminal = computed(() =>
    props.status === 'rejected' || props.status === 'cancelled'
);

const currentIndex = computed(() => {
    if (isTerminal.value) return -1;
    return stepOrder.indexOf(props.status);
});

const stepState = (index: number) => {
    if (isTerminal.value) return 'cancelled';
    if (index < currentIndex.value) return 'done';
    if (index === currentIndex.value) return 'active';
    return 'upcoming';
};
</script>

<template>
    <!-- Rejected / Cancelled banner -->
    <div v-if="status === 'rejected'" class="rounded-xl border border-red-200 bg-red-50 p-4 flex items-center gap-3">
        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
            <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-red-700">Request Rejected</p>
            <p class="text-xs text-red-500">This borrowing request was rejected. Please check remarks for details.</p>
        </div>
    </div>

    <div v-else-if="status === 'cancelled'" class="rounded-xl border border-gray-200 bg-gray-50 p-4 flex items-center gap-3">
        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
            <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-700">Request Cancelled</p>
            <p class="text-xs text-gray-500">This borrowing request was cancelled.</p>
        </div>
    </div>

    <!-- Progress tracker -->
    <div v-else class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Request Progress</p>

        <div class="relative">
            <!-- Connector line -->
            <div class="absolute left-4 top-4 bottom-4 w-0.5 bg-gray-200" style="left: 15px;" />

            <div class="space-y-5">
                <div
                    v-for="(step, index) in steps"
                    :key="step.key"
                    class="relative flex items-start gap-4"
                >
                    <!-- Circle -->
                    <div class="flex-shrink-0 relative z-10">
                        <!-- Done -->
                        <div v-if="stepState(index) === 'done'"
                            class="w-8 h-8 rounded-full bg-green-600 flex items-center justify-center ring-2 ring-white">
                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <!-- Active -->
                        <div v-else-if="stepState(index) === 'active'"
                            class="w-8 h-8 rounded-full bg-green-600 flex items-center justify-center ring-2 ring-green-200 ring-offset-1">
                            <span class="w-2.5 h-2.5 rounded-full bg-white animate-pulse" />
                        </div>
                        <!-- Upcoming -->
                        <div v-else
                            class="w-8 h-8 rounded-full bg-white border-2 border-gray-300 flex items-center justify-center">
                            <span class="w-2 h-2 rounded-full bg-gray-300" />
                        </div>
                    </div>

                    <!-- Label -->
                    <div class="pt-1 pb-1 min-w-0">
                        <p :class="[
                            'text-sm font-medium',
                            stepState(index) === 'done' ? 'text-green-700' :
                            stepState(index) === 'active' ? 'text-gray-900' :
                            'text-gray-400'
                        ]">{{ step.label }}</p>
                        <p v-if="stepState(index) === 'active'" class="text-xs text-gray-500 mt-0.5">{{ step.description }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
