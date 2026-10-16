<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';

defineProps({ status: { type: String, default: null } });

const form = useForm({});
</script>

<template>
    <Head title="تأیید ایمیل" />
    <div class="mb-6 flex size-14 items-center justify-center rounded-2xl bg-accent-soft text-accent"><MailCheck class="size-7" /></div>
    <h1 class="text-3xl font-black tracking-tight">ایمیل خود را تأیید کنید</h1>
    <p class="mt-3 text-sm leading-7 text-ink-3">لینک تأیید به ایمیل شما ارسال شد. پس از کلیک روی لینک، می‌توانید از دفتر استفاده کنید.</p>

    <div v-if="status === 'verification-link-sent'" class="mt-6 rounded-xl bg-success-soft px-4 py-3 text-sm text-success">لینک تأیید جدید ارسال شد.</div>

    <div class="mt-8 flex items-center gap-3">
        <Button :loading="form.processing" @click="form.post('/email/verification-notification')">ارسال دوباره لینک</Button>
        <Link href="/logout" method="post" as="button" class="text-sm text-ink-3 hover:text-ink">خروج</Link>
    </div>
</template>
