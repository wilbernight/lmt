<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CircleCheck, CircleX, MessageSquareText, Plug, RadioTower, Search, Send, Settings2, Signal, Users, UsersRound } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SmsGroupManager from '@/Components/SmsGroupManager.vue';
import http from '@/lib/http';
import { faNum, jDateTime } from '@/lib/format';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({
    settings: Object, hardLimit: Number, maxRecipients: Number, usedToday: Number, live: Boolean,
    people: Array, history: Object, statuses: Object, filters: Object, groups: Array, allUsers: Array, groupColors: Array,
});

// ---- Settings ----------------------------------------------------------
const settingsForm = useForm({ ...props.settings });
const saveSettings = () => settingsForm.put(route('admin.sms.settings'), { preserveScroll: true });

const testing = ref(false);
const modem = ref(null);
async function test() {
    testing.value = true;
    modem.value = null;
    try {
        modem.value = (await http.post(route('admin.sms.test'))).data;
    } finally {
        testing.value = false;
    }
}
const signalPercent = computed(() => (modem.value?.signal != null ? Math.round((modem.value.signal / 31) * 100) : 0));
const operatorName = (op) => ({ TCI: 'همراه اول', 'MCI': 'همراه اول', IR: 'ایرانسل', 'MTN Irancell': 'ایرانسل', RighTel: 'رایتل' })[op] ?? op;

// ---- Compose -----------------------------------------------------------
const form = useForm({ users: [], groups: [], numbers: '', body: '' });
const managing = ref(false);
const toggleGroup = (id) => (form.groups = form.groups.includes(id) ? form.groups.filter((x) => x !== id) : [...form.groups, id]);
const q = ref('');
const shownPeople = computed(() => props.people.filter((p) => !q.value || p.name.includes(q.value.trim())));
const toggle = (id) => (form.users = form.users.includes(id) ? form.users.filter((x) => x !== id) : [...form.users, id]);
const typedNumbers = computed(() => form.numbers.split(/[\s,،;]+/).filter(Boolean).length);
// Distinct people with a phone: picked one by one or through a group.
const reachableIds = computed(() => new Set(props.people.map((p) => p.id)));
const pickedPeople = computed(() => {
    const ids = new Set(form.users);
    props.groups.filter((g) => form.groups.includes(g.id)).forEach((g) => g.members.forEach((id) => reachableIds.value.has(id) && ids.add(id)));
    return ids.size;
});
const recipientCount = computed(() => pickedPeople.value + typedNumbers.value);

// SIM800: Latin = GSM 7-bit (160 / 153 per part); Persian = UCS-2 (70 / 67 per part).
const isUnicode = computed(() => /[^\x20-\x7E\r\n]/.test(form.body));
const parts = computed(() => {
    const n = [...form.body].length;
    if (!n) return 0;
    const [single, multi] = isUnicode.value ? [70, 67] : [160, 153];
    return n <= single ? 1 : Math.ceil(n / multi);
});

async function send() {
    const ok = await confirmDialog({
        title: 'ارسال پیامک',
        text: props.live
            ? `پیامک برای ${faNum(recipientCount.value)} گیرنده واقعاً ارسال می‌شود (${faNum(parts.value)} بخش برای هر نفر).`
            : 'ارسال روی «فقط ثبت» است؛ پیامکی واقعاً ارسال نمی‌شود و فقط در تاریخچه ثبت می‌شود.',
        confirmText: 'ارسال',
    });
    if (ok) form.post(route('admin.sms.send'), { preserveScroll: true, onSuccess: () => form.reset() });
}

const tone = { sent: 'emerald', failed: 'rose', limited: 'amber', invalid: 'slate', logged: 'sky' };
const usedPercent = computed(() => Math.round((props.usedToday / props.settings.daily_limit) * 100));
</script>

<template>
    <Head title="پیامک" />

    <PageHeader title="پیامک" subtitle="ارسال از طریق مودم GSM روی شبکه داخلی — فقط مدیران سایت" :icon="MessageSquareText" tone="rose">
        <span class="chip" :class="live ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning'">{{ live ? 'ارسال واقعی فعال' : 'فقط ثبت (ارسال خاموش)' }}</span>
    </PageHeader>

    <div class="mt-6 grid gap-5 xl:grid-cols-[1.4fr_1fr]">
        <!-- Compose -->
        <section class="card p-6">
            <h2 class="flex items-center gap-2 font-bold"><Send class="size-4 text-accent" /> ارسال پیامک</h2>

            <div class="mt-5">
                <div class="mb-2 flex items-center justify-between">
                    <p class="label mb-0 flex items-center gap-1.5"><UsersRound class="size-3.5" /> ارسال به گروه</p>
                    <button type="button" class="flex items-center gap-1 text-xs font-semibold text-accent-ink hover:underline" @click="managing = true"><Settings2 class="size-3.5" /> مدیریت گروه‌ها</button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="g in groups"
                        :key="g.id"
                        type="button"
                        class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition-colors"
                        :class="[`tone-${g.color}`, form.groups.includes(g.id) ? 'tone-chip border-transparent' : 'border-line-strong text-ink-2 hover:bg-surface-2']"
                        :aria-pressed="form.groups.includes(g.id)"
                        @click="toggleGroup(g.id)"
                    >
                        <span class="size-2.5 rounded-full tone-dot" />
                        {{ g.name }}
                        <span class="num text-xs font-normal opacity-75">{{ faNum(g.reachable) }}</span>
                    </button>
                    <button v-if="!groups.length" type="button" class="rounded-xl border border-dashed border-line-strong px-3 py-2 text-sm text-ink-3 hover:text-ink" @click="managing = true">+ ساخت اولین گروه</button>
                </div>
            </div>

            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <div>
                    <p class="label flex items-center gap-1.5"><Users class="size-3.5" /> همکاران (شماره از پروفایل)</p>
                    <div class="relative mb-2">
                        <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
                        <input v-model="q" class="field h-9 ps-9 text-sm" placeholder="جستجوی نام..." />
                    </div>
                    <div class="max-h-64 overflow-y-auto rounded-xl border border-line">
                        <label v-for="p in shownPeople" :key="p.id" class="flex cursor-pointer items-center gap-3 border-b border-line px-3 py-2 last:border-0 hover:bg-surface-2">
                            <input type="checkbox" class="size-4 accent-[var(--accent)]" :checked="form.users.includes(p.id)" @change="toggle(p.id)" />
                            <Avatar :user="p" size="xs" />
                            <span class="flex-1 text-sm">{{ p.name }}</span>
                            <span class="font-mono text-xs text-ink-3" dir="ltr">{{ p.phone }}</span>
                        </label>
                        <p v-if="!people.length" class="px-3 py-6 text-center text-xs leading-6 text-ink-3">هیچ کاربری شماره تماس در پروفایلش ثبت نکرده است.</p>
                    </div>
                </div>
                <Field label="شماره‌های دیگر" hint="با فاصله، ویرگول یا خط جدید جدا کنید. مثال: 09121234567" :error="form.errors.numbers">
                    <textarea v-model="form.numbers" rows="9" dir="ltr" class="field font-mono text-sm" placeholder="09121234567&#10;09351234567" />
                </Field>
            </div>

            <Field label="متن پیام" class="mt-5" :error="form.errors.body || form.errors.users">
                <textarea v-model="form.body" rows="4" maxlength="670" class="field leading-7" placeholder="متن پیامک..." />
            </Field>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-3">
                <span class="num">{{ faNum([...form.body].length) }} نویسه</span>
                <span class="num">{{ faNum(parts) }} بخش {{ isUnicode ? '(فارسی: ۷۰ نویسه هر بخش)' : '(لاتین: ۱۶۰ نویسه هر بخش)' }}</span>
                <span class="num" :class="recipientCount > maxRecipients && 'font-bold text-danger'">{{ faNum(recipientCount) }} گیرنده (حداکثر {{ faNum(maxRecipients) }} در هر بار)</span>
            </div>

            <div class="mt-5 flex justify-end">
                <Button :loading="form.processing" :disabled="!form.body.trim() || !recipientCount || recipientCount > maxRecipients" @click="send">
                    <Send class="size-4 -scale-x-100" /> ارسال
                </Button>
            </div>
            <p v-if="form.processing" class="mt-2 text-end text-xs text-ink-3">ارسال از مودم چند ثانیه برای هر پیام طول می‌کشد...</p>
        </section>

        <!-- Gateway -->
        <section class="space-y-5">
            <div class="card p-6">
                <div class="flex items-center gap-2">
                    <h2 class="flex flex-1 items-center gap-2 font-bold"><RadioTower class="size-4 text-accent" /> وضعیت مودم</h2>
                    <Button size="sm" variant="secondary" :loading="testing" @click="test"><Plug class="size-4" /> تست اتصال</Button>
                </div>
                <div v-if="modem" class="mt-4 rounded-xl p-4 text-sm" :class="modem.ok ? 'bg-success-soft' : 'bg-danger-soft'">
                    <template v-if="modem.ok">
                        <p class="flex items-center gap-2 font-bold text-success"><CircleCheck class="size-4" /> مودم پاسخ داد</p>
                        <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-ink-2">
                            <div><dt class="text-ink-3">مدل</dt><dd class="font-mono font-semibold" dir="ltr">{{ modem.model }}</dd></div>
                            <div><dt class="text-ink-3">اپراتور</dt><dd class="font-semibold">{{ operatorName(modem.operator) || '—' }}</dd></div>
                            <div><dt class="text-ink-3">ثبت در شبکه</dt><dd class="font-semibold">{{ modem.registered ? 'بله' : 'خیر' }}</dd></div>
                            <div>
                                <dt class="flex items-center gap-1 text-ink-3"><Signal class="size-3" /> آنتن</dt>
                                <dd class="mt-1 flex items-center gap-2"><ProgressBar :value="signalPercent" class="w-20" /> <span class="num">{{ faNum(modem.signal ?? '?') }}/۳۱</span></dd>
                            </div>
                        </dl>
                    </template>
                    <p v-else class="flex items-start gap-2 text-danger"><CircleX class="mt-0.5 size-4 shrink-0" /> <span class="break-all">{{ modem.error }}</span></p>
                </div>
                <div class="mt-4">
                    <div class="mb-1.5 flex justify-between text-xs text-ink-3"><span>ارسال امروز</span><span class="num">{{ faNum(usedToday) }} از {{ faNum(settings.daily_limit) }}</span></div>
                    <ProgressBar :value="usedPercent" />
                </div>
            </div>

            <form class="card space-y-4 p-6" @submit.prevent="saveSettings">
                <h2 class="flex items-center gap-2 font-bold"><Settings2 class="size-4 text-accent" /> تنظیمات اتصال</h2>
                <Field label="روش ارسال" :error="settingsForm.errors.driver">
                    <select v-model="settingsForm.driver" class="field">
                        <option value="gsm">مودم GSM (ارسال واقعی)</option>
                        <option value="log">فقط ثبت (بدون ارسال، برای آزمایش)</option>
                    </select>
                </Field>
                <div class="grid grid-cols-[1fr_7rem] gap-3">
                    <Field label="آدرس مودم" :error="settingsForm.errors.host"><input v-model="settingsForm.host" dir="ltr" class="field font-mono" placeholder="192.168.77.248" /></Field>
                    <Field label="پورت" :error="settingsForm.errors.port"><input v-model.number="settingsForm.port" type="number" dir="ltr" class="field font-mono" /></Field>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <Field label="مهلت (ثانیه)" :error="settingsForm.errors.timeout"><input v-model.number="settingsForm.timeout" type="number" min="2" max="30" dir="ltr" class="field" /></Field>
                    <Field label="کد کشور" :error="settingsForm.errors.country_code"><input v-model="settingsForm.country_code" dir="ltr" class="field" /></Field>
                    <Field label="سقف روزانه" :error="settingsForm.errors.daily_limit"><input v-model.number="settingsForm.daily_limit" type="number" min="1" :max="hardLimit" dir="ltr" class="field" /></Field>
                </div>
                <p class="text-xs leading-6 text-ink-3">این سیم‌کارت با پنل WilberNight مشترک است و اپراتور روزانه حداکثر {{ faNum(hardLimit) }} پیامک اجازه می‌دهد؛ سقف این بخش را طوری بگذارید که برای کدهای ورود پنل جا بماند.</p>
                <Button type="submit" :loading="settingsForm.processing">ذخیره تنظیمات</Button>
            </form>
        </section>
    </div>

    <SmsGroupManager :show="managing" :groups="groups" :users="allUsers" :colors="groupColors" @close="managing = false" />

    <!-- History -->
    <section class="card mt-5 overflow-hidden">
        <header class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-4">
            <h2 class="flex-1 text-sm font-bold">تاریخچه ارسال</h2>
            <select class="field h-9 w-auto py-1 text-xs" :value="filters.status ?? ''" @change="router.get(route('admin.sms.index'), { status: $event.target.value || undefined }, { preserveScroll: true, preserveState: true })">
                <option value="">همه وضعیت‌ها</option>
                <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
            </select>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[46rem] text-sm">
                <thead class="text-xs text-ink-3"><tr class="border-b border-line">
                    <th class="px-5 py-2.5 text-start font-semibold">گیرنده</th><th class="px-3 py-2.5 text-start font-semibold">متن</th>
                    <th class="px-3 py-2.5 font-semibold">وضعیت</th><th class="px-3 py-2.5 font-semibold">فرستنده</th><th class="px-5 py-2.5 font-semibold">زمان</th>
                </tr></thead>
                <tbody>
                    <tr v-for="m in history.data" :key="m.id" class="border-b border-line align-top last:border-0">
                        <td class="px-5 py-3"><span class="block font-semibold">{{ m.recipient ?? '—' }}</span><span class="font-mono text-xs text-ink-3" dir="ltr">{{ m.phone }}</span></td>
                        <td class="max-w-md px-3 py-3 text-xs leading-6 text-ink-2"><p class="line-clamp-2 whitespace-pre-wrap">{{ m.body }}</p></td>
                        <td class="px-3 py-3 text-center">
                            <span class="chip tone-chip" :class="`tone-${tone[m.status]}`">{{ statuses[m.status] }}</span>
                            <p v-if="m.error" class="mt-1 max-w-40 text-[0.65rem] text-danger" :title="m.error">{{ m.error }}</p>
                        </td>
                        <td class="px-3 py-3 text-center text-xs">{{ m.sender ?? '—' }}</td>
                        <td class="num px-5 py-3 text-center text-xs text-ink-3">{{ jDateTime(m.created_at) }}<span v-if="m.duration_ms" class="block">{{ faNum((m.duration_ms / 1000).toFixed(1)) }} ثانیه</span></td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-if="!history.data.length" :icon="MessageSquareText" title="هنوز پیامکی ارسال نشده" compact />
        </div>
        <div v-if="history.last_page > 1" class="flex justify-center gap-1 border-t border-line p-3">
            <template v-for="link in history.links" :key="link.label">
                <Link v-if="link.url" :href="link.url" preserve-scroll class="num min-w-9 rounded-lg px-3 py-1.5 text-center text-sm" :class="link.active ? 'bg-accent text-on-accent' : 'hover:bg-surface-3'">{{ faNum(link.label.replace(/&laquo;|&raquo;/g, '').trim()) }}</Link>
            </template>
        </div>
    </section>
</template>
