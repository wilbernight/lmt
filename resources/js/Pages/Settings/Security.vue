<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Copy, KeyRound, LogOut, Monitor, ShieldCheck, ShieldOff, Smartphone, TriangleAlert } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Modal from '@/Components/ui/Modal.vue';
import SettingsNav from './Nav.vue';
import http from '@/lib/http';
import { jDateTime, relative } from '@/lib/format';
import { toast } from '@/composables/useToast';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({ sessions: Array, twoFactor: Object, activity: Array });

// Two-factor -----------------------------------------------------------
const qr = ref(null);
const secret = ref(null);
const codes = ref([]);
const confirming = ref(props.twoFactor.pending);
const confirmForm = useForm({ code: '' });
const busy = ref(false);

async function loadSetup() {
    const [svg, key] = await Promise.all([http.get('/user/two-factor-qr-code'), http.get('/user/two-factor-secret-key')]);
    qr.value = svg.data.svg;
    secret.value = key.data.secretKey;
}

async function enable() {
    busy.value = true;
    router.post('/user/two-factor-authentication', {}, {
        preserveScroll: true,
        onSuccess: async () => {
            confirming.value = true;
            await loadSetup();
        },
        onFinish: () => (busy.value = false),
    });
}

if (props.twoFactor.pending) loadSetup().catch(() => {});

function confirm2fa() {
    confirmForm.post('/user/confirmed-two-factor-authentication', {
        errorBag: 'confirmTwoFactorAuthentication',
        preserveScroll: true,
        onSuccess: async () => {
            confirming.value = false;
            qr.value = null;
            await showCodes();
        },
    });
}

async function showCodes() {
    codes.value = (await http.get('/user/two-factor-recovery-codes')).data;
}

async function regenerate() {
    await http.post('/user/two-factor-recovery-codes');
    await showCodes();
    toast.success('کدهای بازیابی جدید ساخته شد.');
}

async function disable() {
    if (!(await confirmDialog({ title: 'غیرفعال کردن ورود دومرحله‌ای', text: 'امنیت حساب شما کاهش می‌یابد. ادامه می‌دهید؟', confirmText: 'غیرفعال کن', danger: true }))) return;
    router.delete('/user/two-factor-authentication', { preserveScroll: true, onSuccess: () => (codes.value = []) });
}

const copyCodes = () => navigator.clipboard?.writeText(codes.value.join('\n')).then(() => toast.success('کدها کپی شد.'));

// Sessions --------------------------------------------------------------
const logoutModal = ref(false);
const logoutForm = useForm({ password: '' });
const logoutOthers = () => logoutForm.delete(route('settings.sessions.destroy'), { preserveScroll: true, onSuccess: () => { logoutModal.value = false; logoutForm.reset(); } });

// Delete account ---------------------------------------------------------
const deleteModal = ref(false);
const deleteForm = useForm({ password: '' });
const deleteAccount = () => deleteForm.delete(route('settings.account.destroy'), { preserveScroll: true });

const actionLabel = {
    'auth.login': 'ورود موفق', 'auth.logout': 'خروج', 'auth.failed': 'تلاش ناموفق برای ورود', 'auth.lockout': 'قفل موقت به دلیل تلاش زیاد',
    'auth.password_reset': 'بازیابی رمز', 'account.2fa_enabled': 'فعال‌سازی ورود دومرحله‌ای', 'account.2fa_disabled': 'غیرفعال‌سازی ورود دومرحله‌ای',
    'account.sessions_revoked': 'خروج از سایر دستگاه‌ها', 'account.email_changed': 'تغییر ایمیل',
};
</script>

<template>
    <Head title="امنیت و ورود" />

    <div class="mx-auto grid max-w-5xl gap-8 lg:grid-cols-[13rem_1fr]">
        <SettingsNav active="security" />

        <div class="space-y-6">
            <!-- 2FA -->
            <section class="card p-6">
                <div class="flex items-start gap-4">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl" :class="twoFactor.enabled ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning'">
                        <component :is="twoFactor.enabled ? ShieldCheck : ShieldOff" class="size-6" />
                    </span>
                    <div class="flex-1">
                        <h2 class="text-lg font-bold">ورود دومرحله‌ای</h2>
                        <p class="mt-1 text-sm leading-7 text-ink-3">با فعال کردن این گزینه، علاوه بر رمز عبور، کد ۶ رقمی برنامه احراز هویت (Google Authenticator، Microsoft Authenticator یا ۱Password) هم لازم است.</p>
                    </div>
                    <span class="chip" :class="twoFactor.enabled ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning'">{{ twoFactor.enabled ? 'فعال' : 'غیرفعال' }}</span>
                </div>

                <div v-if="confirming" class="mt-6 grid gap-6 rounded-2xl border border-line bg-surface-2 p-5 sm:grid-cols-[auto_1fr]">
                    <!-- The SVG comes from our own server (Fortify / BaconQrCode), never from user input. -->
                    <div v-if="qr" class="rounded-xl bg-white p-3" v-html="qr" />
                    <div class="space-y-3 text-sm">
                        <p class="flex items-center gap-2 font-semibold"><Smartphone class="size-4" /> کد QR را با برنامه احراز هویت اسکن کنید</p>
                        <p v-if="secret" class="text-xs text-ink-3">یا این کلید را دستی وارد کنید: <code class="rounded bg-surface px-2 py-0.5 font-mono text-ink" dir="ltr">{{ secret }}</code></p>
                        <form class="flex items-end gap-2" @submit.prevent="confirm2fa">
                            <Field label="کد تأیید" :error="$page.props.errors?.confirmTwoFactorAuthentication?.code" class="w-40">
                                <input v-model="confirmForm.code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" dir="ltr" class="field text-center font-bold tracking-widest" />
                            </Field>
                            <Button type="submit" :loading="confirmForm.processing">تأیید و فعال‌سازی</Button>
                        </form>
                    </div>
                </div>

                <div v-if="codes.length" class="mt-6 rounded-2xl border border-warning/40 bg-warning-soft p-5">
                    <p class="flex items-center gap-2 text-sm font-bold text-warning"><TriangleAlert class="size-4" /> کدهای بازیابی را در جای امنی نگه دارید</p>
                    <p class="mt-1 text-xs text-ink-2">اگر به گوشی دسترسی نداشتید، با هر کدام یک بار می‌توانید وارد شوید.</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm" dir="ltr">
                        <span v-for="c in codes" :key="c" class="rounded-lg bg-surface px-3 py-1.5 text-center">{{ c }}</span>
                    </div>
                    <Button variant="secondary" size="sm" class="mt-3" @click="copyCodes"><Copy class="size-4" /> کپی کدها</Button>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <Button v-if="!twoFactor.enabled && !confirming" :loading="busy" @click="enable"><ShieldCheck class="size-4" /> فعال‌سازی</Button>
                    <template v-if="twoFactor.enabled">
                        <Button variant="secondary" @click="showCodes"><KeyRound class="size-4" /> نمایش کدهای بازیابی</Button>
                        <Button variant="secondary" @click="regenerate">ساخت کدهای جدید</Button>
                        <Button variant="danger-ghost" @click="disable">غیرفعال کردن</Button>
                    </template>
                </div>
            </section>

            <!-- Sessions -->
            <section class="card p-6">
                <div class="flex items-center gap-3">
                    <h2 class="flex-1 text-lg font-bold">نشست‌های فعال</h2>
                    <Button v-if="sessions.length > 1" variant="secondary" size="sm" @click="logoutModal = true"><LogOut class="size-4" /> خروج از سایر دستگاه‌ها</Button>
                </div>
                <ul class="mt-4 divide-y divide-line">
                    <li v-for="(s, i) in sessions" :key="i" class="flex items-center gap-4 py-3.5">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-surface-3 text-ink-2"><Monitor class="size-5" /></span>
                        <div class="flex-1">
                            <p class="text-sm font-semibold">{{ s.agent }}</p>
                            <p class="text-xs text-ink-3"><span dir="ltr">{{ s.ip }}</span> · {{ s.current ? 'همین دستگاه' : relative(s.last_active) }}</p>
                        </div>
                        <span v-if="s.current" class="chip bg-success-soft text-success">نشست فعلی</span>
                    </li>
                </ul>
            </section>

            <!-- Activity -->
            <section class="card p-6">
                <h2 class="text-lg font-bold">رخدادهای اخیر حساب</h2>
                <ul class="mt-4 divide-y divide-line">
                    <li v-for="a in activity" :key="a.id" class="flex items-center gap-3 py-3 text-sm">
                        <span class="size-2 rounded-full" :class="a.action.includes('failed') || a.action.includes('lockout') ? 'bg-danger' : 'bg-success'" />
                        <span class="flex-1">{{ actionLabel[a.action] ?? a.action }} <span class="text-xs text-ink-3">· {{ a.agent }}</span></span>
                        <span class="text-xs text-ink-3"><span dir="ltr">{{ a.ip }}</span> · {{ jDateTime(a.created_at) }}</span>
                    </li>
                    <li v-if="!activity.length" class="py-4 text-center text-xs text-ink-3">رخدادی ثبت نشده است.</li>
                </ul>
            </section>

            <!-- Danger zone -->
            <section class="card border-danger/30 p-6">
                <h2 class="text-lg font-bold text-danger">حذف حساب کاربری</h2>
                <p class="mt-1 text-sm text-ink-3">همه اطلاعات شما و میزکارهایی که مالک آن هستید برای همیشه حذف می‌شود.</p>
                <Button variant="danger" class="mt-4" @click="deleteModal = true">حذف حساب</Button>
            </section>
        </div>
    </div>

    <Modal :show="logoutModal" title="خروج از سایر دستگاه‌ها" size="sm" @close="logoutModal = false">
        <form class="p-5" @submit.prevent="logoutOthers">
            <Field label="برای ادامه رمز عبور خود را وارد کنید" :error="logoutForm.errors.password"><input v-model="logoutForm.password" type="password" autofocus autocomplete="current-password" dir="ltr" class="field" /></Field>
        </form>
        <template #footer>
            <Button variant="ghost" @click="logoutModal = false">انصراف</Button>
            <Button :loading="logoutForm.processing" @click="logoutOthers">خروج از همه</Button>
        </template>
    </Modal>

    <Modal :show="deleteModal" title="حذف دائمی حساب" size="sm" @close="deleteModal = false">
        <form class="p-5" @submit.prevent="deleteAccount">
            <Field label="رمز عبور" :error="deleteForm.errors.password"><input v-model="deleteForm.password" type="password" autofocus autocomplete="current-password" dir="ltr" class="field" /></Field>
        </form>
        <template #footer>
            <Button variant="ghost" @click="deleteModal = false">انصراف</Button>
            <Button variant="danger" :loading="deleteForm.processing" @click="deleteAccount">حذف برای همیشه</Button>
        </template>
    </Modal>
</template>
