<script setup>
import { CircleCheck, FolderKanban, Lock, MessagesSquare, ShieldCheck } from '@lucide/vue';
import Toasts from '@/Components/ui/Toasts.vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import NavProgress from '@/Components/ui/NavProgress.vue';
</script>

<template>
    <div class="grid min-h-dvh bg-bg lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
        <main class="flex flex-col px-5 py-8 sm:px-10">
            <a href="/" class="flex items-center" aria-label="WilberNight"><BrandLogo size="lg" /></a>
            <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10">
                <slot />
            </div>
            <p class="flex items-center justify-center gap-1.5 text-xs text-ink-3"><Lock class="size-3.5" /> ارتباط شما رمزنگاری‌شده و اطلاعات‌تان محافظت‌شده است.</p>
        </main>

        <aside class="relative hidden overflow-hidden lg:block">
            <div class="absolute inset-0 gradient-accent" />
            <div class="absolute inset-0 opacity-30 [background-image:radial-gradient(circle_at_20%_20%,white_0,transparent_35%),radial-gradient(circle_at_80%_70%,white_0,transparent_30%)]" />
            <div class="absolute inset-0 [background-image:linear-gradient(rgba(255,255,255,.07)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.07)_1px,transparent_1px)] [background-size:44px_44px]" />

            <div class="relative flex h-full flex-col justify-center px-14 text-white xl:px-20">
                <h2 class="max-w-md text-4xl font-black leading-[1.35]">همه کارهای تیم، در یک دفتر منظم.</h2>
                <p class="mt-4 max-w-md text-base leading-8 text-white/80">پروژه‌ها، وظایف، گفتگو، نامه‌های اداری و پرونده مشتریان را یک‌جا مدیریت کنید؛ سریع، زیبا و امن.</p>

                <div class="mt-12 grid max-w-lg gap-4">
                    <div class="animate-slide-up flex items-center gap-4 rounded-2xl bg-white/12 p-4 backdrop-blur-md ring-1 ring-white/20">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-white/20"><FolderKanban class="size-5" /></span>
                        <div><p class="font-bold">برد کانبان با کشیدن و رها کردن</p><p class="text-sm text-white/70">وضعیت هر کار را در یک نگاه ببینید.</p></div>
                    </div>
                    <div class="animate-slide-up flex items-center gap-4 rounded-2xl bg-white/12 p-4 backdrop-blur-md ring-1 ring-white/20 [animation-delay:80ms]">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-white/20"><MessagesSquare class="size-5" /></span>
                        <div><p class="font-bold">گفتگوی لحظه‌ای و کارتابل نامه</p><p class="text-sm text-white/70">ارجاع، پاراف و پیگیری بدون کاغذ.</p></div>
                    </div>
                    <div class="animate-slide-up flex items-center gap-4 rounded-2xl bg-white/12 p-4 backdrop-blur-md ring-1 ring-white/20 [animation-delay:160ms]">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-white/20"><ShieldCheck class="size-5" /></span>
                        <div><p class="font-bold">امنیت در اولویت</p><p class="text-sm text-white/70">ورود دومرحله‌ای، ثبت رخدادها و کنترل دسترسی دقیق.</p></div>
                    </div>
                </div>

                <ul class="mt-10 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/80">
                    <li class="flex items-center gap-1.5"><CircleCheck class="size-4" /> تقویم شمسی</li>
                    <li class="flex items-center gap-1.5"><CircleCheck class="size-4" /> حالت تاریک</li>
                    <li class="flex items-center gap-1.5"><CircleCheck class="size-4" /> جستجوی سراسری</li>
                </ul>
            </div>
        </aside>
        <NavProgress />
        <Toasts />
    </div>
</template>
