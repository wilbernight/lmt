<script setup>
defineProps({
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    for: { type: String, default: undefined },
});
</script>

<template>
    <div>
        <label v-if="label" class="label" :for="$props.for">{{ label }}</label>
        <slot />
        <p v-if="error" class="mt-1.5 text-xs font-medium text-danger" role="alert">{{ error }}</p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-ink-3">{{ hint }}</p>
    </div>
</template>
