<script setup>
import { computed } from 'vue';

const props = defineProps({
    user: { type: Object, default: null },
    size: { type: String, default: 'md' }, // xs sm md lg xl
    ring: Boolean,
    status: { type: Boolean, default: true },
});

const hues = [268, 295, 12, 45, 160, 190, 235, 345];
const hue = computed(() => hues[Math.abs(Number(props.user?.id ?? 0)) % hues.length]);

const sizeClass = computed(
    () =>
        ({
            xs: 'size-6 text-[0.6rem]',
            sm: 'size-8 text-[0.7rem]',
            md: 'size-10 text-xs',
            lg: 'size-14 text-base',
            xl: 'size-24 text-2xl',
        })[props.size],
);
</script>

<template>
    <span class="relative inline-flex shrink-0" :title="user?.name">
        <span
            :class="[sizeClass, ring && (size === 'xl' ? 'ring-4 ring-surface shadow-card' : 'ring-2 ring-surface')]"
            class="inline-flex items-center justify-center overflow-hidden rounded-full font-bold"
            :style="{ background: `oklch(0.9 0.06 ${hue})`, color: `oklch(0.4 0.14 ${hue})` }"
        >
            <img v-if="user?.avatar_url" :src="user.avatar_url" :alt="user?.name" class="size-full object-cover" loading="lazy" />
            <span v-else>{{ user?.initials || '?' }}</span>
        </span>
        <span
            v-if="status && user?.online"
            class="absolute bottom-0 end-0 size-2.5 rounded-full bg-success ring-2 ring-surface"
            :class="size === 'xs' && 'size-2'"
        />
    </span>
</template>
