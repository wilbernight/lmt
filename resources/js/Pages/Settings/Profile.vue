<script setup>
import { ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Camera, Trash2 } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import SettingsNav from './Nav.vue';
import { confirmDialog } from '@/composables/useConfirm';

const page = usePage();
const user = page.props.auth.user;

const form = useForm({ name: user.name, email: user.email, phone: user.phone ?? '', theme: user.theme ?? 'system' });
const saveProfile = () =>
    form.put('/user/profile-information', {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => {
            try { localStorage.setItem('theme', form.theme); } catch { /* ignore */ }
        },
    });

const password = useForm({ current_password: '', password: '', password_confirmation: '' });
const savePassword = () => password.put('/user/password', { errorBag: 'updatePassword', preserveScroll: true, onSuccess: () => password.reset() });

const fileInput = ref(null);
const uploadAvatar = (e) => {
    const file = e.target.files[0];
    if (file) router.post(route('settings.avatar'), { avatar: file }, { forceFormData: true, preserveScroll: true });
};
const removeAvatar = async () => {
    if (await confirmDialog({ title: 'حذف تصویر پروفایل', confirmText: 'حذف', danger: true })) router.delete(route('settings.avatar.destroy'), { preserveScroll: true });
};

const errors = (bag) => page.props.errors?.[bag] ?? {};
</script>

<template>
    <Head title="حساب کاربری" />

    <div class="mx-auto grid max-w-5xl gap-8 lg:grid-cols-[13rem_1fr]">
        <SettingsNav active="profile" />

        <div class="space-y-6">
            <section class="card p-6">
                <h2 class="text-lg font-bold">پروفایل</h2>
                <p class="mt-1 text-sm text-ink-3">نام و تصویر شما برای همکاران نمایش داده می‌شود.</p>

                <div class="mt-6 flex items-center gap-5">
                    <Avatar :user="user" size="xl" :status="false" />
                    <div class="flex flex-wrap gap-2">
                        <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="uploadAvatar" />
                        <Button variant="secondary" size="sm" @click="fileInput.click()"><Camera class="size-4" /> انتخاب تصویر</Button>
                        <Button v-if="user.avatar_url" variant="danger-ghost" size="sm" @click="removeAvatar"><Trash2 class="size-4" /> حذف</Button>
                        <p class="w-full text-xs text-ink-3">JPG، PNG یا WebP، حداکثر ۲ مگابایت.</p>
                        <p v-if="page.props.errors?.avatar" class="w-full text-xs text-danger">{{ page.props.errors.avatar }}</p>
                    </div>
                </div>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="saveProfile">
                    <Field label="نام و نام خانوادگی" :error="errors('updateProfileInformation').name"><input v-model="form.name" maxlength="80" class="field" /></Field>
                    <Field label="ایمیل" :error="errors('updateProfileInformation').email" hint="با تغییر ایمیل، باید دوباره آن را تأیید کنید."><input v-model="form.email" type="email" dir="ltr" class="field" /></Field>
                    <Field label="شماره تماس" :error="errors('updateProfileInformation').phone"><input v-model="form.phone" dir="ltr" inputmode="tel" class="field text-end" /></Field>
                    <Field label="پوسته">
                        <select v-model="form.theme" class="field">
                            <option value="system">هماهنگ با سیستم</option>
                            <option value="light">روشن</option>
                            <option value="dark">تاریک</option>
                        </select>
                    </Field>
                    <div class="sm:col-span-2"><Button type="submit" :loading="form.processing">ذخیره تغییرات</Button></div>
                </form>
            </section>

            <section class="card p-6">
                <h2 class="text-lg font-bold">تغییر رمز عبور</h2>
                <p class="mt-1 text-sm text-ink-3">از رمزی طولانی و منحصربه‌فرد استفاده کنید.</p>
                <form class="mt-6 grid max-w-md gap-4" @submit.prevent="savePassword">
                    <Field label="رمز فعلی" :error="errors('updatePassword').current_password"><input v-model="password.current_password" type="password" autocomplete="current-password" dir="ltr" class="field" /></Field>
                    <Field label="رمز جدید" :error="errors('updatePassword').password"><input v-model="password.password" type="password" autocomplete="new-password" dir="ltr" class="field" /></Field>
                    <Field label="تکرار رمز جدید"><input v-model="password.password_confirmation" type="password" autocomplete="new-password" dir="ltr" class="field" /></Field>
                    <div><Button type="submit" :loading="password.processing">به‌روزرسانی رمز</Button></div>
                </form>
            </section>
        </div>
    </div>
</template>
