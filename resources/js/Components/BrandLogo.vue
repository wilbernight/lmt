<script setup>
defineProps({ size: { type: String, default: 'h-7' } });
</script>

<template>
    <span class="inline-flex items-center" dir="ltr">
        <img src="/brand/logo-light.png" alt="WilberNight" :class="size" class="w-auto dark:hidden" />
        <img src="/brand/logo-dark.png" alt="WilberNight" :class="size" class="hidden w-auto dark:block" />
    </span>
</template>
