<script setup>
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { FileText, Paperclip, X } from '@lucide/vue';
import http from '@/lib/http';
import { formatBytes } from '@/lib/format';
import Spinner from './Spinner.vue';

/** Uploads files immediately and exposes their attachment ids through v-model. */
const model = defineModel({ type: Array, default: () => [] });

defineProps({
    compact: Boolean,
    label: { type: String, default: 'پیوست فایل' },
});

const page = usePage();
const items = ref([]);
const uploading = ref(0);
const input = ref(null);

const MAX = 20 * 1024 * 1024;

async function upload(files) {
    for (const file of files) {
        if (file.size > MAX) continue;

        const body = new FormData();
        body.append('file', file);
        uploading.value++;

        try {
            const { data } = await http.post(route('attachments.store', page.props.workspace.id), body);
            items.value.push(data);
            model.value = [...model.value, data.id];
        } catch {
            // the http interceptor already shows the error
        } finally {
            uploading.value--;
        }
    }

    if (input.value) input.value.value = '';
}

async function remove(item) {
    await http.delete(route('attachments.destroy', item.id)).catch(() => {});
    items.value = items.value.filter((i) => i.id !== item.id);
    model.value = model.value.filter((id) => id !== item.id);
}

function reset() {
    items.value = [];
    model.value = [];
}

defineExpose({ open: () => input.value?.click(), reset });
</script>

<template>
    <div>
        <input ref="input" type="file" multiple class="hidden" @change="upload($event.target.files)" />
        <button v-if="!compact" type="button" class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line-strong px-4 py-4 text-sm text-ink-3 transition-colors hover:border-accent hover:text-accent-ink" @click="input.click()" @dragover.prevent @drop.prevent="upload($event.dataTransfer.files)">
            <Paperclip class="size-4" />
            {{ label }}
            <span class="text-xs">(حداکثر ۲۰ مگابایت)</span>
        </button>
        <div v-if="items.length || uploading" class="mt-2 flex flex-wrap gap-2">
            <div v-for="item in items" :key="item.id" class="flex items-center gap-2 rounded-lg border border-line bg-surface-2 py-1 pe-1 ps-2 text-xs">
                <FileText class="size-3.5 text-ink-3" />
                <span class="max-w-40 truncate">{{ item.name }}</span>
                <span class="text-ink-3">{{ formatBytes(item.size) }}</span>
                <button type="button" class="rounded p-0.5 text-ink-3 hover:text-danger" aria-label="حذف فایل" @click="remove(item)"><X class="size-3.5" /></button>
            </div>
            <div v-if="uploading" class="flex items-center gap-2 rounded-lg px-2 py-1 text-xs text-ink-3"><Spinner class="size-3.5" /> در حال بارگذاری...</div>
        </div>
    </div>
</template>
