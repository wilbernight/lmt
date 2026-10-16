<script setup>
import { Check } from '@lucide/vue';

const model = defineModel({ type: Boolean, default: false });

defineProps({
    round: Boolean,
    size: { type: String, default: 'md' },
    disabled: Boolean,
    label: { type: String, default: '' },
});
</script>

<template>
    <button
        type="button"
        role="checkbox"
        :aria-checked="model"
        :aria-label="label || undefined"
        :disabled="disabled"
        class="group inline-flex shrink-0 items-center justify-center border-2 transition-all duration-150 disabled:cursor-not-allowed disabled:opacity-50"
        :class="[
            round ? 'rounded-full' : 'rounded-md',
            size === 'sm' ? 'size-4' : 'size-5',
            model ? 'border-success bg-success text-white' : 'border-line-strong bg-surface hover:border-success',
        ]"
        @click.stop="model = !model"
    >
        <Check class="transition-transform" :class="[size === 'sm' ? 'size-2.5' : 'size-3', model ? 'scale-100' : 'scale-0 group-hover:scale-75 group-hover:text-success']" :stroke-width="3.5" />
    </button>
</template>
