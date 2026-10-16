<script setup>
import { computed } from 'vue';
import { CalendarClock, ListChecks, MessageSquare, Paperclip } from '@lucide/vue';
import AvatarStack from '@/Components/ui/AvatarStack.vue';
import { PRIORITIES, dueLabel, faNum } from '@/lib/format';

const props = defineProps({
    task: { type: Object, required: true },
    showProject: Boolean,
});

const due = computed(() => dueLabel(props.task.due_at, !!props.task.completed_at));
const priority = computed(() => PRIORITIES[props.task.priority]);
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[0.72rem] text-ink-3">
        <span v-if="task.priority >= 2" class="chip tone-chip" :class="`tone-${priority.tone}`">{{ priority.label }}</span>
        <span v-if="showProject && task.project" class="flex items-center gap-1.5">
            <span class="size-2 rounded-full tone-dot" :class="`tone-${task.project.color}`" />{{ task.project.name }}
        </span>
        <span v-if="task.category" class="chip tone-chip" :class="`tone-${task.category.color}`">{{ task.category.name }}</span>
        <span v-if="due" class="flex items-center gap-1" :class="{ 'font-semibold text-danger': due.tone === 'danger', 'font-semibold text-warning': due.tone === 'warning' }">
            <CalendarClock class="size-3.5" /> {{ due.text }}
        </span>
        <span v-if="task.checklist_total" class="num flex items-center gap-1" :class="task.checklist_done === task.checklist_total && 'text-success'">
            <ListChecks class="size-3.5" /> {{ faNum(task.checklist_done) }}/{{ faNum(task.checklist_total) }}
        </span>
        <span v-if="task.comments_count" class="flex items-center gap-1"><MessageSquare class="size-3.5" /> {{ faNum(task.comments_count) }}</span>
        <span v-if="task.attachments_count" class="flex items-center gap-1"><Paperclip class="size-3.5" /> {{ faNum(task.attachments_count) }}</span>
        <span v-for="l in task.labels" :key="l.id" class="chip tone-chip" :class="`tone-${l.color}`">{{ l.name }}</span>
        <AvatarStack v-if="task.assignees?.length" :users="task.assignees" :max="3" class="ms-auto" />
    </div>
</template>
