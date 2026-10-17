<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { PhoneOff, Pencil, Plus, Search, Trash2, Users } from '@lucide/vue';
import Modal from '@/Components/ui/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import ColorSwatches from '@/Components/ui/ColorSwatches.vue';
import { faNum } from '@/lib/format';
import { confirmDialog } from '@/composables/useConfirm';

/** Create, edit and delete SMS groups (site admin). */
const props = defineProps({
    show: Boolean,
    groups: { type: Array, required: true },
    users: { type: Array, required: true },
    colors: { type: Array, required: true },
});
const emit = defineEmits(['close']);

const editing = ref(null); // null = list, 'new' or a group
const q = ref('');
const form = useForm({ name: '', color: 'indigo', members: [] });

const open = (group = 'new') => {
    form.clearErrors();
    q.value = '';
    Object.assign(form, group === 'new' ? { name: '', color: 'indigo', members: [] } : { name: group.name, color: group.color, members: [...group.members] });
    editing.value = group;
};

const shown = computed(() => props.users.filter((u) => !q.value || u.name.includes(q.value.trim())));
const toggle = (id) => (form.members = form.members.includes(id) ? form.members.filter((x) => x !== id) : [...form.members, id]);

const save = () => {
    const done = { preserveScroll: true, onSuccess: () => (editing.value = null) };
    editing.value === 'new' ? form.post(route('admin.sms.groups.store'), done) : form.put(route('admin.sms.groups.update', editing.value.id), done);
};

const remove = async (group) => {
    if (await confirmDialog({ title: `حذف گروه «${group.name}»`, text: 'فقط گروه حذف می‌شود؛ کاربران و پیامک‌های قبلی باقی می‌مانند.', confirmText: 'حذف', danger: true })) {
        router.delete(route('admin.sms.groups.destroy', group.id), { preserveScroll: true });
    }
};

const close = () => {
    editing.value = null;
    emit('close');
};
</script>

<template>
    <Modal :show="show" :title="editing ? (editing === 'new' ? 'گروه جدید' : `ویرایش «${editing.name}»`) : 'گروه‌های همکاران'" size="md" @close="close">
        <!-- List -->
        <div v-if="!editing" class="p-5">
            <ul class="divide-y divide-line rounded-xl border border-line">
                <li v-for="g in groups" :key="g.id" class="flex items-center gap-3 px-4 py-3">
                    <span class="size-3 rounded-full tone-dot" :class="`tone-${g.color}`" />
                    <span class="flex-1">
                        <span class="block text-sm font-bold">{{ g.name }}</span>
                        <span class="text-xs text-ink-3">{{ faNum(g.members.length) }} نفر · {{ faNum(g.reachable) }} نفر با شماره تماس</span>
                    </span>
                    <Button size="icon-sm" variant="ghost" :aria-label="`ویرایش ${g.name}`" @click="open(g)"><Pencil class="size-4" /></Button>
                    <Button size="icon-sm" variant="danger-ghost" :aria-label="`حذف ${g.name}`" @click="remove(g)"><Trash2 class="size-4" /></Button>
                </li>
                <li v-if="!groups.length" class="px-4 py-8 text-center text-sm text-ink-3">هنوز گروهی ساخته نشده است.</li>
            </ul>
            <Button class="mt-4" @click="open()"><Plus class="size-4" /> گروه جدید</Button>
        </div>

        <!-- Form -->
        <form v-else class="space-y-4 p-5" @submit.prevent="save">
            <Field label="نام گروه" :error="form.errors.name"><input v-model="form.name" autofocus maxlength="60" class="field" placeholder="مثلاً: تیم فنی" /></Field>
            <Field label="رنگ"><ColorSwatches v-model="form.color" :colors="colors" /></Field>
            <div>
                <p class="label flex items-center justify-between">
                    <span class="flex items-center gap-1.5"><Users class="size-3.5" /> اعضا</span>
                    <span class="text-xs font-normal text-ink-3">{{ faNum(form.members.length) }} نفر انتخاب شده</span>
                </p>
                <div class="relative mb-2">
                    <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
                    <input v-model="q" class="field h-9 ps-9 text-sm" placeholder="جستجوی نام..." />
                </div>
                <div class="max-h-72 overflow-y-auto rounded-xl border border-line">
                    <label v-for="u in shown" :key="u.id" class="flex cursor-pointer items-center gap-3 border-b border-line px-3 py-2 last:border-0 hover:bg-surface-2">
                        <input type="checkbox" class="size-4 accent-[var(--accent)]" :checked="form.members.includes(u.id)" @change="toggle(u.id)" />
                        <Avatar :user="u" size="xs" />
                        <span class="flex-1 text-sm">{{ u.name }}</span>
                        <span v-if="!u.has_phone" class="flex items-center gap-1 text-[0.7rem] text-warning" title="شماره تماس در پروفایل ثبت نشده؛ پیامک دریافت نمی‌کند"><PhoneOff class="size-3.5" /> بدون شماره</span>
                    </label>
                </div>
            </div>
        </form>

        <template v-if="editing" #footer>
            <Button variant="ghost" @click="editing = null">بازگشت</Button>
            <Button :loading="form.processing" @click="save">ذخیره گروه</Button>
        </template>
    </Modal>
</template>
