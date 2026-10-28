<script setup>
import { ChevronDown, ChevronUp, GripVertical } from '@lucide/vue';
import { usePage } from '@inertiajs/vue3';
import TaskRow from '@/Components/tasks/TaskRow.vue';
import { useManualOrder } from '@/composables/useManualOrder';

const props = defineProps({ tasks: Array });

const ws = usePage().props.workspace;
const { shown, dragId, onDragStart, onDragOver, onDrop, nudge } = useManualOrder(() => props.tasks, ws.id);
</script>

<template>
    <div
        v-for="t in shown"
        :key="t.id"
        class="group/row relative border-b border-line last:border-0"
        :class="dragId === t.id && 'opacity-40'"
        draggable="true"
        @dragstart="onDragStart(t, $event)"
        @dragover="onDragOver(t, $event)"
        @drop="onDrop"
        @dragend="dragId = null"
    >
        <span class="absolute start-1.5 top-1/2 z-10 hidden -translate-y-1/2 cursor-grab text-ink-3 group-hover/row:block print:hidden"><GripVertical class="size-4" /></span>
        <span class="absolute end-1.5 top-1/2 z-10 hidden -translate-y-1/2 flex-col gap-0.5 group-hover/row:flex print:hidden">
            <button class="rounded-md bg-surface p-0.5 text-ink-3 hover:bg-surface-3 hover:text-ink" aria-label="انتقال به بالا" @click.stop="nudge(t, -1)"><ChevronUp class="size-3.5" /></button>
            <button class="rounded-md bg-surface p-0.5 text-ink-3 hover:bg-surface-3 hover:text-ink" aria-label="انتقال به پایین" @click.stop="nudge(t, 1)"><ChevronDown class="size-3.5" /></button>
        </span>
        <TaskRow :task="t" />
    </div>
</template>
