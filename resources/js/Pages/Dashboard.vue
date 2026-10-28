<script setup>
import { computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlarmClock, ArrowLeft, CalendarCheck, CheckCheck, Crown, Eye, Flame, Mailbox, Plus, Sparkles, StickyNote, Trophy,
} from '@lucide/vue';
import Avatar from '@/Components/ui/Avatar.vue';
import AvatarStack from '@/Components/ui/AvatarStack.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import OrderableTasks from '@/Components/tasks/OrderableTasks.vue';
import { faNum, greeting, jDate, relative } from '@/lib/format';
import { openCreateTask, taskModal } from '@/composables/useTaskModal';

const props = defineProps({
    stats: Object,
    myTasks: Array,
    following: Array,
    overdue: Array,
    busiest: Object,
    recentLetters: Array,
    pinnedNotes: Array,
    otherWorkspaces: Array,
});

const page = usePage();

// Ticking or editing a task anywhere on the page refreshes the cards and reports at once.
watch(
    () => taskModal.version,
    () => router.reload({ only: ['stats', 'myTasks', 'following', 'overdue', 'busiest', 'counters'] }),
);
const ws = computed(() => page.props.workspace);
const user = computed(() => page.props.auth.user);

const cards = computed(() => [
    { key: 'today', label: 'کارهای امروز من', value: props.stats.today, icon: CalendarCheck, tone: 'emerald', href: route('tasks.index', [ws.value.id, { due: 'today' }]) },
    { key: 'overdue', label: 'کارهای دارای تأخیر', value: props.stats.overdue, icon: AlarmClock, tone: 'rose', href: route('tasks.index', [ws.value.id, { due: 'overdue' }]) },
    { key: 'following', label: 'کارهای قابل پیگیری', value: props.stats.following, icon: Eye, tone: 'sky', href: route('tasks.index', [ws.value.id, { tab: 'following' }]) },
    { key: 'week', label: 'انجام‌شده این هفته', value: props.stats.completed_week, icon: Trophy, tone: 'amber', href: route('tasks.index', [ws.value.id, { tab: 'done' }]) },
]);

const priorityTone = { normal: 'slate', urgent: 'amber', instant: 'rose' };
</script>

<template>
    <Head :title="`میزکار ${ws.name}`" />

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-6">
            <!-- Hero -->
            <section class="relative overflow-hidden rounded-3xl border border-line bg-surface p-6 shadow-card sm:p-8">
                <div class="relative flex flex-wrap items-end gap-4">
                    <div class="flex-1">
                        <p class="flex items-center gap-2 text-sm font-semibold text-accent-ink"><Sparkles class="size-4" /> {{ greeting() }}، {{ user.name.split(' ')[0] }}</p>
                        <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">میزکار «<span class="text-gradient">{{ ws.name }}</span>»</h1>
                        <p class="mt-2 text-sm text-ink-3">{{ jDate(new Date(), { weekday: true }) }} · {{ faNum(stats.open) }} کار باز دارید</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="hidden text-start sm:block">
                            <p class="text-xs text-ink-3">همکاران من</p>
                            <AvatarStack :users="ws.members" :max="6" size="sm" class="mt-1" />
                        </div>
                        <button v-if="page.props.workspace.role !== 'guest'" class="flex h-11 items-center gap-2 rounded-xl btn-solid px-5 text-sm font-semibold" @click="openCreateTask()">
                            <Plus class="size-4" /> وظیفه جدید
                        </button>
                    </div>
                </div>

                <div class="relative mt-7 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Link
                        v-for="c in cards"
                        :key="c.key"
                        :href="c.href"
                        class="group rounded-2xl border border-line bg-surface-2 p-4 transition-all hover:-translate-y-0.5 hover:border-line-strong hover:bg-surface hover:shadow-card"
                    >
                        <div class="flex items-center justify-between">
                            <span class="flex size-10 items-center justify-center rounded-xl tone-chip" :class="`tone-${c.tone}`"><component :is="c.icon" class="size-5" /></span>
                            <ArrowLeft class="size-4 text-ink-3 opacity-0 transition-all group-hover:-translate-x-1 group-hover:opacity-100" />
                        </div>
                        <p class="num mt-4 text-3xl font-black">{{ faNum(c.value) }}</p>
                        <p class="mt-1 text-xs font-semibold text-ink-3">{{ c.label }}</p>
                    </Link>
                </div>
            </section>

            <!-- Insight strip -->
            <div class="grid gap-4 md:grid-cols-2">
                <div class="card flex items-center gap-4 p-4">
                    <span class="flex size-11 items-center justify-center rounded-xl tone-chip tone-amber"><Crown class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-ink-3">پرمشغله‌ترین همکار (بیشترین وظیفه باز)</p>
                        <p v-if="busiest" class="mt-0.5 flex items-center gap-2 text-sm font-bold"><Avatar :user="busiest" size="xs" /> {{ busiest.name }} <span class="chip bg-surface-3 text-ink-2">{{ faNum(busiest.count) }} کار</span></p>
                        <p v-else class="mt-0.5 text-sm font-semibold text-ink-2">همه کارها انجام شده‌اند 🎉</p>
                    </div>
                </div>
                <div class="card flex items-center gap-4 p-4">
                    <span class="flex size-11 items-center justify-center rounded-xl tone-chip" :class="stats.overdue ? 'tone-rose' : 'tone-emerald'">
                        <component :is="stats.overdue ? Flame : CheckCheck" class="size-5" />
                    </span>
                    <div>
                        <p class="text-xs text-ink-3">وضعیت تأخیرها</p>
                        <p class="mt-0.5 text-sm font-bold">{{ stats.overdue ? `${faNum(stats.overdue)} وظیفه از مهلت گذشته است` : 'تبریک! هیچ وظیفه دارای تأخیری ندارید.' }}</p>
                    </div>
                </div>
            </div>

            <!-- Task lists -->
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="card overflow-hidden">
                    <header class="flex items-center gap-3 border-b border-line px-5 py-4">
                        <span class="size-2.5 rounded-full bg-success" />
                        <h2 class="font-bold">کارهای من</h2>
                        <span class="chip bg-success-soft text-success">{{ faNum(myTasks.length) }} مورد</span>
                        <Link :href="route('tasks.index', ws.id)" class="ms-auto text-xs font-semibold text-accent-ink hover:underline">همه</Link>
                    </header>
                    <div class="max-h-[28rem] overflow-y-auto">
                        <OrderableTasks :tasks="myTasks" />
                        <EmptyState v-if="!myTasks.length" :icon="CheckCheck" title="کار بازی ندارید" text="وظایفی که به شما سپرده شود اینجا نمایش داده می‌شود." compact />
                    </div>
                </section>

                <section class="card overflow-hidden">
                    <header class="flex items-center gap-3 border-b border-line px-5 py-4">
                        <span class="size-2.5 rounded-full bg-danger" />
                        <h2 class="font-bold">پیگیری از دیگران</h2>
                        <span class="chip bg-danger-soft text-danger">{{ faNum(following.length) }} مورد</span>
                        <button
                            v-if="page.props.workspace.role !== 'guest'"
                            class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-accent-ink hover:bg-accent-soft"
                            title="ساخت وظیفه پیگیری؛ حتی بدون ذکر نام همکار"
                            @click="openCreateTask({ assignees: [] })"
                        ><Plus class="size-3.5" /> پیگیری جدید</button>
                        <Link :href="route('tasks.index', [ws.id, { tab: 'following' }])" class="ms-auto text-xs font-semibold text-accent-ink hover:underline">همه</Link>
                    </header>
                    <div class="max-h-[28rem] overflow-y-auto">
                        <OrderableTasks :tasks="following" />
                        <EmptyState v-if="!following.length" :icon="Eye" title="کاری برای پیگیری ندارید" text="وظیفه‌ای را برای پیگیری بسازید — با نام همکار یا بدون ذکر نام." compact />
                    </div>
                </section>
            </div>
        </div>

        <!-- Side column -->
        <aside class="space-y-6">
            <div class="card overflow-hidden">
                <div class="h-20 gradient-accent opacity-90" />
                <div class="-mt-10 px-5 pb-5 text-center">
                    <Avatar :user="user" size="xl" ring class="mx-auto" :status="false" />
                    <p class="mt-3 font-bold">{{ user.name }}</p>
                    <p class="text-xs text-ink-3">{{ ws.role_label }}</p>
                    <p class="mt-3 inline-flex rounded-full bg-surface-3 px-3 py-1 text-xs font-semibold text-ink-2">{{ greeting() }}! {{ jDate(new Date(), { weekday: true, year: false }) }}</p>
                </div>
            </div>

            <div class="card p-5">
                <h3 class="mb-3 flex items-center gap-2 text-sm font-bold"><Mailbox class="size-4 text-orange-500" /> آخرین نامه‌ها</h3>
                <ul class="space-y-1">
                    <li v-for="l in recentLetters" :key="l.id">
                        <Link :href="route('letters.show', [ws.id, l.id])" class="flex items-center gap-3 rounded-xl p-2 hover:bg-surface-2">
                            <Avatar :user="l.sender" size="sm" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold">{{ l.subject }}</span>
                                <span class="block text-[0.7rem] text-ink-3">{{ l.sender.name }} · {{ relative(l.created_at) }}</span>
                            </span>
                            <span v-if="l.priority !== 'normal'" class="size-2 rounded-full tone-dot" :class="`tone-${priorityTone[l.priority]}`" />
                        </Link>
                    </li>
                    <li v-if="!recentLetters.length" class="py-3 text-center text-xs text-ink-3">نامه جدیدی ندارید.</li>
                </ul>
            </div>

            <div v-if="pinnedNotes.length" class="card p-5">
                <h3 class="mb-3 flex items-center gap-2 text-sm font-bold"><StickyNote class="size-4 text-amber-500" /> یادداشت‌های سنجاق‌شده</h3>
                <div class="grid gap-2">
                    <Link v-for="n in pinnedNotes" :key="n.id" :href="route('notes.index', ws.id)" class="rounded-xl border border-line p-3 text-sm [background:var(--note)]" :class="`note-${n.color}`">
                        <p v-if="n.title" class="font-bold">{{ n.title }}</p>
                        <p class="line-clamp-2 text-xs leading-5 text-ink-2">{{ n.body || (n.items || []).map((i) => i.text).join('، ') }}</p>
                    </Link>
                </div>
            </div>

            <div class="card p-5">
                <h3 class="mb-3 text-sm font-bold">سایر میزکارهای شما</h3>
                <div class="space-y-2">
                    <Link v-for="w in otherWorkspaces" :key="w.id" :href="route('workspace.dashboard', w.id)" class="flex items-center gap-3 rounded-xl border border-line p-3 transition-colors hover:border-line-strong hover:bg-surface-2">
                        <span class="flex size-9 items-center justify-center rounded-lg font-black tone-chip" :class="`tone-${w.color}`">{{ w.name.slice(0, 1) }}</span>
                        <span class="flex-1 truncate text-sm font-semibold">{{ w.name }}</span>
                        <span class="chip bg-surface-3 text-ink-2">{{ faNum(w.members_count) }}</span>
                    </Link>
                    <Link :href="route('workspaces.create')" class="flex flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-line-strong p-5 text-sm font-semibold text-accent-ink transition-colors hover:border-accent hover:bg-accent-soft">
                        <Plus class="size-5" /> ایجاد میزکار جدید
                    </Link>
                </div>
            </div>
        </aside>
    </div>
</template>
