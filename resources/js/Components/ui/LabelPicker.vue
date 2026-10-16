<script setup>
import { computed } from 'vue';
import { Check, Tag } from '@lucide/vue';
import Dropdown from './Dropdown.vue';

const model = defineModel({ type: Array, default: () => [] });

const props = defineProps({
    labels: { type: Array, default: () => [] },
    compact: Boolean,
    placeholder: { type: String, default: 'برچسب' },
});

const chosen = computed(() => props.labels.filter((l) => model.value.includes(l.id)));
const toggle = (id) => (model.value = model.value.includes(id) ? model.value.filter((x) => x !== id) : [...model.value, id]);
</script>

<template>
    <Dropdown width="w-56">
        <template #trigger="{ toggle: open }">
            <button type="button" class="field flex flex-wrap items-center gap-1.5 text-start" :class="compact && 'min-h-9 py-1.5 text-xs'" @click="open">
                <template v-if="chosen.length">
                    <span v-for="l in chosen" :key="l.id" class="chip tone-chip" :class="`tone-${l.color}`">{{ l.name }}</span>
                </template>
                <template v-else>
                    <Tag class="size-4 text-ink-3" />
                    <span class="text-ink-3">{{ placeholder }}</span>
                </template>
            </button>
        </template>
        <div class="max-h-60 overflow-y-auto">
            <button v-for="l in labels" :key="l.id" type="button" class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-surface-3" @click="toggle(l.id)">
                <span class="size-2.5 rounded-full tone-dot" :class="`tone-${l.color}`" />
                <span class="flex-1 truncate text-start">{{ l.name }}</span>
                <Check v-if="model.includes(l.id)" class="size-4 text-accent" />
            </button>
            <p v-if="!labels.length" class="px-2 py-3 text-center text-xs leading-5 text-ink-3">هنوز برچسبی ساخته نشده. از تنظیمات میزکار بسازید.</p>
        </div>
    </Dropdown>
</template>
