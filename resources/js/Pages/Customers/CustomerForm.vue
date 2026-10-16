<script setup>
import { Building2, User } from '@lucide/vue';
import Field from '@/Components/ui/Field.vue';
import MemberPicker from '@/Components/ui/MemberPicker.vue';
import LabelPicker from '@/Components/ui/LabelPicker.vue';

defineProps({ form: { type: Object, required: true }, stages: Object, labels: Array });
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-2">
            <button v-for="k in [{ v: 'person', l: 'شخص', i: User }, { v: 'company', l: 'شرکت / سازمان', i: Building2 }]" :key="k.v" type="button" class="flex items-center justify-center gap-2 rounded-xl border p-2.5 text-sm font-semibold" :class="form.kind === k.v ? 'border-accent bg-accent-soft text-accent-ink' : 'border-line-strong text-ink-2 hover:bg-surface-2'" @click="form.kind = k.v">
                <component :is="k.i" class="size-4" /> {{ k.l }}
            </button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <Field :label="form.kind === 'company' ? 'نام سازمان' : 'نام و نام خانوادگی'" :error="form.errors.name"><input v-model="form.name" autofocus maxlength="150" class="field" /></Field>
            <Field v-if="form.kind === 'person'" label="شرکت" :error="form.errors.company"><input v-model="form.company" maxlength="150" class="field" /></Field>
            <Field label="سمت" :error="form.errors.job_title"><input v-model="form.job_title" maxlength="100" class="field" /></Field>
            <Field label="موبایل" :error="form.errors.mobile"><input v-model="form.mobile" dir="ltr" inputmode="tel" maxlength="30" class="field text-end" /></Field>
            <Field label="تلفن" :error="form.errors.phone"><input v-model="form.phone" dir="ltr" inputmode="tel" maxlength="30" class="field text-end" /></Field>
            <Field label="ایمیل" :error="form.errors.email"><input v-model="form.email" dir="ltr" type="email" class="field" /></Field>
            <Field label="وب‌سایت" :error="form.errors.website"><input v-model="form.website" dir="ltr" type="url" placeholder="https://" class="field" /></Field>
            <Field label="شهر" :error="form.errors.city"><input v-model="form.city" maxlength="80" class="field" /></Field>
        </div>
        <Field label="آدرس" :error="form.errors.address"><input v-model="form.address" maxlength="500" class="field" /></Field>
        <div class="grid gap-3 sm:grid-cols-3">
            <Field label="مرحله" :error="form.errors.stage">
                <select v-model="form.stage" class="field"><option v-for="(l, k) in stages" :key="k" :value="k">{{ l }}</option></select>
            </Field>
            <Field label="ارزش معامله (تومان)" :error="form.errors.deal_value"><input v-model.number="form.deal_value" type="number" min="0" dir="ltr" class="field text-end" /></Field>
            <Field label="مسئول پیگیری"><MemberPicker :model-value="form.owner_id ? [form.owner_id] : []" single @update:model-value="form.owner_id = $event[0] ?? null" /></Field>
        </div>
        <Field label="برچسب‌ها"><LabelPicker v-model="form.labels" :labels="labels" /></Field>
        <Field label="یادداشت" :error="form.errors.notes"><textarea v-model="form.notes" rows="3" maxlength="10000" class="field" /></Field>
    </div>
</template>
