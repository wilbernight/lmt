<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Pencil, Search, Trash2, UserRound } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Field from '@/Components/ui/Field.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { faNum, jDate } from '@/lib/format';
import { confirmDialog } from '@/composables/useConfirm';

// Directory of colleagues without an account: referenced by free-typed name in
// tasks, letters and minutes. Rename or remove them from here.
const props = defineProps({ people: Object, filters: Object });

const q = ref(props.filters.q ?? '');
const apply = (extra = {}) => router.get(route('admin.people.index'), { ...props.filters, q: q.value || undefined, ...extra }, { preserveState: true, replace: true });
watch(q, useDebounceFn(() => apply(), 300));

const usage = (p) => {
    const parts = [];

    if (p.tasks_count) parts.push(`${faNum(p.tasks_count)} وظیفه`);
    if (p.letters_count) parts.push(`${faNum(p.letters_count)} نامه`);
    if (p.minutes_count) parts.push(`${faNum(p.minutes_count)} جلسه`);
    if (p.actions_count) parts.push(`${faNum(p.actions_count)} اقدام`);

    return parts;
};

const editing = ref(null);
const form = useForm({ name: '' });
const edit = (p) => {
    editing.value = p;
    form.name = p.name;
};

const rename = () => form.put(route('admin.people.update', editing.value.id), { preserveScroll: true, onSuccess: () => (editing.value = null) });

async function remove(p) {
    const uses = usage(p);

    if (!await confirmDialog({
        title: 'حذف شخص',
        text: `«${p.name}» برای همیشه حذف شود؟ ${uses.length ? `او از ${uses.join('، ')} حذف می‌شود و اقدامات جلسات سپرده به ایش هم حذف می‌شوند.` : ''}`,
        confirmText: 'حذف',
        danger: true,
    })) return;

    router.delete(route('admin.people.destroy', p.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="مدیریت اشخاص" />

    <PageHeader title="اشخاص بدون اکانت" :subtitle="`${faNum(people.total)} شخص در همه میزکارها`" :icon="UserRound" tone="rose">
        <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
            <input v-model="q" class="field h-10 w-64 ps-9" placeholder="نام شخص..." />
        </div>
    </PageHeader>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full min-w-[56rem] text-sm">
            <thead class="border-b border-line bg-surface-2/60 text-xs text-ink-3">
                <tr>
                    <th class="px-5 py-3 text-start font-semibold">شخص</th>
                    <th class="px-3 py-3 text-start font-semibold">میزکار</th>
                    <th class="px-3 py-3 text-start font-semibold">استفاده</th>
                    <th class="px-3 py-3 text-start font-semibold">ثبت‌کننده</th>
                    <th class="px-3 py-3 text-start font-semibold">تاریخ ثبت</th>
                    <th class="px-5 py-3 text-end font-semibold">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="p in people.data" :key="p.id" class="border-b border-line last:border-0 hover:bg-surface-2">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full border border-dashed border-line-strong bg-surface-2 text-xs font-bold text-ink-2">{{ p.initials }}</span>
                            <b class="min-w-0 truncate">{{ p.name }}</b>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <span class="flex items-center gap-1.5 text-xs text-ink-2">
                            <span class="size-2 rounded-full tone-dot" :class="`tone-${p.workspace.color}`" />
                            {{ p.workspace.name }}
                        </span>
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex flex-wrap gap-1">
                            <span v-for="u in usage(p)" :key="u" class="chip bg-surface-3 text-ink-2">{{ u }}</span>
                            <span v-if="!usage(p).length" class="text-xs text-ink-3">—</span>
                        </div>
                    </td>
                    <td class="px-3 py-3 text-xs text-ink-3">{{ p.creator ?? '—' }}</td>
                    <td class="px-3 py-3 text-xs text-ink-3">{{ jDate(p.created_at) }}</td>
                    <td class="px-5 py-3">
                        <div class="flex justify-end gap-1">
                            <Button variant="ghost" size="icon" aria-label="ویرایش نام" @click="edit(p)"><Pencil class="size-4" /></Button>
                            <Button variant="ghost" size="icon" aria-label="حذف" class="text-danger" @click="remove(p)"><Trash2 class="size-4" /></Button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-if="!people.data.length" :icon="UserRound" title="شخصی پیدا نشد" compact />
    </div>

    <div v-if="people.last_page > 1" class="mt-5 flex justify-center gap-1">
        <template v-for="link in people.links" :key="link.label">
            <Link v-if="link.url" :href="link.url" class="num min-w-9 rounded-lg px-3 py-1.5 text-center text-sm" :class="link.active ? 'bg-accent text-on-accent' : 'hover:bg-surface-3'">{{ faNum(link.label.replace(/&laquo;|&raquo;/g, '').trim()) }}</Link>
        </template>
    </div>

    <Modal :show="!!editing" title="ویرایش نام شخص" @close="editing = null">
        <form class="space-y-4 p-5" @submit.prevent="rename">
            <Field label="نام" :error="form.errors.name">
                <input v-model="form.name" autofocus maxlength="120" class="field" placeholder="مثلاً: مهدی رضایی" />
            </Field>
            <p v-if="editing" class="text-xs text-ink-3">میزکار: {{ editing.workspace.name }}</p>
        </form>
        <template #footer>
            <Button variant="ghost" @click="editing = null">انصراف</Button>
            <Button :loading="form.processing" :disabled="!form.name.trim()" @click="rename">ذخیره</Button>
        </template>
    </Modal>
</template>
