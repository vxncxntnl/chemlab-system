<script setup lang="ts">
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from '@/composables/useToast';

const page = usePage();
const { success, error, warning, info } = useToast();

watch(
    () => page.props.flash,
    (flash: any) => {
        if (!flash) return;
        if (flash.success) success(flash.success);
        if (flash.error)   error(flash.error);
        if (flash.warning) warning(flash.warning);
        if (flash.info)    info(flash.info);
    },
    { immediate: true, deep: true }
);
</script>

<template>
    <slot />
</template>
