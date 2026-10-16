<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';

const props = defineProps({ email: String, token: String });

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
</script>

<template>
    <Head title="رمز عبور جدید" />
    <h1 class="text-3xl font-black tracking-tight">تعیین رمز جدید</h1>
    <p class="mt-2 text-sm text-ink-3">یک رمز قوی و منحصربه‌فرد انتخاب کنید.</p>

    <form class="mt-8 space-y-5" @submit.prevent="form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') })">
        <Field label="ایمیل" :error="form.errors.email">
            <input v-model="form.email" type="email" required dir="ltr" class="field h-11" readonly />
        </Field>
        <Field label="رمز عبور جدید" :error="form.errors.password">
            <PasswordInput v-model="form.password" autocomplete="new-password" :invalid="!!form.errors.password" />
        </Field>
        <Field label="تکرار رمز عبور">
            <PasswordInput v-model="form.password_confirmation" autocomplete="new-password" />
        </Field>
        <Button type="submit" size="lg" block :loading="form.processing">ذخیره رمز جدید</Button>
    </form>
</template>
