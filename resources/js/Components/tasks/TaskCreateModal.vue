<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { AlignRight, ChevronDown, FolderKanban, Plus, Trash2 } from '@lucide/vue';
import Modal from '@/Components/ui/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import DatePicker from '@/Components/ui/DatePicker.vue';
import MemberPicker from '@/Components/ui/MemberPicker.vue';
import LabelPicker from '@/Components/ui/LabelPicker.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import http from '@/lib/http';
import { PRIORITIES } from '@/lib/format';
import { taskChanged, taskModal } from '@/composables/useTaskModal';
import { toast } from '@/composables/useToast';

const page = usePage();
const ws = computed(() => page.props.workspace);

const options = ref({ projects: [], categories: [], labels: [] });
const saving = ref(false);
const more = ref(false);
const errors = ref({});
const uploader = ref(null);

const blank = () => ({
    title: '',
    description: '',
    project_id: null,
    column_id: null,
    category_id: null,
    priority: 1,
    due_at: null,
    starts_at: null,
    assignees: [page.props.auth.user.id],
    labels: [],
    checklist: [],
    attachments: [],
});

const form = ref(blank());
const newItem = ref('');

watch(
    () => taskModal.create,
    async (open) => {
        if (!open) return;
        form.value = { ...blank(), ...taskModal.defaults };
        errors.value = {};
        more.value = !!taskModal.defaults.project_id;
        uploader.value?.reset();

        // Pull picker options from the current page when present, otherwise ask the server once.
        const p = page.props;
        if (p.projects && p.categories && p.labels) {
            options.value = { projects: p.projects, categories: p.categories, labels: p.labels };
        } else if (!options.value.projects.length) {
            try {
                const { data } = await http.get(route('tasks.options', ws.value.id));
                options.value = data;
            } catch { /* pickers stay empty */ }
        }
    },
);

const addItem = () => {
    const v = newItem.value.trim();
    if (v) form.value.checklist.push(v);
    newItem.value = '';
};

async function submit() {
    if (!form.value.title.trim()) {
        errors.value = { title: 'عنوان وظیفه را بنویسید.' };
        return;
    }
    saving.value = true;
    errors.value = {};
    try {
        await http.post(route('tasks.store', ws.value.id), form.value);
        toast.success('وظیفه ساخته شد.');
        taskModal.create = false;
        taskChanged();
        router.reload({ only: ['tasks', 'myTasks', 'following', 'stats', 'counters'] });
    } catch (e) {
        errors.value = Object.fromEntries(Object.entries(e.response?.data?.errors ?? {}).map(([k, v]) => [k, v[0]]));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Modal :show="taskModal.create" size="lg" title="وظیفه جدید" @close="taskModal.create = false">
        <form class="space-y-5 p-5" @submit.prevent="submit">
            <div>
                <input
                    v-model="form.title"
                    autofocus
                    maxlength="200"
                    class="w-full border-0 bg-transparent text-xl font-bold outline-none placeholder:text-ink-3"
                    placeholder="چه کاری باید انجام شود؟"
                    :aria-invalid="!!errors.title"
                />
                <p v-if="errors.title" class="mt-1 text-xs text-danger">{{ errors.title }}</p>
            </div>

            <div class="relative">
                <AlignRight class="pointer-events-none absolute start-3 top-3 size-4 text-ink-3" />
                <textarea v-model="form.description" rows="3" maxlength="10000" class="field resize-y ps-9" placeholder="توضیحات (اختیاری)" />
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">مسئول انجام</label>
                    <MemberPicker v-model="form.assignees" />
                </div>
                <div>
                    <label class="label">مهلت انجام</label>
                    <DatePicker v-model="form.due_at" time placeholder="بدون مهلت" />
                </div>
                <div>
                    <label class="label">اولویت</label>
                    <div class="grid grid-cols-4 gap-1 rounded-xl border border-line-strong p-1">
                        <button
                            v-for="p in PRIORITIES"
                            :key="p.value"
                            type="button"
                            class="rounded-lg py-1.5 text-xs font-semibold transition-colors"
                            :class="form.priority === p.value ? `tone-chip tone-${p.tone}` : 'text-ink-3 hover:bg-surface-3'"
                            @click="form.priority = p.value"
                        >
                            {{ p.label }}
                        </button>
                    </div>
                </div>
                <div>
                    <label class="label">پروژه</label>
                    <div class="relative">
                        <FolderKanban class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
                        <select v-model="form.project_id" class="field appearance-none ps-9">
                            <option :value="null">بدون پروژه</option>
                            <option v-for="p in options.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="button" class="flex items-center gap-1.5 text-xs font-semibold text-accent-ink" @click="more = !more">
                <ChevronDown class="size-4 transition-transform" :class="more && 'rotate-180'" /> گزینه‌های بیشتر
            </button>

            <div v-if="more" class="animate-fade space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">دسته‌بندی</label>
                        <select v-model="form.category_id" class="field">
                            <option :value="null">بدون دسته‌بندی</option>
                            <option v-for="c in options.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">برچسب‌ها</label>
                        <LabelPicker v-model="form.labels" :labels="options.labels" />
                    </div>
                    <div>
                        <label class="label">تاریخ شروع</label>
                        <DatePicker v-model="form.starts_at" placeholder="بدون تاریخ شروع" />
                    </div>
                </div>

                <div>
                    <label class="label">چک‌لیست</label>
                    <ul v-if="form.checklist.length" class="mb-2 space-y-1">
                        <li v-for="(item, i) in form.checklist" :key="i" class="flex items-center gap-2 rounded-lg bg-surface-2 px-3 py-2 text-sm">
                            <span class="size-4 rounded border-2 border-line-strong" />
                            <span class="flex-1">{{ item }}</span>
                            <button type="button" class="text-ink-3 hover:text-danger" aria-label="حذف" @click="form.checklist.splice(i, 1)"><Trash2 class="size-4" /></button>
                        </li>
                    </ul>
                    <div class="flex gap-2">
                        <input v-model="newItem" maxlength="300" class="field" placeholder="افزودن مورد..." @keydown.enter.prevent="addItem" />
                        <Button variant="secondary" size="icon" aria-label="افزودن" @click="addItem"><Plus class="size-4" /></Button>
                    </div>
                </div>

                <FileUpload ref="uploader" v-model="form.attachments" />
            </div>
        </form>
        <template #footer>
            <span class="me-auto hidden text-xs text-ink-3 sm:inline">نکته: کلید <kbd class="rounded border border-line px-1">N</kbd> این پنجره را باز می‌کند.</span>
            <Button variant="ghost" @click="taskModal.create = false">انصراف</Button>
            <Button :loading="saving" @click="submit">ایجاد وظیفه</Button>
        </template>
    </Modal>
</template>
