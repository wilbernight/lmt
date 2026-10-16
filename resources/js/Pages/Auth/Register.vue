<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, User } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';

defineProps({ open: { type: Boolean, default: true } });

const form = useForm({ name: '', email: '', password: '', password_confirmation: '', terms: false });

const strength = computed(() => {
    const p = form.password;
    let score = 0;
    if (p.length >= 8) score++;
    if (p.length >= 12) score++;
    if (/[A-Z]/.test(p) && /[a-z]/.test(p)) score++;
    if (/\d/.test(p)) score++;
    if (/[^A-Za-z0-9]/.test(p)) score++;
    return Math.min(score, 4);
});

const strengthLabel = computed(() => ['خیلی ضعیف', 'ضعیف', 'متوسط', 'خوب', 'عالی'][strength.value]);
const strengthTone = computed(() => ['bg-danger', 'bg-danger', 'bg-warning', 'bg-success', 'bg-success'][strength.value]);

const submit = () => form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="ثبت‌نام" />
    <h1 class="text-3xl font-black tracking-tight">ساخت حساب کاربری</h1>
    <p class="mt-2 text-sm text-ink-3">در کمتر از یک دقیقه میزکار تیم خود را بسازید.</p>

    <div v-if="!open" class="mt-8 rounded-xl bg-warning-soft px-4 py-3 text-sm leading-7 text-warning">ثبت‌نام در حال حاضر بسته است. برای ساخت حساب با مدیر سایت تماس بگیرید.</div>

    <form v-else class="mt-8 space-y-5" @submit.prevent="submit">
        <Field label="نام و نام خانوادگی" :error="form.errors.name" for="name">
            <div class="relative">
                <User class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
                <input id="name" v-model="form.name" autocomplete="name" required autofocus maxlength="80" class="field h-11 ps-9" :aria-invalid="!!form.errors.name" />
            </div>
        </Field>
        <Field label="ایمیل" :error="form.errors.email" for="email">
            <div class="relative">
                <Mail class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
                <input id="email" v-model="form.email" type="email" autocomplete="email" required dir="ltr" class="field h-11 ps-9" :aria-invalid="!!form.errors.email" placeholder="name@company.com" />
            </div>
        </Field>
        <Field label="رمز عبور" :error="form.errors.password" for="password">
            <PasswordInput id="password" v-model="form.password" autocomplete="new-password" :invalid="!!form.errors.password" />
            <div v-if="form.password" class="mt-2 flex items-center gap-2">
                <div class="flex flex-1 gap-1">
                    <span v-for="i in 4" :key="i" class="h-1 flex-1 rounded-full transition-colors" :class="i <= strength ? strengthTone : 'bg-surface-3'" />
                </div>
                <span class="text-xs text-ink-3">{{ strengthLabel }}</span>
            </div>
        </Field>
        <Field label="تکرار رمز عبور" for="password_confirmation">
            <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" />
        </Field>
        <Field :error="form.errors.terms">
            <label class="flex cursor-pointer items-start gap-2 text-sm leading-6 text-ink-2">
                <input v-model="form.terms" type="checkbox" class="mt-1 size-4 rounded accent-[var(--accent)]" />
                قوانین استفاده و حریم خصوصی را می‌پذیرم.
            </label>
        </Field>
        <Button type="submit" size="lg" block :loading="form.processing">ساخت حساب</Button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-3">
        قبلاً ثبت‌نام کرده‌اید؟
        <Link href="/login" class="font-bold text-accent-ink hover:underline">وارد شوید</Link>
    </p>
</template>
