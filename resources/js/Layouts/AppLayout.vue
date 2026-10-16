<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell, Building2, ChartColumn, ChevronDown, Contact, FolderKanban, House, ListChecks, LogOut, Mailbox, Menu,
    Gauge, MessagesSquare, Monitor, Moon, Plus, Search, Settings, ShieldCheck, SlidersHorizontal, StickyNote, Sun, UserPlus, Users, X,
} from '@lucide/vue';
import Avatar from '@/Components/ui/Avatar.vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import Dropdown from '@/Components/ui/Dropdown.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import Toasts from '@/Components/ui/Toasts.vue';
import NavProgress from '@/Components/ui/NavProgress.vue';
import ConfirmHost from '@/Components/ui/ConfirmHost.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import CommandPalette from '@/Components/CommandPalette.vue';
import NotificationPanel from '@/Components/NotificationPanel.vue';
import TaskModal from '@/Components/tasks/TaskModal.vue';
import TaskCreateModal from '@/Components/tasks/TaskCreateModal.vue';
import { openCreateTask } from '@/composables/useTaskModal';
import { toast } from '@/composables/useToast';
import { faNum, formatBytes } from '@/lib/format';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const ws = computed(() => page.props.workspace);
const counters = computed(() => page.props.counters ?? {});
const current = computed(() => page.url);

const drawer = ref(false);
const palette = ref(false);
const notifications = ref(false);

const adminNav = computed(() =>
    user.value?.is_admin
        ? [
              { label: 'پنل مدیریت سایت', href: route('admin.dashboard'), icon: Gauge, tone: 'rose', match: '/admin', exact: true },
              { label: 'کاربران', href: route('admin.users.index'), icon: Users, tone: 'rose', match: '/admin/users' },
              { label: 'میزکارها', href: route('admin.workspaces.index'), icon: Building2, tone: 'rose', match: '/admin/workspaces' },
              { label: 'تنظیمات سایت', href: route('admin.settings'), icon: SlidersHorizontal, tone: 'rose', match: '/admin/settings' },
          ]
        : [],
);

const nav = computed(() => {
    const onAdmin = current.value.startsWith('/admin');
    if (onAdmin || !ws.value) return adminNav.value.length ? [adminNav.value] : [];
    const id = ws.value.id;

    const items = [
        { label: 'داشبورد', href: route('workspace.dashboard', id), icon: House, tone: 'rose', match: `/w/${id}`, exact: true },
        { label: 'گفتگو', href: route('chat.index', id), icon: MessagesSquare, tone: 'sky', match: `/w/${id}/chat`, count: counters.value.messages },
        { label: 'پروژه‌ها', href: route('projects.index', id), icon: FolderKanban, tone: 'indigo', match: `/w/${id}/projects` },
        { label: 'وظایف', href: route('tasks.index', id), icon: ListChecks, tone: 'emerald', match: `/w/${id}/tasks`, count: counters.value.tasks, also: `/w/${id}/calendar` },
        { label: 'نامه‌ها', href: route('letters.index', id), icon: Mailbox, tone: 'orange', match: `/w/${id}/letters`, count: counters.value.letters },
        { label: 'یادداشت‌های من', href: route('notes.index', id), icon: StickyNote, tone: 'amber', match: `/w/${id}/notes` },
    ];

    const more = [];
    if (ws.value.role !== 'guest') {
        more.push({ label: 'پرونده مشتریان', href: route('customers.index', id), icon: Contact, tone: 'teal', match: `/w/${id}/customers` });
    }
    if (ws.value.is_admin) {
        more.push({ label: 'مانیتورینگ', href: route('monitoring', id), icon: ChartColumn, tone: 'violet', match: `/w/${id}/monitoring` });
    }

    const groups = [items, more];
    if (adminNav.value.length) groups.push([adminNav.value[0]]);
    return groups;
});

const isActive = (item) => {
    const path = current.value.split('?')[0];
    if (item.exact) return path === item.match || path === `${item.match}/`;
    return path.startsWith(item.match) || (item.also && path.startsWith(item.also));
};

const sectionTone = computed(() => nav.value.flat().find(isActive)?.tone ?? 'indigo');

// Theme -------------------------------------------------------------
const theme = ref('system');
try { theme.value = localStorage.getItem('theme') || user.value?.theme || 'system'; } catch { theme.value = user.value?.theme || 'system'; }

const applyTheme = () => {
    const dark = theme.value === 'dark' || (theme.value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
};

const cycleTheme = () => {
    theme.value = { system: 'light', light: 'dark', dark: 'system' }[theme.value];
    try { localStorage.setItem('theme', theme.value); } catch { /* storage unavailable */ }
    applyTheme();
};

const themeIcon = computed(() => ({ light: Sun, dark: Moon, system: Monitor })[theme.value]);

// Accent color follows the workspace --------------------------------
watch(
    () => ws.value?.color,
    (color) => document.documentElement.setAttribute('data-accent', color || 'indigo'),
    { immediate: true },
);

// Keyboard shortcuts -------------------------------------------------
const onKey = (e) => {
    const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable;
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        palette.value = true;
    } else if (!typing && e.key === 'n' && ws.value && !e.ctrlKey && !e.metaKey && !e.altKey) {
        e.preventDefault();
        openCreateTask();
    } else if (!typing && e.key === '/' && ws.value) {
        e.preventDefault();
        palette.value = true;
    }
};

// Realtime notifications --------------------------------------------
let channel = null;
onMounted(() => {
    document.addEventListener('keydown', onKey);
    if (window.Echo && user.value) {
        channel = window.Echo.private(`users.${user.value.id}`).listen('.notified', (e) => {
            toast.info(e.notification?.title);
            router.reload({ only: ['counters'] });
        });
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    if (channel && user.value) window.Echo?.leave(`users.${user.value.id}`);
});

router.on('navigate', () => (drawer.value = false));

const storagePercent = computed(() => (ws.value ? Math.round((ws.value.storage_used / ws.value.storage_limit) * 100) : 0));
const otherMembers = computed(() => (ws.value?.members ?? []).filter((m) => m.id !== user.value?.id).slice(0, 8));
</script>

<template>
    <div class="min-h-dvh bg-bg">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 start-0 z-40 flex w-[17rem] flex-col border-e border-line bg-surface transition-transform duration-300 lg:translate-x-0"
            :class="drawer ? 'translate-x-0 shadow-pop' : 'translate-x-full'"
        >
            <div class="flex h-16 shrink-0 items-center gap-3 px-5">
                <Link :href="route('dashboard')" class="flex items-center" aria-label="WilberNight"><BrandLogo /></Link>
                <button class="ms-auto rounded-lg p-1.5 text-ink-3 hover:bg-surface-3 lg:hidden" aria-label="بستن منو" @click="drawer = false"><X class="size-5" /></button>
            </div>

            <!-- Workspace switcher -->
            <div v-if="ws" class="px-3 pb-3">
                <Dropdown width="w-[15.5rem]">
                    <template #trigger="{ toggle }">
                        <button class="flex w-full items-center gap-3 rounded-xl border border-line bg-surface-2 p-2.5 text-start transition-colors hover:border-line-strong" @click="toggle">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg text-sm font-black tone-chip" :class="`tone-${ws.color}`">{{ ws.name.slice(0, 1) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold">{{ ws.name }}</span>
                                <span class="block text-[0.7rem] text-ink-3">{{ ws.role_label }} · {{ faNum(ws.members.length) }} عضو</span>
                            </span>
                            <ChevronDown class="size-4 text-ink-3" />
                        </button>
                    </template>
                    <p class="px-2.5 pb-1 pt-1.5 text-[0.7rem] font-semibold text-ink-3">میزکارهای شما</p>
                    <MenuItem v-for="w in page.props.workspaces" :key="w.id" :href="route('workspace.dashboard', w.id)" :active="w.id === ws.id">
                        <span class="size-2.5 rounded-full tone-dot" :class="`tone-${w.color}`" />
                        <span class="flex-1 truncate">{{ w.name }}</span>
                    </MenuItem>
                    <div class="my-1 border-t border-line" />
                    <MenuItem :href="route('workspaces.create')"><Plus class="size-4" /> ایجاد میزکار جدید</MenuItem>
                    <MenuItem v-if="ws.is_admin" :href="route('workspace.settings', ws.id)"><Settings class="size-4" /> تنظیمات میزکار</MenuItem>
                </Dropdown>
            </div>

            <nav class="flex-1 space-y-5 overflow-y-auto px-3 pb-4">
                <ul v-for="(group, gi) in nav" :key="gi" class="space-y-0.5">
                    <li v-for="item in group" :key="item.label">
                        <Link
                            :href="item.href"
                            class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-all"
                            :class="isActive(item) ? 'bg-surface-3 text-ink' : 'text-ink-2 hover:bg-surface-2 hover:text-ink'"
                        >
                            <span v-if="isActive(item)" class="absolute inset-y-2 start-0 w-1 rounded-full tone-bar" :class="`tone-${item.tone}`" />
                            <span class="flex size-8 items-center justify-center rounded-lg tone-chip transition-transform group-hover:scale-105" :class="`tone-${item.tone}`">
                                <component :is="item.icon" class="size-[1.05rem]" />
                            </span>
                            <span class="flex-1">{{ item.label }}</span>
                            <span v-if="item.count" class="chip tone-chip num" :class="`tone-${item.tone}`">{{ faNum(item.count) }}</span>
                        </Link>
                    </li>
                </ul>

                <!-- Colleagues -->
                <div v-if="ws">
                    <div class="mb-2 flex items-center justify-between px-3">
                        <span class="flex items-center gap-1.5 text-xs font-bold text-ink-3"><Users class="size-3.5" /> همکارانم</span>
                        <Link v-if="ws.is_admin" :href="route('workspace.settings', ws.id) + '#members'" class="rounded-md p-1 text-ink-3 hover:bg-surface-3 hover:text-accent" aria-label="دعوت عضو جدید">
                            <UserPlus class="size-3.5" />
                        </Link>
                    </div>
                    <ul class="space-y-0.5">
                        <li v-for="m in otherMembers" :key="m.id">
                            <Link :href="route('chat.direct', ws.id)" method="post" as="button" :data="{ user_id: m.id }" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-1.5 text-start text-sm text-ink-2 hover:bg-surface-2">
                                <Avatar :user="m" size="sm" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-ink">{{ m.name }}</span>
                                    <span class="block truncate text-[0.7rem] text-ink-3">{{ m.job_title || (m.online ? 'آنلاین' : 'آفلاین') }}</span>
                                </span>
                            </Link>
                        </li>
                        <li v-if="!otherMembers.length" class="px-3 text-xs leading-5 text-ink-3">هنوز همکاری ندارید.
                            <Link v-if="ws.is_admin" :href="route('workspace.settings', ws.id) + '#members'" class="font-semibold text-accent-ink hover:underline">دعوت کنید</Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div v-if="ws" class="border-t border-line p-4">
                <div class="mb-1.5 flex justify-between text-[0.7rem] text-ink-3">
                    <span>فضای استفاده‌شده</span>
                    <span class="num">{{ formatBytes(ws.storage_used) }} از {{ formatBytes(ws.storage_limit) }}</span>
                </div>
                <ProgressBar :value="storagePercent" />
            </div>
        </aside>

        <div v-if="drawer" class="fixed inset-0 z-30 bg-[var(--scrim)] lg:hidden" @click="drawer = false" />

        <!-- Main column -->
        <div class="lg:ps-[17rem]">
            <header class="glass sticky top-0 z-20 border-b border-line">
                <div class="flex h-16 items-center gap-2 px-4 sm:gap-3 sm:px-6">
                    <button class="rounded-xl p-2 text-ink-2 hover:bg-surface-3 lg:hidden" aria-label="منو" @click="drawer = true"><Menu class="size-5" /></button>

                    <button
                        v-if="ws"
                        class="flex h-10 max-w-md flex-1 items-center gap-2.5 rounded-xl border border-line bg-surface-2 px-3 text-sm text-ink-3 transition-colors hover:border-line-strong"
                        @click="palette = true"
                    >
                        <Search class="size-4" />
                        <span class="flex-1 text-start">جستجو در کارها، پروژه‌ها، نامه‌ها...</span>
                        <kbd class="hidden rounded-md border border-line bg-surface px-1.5 py-0.5 font-sans text-[0.65rem] font-semibold sm:inline" dir="ltr">Ctrl K</kbd>
                    </button>
                    <div v-else class="flex-1" />

                    <button v-if="ws && ws.role !== 'guest'" class="hidden h-10 items-center gap-2 rounded-xl gradient-accent px-4 text-sm font-semibold text-on-accent shadow-[0_6px_16px_-6px_var(--accent)] hover:brightness-110 sm:flex" @click="openCreateTask()">
                        <Plus class="size-4" /> وظیفه جدید
                    </button>

                    <button class="rounded-xl p-2.5 text-ink-2 hover:bg-surface-3" :aria-label="`پوسته: ${theme}`" @click="cycleTheme"><component :is="themeIcon" class="size-5" /></button>

                    <button class="relative rounded-xl p-2.5 text-ink-2 hover:bg-surface-3" aria-label="اعلان‌ها" @click="notifications = true">
                        <Bell class="size-5" />
                        <span v-if="counters.notifications" class="absolute end-1.5 top-1.5 flex min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[0.6rem] font-bold leading-4 text-white">
                            {{ faNum(counters.notifications > 99 ? '99+' : counters.notifications) }}
                        </span>
                    </button>

                    <Dropdown align="end" width="w-64">
                        <template #trigger="{ toggle }">
                            <button class="flex items-center gap-2 rounded-xl p-1 hover:bg-surface-3" aria-label="منوی کاربر" @click="toggle">
                                <Avatar :user="user" size="sm" :status="false" />
                            </button>
                        </template>
                        <div class="flex items-center gap-3 px-2.5 py-2">
                            <Avatar :user="user" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold">{{ user.name }}</p>
                                <p class="truncate text-xs text-ink-3" dir="ltr">{{ user.email }}</p>
                            </div>
                        </div>
                        <div class="my-1 border-t border-line" />
                        <MenuItem :href="route('settings.profile')"><Settings class="size-4" /> حساب کاربری</MenuItem>
                        <MenuItem :href="route('settings.security')">
                            <ShieldCheck class="size-4" /> امنیت و ورود
                            <span v-if="!user.two_factor" class="chip ms-auto bg-warning-soft text-warning">۲FA خاموش</span>
                        </MenuItem>
                        <MenuItem v-if="ws?.is_admin" :href="route('workspace.settings', ws.id)"><Building2 class="size-4" /> تنظیمات میزکار</MenuItem>
                        <MenuItem v-if="user.is_admin" :href="route('admin.dashboard')"><Gauge class="size-4" /> پنل مدیریت سایت</MenuItem>
                        <div class="my-1 border-t border-line" />
                        <MenuItem :href="route('logout')" method="post" danger><LogOut class="size-4" /> خروج</MenuItem>
                    </Dropdown>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8" :data-section="sectionTone">
                <slot />
            </main>
        </div>

        <CommandPalette v-if="ws" :show="palette" @close="palette = false" />
        <NotificationPanel :show="notifications" @close="notifications = false" />
        <TaskModal v-if="ws" />
        <TaskCreateModal v-if="ws" />
        <NavProgress />
        <Toasts />
        <ConfirmHost />
    </div>
</template>
