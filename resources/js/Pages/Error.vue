<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { faNum } from '@/lib/format';

const props = defineProps({ status: Number });

const text = computed(
    () =>
        ({
            403: ['دسترسی ندارید', 'اجازه مشاهده این بخش را ندارید.'],
            404: ['پیدا نشد', 'صفحه یا موردی که دنبالش هستید وجود ندارد یا به آن دسترسی ندارید.'],
            429: ['کمی آهسته‌تر', 'تعداد درخواست‌ها زیاد است. چند لحظه دیگر دوباره تلاش کنید.'],
            500: ['خطای سرور', 'مشکلی پیش آمد. لطفاً بعداً دوباره تلاش کنید.'],
            503: ['در حال به‌روزرسانی', 'به‌زودی برمی‌گردیم.'],
        })[props.status] ?? ['خطا', ''],
);
</script>

<template>
    <Head :title="text[0]" />
    <p class="num text-gradient text-7xl font-black">{{ faNum(status) }}</p>
    <h1 class="mt-4 text-2xl font-black">{{ text[0] }}</h1>
    <p class="mt-2 text-sm leading-7 text-ink-3">{{ text[1] }}</p>
    <Link href="/" class="mt-8 inline-flex h-11 items-center rounded-xl gradient-accent px-6 text-sm font-semibold text-on-accent">بازگشت به داشبورد</Link>
</template>
