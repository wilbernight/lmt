<script setup>
import { Check } from '@lucide/vue';

const model = defineModel({ type: String, default: '' });
defineProps({ colors: { type: Array, required: true } });
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="c in colors"
            :key="c"
            type="button"
            class="flex size-8 items-center justify-center rounded-full tone-dot ring-offset-2 ring-offset-surface transition-transform hover:scale-110"
            :class="[`tone-${c}`, model === c && 'ring-2 ring-[var(--tone)]']"
            :aria-label="c"
            :aria-pressed="model === c"
            @click="model = c"
        >
            <Check v-if="model === c" class="size-4 text-white" :stroke-width="3" />
        </button>
    </div>
</template>
