<script setup>
import { computed, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { CalendarDays, CheckCheck, ChevronLeft, ChevronRight, Eye, Inbox, PenLine, Plus, Users } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import http from '@/lib/http';
import { MONTHS, WEEKDAYS, monthGrid, toJalali } from '@/lib/jalali';
import { faNum, isSameDay } from '@/lib/format';
import { openCreateTask, openTask, taskModal } from '@/composables/useTaskModal';

const props = defineProps({ tasks: Array, range: Object, canCreate: Boolean, categories: Array, labels: Array, projects: Array });

const page = usePage();
const ws = page.props.workspace;

const now = toJalali(new Date());
const view = ref({ jy: now.jy, jm: now.jm });
const items = ref(props.tasks);

const days = computed(() => monthGrid(view.value.jy, view.value.jm));

async function load() {
    const first = days.value[0];
    const last = new Date(days.value[41]);
    last.setHours(23, 59, 59);
    const { data } = await http.get(route('tasks.calendar', ws.id), { params: { from: first.toISOString(), to: last.toISOString() } });
    items.value = data;
}

watch(view, load, { deep: true });
watch(() => taskModal.version, load);

const step = (delta) => {
    let { jy, jm } = view.value;
    jm += delta;
    if (jm < 1) { jm = 12; jy--; }
    if (jm > 12) { jm = 1; jy++; }
    view.value = { jy, jm };
};

const goToday = () => (view.value = { jy: now.jy, jm: now.jm });

const tasksOn = (day) => items.value.filter((t) => (t.due_at && isSameDay(t.due_at, day)) || (!t.due_at && t.starts_at && isSameDay(t.starts_at, day)));
const inMonth = (d) => toJalali(d).jm === view.value.jm;
const today = new Date();

const createOn = (day) => {
    const d = new Date(day);
    d.setHours(17, 0, 0, 0);
    openCreateTask({ due_at: d.toISOString() });
};

const tabs = [
    { key: 'mine', label: 'کارهای من', icon: Inbox, href: route('tasks.index', [ws.id, { tab: 'mine' }]) },
    { key: 'following', label: 'پیگیری از دیگران', icon: Eye, href: route('tasks.index', [ws.id, { tab: 'following' }]) },
    { key: 'created', label: 'ایجادشده توسط من', icon: PenLine, href: route('tasks.index', [ws.id, { tab: 'created' }]) },
    { key: 'done', label: 'انجام‌شده', icon: CheckCheck, href: route('tasks.index', [ws.id, { tab: 'done' }]) },
    ...(ws.is_admin ? [{ key: 'all', label: 'همه وظایف', icon: Users, href: route('tasks.index', [ws.id, { tab: 'all' }]) }] : []),
    { key: 'calendar', label: 'تقویم', icon: CalendarDays, href: route('tasks.calendar', ws.id) },
];
</script>

<template>
    <Head title="تقویم وظایف" />

    <PageHeader title="وظایف" subtitle="نمای تقویم شمسی" :icon="CalendarDays" tone="emerald">
        <Button v-if="canCreate" @click="openCreateTask()"><Plus class="size-4" /> ایجاد وظیفه</Button>
    </PageHeader>

    <div class="mt-6 border-b border-line"><Tabs :tabs="tabs" active="calendar" /></div>

    <div class="card mt-6 overflow-hidden">
        <header class="flex items-center gap-3 border-b border-line px-5 py-4">
            <h2 class="text-lg font-black">{{ MONTHS[view.jm - 1] }} <span class="num text-ink-3">{{ faNum(view.jy) }}</span></h2>
            <div class="ms-auto flex items-center gap-1">
                <Button variant="secondary" size="sm" @click="goToday">امروز</Button>
                <Button variant="ghost" size="icon-sm" aria-label="ماه قبل" @click="step(-1)"><ChevronRight class="size-4" /></Button>
                <Button variant="ghost" size="icon-sm" aria-label="ماه بعد" @click="step(1)"><ChevronLeft class="size-4" /></Button>
            </div>
        </header>

        <div class="grid grid-cols-7 border-b border-line bg-surface-2/50 text-center text-xs font-bold text-ink-3">
            <div v-for="(w, i) in WEEKDAYS" :key="w" class="py-2.5" :class="i === 6 && 'text-danger'">{{ w }}</div>
        </div>

        <div class="grid grid-cols-7">
            <div
                v-for="(d, i) in days"
                :key="d.toISOString()"
                class="group relative min-h-28 border-b border-e border-line p-1.5 transition-colors sm:min-h-32 [&:nth-child(7n)]:border-e-0"
                :class="[!inMonth(d) && 'bg-bg-subtle/60', isSameDay(d, today) && 'bg-accent-soft/40']"
            >
                <div class="mb-1 flex items-center justify-between px-1">
                    <span
                        class="num flex size-7 items-center justify-center rounded-full text-xs font-bold"
                        :class="[isSameDay(d, today) ? 'bg-accent text-on-accent' : inMonth(d) ? (i % 7 === 6 ? 'text-danger' : 'text-ink') : 'text-ink-3/60']"
                    >
                        {{ faNum(toJalali(d).jd) }}
                    </span>
                    <button v-if="canCreate" class="rounded-md p-0.5 text-ink-3 opacity-0 transition-opacity hover:bg-surface-3 hover:text-accent group-hover:opacity-100" aria-label="وظیفه در این روز" @click="createOn(d)"><Plus class="size-4" /></button>
                </div>
                <div class="space-y-1">
                    <button
                        v-for="t in tasksOn(d).slice(0, 3)"
                        :key="t.id"
                        class="block w-full truncate rounded-md px-1.5 py-1 text-start text-[0.7rem] font-semibold transition-transform hover:scale-[1.02]"
                        :class="[t.completed_at ? 'bg-success-soft text-success line-through' : t.is_overdue ? 'bg-danger-soft text-danger' : 'tone-chip', !t.completed_at && !t.is_overdue && `tone-${t.project?.color ?? 'indigo'}`]"
                        @click="openTask(t.id)"
                    >
                        {{ t.title }}
                    </button>
                    <p v-if="tasksOn(d).length > 3" class="px-1.5 text-[0.65rem] font-semibold text-ink-3">+{{ faNum(tasksOn(d).length - 3) }} مورد دیگر</p>
                </div>
            </div>
        </div>
    </div>
</template>
