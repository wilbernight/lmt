<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
    tone: { type: String, default: 'indigo' },
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
        <div v-if="icon" class="flex size-11 items-center justify-center rounded-2xl tone-chip shadow-soft" :class="`tone-${tone}`">
            <component :is="icon" class="size-5" />
        </div>
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-xl font-extrabold tracking-tight sm:text-2xl">{{ title }}</h1>
            <p v-if="subtitle" class="mt-0.5 text-sm text-ink-3">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2"><slot /></div>
    </div>
</template>
