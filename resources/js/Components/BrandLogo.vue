<script setup>
defineProps({ size: { type: String, default: 'md' } }); // md | lg
</script>

<template>
    <span class="inline-flex items-center gap-2.5" dir="ltr">
        <img src="/brand/icon.png" alt="" :class="size === 'lg' ? 'size-12' : 'size-11'" class="shrink-0" />
        <span :class="size === 'lg' ? 'text-[2rem]' : 'text-[1.85rem]'" class="font-sans leading-none tracking-tight text-ink">
            <span class="font-light">Wilber</span><span class="font-black">Night</span>
        </span>
    </span>
</template>
