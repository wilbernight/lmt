<script setup>
import Avatar from './Avatar.vue';
import { faNum } from '@/lib/format';

defineProps({
    users: { type: Array, default: () => [] },
    max: { type: Number, default: 4 },
    size: { type: String, default: 'xs' },
    total: { type: Number, default: null },
});
</script>

<template>
    <div class="flex items-center -space-x-1.5 space-x-reverse">
        <Avatar v-for="u in users.slice(0, max)" :key="u.id" :user="u" :size="size" ring :status="false" />
        <span
            v-if="(total ?? users.length) > max"
            class="inline-flex size-6 items-center justify-center rounded-full bg-surface-3 text-[0.6rem] font-bold text-ink-2 ring-2 ring-surface"
        >
            +{{ faNum((total ?? users.length) - max) }}
        </span>
    </div>
</template>
