<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { KeyRound, ShieldCheck } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';

const recovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });

const submit = () =>
    form
        .transform((data) => (recovery.value ? { recovery_code: data.recovery_code } : { code: data.code.replace(/\s/g, '') }))
        .post('/two-factor-challenge', { onFinish: () => form.reset() });
</script>

<template>
    <Head title="ورود دومرحله‌ای" />
    <div class="mb-6 flex size-14 items-center justify-center rounded-2xl bg-accent-soft text-accent"><ShieldCheck class="size-7" /></div>
    <h1 class="text-3xl font-black tracking-tight">تأیید دومرحله‌ای</h1>
    <p class="mt-3 text-sm leading-7 text-ink-3">
        {{ recovery ? 'یکی از کدهای بازیابی خود را وارد کنید.' : 'کد ۶ رقمی برنامه احراز هویت (مثل Google Authenticator) را وارد کنید.' }}
    </p>

    <form class="mt-8 space-y-5" @submit.prevent="submit">
        <Field v-if="!recovery" label="کد تأیید" :error="form.errors.code">
            <input v-model="form.code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus dir="ltr" class="field h-14 text-center text-2xl font-bold tracking-[0.5em]" placeholder="000000" />
        </Field>
        <Field v-else label="کد بازیابی" :error="form.errors.recovery_code">
            <input v-model="form.recovery_code" autocomplete="off" autofocus dir="ltr" class="field h-11 text-center font-mono" />
        </Field>
        <Button type="submit" size="lg" block :loading="form.processing">تأیید و ورود</Button>
    </form>

    <button type="button" class="mt-6 flex items-center gap-1.5 text-sm font-semibold text-accent-ink hover:underline" @click="recovery = !recovery">
        <KeyRound class="size-4" /> {{ recovery ? 'استفاده از کد برنامه' : 'استفاده از کد بازیابی' }}
    </button>
</template>
