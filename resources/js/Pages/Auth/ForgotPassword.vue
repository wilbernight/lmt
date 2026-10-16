<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Mail } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';

defineProps({ status: { type: String, default: null } });

const form = useForm({ email: '' });
</script>

<template>
    <Head title="بازیابی رمز عبور" />
    <Link href="/login" class="mb-8 inline-flex items-center gap-1.5 text-sm text-ink-3 hover:text-ink"><ArrowRight class="size-4" /> بازگشت به ورود</Link>
    <h1 class="text-3xl font-black tracking-tight">رمز را فراموش کرده‌اید؟</h1>
    <p class="mt-2 text-sm leading-7 text-ink-3">ایمیل حساب خود را وارد کنید تا لینک تعیین رمز جدید برایتان ارسال شود.</p>

    <div v-if="status" class="mt-6 rounded-xl bg-success-soft px-4 py-3 text-sm leading-6 text-success">{{ status }}</div>

    <form class="mt-8 space-y-5" @submit.prevent="form.post('/forgot-password')">
        <Field label="ایمیل" :error="form.errors.email" for="email">
            <div class="relative">
                <Mail class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
                <input id="email" v-model="form.email" type="email" required autofocus dir="ltr" class="field h-11 ps-9" />
            </div>
        </Field>
        <Button type="submit" size="lg" block :loading="form.processing">ارسال لینک بازیابی</Button>
    </form>
</template>
