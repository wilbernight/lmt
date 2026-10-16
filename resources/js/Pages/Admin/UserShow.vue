<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight, BadgeCheck, Building2, Crown, HardDrive, KeyRound, Lock, LogOut, MailCheck, ShieldCheck, ShieldOff, Unlock, UserX,
} from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Modal from '@/Components/ui/Modal.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import { faNum, formatBytes, jDate, jDateTime, relative } from '@/lib/format';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({ user: Object, workspaces: Array, sessions: Number, activity: Array, defaults: Object });

const page = usePage();
const isMe = props.user.id === page.props.auth.user.id;

const form = useForm({
    is_admin: props.user.is_admin,
    can_create_workspaces: props.user.can_create_workspaces,
    max_workspaces: props.user.max_workspaces,
    storage_quota_mb: props.user.storage_quota_mb,
    apply_storage_to_existing: false,
});
const save = () => form.put(route('admin.users.update', props.user.id), { preserveScroll: true });

const suspendModal = ref(false);
const suspendForm = useForm({ reason: '' });
const suspend = () => suspendForm.post(route('admin.users.suspend', props.user.id), { preserveScroll: true, onSuccess: () => (suspendModal.value = false) });

const act = async (name, title, text, danger = false) => {
    if (await confirmDialog({ title, text, confirmText: 'تأیید', danger })) router.post(route(name, props.user.id), {}, { preserveScroll: true });
};

// Per-workspace storage edits
const editing = ref(null);
const storageForm = useForm({ storage_limit_mb: 0 });
const editStorage = (w) => {
    editing.value = w;
    storageForm.storage_limit_mb = Math.round(w.storage_limit / 1048576);
};
const saveStorage = () => storageForm.put(route('admin.workspaces.update', editing.value.id), { preserveScroll: true, onSuccess: () => (editing.value = null) });
const lockWorkspace = (w) => router.post(route('admin.workspaces.lock', w.id), {}, { preserveScroll: true });

const toggles = [
    { key: 'is_admin', label: 'مدیر سایت', hint: 'دسترسی کامل به این پنل', icon: Crown },
    { key: 'can_create_workspaces', label: 'اجازه ساخت میزکار', hint: 'اگر خاموش باشد فقط در میزکارهای دیگران عضو می‌شود', icon: Building2 },
];
const storagePercent = props.user.storage_allocated ? Math.round((props.user.storage_used / props.user.storage_allocated) * 100) : 0;
</script>

<template>
    <Head :title="user.name" />

    <div class="flex flex-wrap items-center gap-4">
        <Link :href="route('admin.users.index')" class="rounded-xl p-2 text-ink-3 hover:bg-surface-3" aria-label="بازگشت"><ArrowRight class="size-5" /></Link>
        <Avatar :user="user" size="lg" />
        <div class="min-w-0 flex-1">
            <h1 class="flex items-center gap-2 text-2xl font-black">{{ user.name }} <Crown v-if="user.is_admin" class="size-5 text-warning" /></h1>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-ink-3">
                <span dir="ltr">{{ user.email }}</span>
                <span v-if="user.suspended" class="chip bg-danger-soft text-danger">مسدود</span>
                <span v-else class="chip bg-success-soft text-success">فعال</span>
                <span v-if="!user.verified" class="chip bg-warning-soft text-warning">ایمیل تأییدنشده</span>
                <span class="chip" :class="user.two_factor ? 'bg-info-soft text-info' : 'bg-surface-3 text-ink-3'">{{ user.two_factor ? '2FA فعال' : '2FA خاموش' }}</span>
            </p>
        </div>
        <template v-if="!isMe">
            <Button v-if="user.suspended" variant="secondary" @click="act('admin.users.unsuspend', 'فعال‌سازی حساب', 'کاربر دوباره می‌تواند وارد شود.')"><Unlock class="size-4" /> رفع مسدودی</Button>
            <Button v-else variant="danger" @click="suspendModal = true"><UserX class="size-4" /> مسدود کردن</Button>
        </template>
    </div>

    <div v-if="user.suspended && user.suspended_reason" class="mt-4 rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger">دلیل مسدودی: {{ user.suspended_reason }}</div>

    <div class="mt-6 grid gap-5 xl:grid-cols-[1.3fr_1fr]">
        <!-- Permissions & quota -->
        <section class="card p-6">
            <h2 class="flex items-center gap-2 font-bold"><ShieldCheck class="size-4 text-accent" /> دسترسی‌ها و سهمیه</h2>
            <form class="mt-5 space-y-5" @submit.prevent="save">
                <label v-for="t in toggles" :key="t.key" class="flex cursor-pointer items-center gap-4 rounded-xl border border-line p-4 transition-colors hover:bg-surface-2" :class="t.key === 'is_admin' && isMe && 'pointer-events-none opacity-60'">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-accent-soft text-accent-ink"><component :is="t.icon" class="size-5" /></span>
                    <span class="flex-1"><span class="block text-sm font-bold">{{ t.label }}</span><span class="block text-xs text-ink-3">{{ t.hint }}</span></span>
                    <input v-model="form[t.key]" type="checkbox" class="peer sr-only" :disabled="t.key === 'is_admin' && isMe" />
                    <span class="relative h-6 w-11 rounded-full bg-surface-3 transition-colors after:absolute after:start-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:bg-accent peer-checked:after:start-[1.375rem]" />
                </label>
                <p v-if="form.errors.is_admin" class="text-xs text-danger">{{ form.errors.is_admin }}</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="سقف تعداد میزکار" :hint="`خالی = پیش‌فرض سایت (${faNum(defaults.max_workspaces)})`" :error="form.errors.max_workspaces">
                        <input v-model.number="form.max_workspaces" type="number" min="0" max="1000" dir="ltr" class="field text-end" :placeholder="String(defaults.max_workspaces)" />
                    </Field>
                    <Field label="فضای هر میزکار (مگابایت)" :hint="`خالی = پیش‌فرض سایت (${formatBytes(defaults.storage_mb * 1048576)})`" :error="form.errors.storage_quota_mb">
                        <input v-model.number="form.storage_quota_mb" type="number" min="10" dir="ltr" class="field text-end" :placeholder="String(defaults.storage_mb)" />
                    </Field>
                </div>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.apply_storage_to_existing" type="checkbox" class="size-4 accent-[var(--accent)]" /> این فضا روی میزکارهای فعلی کاربر هم اعمال شود</label>

                <Button type="submit" :loading="form.processing">ذخیره دسترسی‌ها</Button>
            </form>
        </section>

        <!-- Account actions -->
        <section class="space-y-5">
            <div class="card p-6">
                <h2 class="flex items-center gap-2 font-bold"><HardDrive class="size-4 text-warning" /> مصرف فضا</h2>
                <p class="num mt-3 text-sm text-ink-2">{{ formatBytes(user.storage_used) }} از {{ formatBytes(user.storage_allocated) }} در {{ faNum(user.owned_workspaces_count) }} میزکار</p>
                <ProgressBar :value="storagePercent" size="md" class="mt-2" />
            </div>
            <div class="card space-y-2 p-6">
                <h2 class="mb-3 font-bold">اقدامات حساب</h2>
                <Button variant="secondary" block :disabled="!sessions || isMe" @click="act('admin.users.logout', 'خروج اجباری', 'کاربر از همه دستگاه‌ها خارج می‌شود.')"><LogOut class="size-4" /> خروج از همه دستگاه‌ها ({{ faNum(sessions) }} نشست)</Button>
                <Button variant="secondary" block :disabled="!user.two_factor" @click="act('admin.users.reset-2fa', 'بازنشانی ورود دومرحله‌ای', 'برای وقتی که کاربر به گوشی خود دسترسی ندارد.', true)"><ShieldOff class="size-4" /> بازنشانی 2FA</Button>
                <Button variant="secondary" block :disabled="user.verified" @click="act('admin.users.verify', 'تأیید ایمیل', 'ایمیل این کاربر بدون لینک تأیید، تأییدشده علامت می‌خورد.')"><MailCheck class="size-4" /> تأیید دستی ایمیل</Button>
                <p class="pt-2 text-xs text-ink-3">عضو از {{ jDate(user.created_at) }} · آخرین حضور {{ user.last_seen_at ? relative(user.last_seen_at) : 'هرگز' }}</p>
            </div>
        </section>
    </div>

    <!-- Workspaces -->
    <section class="card mt-5 overflow-hidden">
        <header class="flex items-center gap-2 border-b border-line px-5 py-4"><Building2 class="size-4 text-accent" /><h2 class="text-sm font-bold">میزکارهای کاربر</h2></header>
        <div v-for="w in workspaces" :key="w.id" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3 last:border-0">
            <span class="flex size-9 items-center justify-center rounded-lg font-black tone-chip" :class="`tone-${w.color}`">{{ w.name.slice(0, 1) }}</span>
            <div class="min-w-40 flex-1">
                <p class="text-sm font-bold">{{ w.name }} <span v-if="w.locked" class="chip bg-danger-soft text-danger">غیرفعال</span></p>
                <p class="text-xs text-ink-3">{{ { owner: 'مالک', admin: 'مدیر', member: 'عضو', guest: 'مهمان' }[w.role] }} · {{ faNum(w.members_count) }} عضو</p>
            </div>
            <span class="num w-44 text-xs text-ink-2">{{ formatBytes(w.storage_used) }} / {{ formatBytes(w.storage_limit) }}</span>
            <template v-if="w.role === 'owner'">
                <Button size="sm" variant="secondary" @click="editStorage(w)"><HardDrive class="size-3.5" /> تغییر فضا</Button>
                <Button size="sm" :variant="w.locked ? 'secondary' : 'danger-ghost'" @click="lockWorkspace(w)"><component :is="w.locked ? Unlock : Lock" class="size-3.5" /> {{ w.locked ? 'فعال‌سازی' : 'غیرفعال' }}</Button>
            </template>
        </div>
        <p v-if="!workspaces.length" class="px-5 py-6 text-center text-xs text-ink-3">عضو هیچ میزکاری نیست.</p>
    </section>

    <!-- Activity -->
    <section class="card mt-5 overflow-hidden">
        <header class="flex items-center gap-2 border-b border-line px-5 py-4"><KeyRound class="size-4 text-accent" /><h2 class="text-sm font-bold">رخدادهای اخیر</h2></header>
        <ul class="divide-y divide-line">
            <li v-for="a in activity" :key="a.id" class="flex items-center gap-3 px-5 py-2.5 text-sm">
                <span class="flex-1 font-mono text-xs" dir="ltr">{{ a.action }}</span>
                <span class="text-xs text-ink-3"><span dir="ltr">{{ a.ip }}</span> · {{ jDateTime(a.created_at) }}</span>
            </li>
            <li v-if="!activity.length" class="px-5 py-6 text-center text-xs text-ink-3">رخدادی ثبت نشده است.</li>
        </ul>
    </section>

    <Modal :show="suspendModal" title="مسدود کردن حساب" size="sm" @close="suspendModal = false">
        <form class="space-y-3 p-5" @submit.prevent="suspend">
            <p class="text-sm leading-7 text-ink-2">کاربر بلافاصله از همه دستگاه‌ها خارج می‌شود و تا رفع مسدودی نمی‌تواند وارد شود. اطلاعاتش حذف نمی‌شود.</p>
            <Field label="دلیل (به کاربر نمایش داده می‌شود)" :error="suspendForm.errors.reason"><input v-model="suspendForm.reason" maxlength="300" class="field" /></Field>
        </form>
        <template #footer>
            <Button variant="ghost" @click="suspendModal = false">انصراف</Button>
            <Button variant="danger" :loading="suspendForm.processing" @click="suspend">مسدود کن</Button>
        </template>
    </Modal>

    <Modal :show="!!editing" :title="`فضای «${editing?.name}»`" size="sm" @close="editing = null">
        <form class="p-5" @submit.prevent="saveStorage">
            <Field label="سقف فضا (مگابایت)" :hint="editing ? `مصرف فعلی: ${formatBytes(editing.storage_used)}` : ''" :error="storageForm.errors.storage_limit_mb">
                <input v-model.number="storageForm.storage_limit_mb" type="number" min="10" dir="ltr" class="field text-end" autofocus />
            </Field>
            <div class="mt-3 flex flex-wrap gap-1.5">
                <button v-for="mb in [512, 1024, 2048, 5120, 10240, 51200]" :key="mb" type="button" class="chip bg-surface-3 text-ink-2 hover:bg-accent-soft hover:text-accent-ink" @click="storageForm.storage_limit_mb = mb">{{ formatBytes(mb * 1048576) }}</button>
            </div>
        </form>
        <template #footer>
            <Button variant="ghost" @click="editing = null">انصراف</Button>
            <Button :loading="storageForm.processing" @click="saveStorage">ذخیره</Button>
        </template>
    </Modal>
</template>
