<script setup>
import { Download, FileText } from '@lucide/vue';
import { formatBytes } from '@/lib/format';

defineProps({ items: { type: Array, default: () => [] } });
</script>

<template>
    <div v-if="items.length" class="flex flex-wrap gap-2">
        <template v-for="a in items" :key="a.id">
            <a v-if="a.is_image" :href="a.url" target="_blank" rel="noopener noreferrer" class="group relative block size-20 overflow-hidden rounded-xl border border-line">
                <img :src="a.url" :alt="a.name" class="size-full object-cover transition-transform group-hover:scale-105" loading="lazy" />
            </a>
            <a v-else :href="a.download_url" rel="noopener noreferrer" class="flex items-center gap-2.5 rounded-xl border border-line bg-surface-2 px-3 py-2 text-xs transition-colors hover:border-accent">
                <span class="flex size-8 items-center justify-center rounded-lg bg-accent-soft text-accent-ink"><FileText class="size-4" /></span>
                <span class="min-w-0">
                    <span class="block max-w-44 truncate font-semibold text-ink">{{ a.name }}</span>
                    <span class="text-ink-3">{{ formatBytes(a.size) }}</span>
                </span>
                <Download class="size-4 text-ink-3" />
            </a>
        </template>
    </div>
</template>
