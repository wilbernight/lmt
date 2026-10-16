<script setup>
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Hand, Plus, Timer, Trash2 } from '@lucide/vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Button from '@/Components/ui/Button.vue';
import DatePicker from '@/Components/ui/DatePicker.vue';
import http from '@/lib/http';
import { formatDuration, jDate, jTime } from '@/lib/format';
import { confirmDialog } from '@/composables/useConfirm';

/** List of time entries on a task, plus a manual "I worked N minutes" form. */
const props = defineProps({ task: { type: Object, required: true }, canWork: Boolean });
const emit = defineEmits(['changed']);

const page = usePage();
const adding = ref(false);
const form = ref({ started_at: null, hours: 0, minutes: 30, note: '' });
const saving = ref(false);

const openForm = () => {
    const d = new Date();
    d.setMinutes(0, 0, 0);
    d.setHours(d.getHours() - 1);
    form.value = { started_at: d.toISOString(), hours: 1, minutes: 0, note: '' };
    adding.value = true;
};

async function save() {
    const minutes = Number(form.value.hours || 0) * 60 + Number(form.value.minutes || 0);
    if (!minutes || !form.value.started_at) return;
    saving.value = true;
    try {
        await http.post(route('time.store', [page.props.workspace.id, props.task.id]), { started_at: form.value.started_at, minutes, note: form.value.note || null });
        adding.value = false;
        emit('changed');
    } finally {
        saving.value = false;
    }
}

async function remove(e) {
    if (!(await confirmDialog({ title: 'حذف زمان ثبت‌شده', text: `${formatDuration(e.seconds)} حذف شود؟`, confirmText: 'حذف', danger: true }))) return;
    await http.delete(route('time.destroy', [page.props.workspace.id, props.task.id, e.id]));
    emit('changed');
}
</script>

<template>
    <div class="mt-5 space-y-3">
        <div v-if="canWork" class="flex justify-end">
            <Button v-if="!adding" size="sm" variant="secondary" @click="openForm"><Plus class="size-4" /> ثبت دستی زمان</Button>
        </div>

        <form v-if="adding" class="grid gap-3 rounded-2xl border border-line bg-surface-2 p-4 sm:grid-cols-[1.4fr_auto_1fr]" @submit.prevent="save">
            <div><label class="label">زمان شروع</label><DatePicker v-model="form.started_at" time compact /></div>
            <div>
                <label class="label">مدت</label>
                <div class="flex items-center gap-1.5" dir="ltr">
                    <input v-model.number="form.hours" type="number" min="0" max="16" class="field h-9 w-16 px-2 text-center" aria-label="ساعت" />
                    <span class="text-ink-3">:</span>
                    <input v-model.number="form.minutes" type="number" min="0" max="59" step="5" class="field h-9 w-16 px-2 text-center" aria-label="دقیقه" />
                </div>
            </div>
            <div><label class="label">توضیح</label><input v-model="form.note" maxlength="300" class="field h-9" placeholder="روی چه کاری؟" /></div>
            <div class="flex justify-end gap-2 sm:col-span-3">
                <Button size="sm" variant="ghost" @click="adding = false">انصراف</Button>
                <Button size="sm" type="submit" :loading="saving">ثبت</Button>
            </div>
        </form>

        <p v-if="!task.time.entries.length" class="py-6 text-center text-sm text-ink-3">هنوز زمانی ثبت نشده است.</p>

        <ul class="divide-y divide-line">
            <li v-for="e in task.time.entries" :key="e.id" class="flex items-center gap-3 py-2.5 text-sm">
                <Avatar :user="e.user" size="xs" />
                <span class="font-semibold">{{ e.user.name }}</span>
                <component :is="e.source === 'manual' ? Hand : Timer" class="size-3.5 text-ink-3" :title="e.source === 'manual' ? 'ثبت دستی' : 'زمان‌سنج'" />
                <span class="text-xs text-ink-3">{{ jDate(e.started_at, { year: false }) }} · {{ jTime(e.started_at) }}<template v-if="e.ended_at"> تا {{ jTime(e.ended_at) }}</template></span>
                <span v-if="e.note" class="truncate text-xs text-ink-2">— {{ e.note }}</span>
                <span class="num ms-auto font-bold" :class="e.running && 'text-success'">{{ e.running ? 'در حال کار' : formatDuration(e.seconds) }}</span>
                <button v-if="e.can_delete && !e.running" class="text-ink-3 hover:text-danger" aria-label="حذف" @click="remove(e)"><Trash2 class="size-3.5" /></button>
            </li>
        </ul>
    </div>
</template>
