<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, BadgeCheck, CalendarClock, CheckCheck, ClipboardList, FilePenLine, Lock, MapPin, Printer, Send, Stamp, Trash2, UserCheck, UserX, Users } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import AttachmentList from '@/Components/ui/AttachmentList.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { confirmDialog } from '@/composables/useConfirm';
import { toast } from '@/composables/useToast';
import http from '@/lib/http';
import { faNum, jDate, jDateTime, dueLabel } from '@/lib/format';
import { openTask, taskChanged } from '@/composables/useTaskModal';

const props = defineProps({ minute: Object, can: Object });

const page = usePage();
const ws = page.props.workspace;
const m = props.minute;

const present = m.participants.filter((p) => p.attended);
const absent = m.participants.filter((p) => !p.attended);
const busyAction = ref(null);

const printMinute = () => window.open(route('minutes.print', [ws.id, m.id, { auto: 1 }]), '_blank', 'noopener');

async function finalize() {
    if (!await confirmDialog({ title: 'نهایی‌سازی صورت‌جلسه', text: 'پس از نهایی شدن، متن صورت‌جلسه قابل ویرایش نیست و برای تأیید الکترونیکی به حاضرین اعلام می‌شود.', confirmText: 'نهایی کن' })) return;
    router.post(route('minutes.finalize', [ws.id, m.id]));
}

async function remove() {
    if (!await confirmDialog({ title: 'حذف صورت‌جلسه', text: `صورت‌جلسه «${m.subject}» و اقدامات آن برای همیشه حذف می‌شود.`, confirmText: 'حذف', danger: true })) return;
    router.delete(route('minutes.destroy', [ws.id, m.id]));
}

async function toTask(a) {
    busyAction.value = a.id;
    try {
        await http.post(route('minutes.actions.task', [ws.id, m.id, a.id]));
        toast.success('از این اقدام وظیفه ساخته شد.');
        taskChanged();
        router.reload({ only: ['minute'] });
    } catch {
        // the http interceptor already shows the error
    } finally {
        busyAction.value = null;
    }
}
</script>

<template>
    <Head :title="`صورت‌جلسه ${faNum(m.number)}`" />

    <div class="mx-auto max-w-4xl">
        <div class="mb-5 flex flex-wrap items-center gap-3">
            <Link :href="route('minutes.index', ws.id)" class="rounded-xl p-2 text-ink-3 hover:bg-surface-3" aria-label="بازگشت"><ArrowRight class="size-5" /></Link>
            <span class="num chip bg-surface-3 text-ink-3">جلسه #{{ faNum(m.number) }}</span>
            <span class="chip tone-chip" :class="m.status === 'final' ? 'tone-emerald' : 'tone-amber'">
                <component :is="m.status === 'final' ? Stamp : FilePenLine" class="size-3.5" />
                {{ m.status === 'final' ? 'نهایی' : 'پیش‌نویس' }}
            </span>
            <div class="ms-auto flex flex-wrap items-center gap-2 print:hidden">
                <Button variant="secondary" size="icon" aria-label="چاپ" @click="printMinute"><Printer class="size-4" /></Button>
                <Button v-if="can.update" variant="secondary" :href="route('minutes.edit', [ws.id, m.id])"><FilePenLine class="size-4" /> ویرایش</Button>
                <Button v-if="can.update && m.status !== 'final'" @click="finalize"><Lock class="size-4" /> نهایی‌سازی</Button>
                <Button v-if="can.delete" variant="ghost" size="icon" aria-label="حذف" class="text-danger" @click="remove"><Trash2 class="size-4" /></Button>
            </div>
        </div>

        <!-- سربرگ صورت‌جلسه -->
        <header class="card p-6 sm:p-7">
            <h1 class="text-xl font-extrabold leading-8 sm:text-2xl">{{ m.subject }}</h1>

            <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-ink-3">
                <span v-if="m.organizer" class="flex items-center gap-2">
                    <Avatar :user="m.organizer" size="xs" :status="false" />
                    مجری: <b class="text-ink-2">{{ m.organizer.name }}</b>
                </span>
                <span v-if="m.held_at" class="flex items-center gap-1.5"><CalendarClock class="size-4" /> {{ jDateTime(m.held_at) }}</span>
                <span v-if="m.location" class="flex items-center gap-1.5"><MapPin class="size-4" /> {{ m.location }}</span>
                <span class="flex items-center gap-1.5 text-xs">ثبت: {{ m.creator }} · {{ jDateTime(m.created_at) }}</span>
            </div>
        </header>

        <!-- حاضرین و غایبان -->
        <section class="card mt-4 p-6 sm:p-7">
            <h2 class="mb-4 flex items-center gap-2 text-sm font-bold"><Users class="size-4 text-ink-3" /> شرکت‌کنندگان <span class="chip bg-surface-3 text-ink-3">{{ faNum(m.participants.length) }} نفر</span></h2>

            <div class="flex flex-wrap gap-1.5">
                <span v-for="p in present" :key="p.key" class="flex items-center gap-1.5 rounded-full border border-success/30 bg-success-soft px-2.5 py-1 text-xs font-semibold text-success" :title="p.external ? 'بدون اکانت' : ''">
                    <UserCheck class="size-3.5" />
                    {{ p.name }}
                    <Lock v-if="p.external" class="size-3 opacity-60" />
                </span>
                <span v-for="p in absent" :key="p.key" class="flex items-center gap-1.5 rounded-full border border-danger/30 bg-danger-soft px-2.5 py-1 text-xs font-semibold text-danger" :title="p.external ? 'بدون اکانت' : ''">
                    <UserX class="size-3.5" />
                    {{ p.name }}
                    <Lock v-if="p.external" class="size-3 opacity-60" />
                </span>
            </div>
            <p v-if="absent.length" class="mt-3 text-xs text-ink-3">{{ faNum(absent.length) }} نفر با علامت قرمز، جلسه را ترک کرده یا غایب بوده‌اند.</p>
        </section>

        <!-- شرح مذاکرات -->
        <section class="card mt-4 p-6 sm:p-7">
            <h2 class="mb-4 text-sm font-bold">شرح مذاکرات و مصوبات</h2>
            <p v-if="m.body" class="whitespace-pre-wrap text-sm leading-8 text-ink-2">{{ m.body }}</p>
            <EmptyState v-else icon="ClipboardList" title="متنی ثبت نشده" text="شرح جلسه با دکمه ویرایش قابل افزودن است." compact />
        </section>

        <!-- اقدامات -->
        <section class="card mt-4 overflow-hidden">
            <header class="flex items-center gap-2.5 border-b border-line bg-surface-2/50 px-5 py-3.5">
                <ClipboardList class="size-4 text-ink-3" />
                <h2 class="text-sm font-bold">اقدامات</h2>
                <span class="chip bg-accent-soft text-accent-ink">{{ faNum(m.actions.length) }} مورد</span>
            </header>
            <div v-if="m.actions.length">
                <div v-for="(a, i) in m.actions" :key="a.id" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3.5 last:border-0">
                    <span class="num flex size-7 shrink-0 items-center justify-center rounded-lg bg-surface-3 text-xs font-bold text-ink-2">{{ faNum(i + 1) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold leading-6">{{ a.title }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-3">
                            <span v-if="a.user" class="flex items-center gap-1.5"><Avatar :user="a.user" size="xs" :status="false" /> {{ a.user.name }}</span>
                            <span v-else-if="a.person" class="flex items-center gap-1.5" :title="`${a.person.name} (بدون اکانت)`">
                                <span class="flex size-5 items-center justify-center rounded-full border border-dashed border-line-strong text-[0.5rem] font-bold text-ink-2">{{ a.person.initials }}</span> {{ a.person.name }}
                            </span>
                            <span v-else>بدون مسئول</span>
                            <span v-if="a.due_at" class="flex items-center gap-1 rounded-md px-1.5 py-0.5" :class="{ 'bg-danger-soft font-semibold text-danger': dueLabel(a.due_at)?.tone === 'danger', 'bg-warning-soft font-semibold text-warning': dueLabel(a.due_at)?.tone === 'warning' }">مهلت: {{ jDate(a.due_at) }}</span>
                        </div>
                    </div>
                    <button
                        v-if="a.task_id"
                        class="chip tone-chip tone-emerald flex items-center gap-1"
                        title="مشاهده وظیفه"
                        @click="openTask(a.task_id)"
                    ><Send class="size-3" /> {{ a.task_title || 'وظیفه' }}</button>
                    <Button v-else-if="can.act" variant="secondary" size="sm" :loading="busyAction === a.id" @click="toTask(a)"><Send class="size-3.5 -scale-x-100" /> تبدیل به وظیفه</Button>
                </div>
            </div>
            <EmptyState v-else icon="ClipboardList" title="اقدامی ثبت نشده" text="برای پیگیری مصوبات، از ویرایش اقدام با مسئول و مهلت اضافه کنید." compact />
        </section>

        <!-- پیوست‌ها -->
        <section v-if="m.attachments.length" class="card mt-4 p-6 sm:p-7">
            <h2 class="mb-4 text-sm font-bold">پیوست‌ها</h2>
            <AttachmentList :items="m.attachments" />
        </section>

        <!-- تأیید الکترونیک -->
        <section v-if="m.status === 'final'" class="card mt-4 p-6 sm:p-7">
            <h2 class="mb-1 flex items-center gap-2 text-sm font-bold"><BadgeCheck class="size-4 text-success" /> تأیید الکترونیکی حاضرین</h2>
            <p class="mb-4 text-xs text-ink-3">هر حاضرِ دارای اکانت می‌تواند این صورت‌جلسه را تأیید کند؛ تأیید شما قابل لغو است.</p>

            <div class="flex flex-wrap items-center gap-1.5">
                <span v-for="u in m.approvals" :key="u.id" class="flex items-center gap-1.5 rounded-full border border-success/30 bg-success-soft px-2.5 py-1 text-xs font-semibold text-success">
                    <CheckCheck class="size-3.5" /> {{ u.name }}
                </span>
                <span v-if="!m.approvals.length" class="text-xs text-ink-3">هنوز کسی تأیید نکرده — اولین نفر باشید.</span>
            </div>

            <Button
                v-if="can.approve"
                class="mt-4"
                :variant="m.my_approval ? 'secondary' : 'primary'"
                @click="router.post(route('minutes.approve', [ws.id, m.id]), {}, { preserveScroll: true })"
            >
                <CheckCheck class="size-4" /> {{ m.my_approval ? 'لغو تأیید من' : 'تأیید من' }}
            </Button>
        </section>
    </div>
</template>
