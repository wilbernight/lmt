<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { LockKeyhole } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';

const form = useForm({ password: '' });
</script>

<template>
    <Head title="تأیید رمز عبور" />
    <div class="mb-6 flex size-14 items-center justify-center rounded-2xl bg-accent-soft text-accent"><LockKeyhole class="size-7" /></div>
    <h1 class="text-3xl font-black tracking-tight">تأیید هویت</h1>
    <p class="mt-3 text-sm leading-7 text-ink-3">این بخش حساس است. برای ادامه، رمز عبور خود را دوباره وارد کنید.</p>

    <form class="mt-8 space-y-5" @submit.prevent="form.post('/user/confirm-password', { onFinish: () => form.reset() })">
        <Field label="رمز عبور" :error="form.errors.password">
            <PasswordInput v-model="form.password" :invalid="!!form.errors.password" />
        </Field>
        <Button type="submit" size="lg" block :loading="form.processing">تأیید</Button>
    </form>
</template>
