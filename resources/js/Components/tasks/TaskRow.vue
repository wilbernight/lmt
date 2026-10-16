<script setup>
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Checkbox from '@/Components/ui/Checkbox.vue';
import TaskMeta from './TaskMeta.vue';
import http from '@/lib/http';
import { openTask, taskChanged } from '@/composables/useTaskModal';

const props = defineProps({
    task: { type: Object, required: true },
    showProject: { type: Boolean, default: true },
    compact: Boolean,
});

const page = usePage();
const done = ref(!!props.task.completed_at);
const busy = ref(false);

async function toggle() {
    if (busy.value) return;
    busy.value = true;
    const before = done.value;
    done.value = !before;
    try {
        await http.post(route('tasks.complete', [page.props.workspace.id, props.task.id]));
        taskChanged();
    } catch {
        done.value = before;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div
        class="group flex cursor-pointer items-start gap-3 border-b border-line px-4 transition-colors last:border-0 hover:bg-surface-2"
        :class="compact ? 'py-2.5' : 'py-3.5'"
        @click="openTask(task.id)"
    >
        <Checkbox :model-value="done" round class="mt-0.5" :label="`انجام شد: ${task.title}`" @update:model-value="toggle" />
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold transition-colors" :class="done ? 'text-ink-3 line-through' : 'text-ink group-hover:text-accent-ink'">{{ task.title }}</p>
            <TaskMeta v-if="!compact || task.due_at || task.assignees?.length" :task="task" :show-project="showProject" class="mt-1.5" />
        </div>
    </div>
</template>
