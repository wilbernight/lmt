<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Bar, Line } from 'vue-chartjs';
import {
    BarElement, CategoryScale, Chart as ChartJS, Filler, LinearScale, LineElement, PointElement, Tooltip,
} from 'chart.js';
import { Activity, CalendarPlus, ChartColumn, Crown, FolderKanban, HardDrive, Mailbox, ShieldAlert, UserPlus, Users } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import { MONTHS, toJalali } from '@/lib/jalali';
import { faNum, formatBytes, jDate, jDateTime, relative } from '@/lib/format';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Filler, Tooltip);

const props = defineProps({ summary: Object, charts: Object, members: Array, audit: Array });
const page = usePage();
const ws = page.props.workspace;

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
const font = { family: 'Vazirmatn Variable', size: 11 };

const baseOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { display: false }, tooltip: { rtl: true, bodyFont: font, titleFont: font } },
    scales: {
        x: { grid: { display: false }, ticks: { color: css('--ink-3'), font, maxRotation: 0, autoSkipPadding: 12 }, reverse: true },
        y: { beginAtZero: true, grid: { color: css('--line') }, ticks: { color: css('--ink-3'), font, precision: 0, callback: (v) => faNum(v) }, position: 'right' },
    },
}));

const dayLabel = (d) => {
    const j = toJalali(new Date(d));
    return `${faNum(j.jd)} ${MONTHS[j.jm - 1].slice(0, 3)}`;
};
const monthLabel = (d) => MONTHS[toJalali(new Date(d)).jm - 1];

const line = (series, color) => ({
    labels: series.map((p) => dayLabel(p.date)),
    datasets: [{ data: series.map((p) => p.count), borderColor: color, backgroundColor: `color-mix(in oklch, ${color} 18%, transparent)`, fill: true, tension: 0.35, pointRadius: 0, borderWidth: 2 }],
});

const messages = computed(() => line(props.charts.messages, css('--info')));
const letters = computed(() => line(props.charts.letters, css('--warning')));
const tasks = computed(() => ({
    labels: props.charts.tasks_created.map((p) => monthLabel(p.month)),
    datasets: [
        { label: 'ایجادشده', data: props.charts.tasks_created.map((p) => p.count), backgroundColor: css('--accent'), borderRadius: 6 },
        { label: 'انجام‌شده', data: props.charts.tasks_completed.map((p) => p.count), backgroundColor: css('--success'), borderRadius: 6 },
    ],
}));

const sum = (s) => s.reduce((a, p) => a + p.count, 0);
const storage = computed(() => Math.round((props.summary.storage_used / props.summary.storage_limit) * 100));

const actionLabels = {
    'auth.login': 'ورود', 'auth.logout': 'خروج', 'auth.failed': 'ورود ناموفق', 'auth.lockout': 'قفل موقت ورود',
    'workspace.created': 'ساخت میزکار', 'workspace.updated': 'ویرایش میزکار', 'member.updated': 'تغییر نقش عضو',
    'member.removed': 'حذف عضو', 'member.left': 'خروج عضو', 'invitation.sent': 'ارسال دعوت', 'invitation.accepted': 'پذیرش دعوت',
    'project.created': 'ساخت پروژه', 'project.updated': 'ویرایش پروژه', 'project.deleted': 'حذف پروژه', 'project.members_updated': 'تغییر اعضای پروژه',
    'task.deleted': 'حذف وظیفه', 'letter.referred': 'ارجاع نامه', 'customer.created': 'ثبت مشتری', 'customer.deleted': 'حذف مشتری', 'customer.exported': 'خروجی مشتریان',
};
const danger = (a) => ['auth.failed', 'auth.lockout', 'member.removed', 'project.deleted', 'task.deleted', 'customer.deleted', 'customer.exported'].includes(a);
</script>

<template>
    <Head title="مانیتورینگ" />

    <PageHeader :title="`مانیتورینگ میزکار «${ws.name}»`" subtitle="نمای کلی فعالیت تیم در ۳۰ روز و ۱۲ ماه گذشته" :icon="ChartColumn" tone="violet" />

    <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card p-4"><Users class="size-5 text-accent" /><p class="num mt-3 text-2xl font-black">{{ faNum(summary.members) }}</p><p class="text-xs text-ink-3">اعضا · {{ faNum(summary.active) }} فعال در هفته</p></div>
        <div class="card p-4"><UserPlus class="size-5 text-info" /><p class="num mt-3 text-2xl font-black">{{ faNum(summary.invited) }}</p><p class="text-xs text-ink-3">دعوت در انتظار</p></div>
        <div class="card p-4"><FolderKanban class="size-5 text-success" /><p class="num mt-3 text-2xl font-black">{{ faNum(summary.projects) }}</p><p class="text-xs text-ink-3">پروژه فعال · {{ faNum(summary.customers) }} مشتری</p></div>
        <div class="card p-4">
            <HardDrive class="size-5 text-warning" />
            <p class="num mt-3 text-lg font-black">{{ formatBytes(summary.storage_used) }}</p>
            <ProgressBar :value="storage" class="mt-2" />
            <p class="mt-1.5 text-xs text-ink-3">از {{ formatBytes(summary.storage_limit) }}</p>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-xs text-ink-3">
        <span class="flex items-center gap-1.5"><Crown class="size-3.5" /> مالک میزکار: <b class="text-ink-2">{{ summary.owner }}</b></span>
        <span class="flex items-center gap-1.5"><CalendarPlus class="size-3.5" /> زمان ایجاد: <b class="text-ink-2">{{ jDate(summary.created_at, { weekday: true }) }}</b></span>
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-2">
        <section class="card p-5">
            <header class="mb-4 flex items-center justify-between"><h3 class="text-sm font-bold text-info">گفتگوها (۳۰ روز گذشته)</h3><span class="num chip bg-info-soft text-info">{{ faNum(sum(charts.messages)) }} پیام</span></header>
            <div class="h-56"><Line :data="messages" :options="baseOptions" /></div>
        </section>
        <section class="card p-5">
            <header class="mb-4 flex items-center justify-between"><h3 class="text-sm font-bold text-warning">نامه‌ها (۳۰ روز گذشته)</h3><span class="num chip bg-warning-soft text-warning">{{ faNum(sum(charts.letters)) }} نامه</span></header>
            <div class="h-56"><Line :data="letters" :options="baseOptions" /></div>
        </section>
        <section class="card p-5 lg:col-span-2">
            <header class="mb-4 flex flex-wrap items-center gap-3">
                <h3 class="text-sm font-bold">وظایف (۱۲ ماه گذشته)</h3>
                <span class="flex items-center gap-1.5 text-xs text-ink-3"><span class="size-2.5 rounded-sm bg-accent" /> ایجادشده</span>
                <span class="flex items-center gap-1.5 text-xs text-ink-3"><span class="size-2.5 rounded-sm bg-success" /> انجام‌شده</span>
            </header>
            <div class="h-64"><Bar :data="tasks" :options="baseOptions" /></div>
        </section>
    </div>

    <div class="mt-6 grid gap-5 xl:grid-cols-[1.2fr_1fr]">
        <section class="card overflow-hidden">
            <header class="flex items-center gap-2 border-b border-line px-5 py-4"><Activity class="size-4 text-accent" /><h3 class="text-sm font-bold">عملکرد اعضا</h3></header>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[34rem] text-sm">
                    <thead class="text-xs text-ink-3"><tr class="border-b border-line">
                        <th class="px-5 py-2.5 text-start font-semibold">عضو</th><th class="px-3 py-2.5 font-semibold">باز</th><th class="px-3 py-2.5 font-semibold">انجام (۳۰ روز)</th><th class="px-3 py-2.5 font-semibold">تأخیر</th><th class="px-5 py-2.5 text-start font-semibold">آخرین حضور</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="m in members" :key="m.id" class="border-b border-line last:border-0">
                            <td class="px-5 py-3"><span class="flex items-center gap-2.5"><Avatar :user="m" size="sm" /><b>{{ m.name }}</b></span></td>
                            <td class="num px-3 py-3 text-center">{{ faNum(m.open) }}</td>
                            <td class="num px-3 py-3 text-center text-success">{{ faNum(m.done) }}</td>
                            <td class="num px-3 py-3 text-center" :class="m.overdue && 'font-bold text-danger'">{{ faNum(m.overdue) }}</td>
                            <td class="px-5 py-3 text-xs text-ink-3">{{ m.last_seen_at ? relative(m.last_seen_at) : 'هرگز' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card overflow-hidden">
            <header class="flex items-center gap-2 border-b border-line px-5 py-4"><ShieldAlert class="size-4 text-accent" /><h3 class="text-sm font-bold">گزارش رخدادهای امنیتی و مدیریتی</h3></header>
            <ul class="max-h-[26rem] divide-y divide-line overflow-y-auto">
                <li v-for="a in audit" :key="a.id" class="flex items-start gap-3 px-5 py-3 text-sm">
                    <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="danger(a.action) ? 'bg-danger' : 'bg-accent'" />
                    <div class="min-w-0 flex-1">
                        <p><b>{{ a.user ?? 'سیستم' }}</b> <span class="text-ink-2">{{ actionLabels[a.action] ?? a.action }}</span></p>
                        <p class="mt-0.5 text-xs text-ink-3" dir="auto">{{ jDateTime(a.created_at) }} · <span dir="ltr">{{ a.ip }}</span></p>
                    </div>
                </li>
                <li v-if="!audit.length" class="px-5 py-8 text-center text-xs text-ink-3">رخدادی ثبت نشده است.</li>
            </ul>
        </section>
    </div>
</template>
