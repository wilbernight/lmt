<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Building2, HardDrive, Lock, Search, Unlock } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Field from '@/Components/ui/Field.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { faNum, formatBytes, jDate } from '@/lib/format';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({ workspaces: Object, filters: Object });

const q = ref(props.filters.q ?? '');
const apply = (extra = {}) => router.get(route('admin.workspaces.index'), { ...props.filters, q: q.value || undefined, ...extra }, { preserveState: true, replace: true });
watch(q, useDebounceFn(() => apply(), 300));

const editing = ref(null);
const form = useForm({ storage_limit_mb: 0 });
const edit = (w) => {
    editing.value = w;
    form.storage_limit_mb = w.storage_limit_mb;
};
const save = () => form.put(route('admin.workspaces.update', editing.value.id), { preserveScroll: true, onSuccess: () => (editing.value = null) });

const lock = async (w) => {
    const ok = w.locked || (await confirmDialog({ title: `غیرفعال کردن «${w.name}»`, text: 'هیچ‌کدام از اعضا تا فعال‌سازی دوباره به این میزکار دسترسی نخواهند داشت. اطلاعات حذف نمی‌شود.', confirmText: 'غیرفعال کن', danger: true }));
    if (ok) router.post(route('admin.workspaces.lock', w.id), {}, { preserveScroll: true });
};

const percent = (w) => Math.min(100, Math.round((w.storage_used / w.storage_limit) * 100));
const statuses = [{ key: undefined, label: 'همه' }, { key: 'full', label: 'نزدیک به پر شدن' }, { key: 'locked', label: 'غیرفعال' }];
</script>

<template>
    <Head title="مدیریت میزکارها" />

    <PageHeader title="میزکارها" :subtitle="`${faNum(workspaces.total)} میزکار`" :icon="Building2" tone="rose">
        <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
            <input v-model="q" class="field h-10 w-64 ps-9" placeholder="نام میزکار یا ایمیل مالک..." />
        </div>
    </PageHeader>

    <div class="mt-5 flex flex-wrap gap-1.5">
        <button v-for="s in statuses" :key="s.label" class="rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors" :class="filters.status === s.key ? 'bg-accent-soft text-accent-ink' : 'text-ink-3 hover:bg-surface-3'" @click="apply({ status: s.key })">{{ s.label }}</button>
    </div>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full min-w-[56rem] text-sm">
            <thead class="border-b border-line bg-surface-2/60 text-xs text-ink-3">
                <tr>
                    <th class="px-5 py-3 text-start font-semibold">میزکار</th>
                    <th class="px-3 py-3 text-start font-semibold">مالک</th>
                    <th class="px-3 py-3 text-start font-semibold">اعضا / پروژه / وظیفه</th>
                    <th class="px-3 py-3 text-start font-semibold">فضا</th>
                    <th class="px-5 py-3 text-end font-semibold">اقدام</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="w in workspaces.data" :key="w.id" class="border-b border-line last:border-0">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <span class="flex size-9 items-center justify-center rounded-lg font-black tone-chip" :class="`tone-${w.color}`">{{ w.name.slice(0, 1) }}</span>
                            <div><p class="font-bold">{{ w.name }} <span v-if="w.locked" class="chip bg-danger-soft text-danger">غیرفعال</span></p><p class="text-xs text-ink-3">ساخته‌شده {{ jDate(w.created_at) }}</p></div>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <Link :href="route('admin.users.show', w.owner.id)" class="flex items-center gap-2 hover:text-accent-ink">
                            <Avatar :user="w.owner" size="xs" />
                            <span class="min-w-0"><span class="block truncate text-xs font-semibold">{{ w.owner.name }}</span><span class="block truncate text-[0.7rem] text-ink-3" dir="ltr">{{ w.owner.email }}</span></span>
                        </Link>
                    </td>
                    <td class="num px-3 py-3 text-xs">{{ faNum(w.members_count) }} / {{ faNum(w.projects_count) }} / {{ faNum(w.tasks_count) }}</td>
                    <td class="w-52 px-3 py-3 text-xs">
                        <p class="num mb-1 text-ink-2">{{ formatBytes(w.storage_used) }} / {{ formatBytes(w.storage_limit) }}</p>
                        <div class="h-1.5 overflow-hidden rounded-full bg-surface-3"><div class="h-full rounded-full" :class="percent(w) >= 90 ? 'bg-danger' : 'gradient-accent'" :style="{ width: `${percent(w)}%` }" /></div>
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex justify-end gap-1.5">
                            <Button size="sm" variant="secondary" @click="edit(w)"><HardDrive class="size-3.5" /> فضا</Button>
                            <Button size="sm" :variant="w.locked ? 'secondary' : 'danger-ghost'" @click="lock(w)"><component :is="w.locked ? Unlock : Lock" class="size-3.5" /> {{ w.locked ? 'فعال‌سازی' : 'غیرفعال' }}</Button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-if="!workspaces.data.length" :icon="Building2" title="میزکاری پیدا نشد" compact />
    </div>

    <div v-if="workspaces.last_page > 1" class="mt-5 flex justify-center gap-1">
        <template v-for="link in workspaces.links" :key="link.label">
            <Link v-if="link.url" :href="link.url" class="num min-w-9 rounded-lg px-3 py-1.5 text-center text-sm" :class="link.active ? 'gradient-accent text-on-accent' : 'hover:bg-surface-3'">{{ faNum(link.label.replace(/&laquo;|&raquo;/g, '').trim()) }}</Link>
        </template>
    </div>

    <Modal :show="!!editing" :title="`فضای «${editing?.name}»`" size="sm" @close="editing = null">
        <form class="p-5" @submit.prevent="save">
            <Field label="سقف فضا (مگابایت)" :hint="editing ? `مصرف فعلی: ${formatBytes(editing.storage_used)}` : ''" :error="form.errors.storage_limit_mb">
                <input v-model.number="form.storage_limit_mb" type="number" min="10" dir="ltr" class="field text-end" autofocus />
            </Field>
            <div class="mt-3 flex flex-wrap gap-1.5">
                <button v-for="mb in [512, 1024, 2048, 5120, 10240, 51200]" :key="mb" type="button" class="chip bg-surface-3 text-ink-2 hover:bg-accent-soft hover:text-accent-ink" @click="form.storage_limit_mb = mb">{{ formatBytes(mb * 1048576) }}</button>
            </div>
        </form>
        <template #footer>
            <Button variant="ghost" @click="editing = null">انصراف</Button>
            <Button :loading="form.processing" @click="save">ذخیره</Button>
        </template>
    </Modal>
</template>
