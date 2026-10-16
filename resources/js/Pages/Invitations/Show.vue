<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { MailOpen, MailX } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({ token: String, workspace: String, email: String, matches: Boolean });
const page = usePage();
const accept = () => router.post(route('invitations.accept', props.token));
</script>

<template>
    <Head title="دعوت به میزکار" />

    <template v-if="token">
        <div class="mb-6 flex size-14 items-center justify-center rounded-2xl bg-accent-soft text-accent"><MailOpen class="size-7" /></div>
        <h1 class="text-3xl font-black tracking-tight">دعوت به «{{ workspace }}»</h1>
        <p class="mt-3 text-sm leading-7 text-ink-3">این دعوت برای <b dir="ltr">{{ email }}</b> ارسال شده است.</p>

        <div class="mt-8">
            <template v-if="!page.props.auth">
                <p class="mb-4 text-sm text-ink-2">برای پذیرفتن دعوت، با همین ایمیل وارد شوید یا ثبت‌نام کنید و دوباره این لینک را باز کنید.</p>
                <div class="flex gap-2">
                    <Button href="/login">ورود</Button>
                    <Button variant="secondary" href="/register">ثبت‌نام</Button>
                </div>
            </template>
            <template v-else-if="matches">
                <Button size="lg" block @click="accept">پیوستن به میزکار</Button>
            </template>
            <p v-else class="rounded-xl bg-warning-soft px-4 py-3 text-sm leading-7 text-warning">
                شما با ایمیل دیگری وارد شده‌اید. برای پذیرش این دعوت، با <b dir="ltr">{{ email }}</b> وارد شوید.
            </p>
        </div>
    </template>

    <template v-else>
        <div class="mb-6 flex size-14 items-center justify-center rounded-2xl bg-danger-soft text-danger"><MailX class="size-7" /></div>
        <h1 class="text-3xl font-black tracking-tight">دعوت‌نامه معتبر نیست</h1>
        <p class="mt-3 text-sm leading-7 text-ink-3">این لینک منقضی شده، قبلاً استفاده شده یا لغو شده است. از مدیر میزکار بخواهید دعوت جدیدی ارسال کند.</p>
        <Link href="/" class="mt-8 inline-block font-semibold text-accent-ink hover:underline">بازگشت به صفحه اصلی</Link>
    </template>
</template>
