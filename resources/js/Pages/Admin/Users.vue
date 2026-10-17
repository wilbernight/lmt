<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { BadgeCheck, Crown, Search, ShieldCheck, Users } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { faNum, formatBytes, relative } from '@/lib/format';

const props = defineProps({ users: Object, filters: Object, defaults: Object });

const q = ref(props.filters.q ?? '');
const apply = (extra = {}) => router.get(route('admin.users.index'), { ...props.filters, q: q.value || undefined, ...extra }, { preserveState: true, replace: true });
watch(q, useDebounceFn(() => apply(), 300));

const statuses = [
    { key: undefined, label: 'همه' },
    { key: 'admins', label: 'مدیران' },
    { key: 'suspended', label: 'مسدود' },
    { key: 'unverified', label: 'ایمیل تأییدنشده' },
];
const percent = (u) => (u.storage_allocated ? Math.min(100, Math.round((u.storage_used / u.storage_allocated) * 100)) : 0);
</script>

<template>
    <Head title="مدیریت کاربران" />

    <PageHeader title="کاربران" :subtitle="`${faNum(users.total)} حساب کاربری`" :icon="Users" tone="rose">
        <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
            <input v-model="q" class="field h-10 w-64 ps-9" placeholder="نام یا ایمیل..." />
        </div>
    </PageHeader>

    <div class="mt-5 flex flex-wrap gap-1.5">
        <button v-for="s in statuses" :key="s.label" class="rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors" :class="filters.status === s.key ? 'bg-accent-soft text-accent-ink' : 'text-ink-3 hover:bg-surface-3'" @click="apply({ status: s.key })">{{ s.label }}</button>
    </div>

    <div class="card mt-4 overflow-x-auto">
        <table class="w-full min-w-[52rem] text-sm">
            <thead class="border-b border-line bg-surface-2/60 text-xs text-ink-3">
                <tr>
                    <th class="px-5 py-3 text-start font-semibold">کاربر</th>
                    <th class="px-3 py-3 text-start font-semibold">وضعیت</th>
                    <th class="px-3 py-3 text-start font-semibold">میزکار (مالک / سقف)</th>
                    <th class="px-3 py-3 text-start font-semibold">فضا</th>
                    <th class="px-5 py-3 text-start font-semibold">آخرین حضور</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="u in users.data" :key="u.id" class="cursor-pointer border-b border-line last:border-0 hover:bg-surface-2" @click="router.visit(route('admin.users.show', u.id))">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <Avatar :user="u" size="sm" />
                            <div class="min-w-0">
                                <Link :href="route('admin.users.show', u.id)" class="flex items-center gap-1.5 font-bold hover:text-accent-ink" @click.stop>
                                    {{ u.name }} <Crown v-if="u.is_admin" class="size-3.5 text-warning" />
                                </Link>
                                <p class="truncate text-xs text-ink-3" dir="ltr">{{ u.email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex flex-wrap gap-1">
                            <span v-if="u.suspended" class="chip bg-danger-soft text-danger">مسدود</span>
                            <span v-else class="chip bg-success-soft text-success">فعال</span>
                            <span v-if="!u.verified" class="chip bg-warning-soft text-warning">تأییدنشده</span>
                            <span v-if="u.two_factor" class="chip bg-info-soft text-info"><ShieldCheck class="size-3" /> 2FA</span>
                            <span v-if="!u.can_create_workspaces" class="chip bg-surface-3 text-ink-2">بدون ساخت میزکار</span>
                        </div>
                    </td>
                    <td class="num px-3 py-3 text-xs">{{ faNum(u.owned_workspaces_count) }} / {{ u.is_admin ? '∞' : faNum(u.max_workspaces ?? defaults.max_workspaces) }}<span class="text-ink-3"> · عضو {{ faNum(u.workspaces_count) }}</span></td>
                    <td class="w-48 px-3 py-3 text-xs">
                        <p class="num mb-1 text-ink-2">{{ formatBytes(u.storage_used) }} / {{ formatBytes(u.storage_allocated) }}</p>
                        <div class="h-1.5 overflow-hidden rounded-full bg-surface-3"><div class="h-full rounded-full" :class="percent(u) >= 90 ? 'bg-danger' : 'gradient-accent'" :style="{ width: `${percent(u)}%` }" /></div>
                    </td>
                    <td class="px-5 py-3 text-xs text-ink-3">{{ u.last_seen_at ? relative(u.last_seen_at) : 'هرگز' }}</td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-if="!users.data.length" :icon="Users" title="کاربری پیدا نشد" compact />
    </div>

    <div v-if="users.last_page > 1" class="mt-5 flex justify-center gap-1">
        <template v-for="link in users.links" :key="link.label">
            <Link v-if="link.url" :href="link.url" class="num min-w-9 rounded-lg px-3 py-1.5 text-center text-sm" :class="link.active ? 'bg-accent text-on-accent' : 'hover:bg-surface-3'">{{ faNum(link.label.replace(/&laquo;|&raquo;/g, '').trim()) }}</Link>
        </template>
    </div>
</template>
