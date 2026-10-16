<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Play, Square, Timer } from '@lucide/vue';
import http from '@/lib/http';
import { formatDuration } from '@/lib/format';
import { useNow } from '@/composables/useNow';

/** Start/stop timer and totals for one task (side panel of the task dialog). */
const props = defineProps({
    task: { type: Object, required: true },
    canWork: Boolean,
});
const emit = defineEmits(['changed']);

const page = usePage();
const now = useNow();
const busy = ref(false);

const runningSince = computed(() => props.task.time.running);
const live = computed(() => (runningSince.value ? Math.max(0, (now.value - new Date(runningSince.value).getTime()) / 1000) : 0));
// Server totals already include the running entry up to load time; add what elapsed since.
const loadedAt = ref(Date.now());
const extra = computed(() => (runningSince.value ? Math.max(0, (now.value - loadedAt.value) / 1000) : 0));

const total = computed(() => props.task.time.total + extra.value);
const mine = computed(() => props.task.time.mine + extra.value);
const estimate = computed(() => (props.task.estimate_minutes ? props.task.estimate_minutes * 60 : null));
const over = computed(() => estimate.value && total.value > estimate.value);

async function toggle() {
    busy.value = true;
    try {
        if (runningSince.value) {
            await http.post(route('timer.stop'));
        } else {
            await http.post(route('timer.start', [page.props.workspace.id, props.task.id]));
        }
        loadedAt.value = Date.now();
        emit('changed');
        router.reload({ only: ['timer'] });
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="rounded-2xl border p-4" :class="runningSince ? 'border-success/40 bg-success-soft/50' : 'border-line bg-surface'">
        <div class="flex items-center gap-3">
            <button
                v-if="canWork && !task.completed_at"
                type="button"
                class="flex size-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-soft transition-transform hover:scale-105 disabled:opacity-60"
                :class="runningSince ? 'bg-danger' : 'bg-success'"
                :disabled="busy"
                :aria-label="runningSince ? 'توقف کار' : 'شروع کار'"
                @click="toggle"
            >
                <component :is="runningSince ? Square : Play" class="size-5" fill="currentColor" />
            </button>
            <span v-else class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-surface-3 text-ink-3"><Timer class="size-5" /></span>
            <div class="min-w-0">
                <p class="text-xs font-semibold" :class="runningSince ? 'text-success' : 'text-ink-3'">{{ runningSince ? 'در حال کار' : canWork && !task.completed_at ? 'شروع ساعت کار' : 'زمان ثبت‌شده' }}</p>
                <p class="num text-2xl font-black tabular-nums" dir="ltr">{{ formatDuration(runningSince ? live : mine, { short: true, withSeconds: !!runningSince }) }}</p>
            </div>
        </div>
        <dl class="mt-3 grid grid-cols-2 gap-2 border-t border-line pt-3 text-xs">
            <div><dt class="text-ink-3">کل زمان وظیفه</dt><dd class="num mt-0.5 font-bold">{{ formatDuration(total) }}</dd></div>
            <div><dt class="text-ink-3">سهم من</dt><dd class="num mt-0.5 font-bold">{{ formatDuration(mine) }}</dd></div>
            <div v-if="estimate" class="col-span-2">
                <dt class="flex justify-between text-ink-3"><span>تخمین: {{ formatDuration(estimate) }}</span><span :class="over && 'font-bold text-danger'">{{ over ? 'بیش از تخمین' : `${Math.round((total / estimate) * 100)}٪` }}</span></dt>
                <dd class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-3"><div class="h-full rounded-full" :class="over ? 'bg-danger' : 'bg-success'" :style="{ width: `${Math.min(100, (total / estimate) * 100)}%` }" /></dd>
            </div>
        </dl>
    </div>
</template>
