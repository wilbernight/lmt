<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRight, Lock, Send } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import PeoplePicker from '@/Components/ui/PeoplePicker.vue';
import LabelPicker from '@/Components/ui/LabelPicker.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';

const props = defineProps({ labels: Array, priorities: Object });
const page = usePage();
const ws = page.props.workspace;
const me = page.props.auth.user.id;

// Recipients/copies: members and colleagues without an account (free-typed names).
const form = useForm({ subject: '', body: '', priority: 'normal', confidential: false, to: [], cc: [], labels: [], attachments: [] });
const priorityTone = { normal: 'slate', urgent: 'amber', instant: 'rose' };

function submit() {
    form
        .transform((data) => ({
            ...data,
            to: data.to.filter((e) => !e.external).map((e) => e.user_id),
            to_names: data.to.filter((e) => e.external).map((e) => e.name),
            cc: data.cc.filter((e) => !e.external).map((e) => e.user_id),
            cc_names: data.cc.filter((e) => e.external).map((e) => e.name),
        }))
        .post(route('letters.store', ws.id));
}
</script>

<template>
    <Head title="نامه جدید" />

    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex items-center gap-3">
            <Link :href="route('letters.index', ws.id)" class="rounded-xl p-2 text-ink-3 hover:bg-surface-3" aria-label="بازگشت"><ArrowRight class="size-5" /></Link>
            <h1 class="text-2xl font-black">نامه جدید</h1>
        </div>

        <form class="card space-y-5 p-6 sm:p-8" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="گیرندگان" :error="form.errors.to ?? form.errors.to_names"><PeoplePicker v-model="form.to" :exclude="[me, ...form.cc.filter((e) => !e.external).map((e) => e.user_id)]" placeholder="انتخاب یا نوشتن نام گیرنده" /></Field>
                <Field label="رونوشت" :error="form.errors.cc ?? form.errors.cc_names"><PeoplePicker v-model="form.cc" :exclude="[me, ...form.to.filter((e) => !e.external).map((e) => e.user_id)]" placeholder="اختیاری" /></Field>
            </div>
            <Field label="موضوع" :error="form.errors.subject"><input v-model="form.subject" maxlength="200" class="field h-11 text-base font-semibold" /></Field>
            <Field label="متن نامه" :error="form.errors.body"><textarea v-model="form.body" rows="12" maxlength="20000" class="field leading-8" /></Field>

            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="فوریت">
                    <div class="grid grid-cols-3 gap-1 rounded-xl border border-line-strong p-1">
                        <button v-for="(label, key) in priorities" :key="key" type="button" class="rounded-lg py-1.5 text-xs font-semibold" :class="form.priority === key ? `tone-chip tone-${priorityTone[key]}` : 'text-ink-3 hover:bg-surface-3'" @click="form.priority = key">{{ label }}</button>
                    </div>
                </Field>
                <Field label="برچسب"><LabelPicker v-model="form.labels" :labels="labels" /></Field>
            </div>

            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line p-3.5 text-sm">
                <input v-model="form.confidential" type="checkbox" class="size-4 accent-[var(--accent)]" />
                <Lock class="size-4 text-warning" />
                <span><b>محرمانه</b> — متن در اعلان‌ها نمایش داده نمی‌شود و فقط فرستنده می‌تواند ارجاع دهد.</span>
            </label>

            <FileUpload v-model="form.attachments" />

            <div class="flex justify-end gap-2 border-t border-line pt-5">
                <Button variant="ghost" :href="route('letters.index', ws.id)">انصراف</Button>
                <Button type="submit" :loading="form.processing"><Send class="size-4 -scale-x-100" /> ارسال نامه</Button>
            </div>
        </form>
    </div>
</template>
