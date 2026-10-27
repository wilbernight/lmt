<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { CircleCheck, ClipboardList, Contact, CornerDownLeft, FileText, FolderKanban, ListChecks, Mailbox, MessagesSquare, Plus, Search, StickyNote, User } from '@lucide/vue';
import Modal from './ui/Modal.vue';
import Spinner from './ui/Spinner.vue';
import Avatar from './ui/Avatar.vue';
import http from '@/lib/http';
import { openCreateTask, openTask } from '@/composables/useTaskModal';

const props = defineProps({ show: Boolean });
const emit = defineEmits(['close']);

const page = usePage();
const q = ref('');
const results = ref([]);
const loading = ref(false);
const index = ref(0);

const icons = { task: ListChecks, project: FolderKanban, letter: Mailbox, minute: ClipboardList, note: StickyNote, customer: Contact, member: User };
const groupNames = { task: 'وظایف', project: 'پروژه‌ها', letter: 'نامه‌ها', minute: 'صورت‌جلسه‌ها', note: 'یادداشت‌ها', customer: 'مشتریان', member: 'همکاران' };

const ws = computed(() => page.props.workspace);

const actions = computed(() => [
    { type: 'action', title: 'ایجاد وظیفه جدید', icon: Plus, run: () => openCreateTask(), hint: 'N' },
    { type: 'action', title: 'رفتن به وظایف من', icon: ListChecks, run: () => router.visit(route('tasks.index', ws.value.id)) },
    { type: 'action', title: 'نوشتن نامه', icon: FileText, run: () => router.visit(route('letters.create', ws.value.id)) },
    { type: 'action', title: 'ثبت صورت‌جلسه', icon: ClipboardList, run: () => router.visit(route('minutes.create', ws.value.id)) },
    { type: 'action', title: 'گفتگوها', icon: MessagesSquare, run: () => router.visit(route('chat.index', ws.value.id)) },
    { type: 'action', title: 'پروژه‌ها', icon: FolderKanban, run: () => router.visit(route('projects.index', ws.value.id)) },
]);

const items = computed(() => (q.value.trim().length < 2 ? actions.value : results.value));

const grouped = computed(() => {
    const groups = {};
    items.value.forEach((item, i) => {
        const key = item.type === 'action' ? 'action' : item.type;
        (groups[key] ??= []).push({ ...item, _i: i });
    });
    return groups;
});

const search = useDebounceFn(async () => {
    const term = q.value.trim();
    if (term.length < 2) return;
    loading.value = true;
    try {
        const { data } = await http.get(route('search', ws.value.id), { params: { q: term } });
        if (term === q.value.trim()) {
            results.value = data;
            index.value = 0;
        }
    } catch {
        results.value = [];
    } finally {
        loading.value = false;
    }
}, 220);

watch(q, () => {
    index.value = 0;
    search();
});

watch(
    () => props.show,
    (open) => {
        if (open) {
            q.value = '';
            results.value = [];
        }
    },
);

function choose(item) {
    emit('close');
    if (item.type === 'action') return item.run();
    if (item.type === 'task') return openTask(item.id);
    if (item.type === 'member') return router.post(route('chat.direct', ws.value.id), { user_id: item.id });
    if (item.url) router.visit(item.url);
}

function onKey(e) {
    const list = items.value;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        index.value = (index.value + 1) % Math.max(list.length, 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        index.value = (index.value - 1 + list.length) % Math.max(list.length, 1);
    } else if (e.key === 'Enter' && list[index.value]) {
        e.preventDefault();
        choose(list[index.value]);
    }
}
</script>

<template>
    <Modal :show="show" size="md" @close="emit('close')">
        <div class="flex items-center gap-3 border-b border-line px-4">
            <Search class="size-5 text-ink-3" />
            <input
                v-model="q"
                autofocus
                class="h-14 flex-1 bg-transparent text-base outline-none placeholder:text-ink-3"
                placeholder="چه چیزی را پیدا کنم؟"
                @keydown="onKey"
            />
            <Spinner v-if="loading" class="size-4 text-ink-3" />
        </div>
        <div class="max-h-[60vh] overflow-y-auto p-2">
            <template v-for="(group, key) in grouped" :key="key">
                <p class="px-3 pb-1 pt-3 text-[0.7rem] font-bold text-ink-3">{{ key === 'action' ? 'دسترسی سریع' : groupNames[key] }}</p>
                <button
                    v-for="item in group"
                    :key="item.type + (item.id ?? item.title)"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-start text-sm transition-colors"
                    :class="index === item._i ? 'bg-accent-soft text-accent-ink' : 'text-ink-2 hover:bg-surface-2'"
                    @mouseenter="index = item._i"
                    @click="choose(item)"
                >
                    <Avatar v-if="item.type === 'member'" :user="item.user" size="xs" />
                    <component :is="item.icon || icons[item.type]" v-else class="size-4 shrink-0" />
                    <span class="flex-1 truncate" :class="item.done && 'line-through opacity-60'">{{ item.title }}</span>
                    <CircleCheck v-if="item.done" class="size-4 text-success" />
                    <span v-if="item.meta" class="text-xs text-ink-3">{{ item.meta }}</span>
                    <kbd v-if="item.hint" class="rounded border border-line px-1.5 text-[0.65rem]">{{ item.hint }}</kbd>
                    <CornerDownLeft v-if="index === item._i" class="size-3.5 opacity-60" />
                </button>
            </template>
            <p v-if="q.trim().length >= 2 && !loading && !results.length" class="px-3 py-10 text-center text-sm text-ink-3">نتیجه‌ای برای «{{ q }}» پیدا نشد.</p>
        </div>
        <div class="flex items-center gap-4 border-t border-line px-4 py-2.5 text-[0.7rem] text-ink-3">
            <span><kbd class="rounded border border-line px-1">↑↓</kbd> جابه‌جایی</span>
            <span><kbd class="rounded border border-line px-1">Enter</kbd> انتخاب</span>
            <span><kbd class="rounded border border-line px-1">Esc</kbd> بستن</span>
        </div>
    </Modal>
</template>
