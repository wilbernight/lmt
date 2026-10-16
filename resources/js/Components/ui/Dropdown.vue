<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    align: { type: String, default: 'start' }, // start | end
    width: { type: String, default: 'w-56' },
    up: Boolean,
});

const open = ref(false);
const root = ref(null);

const onDocument = (e) => {
    if (root.value && !root.value.contains(e.target)) open.value = false;
};
const onKey = (e) => e.key === 'Escape' && (open.value = false);

watch(open, (value) => {
    if (value) {
        document.addEventListener('mousedown', onDocument);
        document.addEventListener('keydown', onKey);
    } else {
        document.removeEventListener('mousedown', onDocument);
        document.removeEventListener('keydown', onKey);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocument);
    document.removeEventListener('keydown', onKey);
});

const toggle = () => (open.value = !open.value);
const close = () => (open.value = false);

defineExpose({ close });
</script>

<template>
    <div ref="root" class="relative">
        <slot name="trigger" :open="open" :toggle="toggle" />
        <Transition enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95" enter-active-class="transition duration-100" leave-active-class="transition duration-75">
            <div
                v-if="open"
                :class="[width, align === 'end' ? 'end-0' : 'start-0', up ? 'bottom-full mb-2 origin-bottom' : 'top-full mt-2 origin-top']"
                class="absolute z-40 rounded-xl border border-line bg-surface p-1.5 shadow-pop"
            >
                <slot :close="close" />
            </div>
        </Transition>
    </div>
</template>
