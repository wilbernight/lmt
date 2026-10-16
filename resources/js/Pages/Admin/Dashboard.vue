<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Gauge, HardDrive, Lock, ShieldAlert, ShieldCheck, UserPlus, Users, UserX } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import { faNum, formatBytes, jDateTime, relative } from '@/lib/format';

const props = defineProps({ stats: Object, recentUsers: Array, security: Array });

const cards = [
    { label: 'کاربران', value: props.stats.users, sub: `${faNum(props.stats.active)} فعال در هفته`, icon: Users, tone: 'indigo', href: route('admin.users.index') },
    { label: 'ثبت‌نام این هفته', value: props.stats.new_week, sub: `${faNum(props.stats.admins)} مدیر سایت`, icon: UserPlus, tone: 'emerald', href: route('admin.users.index') },
    { label: 'حساب‌های مسدود', value: props.stats.suspended, sub: 'دسترسی قطع‌شده', icon: UserX, tone: 'rose', href: route('admin.users.index', { status: 'suspended' }) },
    { label: 'میزکارها', value: props.stats.workspaces, sub: `${faNum(props.stats.locked_workspaces)} غیرفعال`, icon: Building2, tone: 'sky', href: route('admin.workspaces.index') },
];

const labels = {
    'auth.failed': 'ورود ناموفق', 'auth.lockout': 'قفل موقت ورود', 'auth.blocked_suspended': 'تلاش ورود حساب مسدود',
    'admin.user_suspended': 'مسدودسازی کاربر', 'admin.user_updated': 'تغییر دسترسی کاربر', 'admin.workspace_locked': 'غیرفعال‌سازی میزکار',
};
const danger = (a) => ['auth.failed', 'auth.lockout', 'auth.blocked_suspended', 'admin.user_suspended', 'admin.workspace_locked'].includes(a);
const storagePercent = props.stats.storage_allocated ? Math.round((props.stats.storage_used / props.stats.storage_allocated) * 100) : 0;
</script>

<template>
    <Head title="پنل مدیریت سایت" />

    <PageHeader title="پنل مدیریت سایت" subtitle="کنترل کاربران، دسترسی‌ها و فضای ذخیره‌سازی" :icon="Gauge" tone="rose" />

    <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Link v-for="c in cards" :key="c.label" :href="c.href" class="card p-4 transition-all hover:-translate-y-0.5 hover:shadow-card">
            <span class="flex size-10 items-center justify-center rounded-xl tone-chip" :class="`tone-${c.tone}`"><component :is="c.icon" class="size-5" /></span>
            <p class="num mt-4 text-3xl font-black">{{ faNum(c.value) }}</p>
            <p class="mt-1 text-xs font-semibold text-ink-2">{{ c.label }}</p>
            <p class="text-xs text-ink-3">{{ c.sub }}</p>
        </Link>
    </div>

    <section class="card mt-5 p-5">
        <div class="flex flex-wrap items-center gap-3">
            <HardDrive class="size-5 text-warning" />
            <h2 class="font-bold">فضای ذخیره‌سازی کل</h2>
            <span class="num ms-auto text-sm text-ink-2">{{ formatBytes(stats.storage_used) }} مصرف‌شده از {{ formatBytes(stats.storage_allocated) }} تخصیص‌یافته</span>
        </div>
        <ProgressBar :value="storagePercent" size="md" class="mt-3" />
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="card overflow-hidden">
            <header class="flex items-center gap-2 border-b border-line px-5 py-4"><UserPlus class="size-4 text-accent" /><h2 class="text-sm font-bold">کاربران جدید</h2></header>
            <Link v-for="u in recentUsers" :key="u.id" :href="route('admin.users.show', u.id)" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0 hover:bg-surface-2">
                <Avatar :user="u" size="sm" />
                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ u.name }}</p><p class="truncate text-xs text-ink-3" dir="ltr">{{ u.email }}</p></div>
                <span v-if="u.suspended" class="chip bg-danger-soft text-danger">مسدود</span>
                <span class="text-xs text-ink-3">{{ relative(u.created_at) }}</span>
            </Link>
        </section>

        <section class="card overflow-hidden">
            <header class="flex items-center gap-2 border-b border-line px-5 py-4"><ShieldAlert class="size-4 text-accent" /><h2 class="text-sm font-bold">رخدادهای امنیتی اخیر</h2></header>
            <ul class="max-h-[24rem] divide-y divide-line overflow-y-auto">
                <li v-for="e in security" :key="e.id" class="flex items-start gap-3 px-5 py-3 text-sm">
                    <component :is="danger(e.action) ? Lock : ShieldCheck" class="mt-0.5 size-4 shrink-0" :class="danger(e.action) ? 'text-danger' : 'text-success'" />
                    <div class="min-w-0 flex-1">
                        <p><b>{{ e.user ?? e.properties?.email ?? '—' }}</b> <span class="text-ink-2">{{ labels[e.action] ?? e.action }}</span> <span v-if="e.properties?.target" class="text-ink-3" dir="ltr">{{ e.properties.target }}</span></p>
                        <p class="text-xs text-ink-3">{{ jDateTime(e.created_at) }} · <span dir="ltr">{{ e.ip }}</span></p>
                    </div>
                </li>
                <li v-if="!security.length" class="px-5 py-8 text-center text-xs text-ink-3">رخدادی ثبت نشده است.</li>
            </ul>
        </section>
    </div>
</template>
