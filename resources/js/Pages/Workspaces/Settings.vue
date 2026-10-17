<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Building2, Crown, Layers, Mail, Plus, Tag, Trash2, UserPlus, Users, X } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Modal from '@/Components/ui/Modal.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import ColorSwatches from '@/Components/ui/ColorSwatches.vue';
import { faNum, relative } from '@/lib/format';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({ colors: Array, roles: Array, invitations: Array, labels: Array, categories: Array, owner_id: Number });

const page = usePage();
// Reactive, so the member list reflects role changes as soon as the server answers.
const ws = computed(() => page.props.workspace);
const me = page.props.auth.user;
const isOwner = props.owner_id === me.id;

const general = useForm({ name: ws.value.name, color: ws.value.color });
const saveGeneral = () => general.put(route('workspace.update', ws.value.id), { preserveScroll: true });

const invite = useForm({ email: '', role: 'member' });
const sendInvite = () => invite.post(route('invitations.store', ws.value.id), { preserveScroll: true, onSuccess: () => invite.reset('email') });

const assignable = props.roles.filter((r) => r.value !== 'owner' && (isOwner || r.value !== 'admin'));

const changeRole = (m, role) => router.put(route('members.update', [ws.value.id, m.id]), { role, job_title: m.job_title }, { preserveScroll: true });
const setTitle = (m, job_title) => router.put(route('members.update', [ws.value.id, m.id]), { role: m.role, job_title }, { preserveScroll: true });
const removeMember = async (m) => {
    if (await confirmDialog({ title: 'حذف عضو', text: `${m.name} از میزکار حذف شود؟ دسترسی او به همه بخش‌ها قطع می‌شود.`, confirmText: 'حذف عضو', danger: true })) {
        router.delete(route('members.destroy', [ws.value.id, m.id]), { preserveScroll: true });
    }
};
const leave = async () => {
    if (await confirmDialog({ title: 'خروج از میزکار', text: 'دیگر به این میزکار دسترسی نخواهید داشت.', confirmText: 'خروج', danger: true })) router.delete(route('members.destroy', [ws.value.id, me.id]));
};

const tagColors = ['slate', 'rose', 'orange', 'amber', 'lime', 'emerald', 'teal', 'sky', 'indigo', 'violet', 'pink'];
const scopeLabels = { task: 'وظایف', letter: 'نامه‌ها', note: 'یادداشت‌ها', customer: 'مشتریان' };
const labelForm = useForm({ scope: 'task', name: '', color: 'indigo' });
const addLabel = () => labelForm.post(route('labels.store', ws.value.id), { preserveScroll: true, onSuccess: () => labelForm.reset('name') });
const categoryForm = useForm({ name: '', color: 'teal' });
const addCategory = () => categoryForm.post(route('categories.store', ws.value.id), { preserveScroll: true, onSuccess: () => categoryForm.reset('name') });

const deleteModal = ref(false);
const deleteForm = useForm({ password: '', confirm_name: '' });
const destroy = () => deleteForm.delete(route('workspace.destroy', ws.value.id));
</script>

<template>
    <Head title="تنظیمات میزکار" />

    <PageHeader title="تنظیمات میزکار" :subtitle="ws.name" :icon="Building2" :tone="ws.color" />

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="card p-6">
            <h2 class="font-bold">اطلاعات کلی</h2>
            <form class="mt-5 space-y-4" @submit.prevent="saveGeneral">
                <Field label="نام میزکار" :error="general.errors.name"><input v-model="general.name" maxlength="80" class="field" /></Field>
                <Field label="رنگ شاخص"><ColorSwatches v-model="general.color" :colors="colors" /></Field>
                <Button type="submit" :loading="general.processing">ذخیره</Button>
            </form>
        </section>

        <section id="members" class="card p-6">
            <h2 class="flex items-center gap-2 font-bold"><UserPlus class="size-4 text-accent" /> دعوت عضو جدید</h2>
            <form class="mt-5 flex flex-wrap items-start gap-2" @submit.prevent="sendInvite">
                <Field :error="invite.errors.email" class="min-w-56 flex-1"><input v-model="invite.email" type="email" dir="ltr" class="field" placeholder="email@company.com" /></Field>
                <select v-model="invite.role" class="field w-32"><option v-for="r in assignable" :key="r.value" :value="r.value">{{ r.label }}</option></select>
                <Button type="submit" :loading="invite.processing"><Mail class="size-4" /> ارسال دعوت</Button>
            </form>
            <p class="mt-2 text-xs leading-6 text-ink-3">«مهمان» فقط کارهایی را می‌بیند که به او سپرده شده یا در پروژه‌اش عضو است.</p>
            <ul v-if="invitations.length" class="mt-4 divide-y divide-line rounded-xl border border-line">
                <li v-for="i in invitations" :key="i.id" class="flex items-center gap-3 px-4 py-2.5 text-sm">
                    <Mail class="size-4 text-ink-3" />
                    <span class="flex-1 truncate" dir="ltr">{{ i.email }}</span>
                    <span class="chip bg-surface-3 text-ink-2">{{ roles.find((r) => r.value === i.role)?.label }}</span>
                    <span class="text-xs text-ink-3">انقضا {{ relative(i.expires_at) }}</span>
                    <button class="rounded p-1 text-ink-3 hover:text-danger" aria-label="لغو دعوت" @click="router.delete(route('invitations.destroy', [ws.id, i.id]), { preserveScroll: true })"><X class="size-4" /></button>
                </li>
            </ul>
        </section>

        <section class="card overflow-hidden xl:col-span-2">
            <header class="flex items-center gap-2 border-b border-line px-6 py-4"><Users class="size-4 text-accent" /><h2 class="font-bold">اعضا ({{ faNum(ws.members.length) }})</h2></header>
            <ul class="divide-y divide-line">
                <li v-for="m in ws.members" :key="m.id" class="flex flex-wrap items-center gap-3 px-6 py-3">
                    <Avatar :user="m" />
                    <div class="min-w-40 flex-1">
                        <p class="flex items-center gap-1.5 font-semibold">{{ m.name }} <Crown v-if="m.role === 'owner'" class="size-4 text-warning" /> <span v-if="m.id === me.id" class="text-xs text-ink-3">(شما)</span></p>
                        <input :value="m.job_title" maxlength="80" class="mt-1 w-48 rounded-md bg-transparent px-1 text-xs text-ink-3 outline-none hover:bg-surface-2 focus:bg-surface-2" placeholder="سمت (اختیاری)" :disabled="m.role === 'owner' && !isOwner" @change="setTitle(m, $event.target.value)" />
                    </div>
                    <span v-if="m.role === 'owner'" class="chip bg-warning-soft text-warning">مالک</span>
                    <select v-else class="field h-9 w-32 py-1 text-xs" :value="m.role" :disabled="m.id === me.id || (!isOwner && m.role === 'admin')" @change="changeRole(m, $event.target.value)">
                        <option v-for="r in roles.filter((r) => r.value !== 'owner')" :key="r.value" :value="r.value" :disabled="!isOwner && r.value === 'admin'">{{ r.label }}</option>
                    </select>
                    <Button v-if="m.role !== 'owner' && m.id !== me.id && (isOwner || m.role !== 'admin')" variant="danger-ghost" size="icon-sm" aria-label="حذف عضو" @click="removeMember(m)"><Trash2 class="size-4" /></Button>
                </li>
            </ul>
        </section>

        <section class="card p-6">
            <h2 class="flex items-center gap-2 font-bold"><Tag class="size-4 text-accent" /> برچسب‌ها</h2>
            <form class="mt-4 flex flex-wrap gap-2" @submit.prevent="addLabel">
                <select v-model="labelForm.scope" class="field w-32"><option v-for="(l, k) in scopeLabels" :key="k" :value="k">{{ l }}</option></select>
                <input v-model="labelForm.name" maxlength="40" class="field min-w-40 flex-1" placeholder="نام برچسب" />
                <Button type="submit" size="icon" aria-label="افزودن" :loading="labelForm.processing"><Plus class="size-4" /></Button>
            </form>
            <ColorSwatches v-model="labelForm.color" :colors="tagColors" class="mt-3" />
            <p v-if="labelForm.errors.name" class="mt-2 text-xs text-danger">{{ labelForm.errors.name }}</p>
            <div v-for="(l, scope) in scopeLabels" :key="scope" class="mt-4">
                <p class="mb-2 text-xs font-semibold text-ink-3">{{ l }}</p>
                <div class="flex flex-wrap gap-1.5">
                    <span v-for="lb in labels.filter((x) => x.scope === scope)" :key="lb.id" class="chip tone-chip gap-1.5 py-1" :class="`tone-${lb.color}`">
                        {{ lb.name }}
                        <button class="opacity-60 hover:opacity-100" :aria-label="`حذف ${lb.name}`" @click="router.delete(route('labels.destroy', [ws.id, lb.id]), { preserveScroll: true })"><X class="size-3" /></button>
                    </span>
                    <span v-if="!labels.some((x) => x.scope === scope)" class="text-xs text-ink-3">—</span>
                </div>
            </div>
        </section>

        <section class="card p-6">
            <h2 class="flex items-center gap-2 font-bold"><Layers class="size-4 text-accent" /> دسته‌بندی کارها</h2>
            <form class="mt-4 flex gap-2" @submit.prevent="addCategory">
                <input v-model="categoryForm.name" maxlength="60" class="field flex-1" placeholder="نام دسته‌بندی" />
                <Button type="submit" size="icon" aria-label="افزودن" :loading="categoryForm.processing"><Plus class="size-4" /></Button>
            </form>
            <ColorSwatches v-model="categoryForm.color" :colors="tagColors" class="mt-3" />
            <ul class="mt-4 divide-y divide-line">
                <li v-for="c in categories" :key="c.id" class="flex items-center gap-3 py-2.5 text-sm">
                    <span class="size-3 rounded-full tone-dot" :class="`tone-${c.color}`" />
                    <span class="flex-1 font-semibold">{{ c.name }}</span>
                    <span class="text-xs text-ink-3">{{ faNum(c.tasks_count) }} وظیفه</span>
                    <button class="rounded p-1 text-ink-3 hover:text-danger" aria-label="حذف" @click="router.delete(route('categories.destroy', [ws.id, c.id]), { preserveScroll: true })"><Trash2 class="size-4" /></button>
                </li>
            </ul>
        </section>

        <section class="card border-danger/30 p-6 xl:col-span-2">
            <h2 class="font-bold text-danger">منطقه خطر</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                <Button v-if="!isOwner" variant="danger-ghost" @click="leave">خروج از این میزکار</Button>
                <Button v-if="isOwner" variant="danger" @click="deleteModal = true"><Trash2 class="size-4" /> حذف میزکار</Button>
            </div>
        </section>
    </div>

    <Modal :show="deleteModal" title="حذف دائمی میزکار" size="sm" @close="deleteModal = false">
        <form class="space-y-4 p-5" @submit.prevent="destroy">
            <p class="text-sm leading-7 text-ink-2">همه پروژه‌ها، وظایف، نامه‌ها، گفتگوها و فایل‌های این میزکار برای همیشه حذف می‌شود.</p>
            <Field :label="`برای تأیید، نام «${ws.name}» را بنویسید`" :error="deleteForm.errors.confirm_name"><input v-model="deleteForm.confirm_name" class="field" /></Field>
            <Field label="رمز عبور" :error="deleteForm.errors.password"><input v-model="deleteForm.password" type="password" dir="ltr" autocomplete="current-password" class="field" /></Field>
        </form>
        <template #footer>
            <Button variant="ghost" @click="deleteModal = false">انصراف</Button>
            <Button variant="danger" :loading="deleteForm.processing" :disabled="deleteForm.confirm_name !== ws.name" @click="destroy">حذف میزکار</Button>
        </template>
    </Modal>
</template>
