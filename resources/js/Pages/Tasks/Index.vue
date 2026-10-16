<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { CalendarDays, CheckCheck, Eye, Inbox, ListChecks, PenLine, Plus, Printer, Search, Users, X } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import TaskRow from '@/Components/tasks/TaskRow.vue';
import { PRIORITIES, faNum, isSameDay } from '@/lib/format';
import { openCreateTask, openTask, taskModal } from '@/composables/useTaskModal';

const props = defineProps({
    tab: String, tasks: Array, filters: Object, categories: Array, labels: Array, projects: Array, canCreate: Boolean, openTask: String,
});

const page = usePage();
const ws = page.props.workspace;
const q = ref(props.filters.q ?? '');

const tabs = computed(() => [
    { key: 'mine', label: 'کارهای من', icon: Inbox, href: route('tasks.index', [ws.id, { tab: 'mine' }]) },
    { key: 'following', label: 'پیگیری از دیگران', icon: Eye, href: route('tasks.index', [ws.id, { tab: 'following' }]) },
    { key: 'created', label: 'ایجادشده توسط من', icon: PenLine, href: route('tasks.index', [ws.id, { tab: 'created' }]) },
    { key: 'done', label: 'انجام‌شده', icon: CheckCheck, href: route('tasks.index', [ws.id, { tab: 'done' }]) },
    ...(ws.is_admin ? [{ key: 'all', label: 'همه وظایف', icon: Users, href: route('tasks.index', [ws.id, { tab: 'all' }]) }] : []),
    { key: 'calendar', label: 'تقویم', icon: CalendarDays, href: route('tasks.calendar', ws.id) },
]);

const apply = (extra = {}) =>
    router.get(route('tasks.index', ws.id), { ...props.filters, tab: props.tab, q: q.value || undefined, ...extra }, { preserveState: true, preserveScroll: true, replace: true });

watch(q, useDebounceFn(() => apply(), 300));
watch(() => taskModal.version, () => router.reload({ only: ['tasks'] }));

onMounted(() => props.openTask && openTask(props.openTask));

const printPage = () => window.print();

const hasFilters = computed(() => ['category', 'label', 'priority', 'project', 'due', 'q'].some((k) => props.filters[k]));

// Group by due date, like a planner.
const groups = computed(() => {
    if (props.tab === 'done') return [{ key: 'done', label: 'انجام‌شده‌ها', tone: 'emerald', items: props.tasks }];

    const now = new Date();
    const weekEnd = new Date(now);
    weekEnd.setDate(now.getDate() + 7);

    const buckets = [
        { key: 'overdue', label: 'دارای تأخیر', tone: 'rose', items: [] },
        { key: 'today', label: 'امروز', tone: 'amber', items: [] },
        { key: 'week', label: 'هفته پیش رو', tone: 'sky', items: [] },
        { key: 'later', label: 'بعداً', tone: 'indigo', items: [] },
        { key: 'none', label: 'وظایف بدون زمان', tone: 'slate', items: [] },
    ];
    const by = Object.fromEntries(buckets.map((b) => [b.key, b]));

    props.tasks.forEach((t) => {
        if (!t.due_at) return by.none.items.push(t);
        const d = new Date(t.due_at);
        if (isSameDay(d, now)) return by.today.items.push(t);
        if (d < now) return by.overdue.items.push(t);
        if (d <= weekEnd) return by.week.items.push(t);
        by.later.items.push(t);
    });

    return buckets.filter((b) => b.items.length);
});
</script>

<template>
    <Head title="وظایف" />

    <PageHeader title="وظایف" :subtitle="`${faNum(tasks.length)} وظیفه`" :icon="ListChecks" tone="emerald">
        <Button variant="secondary" size="icon" aria-label="چاپ" class="print:hidden" @click="printPage"><Printer class="size-4" /></Button>
        <Button v-if="canCreate" @click="openCreateTask()"><Plus class="size-4" /> ایجاد وظیفه</Button>
    </PageHeader>

    <div class="mt-6 border-b border-line">
        <Tabs :tabs="tabs" :active="tab" />
    </div>

    <!-- Filters -->
    <div class="mt-5 flex flex-wrap items-center gap-2 print:hidden">
        <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
            <input v-model="q" class="field h-10 w-60 ps-9" placeholder="جستجو در عنوان وظایف..." />
        </div>
        <select class="field h-10 w-auto" :value="filters.due ?? ''" @change="apply({ due: $event.target.value || undefined })">
            <option value="">هر زمانی</option>
            <option value="today">امروز</option>
            <option value="week">این هفته</option>
            <option value="overdue">دارای تأخیر</option>
            <option value="none">بدون زمان</option>
        </select>
        <select class="field h-10 w-auto" :value="filters.category ?? ''" @change="apply({ category: $event.target.value || undefined })">
            <option value="">همه دسته‌بندی‌ها</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select v-if="labels.length" class="field h-10 w-auto" :value="filters.label ?? ''" @change="apply({ label: $event.target.value || undefined })">
            <option value="">همه برچسب‌ها</option>
            <option v-for="l in labels" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
        <select class="field h-10 w-auto" :value="filters.project ?? ''" @change="apply({ project: $event.target.value || undefined })">
            <option value="">همه پروژه‌ها</option>
            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <select class="field h-10 w-auto" :value="filters.priority ?? ''" @change="apply({ priority: $event.target.value || undefined })">
            <option value="">هر اولویتی</option>
            <option v-for="p in PRIORITIES" :key="p.value" :value="p.value">{{ p.label }}</option>
        </select>
        <button v-if="hasFilters" class="flex items-center gap-1 text-xs font-semibold text-danger" @click="q = ''; router.get(route('tasks.index', [ws.id, { tab }]))"><X class="size-3.5" /> حذف فیلترها</button>
    </div>

    <div class="mt-6 space-y-6">
        <section v-for="g in groups" :key="g.key" class="card overflow-hidden" :class="`tone-${g.tone}`">
            <header class="flex items-center gap-2.5 border-b border-line bg-surface-2/50 px-5 py-3">
                <span class="size-2.5 rounded-full tone-dot" />
                <h2 class="text-sm font-bold">{{ g.label }}</h2>
                <span class="chip tone-chip">{{ faNum(g.items.length) }} مورد</span>
            </header>
            <TaskRow v-for="t in g.items" :key="t.id" :task="t" />
        </section>

        <EmptyState v-if="!tasks.length" :icon="CheckCheck" :title="hasFilters ? 'وظیفه‌ای با این فیلترها پیدا نشد' : 'فهرست وظایف خالی است'" text="وظیفه جدید بسازید یا فیلترها را تغییر دهید.">
            <Button v-if="canCreate" @click="openCreateTask()"><Plus class="size-4" /> ایجاد وظیفه</Button>
        </EmptyState>
    </div>
</template>
