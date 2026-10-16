<script setup>
defineProps({
    value: { type: Number, default: 0 },
    tone: { type: String, default: 'accent' },
    size: { type: String, default: 'sm' },
});
</script>

<template>
    <div class="w-full overflow-hidden rounded-full bg-surface-3" :class="size === 'sm' ? 'h-1.5' : 'h-2.5'" role="progressbar" :aria-valuenow="value" aria-valuemin="0" aria-valuemax="100">
        <div
            class="h-full rounded-full transition-[width] duration-500"
            :class="tone === 'success' || value >= 100 ? 'bg-success' : 'gradient-accent'"
            :style="{ width: `${Math.min(100, Math.max(0, value))}%` }"
        />
    </div>
</template>
