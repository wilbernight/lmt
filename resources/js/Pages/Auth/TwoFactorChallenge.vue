<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { KeyRound, MessageSquareText, RotateCw, ShieldCheck } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';

const props = defineProps({ method: { type: String, default: 'totp' }, phone: String, resendIn: { type: Number, default: 0 } });

const sms = computed(() => props.method === 'sms');
const recovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });

const submit = () => {
    if (recovery.value) {
        form.transform((d) => ({ recovery_code: d.recovery_code.trim() })).post('/two-factor-challenge', { onFinish: () => form.reset() });
    } else {
        form.transform((d) => ({ code: d.code.replace(/\s/g, '') })).post(sms.value ? '/two-factor-challenge/sms' : '/two-factor-challenge', { onFinish: () => form.reset() });
    }
};

// Resend countdown (the server enforces the same gap).
const wait = ref(props.resendIn);
const timer = setInterval(() => wait.value > 0 && wait.value--, 1000);
onBeforeUnmount(() => clearInterval(timer));
const resending = ref(false);
const resend = () =>
    router.post('/two-factor-challenge/sms/resend', {}, {
        preserveScroll: true,
        onStart: () => (resending.value = true),
        onFinish: () => (resending.value = false),
        onSuccess: (page) => (wait.value = page.props.resendIn || 60),
    });

const hint = computed(() => {
    if (recovery.value) return 'یکی از کدهای بازیابی خود را وارد کنید.';
    return sms.value
        ? `کد ۶ رقمی که به شماره ${props.phone ?? 'موبایل شما'} پیامک شد را وارد کنید.`
        : 'کد ۶ رقمی برنامه احراز هویت (مثل Google Authenticator) را وارد کنید.';
});
</script>

<template>
    <Head title="ورود دومرحله‌ای" />
    <div class="mb-6 flex size-14 items-center justify-center rounded-2xl bg-accent-soft text-accent">
        <component :is="sms && !recovery ? MessageSquareText : ShieldCheck" class="size-7" />
    </div>
    <h1 class="text-3xl font-black tracking-tight">تأیید دومرحله‌ای</h1>
    <p class="mt-3 text-sm leading-7 text-ink-3">
        {{ hint }}
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

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm">
        <button type="button" class="flex items-center gap-1.5 font-semibold text-accent-ink hover:underline" @click="recovery = !recovery">
            <KeyRound class="size-4" /> {{ recovery ? (sms ? 'استفاده از کد پیامکی' : 'استفاده از کد برنامه') : 'استفاده از کد بازیابی' }}
        </button>
        <button
            v-if="sms && !recovery"
            type="button"
            class="flex items-center gap-1.5 font-semibold text-ink-2 hover:text-ink disabled:cursor-not-allowed disabled:text-ink-3"
            :disabled="wait > 0 || resending"
            @click="resend"
        >
            <RotateCw class="size-4" :class="resending && 'animate-spin'" />
            {{ wait > 0 ? `ارسال دوباره تا ${wait.toLocaleString('fa-IR')} ثانیه` : 'ارسال دوباره کد' }}
        </button>
    </div>
</template>
