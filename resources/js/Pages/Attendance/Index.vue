<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Clock, Download, Info, LogIn, LogOut, Timer, UserCheck, Users } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import DatePicker from '@/Components/ui/DatePicker.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { MONTHS, WEEKDAYS_SHORT, persianWeekday, toJalali } from '@/lib/jalali';
import { faNum, formatDuration, jDate, jTime } from '@/lib/format';
import { useNow } from '@/composables/useNow';

const props = defineProps({
    range: Object, days: Array, rows: Array, sessions: Array, isAdmin: Boolean, selected: Number, me: Object, idleMinutes: Number,
});

const page = usePage();
const ws = page.props.workspace;
const now = useNow(30000);
const loadedAt = Date.now();

const go = (params) => router.get(route('attendance.index', ws.id), { from: props.range.from, to: props.range.to, user: props.selected ?? undefined, ...params }, { preserveState: true, preserveScroll: true, replace: true });

const iso = (d) => {
    const x = new Date(d);
    x.setMinutes(x.getMinutes() - x.getTimezoneOffset());
    return x.toISOString().slice(0, 10);
};

const presets = computed(() => {
    const today = new Date();
    const weekStart = new Date(today);
    weekStart.setDate(today.getDate() - persianWeekday(today));
    const j = toJalali(today);
    // First day of the current Jalali month.
    const monthStart = new Date(today);
    monthStart.setDate(today.getDate() - (j.jd - 1));
    return [
        { label: 'امروز', from: iso(today), to: iso(today) },
        { label: 'این هفته', from: iso(weekStart), to: iso(today) },
        { label: 'این ماه', from: iso(monthStart), to: iso(today) },
    ];
});

const exportUrl = computed(() => route('attendance.export', [ws.id, { from: props.range.from, to: props.range.to, user: props.selected ?? undefined }]));

const myToday = computed(() => props.me.seconds + (props.me.in_since ? Math.max(0, (now.value - new Date(props.me.as_of).getTime()) / 1000) : 0));
const maxDay = computed(() => Math.max(1, ...props.rows.flatMap((r) => Object.values(r.per_day))));
const showGrid = computed(() => props.days.length <= 31);
const dayHead = (d) => {
    const date = new Date(`${d}T12:00:00`);
    return { day: faNum(toJalali(date).jd), week: WEEKDAYS_SHORT[persianWeekday(date)], friday: date.getDay() === 5 };
};
const reasons = { logout: 'خروج', timeout: 'عدم فعالیت', relogin: 'ورود مجدد' };
const memberName = (id) => props.rows.find((r) => r.user.id === id)?.user;
const ratio = (r) => (r.total ? Math.round((r.logged / r.total) * 100) : 0);
</script>

<template>
    <Head title="حضور و ساعت کار" />

    <PageHeader title="حضور و ساعت کار" :subtitle="isAdmin ? 'ورود و خروج پرسنل، به تفکیک روز' : 'ساعات حضور شما'" :icon="Clock" tone="sky">
        <a :href="exportUrl" class="inline-flex h-10 items-center gap-2 rounded-xl border border-line-strong bg-surface px-4 text-sm font-semibold shadow-soft hover:bg-surface-2"><Download class="size-4" /> خروجی اکسل</a>
    </PageHeader>

    <!-- Today -->
    <div class="mt-6 grid gap-3 sm:grid-cols-3">
        <div class="card flex items-center gap-4 p-4">
            <span class="flex size-11 items-center justify-center rounded-xl tone-chip tone-sky"><LogIn class="size-5" /></span>
            <div><p class="text-xs text-ink-3">ورود امروز شما</p><p class="num text-xl font-black">{{ me.first_in ? jTime(me.first_in) : '—' }}</p></div>
        </div>
        <div class="card flex items-center gap-4 p-4">
            <span class="flex size-11 items-center justify-center rounded-xl tone-chip tone-emerald"><Clock class="size-5" /></span>
            <div><p class="text-xs text-ink-3">حضور امروز</p><p class="num text-xl font-black">{{ formatDuration(myToday, { short: true }) }}</p></div>
        </div>
        <div class="card flex items-center gap-4 p-4">
            <span class="flex size-11 items-center justify-center rounded-xl tone-chip" :class="me.in_since ? 'tone-emerald' : 'tone-slate'"><UserCheck class="size-5" /></span>
            <div><p class="text-xs text-ink-3">وضعیت</p><p class="text-sm font-bold">{{ me.in_since ? `حاضر از ساعت ${jTime(me.in_since)}` : 'خارج شده' }}</p></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mt-6 flex flex-wrap items-end gap-2">
        <div class="w-40"><label class="label">از</label><DatePicker :model-value="`${range.from}T12:00:00`" compact @update:model-value="$event && go({ from: iso($event) })" /></div>
        <div class="w-40"><label class="label">تا</label><DatePicker :model-value="`${range.to}T12:00:00`" compact @update:model-value="$event && go({ to: iso($event) })" /></div>
        <button v-for="p in presets" :key="p.label" class="h-9 rounded-lg px-3 text-sm font-semibold" :class="range.from === p.from && range.to === p.to ? 'bg-accent-soft text-accent-ink' : 'text-ink-3 hover:bg-surface-3'" @click="go({ from: p.from, to: p.to })">{{ p.label }}</button>
        <select v-if="isAdmin" class="field ms-auto h-9 w-48 py-1 text-sm" :value="selected ?? ''" @change="go({ user: $event.target.value || undefined })">
            <option value="">همه پرسنل</option>
            <option v-for="r in rows" :key="r.user.id" :value="r.user.id">{{ r.user.name }}</option>
        </select>
    </div>

    <!-- Summary per person -->
    <section class="card mt-4 overflow-x-auto">
        <table class="w-full text-sm" :class="showGrid ? 'min-w-[60rem]' : 'min-w-[40rem]'">
            <thead class="border-b border-line bg-surface-2/60 text-xs text-ink-3">
                <tr>
                    <th class="sticky start-0 bg-surface-2 px-5 py-3 text-start font-semibold">پرسنل</th>
                    <th class="px-3 py-3 font-semibold">روزهای حضور</th>
                    <th class="px-3 py-3 font-semibold">جمع حضور</th>
                    <th class="px-3 py-3 font-semibold" title="زمانی که روی وظایف ثبت شده">کار ثبت‌شده</th>
                    <template v-if="showGrid">
                        <th v-for="d in days" :key="d" class="px-0.5 py-2 text-center text-[0.6rem] font-semibold" :class="dayHead(d).friday && 'text-danger'">
                            {{ dayHead(d).week }}<br />{{ dayHead(d).day }}
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in rows" :key="r.user.id" class="border-b border-line last:border-0" :class="selected === r.user.id && 'bg-accent-soft/30'">
                    <td class="sticky start-0 bg-surface px-5 py-3">
                        <button class="flex items-center gap-2.5 text-start" @click="isAdmin && go({ user: selected === r.user.id ? undefined : r.user.id })">
                            <Avatar :user="r.user" size="sm" />
                            <span>
                                <span class="block font-semibold">{{ r.user.name }}</span>
                                <span v-if="r.in_now" class="flex items-center gap-1 text-[0.7rem] text-success"><span class="size-1.5 rounded-full bg-success" /> حاضر از {{ jTime(r.in_since) }}</span>
                                <span v-else class="text-[0.7rem] text-ink-3">{{ r.job_title || 'خارج' }}</span>
                            </span>
                        </button>
                    </td>
                    <td class="num px-3 py-3 text-center">{{ faNum(r.days_present) }}</td>
                    <td class="num px-3 py-3 text-center font-bold">{{ formatDuration(r.total, { short: true }) }}</td>
                    <td class="num px-3 py-3 text-center">
                        {{ formatDuration(r.logged, { short: true }) }}
                        <span v-if="r.total" class="block text-[0.65rem] text-ink-3">{{ faNum(ratio(r)) }}٪ از حضور</span>
                    </td>
                    <template v-if="showGrid">
                        <td v-for="d in days" :key="d" class="px-0.5 py-2">
                            <div
                                class="mx-auto flex size-7 items-center justify-center rounded-md text-[0.55rem] font-bold"
                                :class="r.per_day[d] ? 'text-white' : 'bg-surface-3 text-transparent'"
                                :style="r.per_day[d] ? { background: `color-mix(in oklch, var(--accent) ${25 + (r.per_day[d] / maxDay) * 75}%, var(--surface))` } : {}"
                                :title="`${jDate(`${d}T12:00:00`)}: ${formatDuration(r.per_day[d])}`"
                            >
                                {{ r.per_day[d] ? faNum(Math.round(r.per_day[d] / 360) / 10) : '' }}
                            </div>
                        </td>
                    </template>
                </tr>
            </tbody>
        </table>
    </section>
    <p v-if="showGrid" class="mt-2 text-[0.7rem] text-ink-3">اعداد خانه‌ها = ساعت حضور در آن روز.</p>

    <!-- Sessions -->
    <section class="card mt-6 overflow-hidden">
        <header class="flex items-center gap-2 border-b border-line px-5 py-4">
            <Timer class="size-4 text-accent" />
            <h2 class="text-sm font-bold">ورود و خروج‌ها <span v-if="selected" class="font-normal text-ink-3">— {{ memberName(selected)?.name }}</span></h2>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm">
                <thead class="text-xs text-ink-3">
                    <tr class="border-b border-line">
                        <th v-if="isAdmin && !selected" class="px-5 py-2.5 text-start font-semibold">پرسنل</th>
                        <th class="px-5 py-2.5 text-start font-semibold">تاریخ</th>
                        <th class="px-3 py-2.5 font-semibold">ورود</th>
                        <th class="px-3 py-2.5 font-semibold">خروج</th>
                        <th class="px-3 py-2.5 font-semibold">مدت</th>
                        <th class="px-3 py-2.5 font-semibold">نحوه خروج</th>
                        <th v-if="isAdmin" class="px-5 py-2.5 font-semibold">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in sessions" :key="s.id" class="border-b border-line last:border-0">
                        <td v-if="isAdmin && !selected" class="px-5 py-2.5"><span class="flex items-center gap-2"><Avatar :user="memberName(s.user_id)" size="xs" /> {{ memberName(s.user_id)?.name }}</span></td>
                        <td class="px-5 py-2.5">{{ jDate(s.started_at, { weekday: true, year: false }) }}</td>
                        <td class="num px-3 py-2.5 text-center"><span class="inline-flex items-center gap-1 text-success"><LogIn class="size-3.5" /> {{ jTime(s.started_at) }}</span></td>
                        <td class="num px-3 py-2.5 text-center">
                            <span v-if="s.ended_at" class="inline-flex items-center gap-1 text-danger"><LogOut class="size-3.5" /> {{ jTime(s.ended_at) }}</span>
                            <span v-else class="chip bg-success-soft text-success">حاضر</span>
                        </td>
                        <td class="num px-3 py-2.5 text-center font-semibold">{{ formatDuration(s.open ? s.seconds + (now - loadedAt) / 1000 : s.seconds, { short: true }) }}</td>
                        <td class="px-3 py-2.5 text-center text-xs" :class="s.end_reason === 'timeout' ? 'text-warning' : 'text-ink-3'">{{ reasons[s.end_reason] ?? '—' }}</td>
                        <td v-if="isAdmin" class="px-5 py-2.5 text-center font-mono text-xs text-ink-3" dir="ltr">{{ s.ip }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-if="!sessions.length" :icon="Users" title="در این بازه ورودی ثبت نشده" compact />
        </div>
    </section>

    <p class="mt-4 flex items-start gap-2 text-xs leading-6 text-ink-3">
        <Info class="mt-0.5 size-4 shrink-0" />
        ورود با وارد شدن به پنل ثبت می‌شود و خروج با زدن «خروج». اگر کسی بدون خروج مرورگر را ببندد، بعد از {{ faNum(idleMinutes) }} دقیقه عدم فعالیت، خروجش در زمان آخرین فعالیت ثبت می‌شود و زمان‌سنج وظیفه‌اش هم متوقف می‌شود.
    </p>
</template>
