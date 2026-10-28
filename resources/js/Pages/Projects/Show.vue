<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import { useDebounceFn } from '@vueuse/core';
import {
    Archive, ArrowRight, Clock, Columns3, Filter, Globe, LayoutList, Lock, MoreHorizontal, Pencil, Plus, Search, Settings2, Trash2, Users, X,
} from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Field from '@/Components/ui/Field.vue';
import Dropdown from '@/Components/ui/Dropdown.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import ColorSwatches from '@/Components/ui/ColorSwatches.vue';
import AvatarStack from '@/Components/ui/AvatarStack.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import DatePicker from '@/Components/ui/DatePicker.vue';
import TaskCard from '@/Components/tasks/TaskCard.vue';
import TaskRow from '@/Components/tasks/TaskRow.vue';
import ProjectTimeReport from '@/Components/ProjectTimeReport.vue';
import { faNum, hours } from '@/lib/format';
import { openCreateTask, taskModal } from '@/composables/useTaskModal';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({ project: Object, tasks: Array, labels: Array, categories: Array, filters: Object, colors: Array, can: Object });

const page = usePage();
const ws = page.props.workspace;
const view = ref('board');
const q = ref(props.filters.q ?? '');

// Board state: tasks grouped by column, kept in sync with props.
const board = ref({});
const build = () => {
    const map = Object.fromEntries(props.project.columns.map((c) => [c.id, []]));
    props.tasks.forEach((t) => (map[t.column_id] ?? map[props.project.columns[0]?.id])?.push(t));
    board.value = map;
};
watch(() => [props.tasks, props.project.columns], build, { immediate: true });

const reload = () => router.reload({ only: ['tasks', 'project'] });
watch(() => taskModal.version, reload);

// Live updates from teammates
let channel = null;
onMounted(() => {
    if (window.Echo) channel = window.Echo.private(`projects.${props.project.id}`).listen('.board.changed', useDebounceFn(reload, 400));
});
onBeforeUnmount(() => channel && window.Echo?.leave(`projects.${props.project.id}`));

// Filters ------------------------------------------------------------
const applyFilters = (extra = {}) => router.get(route('projects.show', [ws.id, props.project.id]), { ...props.filters, q: q.value || undefined, ...extra }, { preserveState: true, preserveScroll: true, replace: true });
watch(q, useDebounceFn(() => applyFilters(), 300));

// Columns ------------------------------------------------------------
const columnForm = useForm({ id: null, name: '', color: 'slate', is_done: false, wip_limit: null });
const columnModal = ref(false);
const editColumn = (c = null) => {
    columnForm.clearErrors();
    Object.assign(columnForm, c ? { id: c.id, name: c.name, color: c.color, is_done: c.is_done, wip_limit: c.wip_limit } : { id: null, name: '', color: 'slate', is_done: false, wip_limit: null });
    columnModal.value = true;
};
const saveColumn = () => {
    const opts = { preserveScroll: true, onSuccess: () => (columnModal.value = false) };
    columnForm.id ? columnForm.put(route('columns.update', [ws.id, props.project.id, columnForm.id]), opts) : columnForm.post(route('columns.store', [ws.id, props.project.id]), opts);
};
const deleteColumn = async (c) => {
    if (!(await confirmDialog({ title: `حذف ستون «${c.name}»`, text: 'کارهای این ستون به ستون اول منتقل می‌شوند.', confirmText: 'حذف ستون', danger: true }))) return;
    router.delete(route('columns.destroy', [ws.id, props.project.id, c.id]), { preserveScroll: true });
};
const columns = ref([]);
watch(() => props.project.columns, (c) => (columns.value = [...c]), { immediate: true });
const reorderColumns = () => router.put(route('columns.reorder', [ws.id, props.project.id]), { ids: columns.value.map((c) => c.id) }, { preserveScroll: true });

// Settings -----------------------------------------------------------
const settings = ref(false);
const settingsForm = useForm({ name: props.project.name, description: props.project.description, color: props.project.color, visibility: props.project.visibility, starts_on: props.project.starts_on, due_on: props.project.due_on });
const saveSettings = () => settingsForm.put(route('projects.update', [ws.id, props.project.id]), { preserveScroll: true, onSuccess: () => (settings.value = false) });

const membersModal = ref(false);
const memberRoles = ref({});
const openMembers = () => {
    memberRoles.value = Object.fromEntries(props.project.members.map((m) => [m.id, m.role]));
    membersModal.value = true;
};
const addMembers = (ids) => ids.forEach((id) => (memberRoles.value[id] ??= 'member'));
const saveMembers = () =>
    router.put(route('projects.members', [ws.id, props.project.id]), { members: Object.entries(memberRoles.value).map(([id, role]) => ({ id: Number(id), role })) }, { preserveScroll: true, onSuccess: () => (membersModal.value = false) });
const removeMember = (id) => {
    const next = { ...memberRoles.value };
    delete next[id];
    memberRoles.value = next;
};
const memberById = (id) => ws.members.find((m) => m.id === Number(id));

const archive = () => router.post(route('projects.archive', [ws.id, props.project.id]), {}, { preserveScroll: true });
const destroy = async () => {
    if (!(await confirmDialog({ title: 'حذف پروژه', text: `پروژه «${props.project.name}» و همه وظایفش برای همیشه حذف می‌شود.`, confirmText: 'حذف پروژه', danger: true }))) return;
    router.delete(route('projects.destroy', [ws.id, props.project.id]), { data: { confirm_name: props.project.name } });
};

const total = computed(() => props.tasks.length);
const done = computed(() => props.tasks.filter((t) => t.completed_at).length);
const percent = computed(() => (total.value ? Math.round((done.value / total.value) * 100) : 0));
const roleLabel = { manager: 'مدیر پروژه', member: 'عضو', viewer: 'ناظر' };
</script>

<template>
    <Head :title="project.name" />

    <!-- Header -->
    <div class="flex flex-wrap items-center gap-4" :class="`tone-${project.color}`">
        <Link :href="route('projects.index', ws.id)" class="rounded-xl p-2 text-ink-3 hover:bg-surface-3" aria-label="بازگشت"><ArrowRight class="size-5" /></Link>
        <span class="flex size-12 items-center justify-center rounded-2xl tone-chip text-xl font-black shadow-soft">{{ project.name.slice(0, 1) }}</span>
        <div class="min-w-0 flex-1">
            <h1 class="flex items-center gap-2 truncate text-2xl font-black tracking-tight">
                {{ project.name }}
                <span v-if="project.archived" class="chip bg-warning-soft text-warning">بایگانی</span>
            </h1>
            <p class="mt-0.5 flex items-center gap-3 text-sm text-ink-3">
                <span class="flex items-center gap-1"><component :is="project.visibility === 'workspace' ? Globe : Lock" class="size-3.5" /> {{ project.visibility === 'workspace' ? 'عمومی' : 'خصوصی' }}</span>
                <span class="num">{{ faNum(done) }}/{{ faNum(total) }} انجام شده · {{ faNum(percent) }}٪</span>
                <button class="flex items-center gap-1 hover:text-accent-ink" @click="view = 'time'"><Clock class="size-3.5" /> {{ hours(project.tracked_seconds) }} ساعت کار</button>
            </p>
        </div>
        <button class="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2 hover:bg-surface-2" @click="can.manage && openMembers()">
            <AvatarStack :users="project.members" :max="5" size="sm" />
            <Users v-if="can.manage" class="size-4 text-ink-3" />
        </button>
        <Button v-if="can.contribute" @click="openCreateTask({ project_id: project.id })"><Plus class="size-4" /> وظیفه جدید</Button>
        <Dropdown v-if="can.manage" align="end">
            <template #trigger="{ toggle }"><Button variant="secondary" size="icon" aria-label="تنظیمات پروژه" @click="toggle"><MoreHorizontal class="size-4" /></Button></template>
            <template #default="{ close }">
                <MenuItem @click="settings = true; close()"><Settings2 class="size-4" /> تنظیمات پروژه</MenuItem>
                <MenuItem @click="openMembers(); close()"><Users class="size-4" /> اعضای پروژه</MenuItem>
                <MenuItem @click="editColumn(); close()"><Columns3 class="size-4" /> ستون جدید</MenuItem>
                <MenuItem @click="archive(); close()"><Archive class="size-4" /> {{ project.archived ? 'خروج از بایگانی' : 'بایگانی پروژه' }}</MenuItem>
                <MenuItem v-if="can.delete" danger @click="destroy(); close()"><Trash2 class="size-4" /> حذف پروژه</MenuItem>
            </template>
        </Dropdown>
    </div>

    <!-- Toolbar -->
    <div class="mt-6 flex flex-wrap items-center gap-2">
        <div class="flex rounded-xl border border-line bg-surface p-1">
            <button v-for="v in [{ k: 'board', l: 'برد', i: Columns3 }, { k: 'list', l: 'لیست', i: LayoutList }, { k: 'time', l: 'ساعت کار', i: Clock }]" :key="v.k" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors" :class="view === v.k ? 'bg-accent-soft text-accent-ink' : 'text-ink-3 hover:text-ink'" @click="view = v.k">
                <component :is="v.i" class="size-4" /> {{ v.l }}
            </button>
        </div>
        <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
            <input v-model="q" class="field h-10 w-52 ps-9" placeholder="جستجو در وظایف..." />
        </div>
        <select class="field h-10 w-auto" :value="filters.assignee ?? ''" @change="applyFilters({ assignee: $event.target.value || undefined })">
            <option value="">همه افراد</option>
            <option v-for="m in project.members" :key="m.id" :value="m.id">{{ m.name }}</option>
        </select>
        <select v-if="labels.length" class="field h-10 w-auto" :value="filters.label ?? ''" @change="applyFilters({ label: $event.target.value || undefined })">
            <option value="">همه برچسب‌ها</option>
            <option v-for="l in labels" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
        <button v-if="filters.assignee || filters.label || filters.q" class="flex items-center gap-1 text-xs font-semibold text-danger" @click="q = ''; applyFilters({ assignee: undefined, label: undefined, q: undefined })"><X class="size-3.5" /> حذف فیلترها</button>
    </div>

    <!-- Board -->
    <div v-if="view === 'board'" class="-mx-4 mt-5 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <draggable v-model="columns" item-key="id" class="flex items-start gap-4" handle=".col-handle" :disabled="!can.manage" :animation="200" @end="reorderColumns">
            <template #item="{ element: column }">
                <section class="flex max-h-[calc(100dvh-16rem)] w-[19rem] shrink-0 flex-col rounded-2xl border border-line bg-bg-subtle" :class="`tone-${column.color}`">
                    <header class="col-handle flex items-center gap-2 px-3.5 py-3" :class="can.manage && 'cursor-grab active:cursor-grabbing'">
                        <span class="size-2.5 rounded-full tone-dot" />
                        <h3 class="truncate text-sm font-bold">{{ column.name }}</h3>
                        <span class="chip bg-surface-3 text-ink-2 num">{{ faNum(board[column.id]?.length ?? 0) }}<template v-if="column.wip_limit">/{{ faNum(column.wip_limit) }}</template></span>
                        <div class="ms-auto flex items-center">
                            <button v-if="can.contribute" class="rounded-lg p-1 text-ink-3 hover:bg-surface-3 hover:text-ink" aria-label="افزودن وظیفه به این ستون" @click="openCreateTask({ project_id: project.id, column_id: column.id })"><Plus class="size-4" /></button>
                            <Dropdown v-if="can.manage" align="end" width="w-44">
                                <template #trigger="{ toggle }"><button class="rounded-lg p-1 text-ink-3 hover:bg-surface-3 hover:text-ink" aria-label="گزینه‌های ستون" @click="toggle"><MoreHorizontal class="size-4" /></button></template>
                                <template #default="{ close }">
                                    <MenuItem @click="editColumn(column); close()"><Pencil class="size-4" /> ویرایش ستون</MenuItem>
                                    <MenuItem danger @click="deleteColumn(column); close()"><Trash2 class="size-4" /> حذف ستون</MenuItem>
                                </template>
                            </Dropdown>
                        </div>
                    </header>
                    <!-- Tasks are not draggable on the board — their column is changed from the task window. -->
                    <div class="flex min-h-24 flex-1 flex-col gap-2.5 overflow-y-auto px-2.5 pb-3">
                        <TaskCard v-for="element in board[column.id]" :key="element.id" :task="element" />
                        <p v-if="!board[column.id]?.length" class="rounded-xl border-2 border-dashed border-line py-6 text-center text-xs text-ink-3">کاری در این ستون نیست</p>
                    </div>
                </section>
            </template>
            <template #footer>
                <button v-if="can.manage" class="flex h-14 w-[19rem] shrink-0 items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-line-strong text-sm font-semibold text-ink-3 hover:border-accent hover:text-accent-ink" @click="editColumn()">
                    <Plus class="size-4" /> افزودن ستون
                </button>
            </template>
        </draggable>
    </div>

    <ProjectTimeReport v-else-if="view === 'time'" :project-id="project.id" />

    <!-- List -->
    <div v-else class="mt-5 space-y-5">
        <section v-for="column in project.columns" :key="column.id" class="card overflow-hidden" :class="`tone-${column.color}`">
            <header class="flex items-center gap-2 border-b border-line px-5 py-3">
                <span class="size-2.5 rounded-full tone-dot" />
                <h3 class="text-sm font-bold">{{ column.name }}</h3>
                <span class="chip bg-surface-3 text-ink-2">{{ faNum(board[column.id]?.length ?? 0) }}</span>
            </header>
            <TaskRow v-for="t in board[column.id]" :key="t.id" :task="t" :show-project="false" />
            <p v-if="!board[column.id]?.length" class="px-5 py-4 text-xs text-ink-3">خالی</p>
        </section>
    </div>

    <!-- Column modal -->
    <Modal :show="columnModal" :title="columnForm.id ? 'ویرایش ستون' : 'ستون جدید'" size="sm" @close="columnModal = false">
        <form class="space-y-4 p-5" @submit.prevent="saveColumn">
            <Field label="نام ستون" :error="columnForm.errors.name"><input v-model="columnForm.name" autofocus maxlength="60" class="field" /></Field>
            <Field label="رنگ"><ColorSwatches v-model="columnForm.color" :colors="colors" /></Field>
            <Field label="سقف کارهای هم‌زمان (WIP)" hint="خالی یعنی بدون محدودیت" :error="columnForm.errors.wip_limit"><input v-model.number="columnForm.wip_limit" type="number" min="1" max="999" class="field" /></Field>
            <label class="flex items-center gap-2 text-sm"><input v-model="columnForm.is_done" type="checkbox" class="size-4 accent-[var(--accent)]" /> کارهای این ستون «انجام‌شده» محسوب شوند</label>
        </form>
        <template #footer>
            <Button variant="ghost" @click="columnModal = false">انصراف</Button>
            <Button :loading="columnForm.processing" @click="saveColumn">ذخیره</Button>
        </template>
    </Modal>

    <!-- Settings modal -->
    <Modal :show="settings" title="تنظیمات پروژه" @close="settings = false">
        <form class="space-y-4 p-5" @submit.prevent="saveSettings">
            <Field label="نام پروژه" :error="settingsForm.errors.name"><input v-model="settingsForm.name" maxlength="120" class="field" /></Field>
            <Field label="توضیحات"><textarea v-model="settingsForm.description" rows="3" class="field" /></Field>
            <Field label="رنگ"><ColorSwatches v-model="settingsForm.color" :colors="colors" /></Field>
            <div class="grid gap-3 sm:grid-cols-2">
                <Field label="شروع"><DatePicker v-model="settingsForm.starts_on" /></Field>
                <Field label="پایان" :error="settingsForm.errors.due_on"><DatePicker v-model="settingsForm.due_on" /></Field>
            </div>
            <Field label="دسترسی">
                <select v-model="settingsForm.visibility" class="field">
                    <option value="members">فقط اعضای پروژه</option>
                    <option value="workspace">همه اعضای میزکار</option>
                </select>
            </Field>
        </form>
        <template #footer>
            <Button variant="ghost" @click="settings = false">انصراف</Button>
            <Button :loading="settingsForm.processing" @click="saveSettings">ذخیره تغییرات</Button>
        </template>
    </Modal>

    <!-- Members modal -->
    <Modal :show="membersModal" title="اعضای پروژه" @close="membersModal = false">
        <div class="space-y-4 p-5">
            <MemberPicker :model-value="[]" placeholder="افزودن عضو به پروژه" :exclude="Object.keys(memberRoles).map(Number)" @update:model-value="addMembers" />
            <ul class="divide-y divide-line">
                <li v-for="(role, id) in memberRoles" :key="id" class="flex items-center gap-3 py-2.5">
                    <Avatar :user="memberById(id)" size="sm" />
                    <span class="flex-1 text-sm font-semibold">{{ memberById(id)?.name }}</span>
                    <select v-model="memberRoles[id]" class="field h-9 w-36 py-1 text-xs">
                        <option v-for="(label, key) in roleLabel" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <button class="rounded-lg p-1.5 text-ink-3 hover:bg-danger-soft hover:text-danger" aria-label="حذف از پروژه" @click="removeMember(id)"><X class="size-4" /></button>
                </li>
            </ul>
        </div>
        <template #footer>
            <Button variant="ghost" @click="membersModal = false">انصراف</Button>
            <Button @click="saveMembers">ذخیره</Button>
        </template>
    </Modal>
</template>
