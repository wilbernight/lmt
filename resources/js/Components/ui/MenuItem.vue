<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, default: null },
    method: { type: String, default: 'get' },
    danger: Boolean,
    active: Boolean,
});
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        :method="href ? method : undefined"
        :as="href && method !== 'get' ? 'button' : undefined"
        type="button"
        class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-start text-sm transition-colors"
        :class="[danger ? 'text-danger hover:bg-danger-soft' : 'text-ink-2 hover:bg-surface-3 hover:text-ink', active && 'bg-accent-soft text-accent-ink']"
    >
        <slot />
    </component>
</template>
