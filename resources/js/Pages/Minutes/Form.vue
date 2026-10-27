<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRight, CalendarClock, ClipboardList, MapPin, Plus, Save, Trash2, UserCheck, UserX, Users } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import DatePicker from '@/Components/ui/DatePicker.vue';
import MemberPicker from '@/Components/ui/MemberPicker.vue';
import PeoplePicker from '@/Components/ui/PeoplePicker.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import AttachmentList from '@/Components/ui/AttachmentList.vue';

const props = defineProps({ minute: { type: Object, default: null } });

const page = usePage();
const ws = page.props.workspace;
const me = page.props.auth.user;
const editing = !!props.minute;

// ---------------------------------------------------------------- form state
// Participants/actions are stored as picker entries (see PeoplePicker) and
// transformed into the nested payload the controller expects on submit.
const form = useForm({
    subject: props.minute?.subject ?? '',
    held_at: props.minute?.held_at ?? null,
    location: props.minute?.location ?? '',
    organizer: [props.minute?.organizer?.id ?? me.id],
    body: props.minute?.body ?? '',
    participants: (props.minute?.participants ?? []).map((p) => ({
        user_id: p.user_id,
        person_id: p.person_id,
        name: p.name,
        external: p.external,
        attended: p.attended,
    })),
    actions: (props.minute?.actions ?? []).map((a) => ({
        title: a.title,
        due_at: a.due_at,
        who: a.user ? [{ user_id: a.user.id, name: a.user.name, external: false }]
            : a.person ? [{ person_id: a.person.id, name: a.person.name, external: true }]
            : [],
    })),
    attachments: [],
});

const addAction = () => form.actions.push({ title: '', due_at: null, who: [] });

// ------------------------------------------------------------ serialization
/** Picker entry -> participant row; free-typed names (person_id null) resolve server-side. */
const participantRow = (e) => (e.external ? { person_id: e.person_id, name: e.name, attended: e.attended ?? true } : { user_id: e.user_id, attended: e.attended ?? true });

/** Action row: title + single responsible (member / external person) + deadline. */
const actionRow = (a) => {
    const who = a.who[0];
    return {
        title: a.title,
        due_at: a.due_at,
        ...(who ? (who.external ? { person_id: who.person_id, name: who.name } : { user_id: who.user_id }) : {}),
    };
};

function submit() {
    form
        .transform((data) => ({
            ...data,
            organizer_id: data.organizer[0] ?? me.id,
            participants: data.participants.map(participantRow),
            actions: data.actions.filter((a) => a.title.trim()).map(actionRow),
        }))
        .submit(editing ? 'put' : 'post', editing ? route('minutes.update', [ws.id, props.minute.id]) : route('minutes.store', ws.id));
}
</script>

<template>
    <Head :title="editing ? 'ویرایش صورت‌جلسه' : 'ثبت صورت‌جلسه'" />

    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex items-center gap-3">
            <Link :href="editing ? route('minutes.show', [ws.id, minute.id]) : route('minutes.index', ws.id)" class="rounded-xl p-2 text-ink-3 hover:bg-surface-3" aria-label="بازگشت"><ArrowRight class="size-5" /></Link>
            <div>
                <h1 class="text-xl font-extrabold">{{ editing ? 'ویرایش صورت‌جلسه' : 'ثبت صورت‌جلسه جدید' }}</h1>
                <p class="text-sm text-ink-3">معلومات جلسه، حاضرین و غایبان، و اقدامات مصوب</p>
            </div>
        </div>

        <form class="card space-y-5 p-6 sm:p-8" @submit.prevent="submit">
            <Field label="موضوع جلسه *" :error="form.errors.subject">
                <input v-model="form.subject" class="field" placeholder="مثلاً: جلسه هم‌راستایی محصول — هفته چهارم" />
            </Field>

            <div class="grid gap-4 sm:grid-cols-3">
                <Field label="تاریخ برگزاری" :error="form.errors.held_at">
                    <DatePicker v-model="form.held_at" time placeholder="تاریخ و ساعت جلسه" />
                </Field>
                <Field label="محل برگزاری" :error="form.errors.location">
                    <div class="relative">
                        <MapPin class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
                        <input v-model="form.location" class="field ps-9" placeholder="اتاق جلسه / آنلاین..." />
                    </div>
                </Field>
                <Field label="مجری جلسه" :error="form.errors.organizer_id" hint="مسئول برگزاری و امضای صورت‌جلسه">
                    <MemberPicker v-model="form.organizer" single placeholder="انتخاب مجری" />
                </Field>
            </div>

            <!-- شرکت‌کنندگان: حاضر و غایب -->
            <section class="rounded-xl border border-line bg-surface-2/40 p-4">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <Users class="size-4 text-ink-3" />
                    <h2 class="text-sm font-bold">شرکت‌کنندگان</h2>
                    <span class="text-xs text-ink-3">اعضا و افراد بدون اکانت؛ روی هر نفر بزنید تا حاضر/غایب شود</span>
                </div>
                <PeoplePicker v-model="form.participants" placeholder="افزودن شرکت‌کننده (عضو یا با نوشتن نام)" />
                <div v-if="form.participants.length" class="mt-3 flex flex-wrap gap-1.5">
                    <button
                        v-for="(p, i) in form.participants"
                        :key="i"
                        type="button"
                        class="flex items-center gap-1.5 rounded-full border py-1 pe-3 ps-1.5 text-xs font-semibold transition-colors"
                        :class="p.attended ? 'border-success/40 bg-success-soft text-success' : 'border-danger/40 bg-danger-soft text-danger'"
                        :title="p.attended ? 'حاضر — برای تغییر به غایب کلیک کنید' : 'غایب — برای تغییر به حاضر کلیک کنید'"
                        @click="p.attended = !p.attended"
                    >
                        <component :is="p.attended ? UserCheck : UserX" class="size-3.5" />
                        {{ p.name }}
                    </button>
                </div>
            </section>

            <!-- شرح مذاکرات -->
            <Field label="شرح مذاکرات و مصوبات" :error="form.errors.body" hint="متن اصلی صورت‌جلسه؛ اقدامات اجرایی را در بخش زیر فهرست کنید">
                <textarea v-model="form.body" rows="10" class="field leading-8" placeholder="گفتگوها، توضیحات و تصمیم‌های جلسه..." />
            </Field>

            <!-- اقدامات -->
            <section class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <ClipboardList class="size-4 text-ink-3" />
                    <h2 class="text-sm font-bold">اقدامات</h2>
                    <span class="text-xs text-ink-3">هر اقدام پس از ثبت صورت‌جلسه با یک کلیک به وظیفه تبدیل می‌شود</span>
                    <Button type="button" variant="secondary" size="sm" class="ms-auto" @click="addAction"><Plus class="size-3.5" /> افزودن اقدام</Button>
                </div>

                <div v-for="(a, i) in form.actions" :key="i" class="grid gap-2 rounded-xl border border-line bg-surface-2/40 p-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-center">
                    <input v-model="a.title" class="field h-10" :placeholder="`عنوان اقدام ${i + 1}...`" />
                    <PeoplePicker v-model="a.who" single compact placeholder="مسئول اقدام" />
                    <div class="w-40"><DatePicker v-model="a.due_at" compact placeholder="مهلت" /></div>
                    <button type="button" class="justify-self-end rounded-lg p-2 text-ink-3 hover:bg-danger-soft hover:text-danger" aria-label="حذف اقدام" @click="form.actions.splice(i, 1)"><Trash2 class="size-4" /></button>
                </div>
                <p v-if="!form.actions.length" class="rounded-xl border border-dashed border-line px-4 py-6 text-center text-xs text-ink-3">هنوز اقدامی اضافه نشده — «افزودن اقدام» را بزنید.</p>
            </section>

            <div v-if="editing && minute.attachments?.length">
                <p class="label mb-2">پیوست‌های فعلی</p>
                <AttachmentList :items="minute.attachments" />
            </div>
            <FileUpload v-model="form.attachments" />

            <div class="flex justify-end gap-2 border-t border-line pt-5">
                <Button variant="ghost" :href="editing ? route('minutes.show', [ws.id, minute.id]) : route('minutes.index', ws.id)">انصراف</Button>
                <Button type="submit" :loading="form.processing"><Save class="size-4" /> {{ editing ? 'ذخیره تغییرات' : 'ثبت صورت‌جلسه' }}</Button>
            </div>
        </form>
    </div>
</template>
