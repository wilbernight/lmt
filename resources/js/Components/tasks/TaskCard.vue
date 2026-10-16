<script setup>
import { computed } from 'vue';
import { CalendarClock, CircleCheck, ListChecks, MessageSquare, Paperclip } from '@lucide/vue';
import AvatarStack from '@/Components/ui/AvatarStack.vue';
import { PRIORITIES, dueLabel, faNum } from '@/lib/format';
import { openTask } from '@/composables/useTaskModal';

const props = defineProps({ task: { type: Object, required: true } });

const due = computed(() => dueLabel(props.task.due_at, !!props.task.completed_at));
const priority = computed(() => PRIORITIES[props.task.priority]);
const progress = computed(() => (props.task.checklist_total ? Math.round((props.task.checklist_done / props.task.checklist_total) * 100) : 0));
</script>

<template>
    <article
        class="group relative cursor-pointer overflow-hidden rounded-xl border border-line bg-surface p-3.5 shadow-soft transition-all duration-150 hover:-translate-y-0.5 hover:border-line-strong hover:shadow-card"
        :data-id="task.id"
        @click="openTask(task.id)"
    >
        <span v-if="task.priority >= 2" class="absolute inset-y-0 start-0 w-1 tone-bar" :class="`tone-${priority.tone}`" />

        <div v-if="task.labels?.length || task.category" class="mb-2 flex flex-wrap gap-1">
            <span v-if="task.category" class="chip tone-chip" :class="`tone-${task.category.color}`">{{ task.category.name }}</span>
            <span v-for="l in task.labels" :key="l.id" class="chip tone-chip" :class="`tone-${l.color}`">{{ l.name }}</span>
        </div>

        <h4 class="flex items-start gap-1.5 text-sm font-semibold leading-6" :class="task.completed_at ? 'text-ink-3 line-through' : 'text-ink'">
            <CircleCheck v-if="task.completed_at" class="mt-1 size-4 shrink-0 text-success" />
            {{ task.title }}
        </h4>

        <div v-if="task.checklist_total" class="mt-2.5 flex items-center gap-2">
            <div class="h-1 flex-1 overflow-hidden rounded-full bg-surface-3">
                <div class="h-full rounded-full" :class="progress === 100 ? 'bg-success' : 'gradient-accent'" :style="{ width: `${progress}%` }" />
            </div>
        </div>

        <footer class="mt-3 flex items-center gap-2.5 text-[0.7rem] text-ink-3">
            <span v-if="due" class="flex items-center gap-1 rounded-md px-1.5 py-0.5" :class="{ 'bg-danger-soft font-semibold text-danger': due.tone === 'danger', 'bg-warning-soft font-semibold text-warning': due.tone === 'warning' }">
                <CalendarClock class="size-3.5" /> {{ due.text }}
            </span>
            <span v-if="task.checklist_total" class="num flex items-center gap-1"><ListChecks class="size-3.5" />{{ faNum(task.checklist_done) }}/{{ faNum(task.checklist_total) }}</span>
            <span v-if="task.comments_count" class="flex items-center gap-1"><MessageSquare class="size-3.5" />{{ faNum(task.comments_count) }}</span>
            <span v-if="task.attachments_count" class="flex items-center gap-1"><Paperclip class="size-3.5" />{{ faNum(task.attachments_count) }}</span>
            <AvatarStack :users="task.assignees ?? []" :max="3" class="ms-auto" />
        </footer>
    </article>
</template>
