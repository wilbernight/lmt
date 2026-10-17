<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    Archive, ArchiveRestore, ListChecks, Palette, Pin, PinOff, Plus, RotateCcw, Search, StickyNote, Tag, Trash2, X,
} from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Dropdown from '@/Components/ui/Dropdown.vue';
import LabelPicker from '@/Components/ui/LabelPicker.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import http from '@/lib/http';
import { relative } from '@/lib/format';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({ view: String, notes: Array, labels: Array, colors: Array, filters: Object });

const page = usePage();
const ws = page.props.workspace;
const q = ref(props.filters.q ?? '');

const tabs = computed(() => [
    { key: 'all', label: 'همه یادداشت‌ها', icon: StickyNote, href: route('notes.index', ws.id) },
    { key: 'archived', label: 'آرشیو یادداشت‌ها', icon: Archive, href: route('notes.index', [ws.id, { view: 'archived' }]) },
    { key: 'trash', label: 'یادداشت‌های حذف‌شده', icon: Trash2, href: route('notes.index', [ws.id, { view: 'trash' }]) },
]);

watch(q, useDebounceFn(() => router.get(route('notes.index', ws.id), { view: props.view, q: q.value || undefined, label: props.filters.label }, { preserveState: true, replace: true }), 300));

// Composer -------------------------------------------------------------
const composerOpen = ref(false);
const blank = () => ({ title: '', body: '', items: [], color: 'default', pinned: false, labels: [], checklist: false });
const draft = ref(blank());
const newItem = ref('');

const addItem = (target) => {
    const text = newItem.value.trim();
    if (text) target.items.push({ text, done: false });
    newItem.value = '';
};

const composer = ref(null);

/** Open the composer from anywhere: the header button, the empty state or ?new=1. */
const startNew = async (checklist = false) => {
    if (props.view !== 'all') {
        router.get(route('notes.index', [ws.id, { new: 1 }]));
        return;
    }
    composerOpen.value = true;
    draft.value.checklist = checklist;
    await nextTick();
    composer.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    composer.value?.querySelector('input')?.focus();
};

onMounted(() => {
    if (new URLSearchParams(window.location.search).has('new')) startNew();
});

const create = () => {
    const d = draft.value;
    if (!d.title.trim() && !d.body.trim() && !d.items.length) {
        composerOpen.value = false;
        return;
    }
    router.post(route('notes.store', ws.id), { title: d.title, body: d.checklist ? null : d.body, items: d.checklist ? d.items : null, color: d.color, pinned: d.pinned, labels: d.labels }, {
        preserveScroll: true,
        onSuccess: () => { draft.value = blank(); composerOpen.value = false; },
    });
};

// Editing --------------------------------------------------------------
const editing = ref(null);
const open = (n) => {
    if (n.trashed) return;
    editing.value = JSON.parse(JSON.stringify({ ...n, labels: n.labels.map((l) => l.id), checklist: n.items.length > 0 }));
};

const saveEditing = async () => {
    const e = editing.value;
    if (!e) return;
    await http.patch(route('notes.update', [ws.id, e.id]), { title: e.title, body: e.checklist ? null : e.body, items: e.checklist ? e.items : null, color: e.color, pinned: e.pinned, labels: e.labels });
    editing.value = null;
    router.reload({ only: ['notes'] });
};

/** Small edits (pin, checklist tick) show at once and roll back if the server refuses. */
const quick = async (n, fields) => {
    const previous = Object.fromEntries(Object.keys(fields).map((k) => [k, n[k]]));
    Object.assign(n, fields);

    try {
        const { data } = await http.patch(route('notes.update', [ws.id, n.id]), fields);
        Object.assign(n, data);
    } catch {
        Object.assign(n, previous);
    }
};

const toggleItem = (n, i) => {
    const items = n.items.map((it, idx) => (idx === i ? { ...it, done: !it.done } : it));
    n.items = items;
    quick(n, { items });
};

const archive = (n) => router.post(route('notes.archive', [ws.id, n.id]), {}, { preserveScroll: true });
const trash = (n) => router.delete(route('notes.destroy', [ws.id, n.id]), { preserveScroll: true });
const restore = (n) => router.post(route('notes.restore', [ws.id, n.id]), {}, { preserveScroll: true });
const forceDelete = async (n) => {
    if (await confirmDialog({ title: 'حذف دائمی', text: 'این یادداشت برای همیشه حذف می‌شود و قابل بازگشت نیست.', confirmText: 'حذف دائمی', danger: true })) {
        router.delete(route('notes.force-destroy', [ws.id, n.id]), { preserveScroll: true });
    }
};

const pinned = computed(() => props.notes.filter((n) => n.pinned));
const others = computed(() => props.notes.filter((n) => !n.pinned));
</script>

<template>
    <Head title="یادداشت‌های من" />

    <PageHeader title="یادداشت‌های من" subtitle="فقط خودتان آن‌ها را می‌بینید" :icon="StickyNote" tone="amber">
        <Button @click="startNew()"><Plus class="size-4" /> یادداشت جدید</Button>
        <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
            <input v-model="q" class="field h-10 w-56 ps-9" placeholder="جستجو در یادداشت‌ها..." />
        </div>
        <select v-if="labels.length" class="field h-10 w-auto" :value="filters.label ?? ''" @change="router.get(route('notes.index', ws.id), { view, label: $event.target.value || undefined })">
            <option value="">همه برچسب‌ها</option>
            <option v-for="l in labels" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
    </PageHeader>

    <div class="mt-6 border-b border-line"><Tabs :tabs="tabs" :active="view" /></div>

    <!-- Composer -->
    <div v-if="view === 'all'" class="mx-auto mt-8 max-w-xl">
        <div ref="composer" class="rounded-2xl border-2 shadow-card transition-all [background:var(--note)]" :class="[`note-${draft.color}`, composerOpen ? 'border-accent' : 'border-dashed border-line-strong hover:border-accent']">
            <div v-if="!composerOpen" class="flex items-center gap-2 px-3 py-2">
                <button type="button" class="flex flex-1 items-center gap-3 rounded-xl px-2 py-2.5 text-start text-ink-3 hover:text-ink" @click="startNew()">
                    <Plus class="size-5 text-accent" />
                    <span class="flex-1">یادداشت جدید بنویسید...</span>
                </button>
                <button type="button" class="flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold text-ink-3 hover:bg-surface-3 hover:text-ink" @click="startNew(true)">
                    <ListChecks class="size-4" /> چک‌لیست
                </button>
            </div>
            <div v-else class="p-4">
                <input v-model="draft.title" autofocus maxlength="150" class="w-full bg-transparent px-1 text-base font-bold outline-none placeholder:text-ink-3" placeholder="عنوان" />
                <template v-if="!draft.checklist">
                    <textarea v-model="draft.body" rows="3" maxlength="20000" class="mt-2 w-full resize-none bg-transparent px-1 text-sm leading-7 outline-none placeholder:text-ink-3" placeholder="یادداشت..." />
                </template>
                <div v-else class="mt-2 space-y-1">
                    <div v-for="(it, i) in draft.items" :key="i" class="flex items-center gap-2 px-1 text-sm">
                        <Checkbox v-model="it.done" size="sm" />
                        <input v-model="it.text" class="flex-1 bg-transparent outline-none" :class="it.done && 'line-through opacity-60'" />
                        <button class="text-ink-3 hover:text-danger" aria-label="حذف" @click="draft.items.splice(i, 1)"><X class="size-3.5" /></button>
                    </div>
                    <div class="flex items-center gap-2 px-1"><Plus class="size-4 text-ink-3" /><input v-model="newItem" maxlength="300" class="flex-1 bg-transparent py-1 text-sm outline-none placeholder:text-ink-3" placeholder="مورد جدید و Enter" @keydown.enter.prevent="addItem(draft)" /></div>
                </div>
                <div class="mt-3 flex items-center gap-1">
                    <button class="rounded-lg p-2 text-ink-3 hover:bg-black/5 hover:text-ink dark:hover:bg-white/10" :aria-label="draft.checklist ? 'متن' : 'چک‌لیست'" @click="draft.checklist = !draft.checklist"><ListChecks class="size-4" /></button>
                    <Dropdown width="w-auto" up>
                        <template #trigger="{ toggle }"><button class="rounded-lg p-2 text-ink-3 hover:bg-black/5 hover:text-ink dark:hover:bg-white/10" aria-label="رنگ" @click="toggle"><Palette class="size-4" /></button></template>
                        <div class="flex gap-1.5 p-1">
                            <button v-for="c in colors" :key="c" class="size-7 rounded-full border border-line-strong [background:var(--note)]" :class="[`note-${c}`, draft.color === c && 'ring-2 ring-accent']" :aria-label="c" @click="draft.color = c" />
                        </div>
                    </Dropdown>
                    <button class="rounded-lg p-2 hover:bg-black/5 dark:hover:bg-white/10" :class="draft.pinned ? 'text-accent' : 'text-ink-3'" aria-label="سنجاق" @click="draft.pinned = !draft.pinned"><Pin class="size-4" /></button>
                    <div class="w-36"><LabelPicker v-model="draft.labels" :labels="labels" compact /></div>
                    <Button size="sm" variant="ghost" class="ms-auto" @click="draft = blank(); composerOpen = false">انصراف</Button>
                    <Button size="sm" @click="create">ذخیره</Button>
                </div>
            </div>
        </div>
    </div>

    <!-- Grid -->
    <template v-for="section in [{ k: 'p', title: 'سنجاق‌شده', items: pinned }, { k: 'o', title: pinned.length ? 'سایر یادداشت‌ها' : '', items: others }]" :key="section.k">
        <h3 v-if="section.items.length && section.title" class="mb-3 mt-8 text-xs font-bold text-ink-3">{{ section.title }}</h3>
        <div v-if="section.items.length" class="mt-4 columns-1 gap-4 sm:columns-2 lg:columns-3 2xl:columns-4">
            <article
                v-for="n in section.items"
                :key="n.id"
                class="group mb-4 break-inside-avoid rounded-2xl border border-line p-4 shadow-soft transition-shadow [background:var(--note)] hover:shadow-card"
                :class="[`note-${n.color}`, !n.trashed && 'cursor-pointer']"
                @click="open(n)"
            >
                <div class="flex items-start gap-2">
                    <h4 v-if="n.title" class="flex-1 font-bold leading-7">{{ n.title }}</h4>
                    <button v-if="!n.trashed" class="ms-auto rounded-lg p-1 opacity-0 transition-opacity group-hover:opacity-100" :class="n.pinned ? 'text-accent opacity-100' : 'text-ink-3'" :aria-label="n.pinned ? 'برداشتن سنجاق' : 'سنجاق'" @click.stop="quick(n, { pinned: !n.pinned })">
                        <component :is="n.pinned ? PinOff : Pin" class="size-4" />
                    </button>
                </div>
                <p v-if="n.body" class="mt-1 line-clamp-[12] whitespace-pre-wrap break-words text-sm leading-7 text-ink-2">{{ n.body }}</p>
                <ul v-if="n.items.length" class="mt-2 space-y-1.5">
                    <li v-for="(it, i) in n.items" :key="i" class="flex items-start gap-2 text-sm" @click.stop>
                        <Checkbox :model-value="it.done" size="sm" class="mt-1" :disabled="n.trashed" @update:model-value="toggleItem(n, i)" />
                        <span :class="it.done && 'text-ink-3 line-through'">{{ it.text }}</span>
                    </li>
                </ul>
                <div v-if="n.labels.length" class="mt-3 flex flex-wrap gap-1"><span v-for="l in n.labels" :key="l.id" class="chip tone-chip" :class="`tone-${l.color}`">{{ l.name }}</span></div>
                <footer class="mt-3 flex items-center gap-1 text-ink-3" @click.stop>
                    <span class="text-[0.7rem]">{{ relative(n.updated_at) }}</span>
                    <div class="ms-auto flex gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                        <template v-if="n.trashed">
                            <button class="rounded-lg p-1.5 hover:bg-black/5 hover:text-ink dark:hover:bg-white/10" aria-label="بازیابی" @click="restore(n)"><RotateCcw class="size-4" /></button>
                            <button class="rounded-lg p-1.5 hover:bg-danger-soft hover:text-danger" aria-label="حذف دائمی" @click="forceDelete(n)"><Trash2 class="size-4" /></button>
                        </template>
                        <template v-else>
                            <button class="rounded-lg p-1.5 hover:bg-black/5 hover:text-ink dark:hover:bg-white/10" :aria-label="n.archived ? 'خروج از آرشیو' : 'آرشیو'" @click="archive(n)"><component :is="n.archived ? ArchiveRestore : Archive" class="size-4" /></button>
                            <button class="rounded-lg p-1.5 hover:bg-danger-soft hover:text-danger" aria-label="حذف" @click="trash(n)"><Trash2 class="size-4" /></button>
                        </template>
                    </div>
                </footer>
            </article>
        </div>
    </template>

    <EmptyState v-if="!notes.length" :icon="view === 'trash' ? Trash2 : view === 'archived' ? Archive : StickyNote" :title="{ all: 'یادداشتی ندارید', archived: 'آرشیو خالی است', trash: 'سطل زباله خالی است' }[view]" class="mt-6">
        <Button v-if="view === 'all'" @click="startNew()"><Plus class="size-4" /> اولین یادداشت</Button>
    </EmptyState>

    <!-- Edit modal -->
    <Modal :show="!!editing" size="md" @close="saveEditing">
        <div v-if="editing" class="rounded-2xl p-5 [background:var(--note)]" :class="`note-${editing.color}`">
            <input v-model="editing.title" maxlength="150" class="w-full bg-transparent text-lg font-bold outline-none placeholder:text-ink-3" placeholder="عنوان" />
            <textarea v-if="!editing.checklist" v-model="editing.body" rows="8" maxlength="20000" class="mt-3 w-full resize-y bg-transparent text-sm leading-7 outline-none" placeholder="یادداشت..." />
            <div v-else class="mt-3 space-y-1.5">
                <div v-for="(it, i) in editing.items" :key="i" class="flex items-center gap-2 text-sm">
                    <Checkbox v-model="it.done" size="sm" />
                    <input v-model="it.text" class="flex-1 bg-transparent outline-none" :class="it.done && 'line-through opacity-60'" />
                    <button class="text-ink-3 hover:text-danger" aria-label="حذف" @click="editing.items.splice(i, 1)"><X class="size-3.5" /></button>
                </div>
                <div class="flex items-center gap-2"><Plus class="size-4 text-ink-3" /><input v-model="newItem" class="flex-1 bg-transparent py-1 text-sm outline-none placeholder:text-ink-3" placeholder="مورد جدید و Enter" @keydown.enter.prevent="addItem(editing)" /></div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button class="rounded-lg p-2 text-ink-3 hover:bg-black/5 dark:hover:bg-white/10" aria-label="حالت چک‌لیست" @click="editing.checklist = !editing.checklist"><ListChecks class="size-4" /></button>
                <div class="flex gap-1.5">
                    <button v-for="c in colors" :key="c" class="size-6 rounded-full border border-line-strong [background:var(--note)]" :class="[`note-${c}`, editing.color === c && 'ring-2 ring-accent']" :aria-label="c" @click="editing.color = c" />
                </div>
                <div class="w-40"><LabelPicker v-model="editing.labels" :labels="labels" compact /></div>
                <Button size="sm" class="ms-auto" @click="saveEditing">بستن و ذخیره</Button>
            </div>
        </div>
    </Modal>
</template>
