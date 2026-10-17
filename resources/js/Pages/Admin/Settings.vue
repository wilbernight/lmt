<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldCheck, SlidersHorizontal } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import { formatBytes } from '@/lib/format';

const props = defineProps({ settings: Object, requires_2fa: Boolean });
const form = useForm({ ...props.settings });
const save = () => form.put(route('admin.settings.update'), { preserveScroll: true });
</script>

<template>
    <Head title="تنظیمات سایت" />

    <PageHeader title="تنظیمات سایت" subtitle="مقادیر پیش‌فرض برای همه کاربران" :icon="SlidersHorizontal" tone="rose" />

    <form class="card mt-6 max-w-3xl space-y-6 p-6" @submit.prevent="save">
        <label class="flex cursor-pointer items-center gap-4 rounded-xl border border-line p-4 hover:bg-surface-2">
            <span class="flex-1"><span class="block text-sm font-bold">ثبت‌نام آزاد</span><span class="block text-xs text-ink-3">اگر خاموش باشد، فقط با دعوت‌نامه یا توسط مدیر حساب ساخته می‌شود.</span></span>
            <input v-model="form.registration_open" type="checkbox" class="peer sr-only" />
            <span class="relative h-6 w-11 rounded-full bg-surface-3 transition-colors after:absolute after:start-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:bg-accent peer-checked:after:start-[1.375rem]" />
        </label>

        <div class="grid gap-4 sm:grid-cols-3">
            <Field label="فضای پیش‌فرض هر میزکار (MB)" :hint="formatBytes((form.default_storage_mb || 0) * 1048576)" :error="form.errors.default_storage_mb">
                <input v-model.number="form.default_storage_mb" type="number" min="10" dir="ltr" class="field text-end" />
            </Field>
            <Field label="سقف پیش‌فرض تعداد میزکار" :error="form.errors.default_max_workspaces">
                <input v-model.number="form.default_max_workspaces" type="number" min="0" max="1000" dir="ltr" class="field text-end" />
            </Field>
            <Field label="حداکثر حجم هر فایل (MB)" hint="بین ۱ تا ۱۰۰" :error="form.errors.max_upload_mb">
                <input v-model.number="form.max_upload_mb" type="number" min="1" max="100" dir="ltr" class="field text-end" />
            </Field>
        </div>
        <div class="border-t border-line pt-6">
            <h2 class="font-bold">سربرگ نامه‌ها</h2>
            <p class="mt-1 text-xs text-ink-3">این اطلاعات بالای و پایین نامه‌های چاپی نمایش داده می‌شود.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <Field label="نام مجموعه" :error="form.errors.company_name"><input v-model="form.company_name" maxlength="80" class="field" /></Field>
                <Field label="شعار / حوزه فعالیت" :error="form.errors.company_tagline"><input v-model="form.company_tagline" maxlength="120" class="field" /></Field>
                <Field label="نشانی" :error="form.errors.company_address" class="sm:col-span-2"><input v-model="form.company_address" maxlength="200" class="field" /></Field>
                <Field label="تلفن" :error="form.errors.company_phone"><input v-model="form.company_phone" dir="ltr" maxlength="40" class="field text-end" /></Field>
                <Field label="ایمیل" :error="form.errors.company_email"><input v-model="form.company_email" type="email" dir="ltr" class="field" /></Field>
                <Field label="وب‌سایت" :error="form.errors.company_website"><input v-model="form.company_website" dir="ltr" placeholder="wilbernight.net" class="field" /></Field>
            </div>
        </div>

        <p class="text-xs leading-6 text-ink-3">تغییر فضای پیش‌فرض فقط روی میزکارهای جدید اعمال می‌شود. برای میزکارهای فعلی از صفحه «میزکارها» یا صفحه هر کاربر استفاده کنید.</p>

        <div class="flex items-center gap-3 rounded-xl bg-surface-2 p-4 text-sm">
            <ShieldCheck class="size-5" :class="requires_2fa ? 'text-success' : 'text-warning'" />
            <span class="flex-1">{{ requires_2fa ? 'ورود دومرحله‌ای برای مدیران سایت الزامی است.' : 'ورود دومرحله‌ای برای مدیران الزامی نیست (در production خودکار الزامی می‌شود؛ ADMIN_REQUIRES_2FA در .env).' }}</span>
        </div>

        <Button type="submit" :loading="form.processing">ذخیره تنظیمات</Button>
    </form>
</template>
