<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';

defineProps({ status: { type: String, default: null } });

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="ورود" />
    <h1 class="text-3xl font-black tracking-tight">خوش برگشتید 👋</h1>
    <p class="mt-2 text-sm text-ink-3">برای ادامه وارد حساب کاربری خود شوید.</p>

    <div v-if="status" class="mt-6 rounded-xl bg-success-soft px-4 py-3 text-sm text-success">{{ status }}</div>

    <form class="mt-8 space-y-5" @submit.prevent="submit">
        <Field label="ایمیل" :error="form.errors.email" for="email">
            <div class="relative">
                <Mail class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
                <input id="email" v-model="form.email" type="email" autocomplete="username" required autofocus dir="ltr" class="field h-11 ps-9" :aria-invalid="!!form.errors.email" placeholder="name@company.com" />
            </div>
        </Field>
        <Field :error="form.errors.password">
            <div class="mb-1.5 flex items-center justify-between">
                <label class="label mb-0" for="password">رمز عبور</label>
                <Link href="/forgot-password" class="text-xs font-semibold text-accent-ink hover:underline">فراموش کرده‌اید؟</Link>
            </div>
            <PasswordInput id="password" v-model="form.password" :invalid="!!form.errors.password" />
        </Field>
        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-2">
            <input v-model="form.remember" type="checkbox" class="size-4 rounded accent-[var(--accent)]" />
            مرا به خاطر بسپار
        </label>
        <Button type="submit" size="lg" block :loading="form.processing">ورود</Button>
    </form>

    <p v-if="$page.props.app.registration_open" class="mt-8 text-center text-sm text-ink-3">
        حساب کاربری ندارید؟
        <Link href="/register" class="font-bold text-accent-ink hover:underline">ثبت‌نام رایگان</Link>
    </p>
</template>
