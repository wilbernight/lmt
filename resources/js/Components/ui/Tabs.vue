<script setup>
import { Link } from '@inertiajs/vue3';
import { faNum } from '@/lib/format';

defineProps({
    tabs: { type: Array, required: true }, // [{ key, label, href?, icon?, count? }]
    active: { type: String, required: true },
});

defineEmits(['change']);
</script>

<template>
    <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Tabs">
        <component
            :is="tab.href ? Link : 'button'"
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            preserve-scroll
            type="button"
            class="relative inline-flex shrink-0 items-center gap-2 rounded-t-lg px-3.5 pb-3 pt-2 text-sm font-semibold transition-colors"
            :class="active === tab.key ? 'text-accent-ink' : 'text-ink-3 hover:text-ink'"
            @click="!tab.href && $emit('change', tab.key)"
        >
            <component :is="tab.icon" v-if="tab.icon" class="size-4" />
            {{ tab.label }}
            <span v-if="tab.count" class="chip bg-accent-soft px-1.5 text-accent-ink">{{ faNum(tab.count) }}</span>
            <span v-if="active === tab.key" class="absolute inset-x-2 bottom-0 h-0.5 rounded-full gradient-accent" />
        </component>
    </nav>
</template>
