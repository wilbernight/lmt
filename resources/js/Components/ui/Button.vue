<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Spinner from './Spinner.vue';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | secondary | ghost | soft | danger
    size: { type: String, default: 'md' }, // sm | md | lg | icon
    href: { type: String, default: null },
    type: { type: String, default: 'button' },
    loading: Boolean,
    disabled: Boolean,
    block: Boolean,
});

const classes = computed(() => [
    'relative inline-flex select-none items-center justify-center gap-2 whitespace-nowrap font-semibold transition-all duration-150 disabled:cursor-not-allowed disabled:opacity-55 active:scale-[0.98]',
    {
        primary: 'btn-solid',
        secondary: 'border border-line-strong bg-surface text-ink shadow-soft hover:bg-surface-2',
        ghost: 'text-ink-2 hover:bg-surface-3 hover:text-ink',
        soft: 'bg-accent-soft text-accent-ink hover:brightness-95 dark:hover:brightness-110',
        danger: 'btn-solid-danger',
        'danger-ghost': 'text-danger hover:bg-danger-soft',
    }[props.variant],
    {
        sm: 'h-8 rounded-lg px-3 text-xs',
        md: 'h-10 rounded-xl px-4 text-sm',
        lg: 'h-12 rounded-xl px-6 text-[0.95rem]',
        icon: 'size-9 rounded-xl',
        'icon-sm': 'size-7 rounded-lg',
    }[props.size],
    props.block && 'w-full',
]);
</script>

<template>
    <Link v-if="href" :href="href" :class="classes"><slot /></Link>
    <button v-else :type="type" :class="classes" :disabled="disabled || loading">
        <Spinner v-if="loading" class="size-4" />
        <slot />
    </button>
</template>
