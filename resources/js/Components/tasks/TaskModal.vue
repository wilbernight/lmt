<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    AtSign, Bookmark, CalendarClock, CircleCheck, Clock, Copy, Eye, EyeOff, Flag, FolderKanban, History, Layers,
    ListChecks, MessageSquare, MoreHorizontal, Paperclip, Pencil, Plus, Send, Tag, Timer, Trash2, Users, X,
} from '@lucide/vue';
import Modal from '@/Components/ui/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import DatePicker from '@/Components/ui/DatePicker.vue';
import MemberPicker from '@/Components/ui/MemberPicker.vue';
import PeoplePicker from '@/Components/ui/PeoplePicker.vue';
import LabelPicker from '@/Components/ui/LabelPicker.vue';
import Dropdown from '@/Components/ui/Dropdown.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import AttachmentList from '@/Components/ui/AttachmentList.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import Spinner from '@/Components/ui/Spinner.vue';
import TaskTimer from './TaskTimer.vue';
import TaskTimeLog from './TaskTimeLog.vue';
import http from '@/lib/http';
import { PRIORITIES, faNum, jDateTime, relative } from '@/lib/format';
import { closeTask, taskChanged, taskModal } from '@/composables/useTaskModal';
import { confirmDialog } from '@/composables/useConfirm';
import { toast } from '@/composables/useToast';

const page = usePage();
const ws = computed(() => page.props.workspace);

const task = ref(null);
const loading = ref(false);
const tab = ref('reports');
const editingTitle = ref(false);
const editingDesc = ref(false);
const draft = ref({ title: '', description: '' });
const newItem = ref('');
const report = ref({ body: '', progress: null, mentions: [], attachments: [] });
const sending = ref(false);
const uploader = ref(null);
const reportUploader = ref(null);
const options = ref({ categories: [], labels: [] });

const can = computed(() => task.value?.can ?? {});
const url = (name, extra = []) => route(name, [ws.value.id, task.value.id, ...extra]);

async function load(id = taskModal.id) {
    if (!id) return;
    loading.value = !task.value || task.value.id !== id;
    try {
        const { data } = await http.get(route('tasks.show', [ws.value.id, id]));
        task.value = data;
        draft.value = { title: data.title, description: data.description ?? '' };
    } catch {
        closeTask();
    } finally {
        loading.value = false;
    }
}

watch(
    () => taskModal.id,
    async (id) => {
        if (!id) {
            task.value = null;
            return;
        }
        tab.value = 'reports';
        editingTitle.value = editingDesc.value = false;
        report.value = { body: '', progress: null, mentions: [], attachments: [] };
        await load(id);
        if (!options.value.categories.length) {
            http.get(route('tasks.options', ws.value.id)).then(({ data }) => (options.value = data)).catch(() => {});
        }
    },
    { immediate: true },
);

function changed() {
    taskChanged();
    router.reload({ only: ['tasks', 'myTasks', 'following', 'overdue', 'stats', 'counters', 'timer', 'project'] });
}

async function patch(fields) {
    try {
        await http.patch(url('tasks.update'), fields);
        await load();
        changed();
    } catch {
        await load();
    }
}

async function toggleComplete() {
    await http.post(url('tasks.complete'));
    await load();
    changed();
}

async function saveTitle() {
    editingTitle.value = false;
    const title = draft.value.title.trim();
    if (title && title !== task.value.title) await patch({ title });
}

async function saveDescription() {
    editingDesc.value = false;
    if ((draft.value.description ?? '') !== (task.value.description ?? '')) await patch({ description: draft.value.description || null });
}

// Checklist ---------------------------------------------------------
async function addItem() {
    const body = newItem.value.trim();
    if (!body) return;
    newItem.value = '';
    const { data } = await http.post(url('checklist.store'), { body });
    task.value.checklist.push(data);
    changed();
}

async function toggleItem(item) {
    item.is_done = !item.is_done;
    try {
        const { data } = await http.patch(url('checklist.update', [item.id]), { is_done: item.is_done });
        task.value.progress = data.progress;
        changed();
    } catch {
        item.is_done = !item.is_done;
    }
}

async function removeItem(item) {
    const { data } = await http.delete(url('checklist.destroy', [item.id]));
    task.value.checklist = task.value.checklist.filter((i) => i.id !== item.id);
    task.value.progress = data.progress;
    changed();
}

const checklistDone = computed(() => task.value?.checklist.filter((i) => i.is_done).length ?? 0);

// Reports ------------------------------------------------------------
async function sendReport() {
    if (!report.value.body.trim()) return;
    sending.value = true;
    try {
        await http.post(url('comments.store'), report.value);
        report.value = { body: '', progress: null, mentions: [], attachments: [] };
        reportUploader.value?.reset();
        await load();
        changed();
    } finally {
        sending.value = false;
    }
}

async function deleteComment(c) {
    if (!(await confirmDialog({ title: 'حذف گزارش', text: 'این گزارش حذف شود؟', confirmText: 'حذف', danger: true }))) return;
    await http.delete(url('comments.destroy', [c.id]));
    await load();
}

const mentionNames = computed(() => (ws.value?.members ?? []).filter((m) => report.value.mentions.includes(m.id)));

// Assignees ----------------------------------------------------------
// Current assignees as picker entries: members + colleagues without an account.
const assigneeEntries = computed(() => [
    ...(task.value?.assignees ?? []).map((a) => ({ user_id: a.id, name: a.name, external: false })),
    ...(task.value?.people ?? []).map((p) => ({ person_id: p.id, name: p.name, external: true })),
]);

/** Split picker entries into member ids and free-typed external names. */
const assigneePayload = (entries) => ({
    assignees: entries.filter((e) => !e.external).map((e) => e.user_id),
    people_names: entries.filter((e) => e.external).map((e) => e.name),
});

// Attachments --------------------------------------------------------
const pendingFiles = ref([]);
watch(pendingFiles, async (ids) => {
    if (!ids.length) return;
    // Files uploaded here are claimed by the task right away.
    await patch({ attachments: ids });
    uploader.value?.reset();
});

// Misc ---------------------------------------------------------------
async function toggleWatch() {
    const { data } = await http.post(url('tasks.watch'));
    task.value.watching = data.watching;
}

async function toggleBookmark() {
    const { data } = await http.post(url('tasks.bookmark'));
    task.value.bookmarked = data.bookmarked;
    toast.success(data.bookmarked ? 'به نشان‌شده‌ها اضافه شد.' : 'از نشان‌شده‌ها حذف شد.');
}

async function destroy(close) {
    close?.();
    if (!(await confirmDialog({ title: 'حذف وظیفه', text: `«${task.value.title}» حذف شود؟`, confirmText: 'حذف وظیفه', danger: true }))) return;
    await http.delete(url('tasks.destroy'));
    toast.success('وظیفه حذف شد.');
    closeTask();
    changed();
}

function copyLink(close) {
    close?.();
    const link = new URL(route('tasks.index', [ws.value.id, { task: task.value.id }]), window.location.origin).href;
    navigator.clipboard?.writeText(link).then(() => toast.success('پیوند کپی شد.'));
}

const activityText = (a) =>
    ({
        created: 'وظیفه را ساخت',
        updated: 'جزئیات را ویرایش کرد',
        completed: 'وظیفه را انجام‌شده علامت زد',
        reopened: 'وظیفه را دوباره باز کرد',
        moved: `وظیفه را به «${a.data?.to}» برد`,
        assigned: `مسئولیت را به ${(a.data?.users ?? []).join('، ')} سپرد`,
        commented: 'گزارش ثبت کرد',
        checked: `«${a.data?.item}» را تیک زد`,
        unchecked: `تیک «${a.data?.item}» را برداشت`,
        timer_started: 'شروع به کار کرد',
        timer_stopped: `کار را متوقف کرد (${a.data?.minutes ?? 0} دقیقه)`,
        time_logged: `${a.data?.minutes ?? 0} دقیقه زمان ثبت کرد`,
    })[a.type] ?? a.type;
</script>

<template>
    <Modal :show="!!taskModal.id" size="xl" @close="closeTask">
        <div v-if="loading || !task" class="flex h-96 items-center justify-center"><Spinner class="size-7 text-accent" /></div>

        <div v-else class="grid lg:grid-cols-[1fr_18rem]">
            <!-- Main column -->
            <section class="min-w-0 p-5 sm:p-7">
                <div class="mb-4 flex items-center gap-2 text-xs text-ink-3">
                    <template v-if="task.project">
                        <span class="size-2 rounded-full tone-dot" :class="`tone-${task.project.color}`" />
                        <span class="font-semibold">{{ task.project.name }}</span>
                        <span>·</span>
                    </template>
                    <span>ساخته‌شده {{ relative(task.created_at) }} توسط {{ task.creator.name }}</span>
                    <div class="ms-auto flex items-center gap-1">
                        <button class="rounded-lg p-1.5 hover:bg-surface-3" :class="task.bookmarked ? 'text-warning' : 'text-ink-3'" :aria-label="task.bookmarked ? 'حذف نشان' : 'نشان کردن'" @click="toggleBookmark">
                            <Bookmark class="size-4" :fill="task.bookmarked ? 'currentColor' : 'none'" />
                        </button>
                        <Dropdown align="end" width="w-52">
                            <template #trigger="{ toggle }">
                                <button class="rounded-lg p-1.5 text-ink-3 hover:bg-surface-3" aria-label="گزینه‌ها" @click="toggle"><MoreHorizontal class="size-4" /></button>
                            </template>
                            <template #default="{ close }">
                                <MenuItem @click="toggleWatch(); close()">
                                    <component :is="task.watching ? EyeOff : Eye" class="size-4" /> {{ task.watching ? 'لغو پیگیری' : 'پیگیری این وظیفه' }}
                                </MenuItem>
                                <MenuItem @click="copyLink(close)"><Copy class="size-4" /> کپی پیوند</MenuItem>
                                <MenuItem v-if="can.delete" danger @click="destroy(close)"><Trash2 class="size-4" /> حذف وظیفه</MenuItem>
                            </template>
                        </Dropdown>
                        <button class="rounded-lg p-1.5 text-ink-3 hover:bg-surface-3" aria-label="بستن" @click="closeTask"><X class="size-5" /></button>
                    </div>
                </div>

                <!-- Title -->
                <div class="flex items-start gap-3">
                    <Checkbox :model-value="!!task.completed_at" round class="mt-1.5" :disabled="!can.work" label="انجام شد" @update:model-value="toggleComplete" />
                    <textarea
                        v-if="editingTitle"
                        v-model="draft.title"
                        autofocus
                        rows="1"
                        maxlength="200"
                        class="field flex-1 resize-none text-lg font-bold"
                        @blur="saveTitle"
                        @keydown.enter.prevent="saveTitle"
                    />
                    <h2
                        v-else
                        class="flex-1 rounded-lg text-xl font-extrabold leading-8"
                        :class="[task.completed_at && 'text-ink-3 line-through', can.update && 'cursor-text hover:bg-surface-2']"
                        @click="can.update && (editingTitle = true)"
                    >
                        {{ task.title }}
                    </h2>
                </div>

                <div v-if="task.completed_at" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-success-soft px-3 py-1.5 text-xs font-semibold text-success">
                    <CircleCheck class="size-4" /> انجام شده در {{ jDateTime(task.completed_at) }}
                </div>

                <!-- Description -->
                <div class="mt-5">
                    <div v-if="editingDesc">
                        <textarea v-model="draft.description" autofocus rows="5" maxlength="10000" class="field resize-y leading-7" placeholder="توضیحات وظیفه..." />
                        <div class="mt-2 flex gap-2">
                            <Button size="sm" @click="saveDescription">ذخیره</Button>
                            <Button size="sm" variant="ghost" @click="editingDesc = false; draft.description = task.description ?? ''">انصراف</Button>
                        </div>
                    </div>
                    <div
                        v-else
                        class="group rounded-xl border border-transparent p-3 text-sm leading-7 transition-colors"
                        :class="can.update && 'cursor-text hover:border-line hover:bg-surface-2'"
                        @click="can.update && (editingDesc = true)"
                    >
                        <p v-if="task.description" class="whitespace-pre-wrap text-ink-2">{{ task.description }}</p>
                        <p v-else class="flex items-center gap-2 text-ink-3"><Pencil class="size-4" /> افزودن توضیحات...</p>
                    </div>
                </div>

                <!-- Checklist -->
                <div class="mt-6">
                    <div class="mb-3 flex items-center gap-3">
                        <h3 class="flex items-center gap-2 text-sm font-bold"><ListChecks class="size-4 text-accent" /> چک‌لیست</h3>
                        <span v-if="task.checklist.length" class="num text-xs text-ink-3">{{ faNum(checklistDone) }} از {{ faNum(task.checklist.length) }}</span>
                        <div v-if="task.checklist.length" class="flex flex-1 items-center gap-2">
                            <ProgressBar :value="task.progress" />
                            <span class="num text-xs font-bold text-ink-2">{{ faNum(task.progress) }}٪</span>
                        </div>
                    </div>
                    <ul class="space-y-1">
                        <li v-for="item in task.checklist" :key="item.id" class="group flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-surface-2">
                            <Checkbox :model-value="item.is_done" size="sm" :disabled="!can.work" :label="item.body" @update:model-value="toggleItem(item)" />
                            <span class="flex-1 text-sm" :class="item.is_done && 'text-ink-3 line-through'">{{ item.body }}</span>
                            <button v-if="can.update" class="text-ink-3 opacity-0 transition-opacity hover:text-danger group-hover:opacity-100" aria-label="حذف مورد" @click="removeItem(item)"><Trash2 class="size-4" /></button>
                        </li>
                    </ul>
                    <div v-if="can.update" class="mt-2 flex gap-2">
                        <input v-model="newItem" maxlength="300" class="field h-9 text-sm" placeholder="افزودن مورد جدید و Enter" @keydown.enter.prevent="addItem" />
                        <Button variant="secondary" size="icon" aria-label="افزودن" @click="addItem"><Plus class="size-4" /></Button>
                    </div>
                </div>

                <!-- Attachments -->
                <div class="mt-6">
                    <div class="mb-3 flex items-center gap-2">
                        <h3 class="flex items-center gap-2 text-sm font-bold"><Paperclip class="size-4 text-accent" /> پیوست‌ها</h3>
                        <button v-if="can.update" type="button" class="ms-auto flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-accent-ink hover:bg-accent-soft" @click="uploader.open()">
                            <Plus class="size-3.5" /> افزودن فایل
                        </button>
                    </div>
                    <FileUpload ref="uploader" v-model="pendingFiles" compact />
                    <AttachmentList :items="task.attachments" />
                    <p v-if="!task.attachments.length" class="text-xs text-ink-3">فایلی پیوست نشده است.</p>
                </div>

                <!-- Tabs: reports / activity -->
                <div class="mt-8 border-b border-line">
                    <div class="flex gap-1">
                        <button v-for="t in [{ k: 'reports', l: 'گزارش‌ها', i: MessageSquare, c: task.comments.length }, { k: 'time', l: 'ساعت کار', i: Timer, c: task.time.entries.length }, { k: 'activity', l: 'تاریخچه', i: History }]" :key="t.k" class="relative flex items-center gap-2 px-3 pb-3 text-sm font-semibold" :class="tab === t.k ? 'text-accent-ink' : 'text-ink-3 hover:text-ink'" @click="tab = t.k">
                            <component :is="t.i" class="size-4" /> {{ t.l }} <span v-if="t.c" class="num text-xs">({{ faNum(t.c) }})</span>
                            <span v-if="tab === t.k" class="absolute inset-x-2 bottom-0 h-0.5 rounded-full gradient-accent" />
                        </button>
                    </div>
                </div>

                <div v-if="tab === 'reports'" class="mt-5 space-y-5">
                    <form v-if="can.comment" class="rounded-2xl border border-line bg-surface-2 p-3" @submit.prevent="sendReport">
                        <textarea v-model="report.body" rows="3" maxlength="5000" class="w-full resize-y bg-transparent p-1 text-sm leading-7 outline-none placeholder:text-ink-3" placeholder="گزارش یا نظر خود را بنویسید..." />
                        <div v-if="mentionNames.length" class="mb-2 flex flex-wrap gap-1">
                            <span v-for="m in mentionNames" :key="m.id" class="chip bg-accent-soft text-accent-ink">@{{ m.name }}</span>
                        </div>
                        <FileUpload ref="reportUploader" v-model="report.attachments" compact />
                        <div class="flex flex-wrap items-center gap-2 border-t border-line pt-2.5">
                            <div class="w-44"><MemberPicker v-model="report.mentions" compact up placeholder="منشن همکار" /></div>
                            <button type="button" class="flex h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs text-ink-3 hover:bg-surface-3 hover:text-ink" @click="reportUploader.open()"><Paperclip class="size-4" /> پیوست</button>
                            <label class="flex h-9 items-center gap-1.5 rounded-lg px-2 text-xs text-ink-3">
                                پیشرفت
                                <input v-model.number="report.progress" type="number" min="0" max="100" class="field h-8 w-16 px-2 py-0 text-center text-xs" placeholder="٪" />
                            </label>
                            <Button type="submit" size="sm" class="ms-auto" :loading="sending" :disabled="!report.body.trim()"><Send class="size-3.5" /> ثبت گزارش</Button>
                        </div>
                    </form>

                    <p v-if="!task.comments.length" class="py-6 text-center text-sm text-ink-3">هنوز گزارشی ثبت نشده است.</p>

                    <article v-for="c in task.comments" :key="c.id" class="flex gap-3">
                        <Avatar :user="c.user" size="sm" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 text-xs">
                                <span class="font-bold text-ink">{{ c.user.name }}</span>
                                <span class="text-ink-3" :title="jDateTime(c.created_at)">{{ relative(c.created_at) }}</span>
                                <span v-if="c.edited" class="text-ink-3">(ویرایش‌شده)</span>
                                <span v-if="c.progress !== null" class="chip bg-success-soft text-success">پیشرفت {{ faNum(c.progress) }}٪</span>
                                <button v-if="c.can_delete" class="ms-auto text-ink-3 hover:text-danger" aria-label="حذف گزارش" @click="deleteComment(c)"><Trash2 class="size-3.5" /></button>
                            </div>
                            <div class="mt-1.5 rounded-2xl rounded-ss-sm bg-surface-2 px-4 py-3 text-sm leading-7">
                                <p class="whitespace-pre-wrap break-words">{{ c.body }}</p>
                                <AttachmentList v-if="c.attachments.length" :items="c.attachments" class="mt-3" />
                            </div>
                        </div>
                    </article>
                </div>

                <TaskTimeLog v-else-if="tab === 'time'" :task="task" :can-work="can.work" @changed="load(); changed()" />

                <ol v-else class="relative mt-5 space-y-4 border-s border-line ps-5">
                    <li v-for="a in task.activities" :key="a.id" class="relative text-sm">
                        <span class="absolute -start-[1.6rem] top-1.5 size-2.5 rounded-full border-2 border-surface bg-accent" />
                        <span class="font-semibold">{{ a.user ?? 'سیستم' }}</span>
                        <span class="text-ink-2"> {{ activityText(a) }}</span>
                        <span class="block text-xs text-ink-3">{{ jDateTime(a.created_at) }}</span>
                    </li>
                </ol>
            </section>

            <!-- Side panel -->
            <aside class="space-y-5 border-t border-line bg-surface-2/60 p-5 lg:rounded-e-2xl lg:border-s lg:border-t-0">
                <TaskTimer :key="task.id + (task.time.running ?? '')" :task="task" :can-work="can.work" @changed="load(); changed()" />

                <div>
                    <p class="label flex items-center gap-1.5"><Timer class="size-3.5" /> تخمین زمان (ساعت)</p>
                    <input
                        type="number"
                        min="0"
                        step="0.5"
                        dir="ltr"
                        class="field h-9 py-1 text-end text-xs"
                        :value="task.estimate_minutes ? task.estimate_minutes / 60 : ''"
                        :disabled="!can.update"
                        placeholder="—"
                        @change="patch({ estimate_minutes: $event.target.value ? Math.round(Number($event.target.value) * 60) : null })"
                    />
                </div>

                <div>
                    <p class="label flex items-center gap-1.5"><Users class="size-3.5" /> مسئولان</p>
                    <PeoplePicker
                        v-if="can.update"
                        :model-value="assigneeEntries"
                        compact
                        placeholder="انتخاب یا نوشتن نام همکار"
                        @update:model-value="patch(assigneePayload($event))"
                    />
                    <div v-else class="flex flex-wrap gap-2">
                        <span v-for="a in task.assignees" :key="a.id" class="flex items-center gap-1.5 text-sm"><Avatar :user="a" size="xs" /> {{ a.name }}</span>
                        <span v-for="p in task.people ?? []" :key="p.id" class="flex items-center gap-1.5 text-sm text-ink-2" :title="`${p.name} (بدون اکانت)`">
                            <span class="flex size-6 items-center justify-center rounded-full border border-dashed border-line-strong bg-surface-2 text-[0.55rem] font-bold text-ink-2">{{ p.initials }}</span>
                            {{ p.name }}
                        </span>
                    </div>
                </div>

                <div>
                    <p class="label flex items-center gap-1.5"><CalendarClock class="size-3.5" /> مهلت</p>
                    <DatePicker v-if="can.update" :model-value="task.due_at" time compact placeholder="بدون مهلت" @update:model-value="patch({ due_at: $event })" />
                    <p v-else class="text-sm">{{ task.due_at ? jDateTime(task.due_at) : '—' }}</p>
                </div>

                <div>
                    <p class="label flex items-center gap-1.5"><Clock class="size-3.5" /> شروع</p>
                    <DatePicker v-if="can.update" :model-value="task.starts_at" compact placeholder="بدون تاریخ" @update:model-value="patch({ starts_at: $event })" />
                    <p v-else class="text-sm">{{ task.starts_at ? jDateTime(task.starts_at) : '—' }}</p>
                </div>

                <div>
                    <p class="label flex items-center gap-1.5"><Flag class="size-3.5" /> اولویت</p>
                    <div class="grid grid-cols-4 gap-1 rounded-xl border border-line-strong bg-surface p-1">
                        <button
                            v-for="p in PRIORITIES"
                            :key="p.value"
                            type="button"
                            :disabled="!can.update"
                            class="rounded-lg py-1 text-[0.7rem] font-semibold transition-colors"
                            :class="task.priority === p.value ? `tone-chip tone-${p.tone}` : 'text-ink-3 hover:bg-surface-3'"
                            @click="patch({ priority: p.value })"
                        >
                            {{ p.label }}
                        </button>
                    </div>
                </div>

                <div v-if="task.columns.length">
                    <p class="label flex items-center gap-1.5"><FolderKanban class="size-3.5" /> وضعیت در برد</p>
                    <select
                        class="field h-9 py-1 text-xs"
                        :value="task.column_id"
                        :disabled="!can.update"
                        @change="http.post(url('tasks.move'), { column_id: $event.target.value, position: Date.now() / 1000 }).then(() => { load(); changed(); })"
                    >
                        <option v-for="c in task.columns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>

                <div>
                    <p class="label flex items-center gap-1.5"><Layers class="size-3.5" /> دسته‌بندی</p>
                    <select class="field h-9 py-1 text-xs" :value="task.category?.id ?? ''" :disabled="!can.update" @change="patch({ category_id: $event.target.value || null })">
                        <option value="">بدون دسته‌بندی</option>
                        <option v-for="c in options.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>

                <div>
                    <p class="label flex items-center gap-1.5"><Tag class="size-3.5" /> برچسب‌ها</p>
                    <LabelPicker v-if="can.update" :model-value="task.labels.map((l) => l.id)" :labels="options.labels" compact @update:model-value="patch({ labels: $event })" />
                    <div v-else class="flex flex-wrap gap-1">
                        <span v-for="l in task.labels" :key="l.id" class="chip tone-chip" :class="`tone-${l.color}`">{{ l.name }}</span>
                    </div>
                </div>

                <div>
                    <p class="label flex items-center gap-1.5"><Eye class="size-3.5" /> پیگیری‌کنندگان</p>
                    <MemberPicker v-if="can.update" :model-value="task.watchers.map((a) => a.id)" compact placeholder="افزودن ناظر" @update:model-value="patch({ watchers: $event })" />
                    <p v-else class="text-sm text-ink-2">{{ task.watchers.map((w) => w.name).join('، ') || '—' }}</p>
                </div>
            </aside>
        </div>
    </Modal>
</template>
