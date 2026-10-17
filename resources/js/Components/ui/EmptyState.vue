<script setup>
defineProps({
    icon: { type: [Object, Function], default: null },
    title: { type: String, required: true },
    text: { type: String, default: '' },
    compact: Boolean,
});
</script>

<template>
    <div class="flex flex-col items-center justify-center text-center" :class="compact ? 'py-8' : 'py-16'">
        <div v-if="icon" class="relative mb-4">
            <div class="relative flex size-14 items-center justify-center rounded-2xl border border-line bg-surface text-accent shadow-card">
                <component :is="icon" class="size-6" />
            </div>
        </div>
        <h3 class="font-bold text-ink" :class="compact ? 'text-sm' : 'text-base'">{{ title }}</h3>
        <p v-if="text" class="mt-1 max-w-sm text-sm text-ink-3">{{ text }}</p>
        <div v-if="$slots.default" class="mt-5"><slot /></div>
    </div>
</template>
