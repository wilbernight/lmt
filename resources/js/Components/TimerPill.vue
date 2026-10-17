<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Clock, LogIn, Square } from '@lucide/vue';
import http from '@/lib/http';
import { formatDuration, jTime } from '@/lib/format';
import { openTask, taskChanged } from '@/composables/useTaskModal';
import { useNow } from '@/composables/useNow';

/** Header widget: running task timer, plus today's clock-in time. */
const page = usePage();
const now = useNow();

const timer = computed(() => page.props.timer);
const presence = computed(() => page.props.presence);

const elapsed = computed(() => (timer.value ? (now.value - new Date(timer.value.started_at).getTime()) / 1000 : 0));

// Today's presence = closed sessions + the open one up to now.
const today = computed(() => {
    const p = presence.value;
    if (!p) return 0;
    const since = p.in_since ? Math.max(0, (now.value - new Date(p.as_of).getTime()) / 1000) : 0;
    return p.seconds + since;
});

async function stop() {
    await http.post(route('timer.stop'));
    taskChanged();
    router.reload({ only: ['timer'] });
}

const open = () => {
    const ws = page.props.workspace;
    if (ws && ws.id === timer.value.workspace_id) openTask(timer.value.task_id);
    else router.visit(route('tasks.index', [timer.value.workspace_id, { task: timer.value.task_id }]));
};
</script>

<template>
    <div class="flex items-center gap-2">
        <div v-if="timer" class="flex h-10 items-center gap-1 rounded-xl border border-success/40 bg-success-soft ps-1 pe-3 text-success">
            <button class="flex size-8 items-center justify-center rounded-lg bg-success text-white transition-transform hover:scale-105" aria-label="توقف زمان‌سنج" @click="stop">
                <Square class="size-3.5" fill="currentColor" />
            </button>
            <button class="flex min-w-0 items-center gap-2 text-start" @click="open">
                <span class="size-2 rounded-full bg-success" />
                <span class="num text-sm font-black tabular-nums" dir="ltr">{{ formatDuration(elapsed, { short: true, withSeconds: true }) }}</span>
                <span class="hidden max-w-40 truncate text-xs font-semibold xl:inline">{{ timer.task_title }}</span>
            </button>
        </div>
        <div
            v-if="presence?.in_since"
            class="hidden h-10 items-center gap-2 rounded-xl border border-line bg-surface-2 px-3 text-xs text-ink-2 md:flex"
            :title="`ورود امروز: ${jTime(presence.first_in)}`"
        >
            <LogIn class="size-4 text-info" />
            <span>ورود {{ jTime(presence.first_in ?? presence.in_since) }}</span>
            <span class="text-ink-3">·</span>
            <Clock class="size-3.5 text-ink-3" />
            <span class="num font-bold">{{ formatDuration(today, { short: true }) }}</span>
        </div>
    </div>
</template>
