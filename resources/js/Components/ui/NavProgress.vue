<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/** Top loading bar for page visits (Inertia's built-in one injects inline styles, which our CSP blocks). */
const width = ref(0);
const visible = ref(false);
let delay = null;
let tick = null;

const start = () => {
    clearTimeout(delay);
    delay = setTimeout(() => {
        visible.value = true;
        width.value = 8;
        tick = setInterval(() => (width.value += (90 - width.value) * 0.08), 200);
    }, 150);
};

const finish = () => {
    clearTimeout(delay);
    clearInterval(tick);
    if (!visible.value) return;
    width.value = 100;
    setTimeout(() => {
        visible.value = false;
        width.value = 0;
    }, 250);
};

const offStart = router.on('start', start);
const offFinish = router.on('finish', finish);

onBeforeUnmount(() => {
    offStart();
    offFinish();
    clearTimeout(delay);
    clearInterval(tick);
});
</script>

<template>
    <div v-show="visible" class="pointer-events-none fixed inset-x-0 top-0 z-[70] h-0.5">
        <div class="h-full bg-accent transition-[width] duration-200 ease-out" :style="{ width: `${width}%` }" />
    </div>
</template>
