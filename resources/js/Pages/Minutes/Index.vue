<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { CalendarClock, ClipboardList, FilePenLine, MapPin, Search } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { faNum, jDateTime } from '@/lib/format';

const props = defineProps({ minutes: Object, filters: Object });

const page = usePage();
const ws = page.props.workspace;
const q = ref(props.filters.q ?? '');

const tabs = computed(() => [
    { key: 'all', label: 'همه', icon: ClipboardList, href: route('minutes.index', [ws.id, { status: undefined }]) },
    { key: 'draft', label: 'پیش‌نویس', icon: FilePenLine, href: route('minutes.index', [ws.id, { status: 'draft' }]) },
    { key: 'final', label: 'نهایی', icon: ClipboardList, href: route('minutes.index', [ws.id, { status: 'final' }]) },
]);

const apply = (extra = {}) => router.get(route('minutes.index', ws.id), { ...props.filters, q: q.value || undefined, ...extra }, { preserveState: true, replace: true });
watch(q, useDebounceFn(() => apply(), 300));

const initialsOf = (name) => name?.split(/\s+/).slice(0, 2).map((w) => w[0] ?? '').join('');
</script>

<template>
    <Head title="صورت‌جلسه‌ها" />

    <PageHeader title="صورت‌جلسه‌ها" subtitle="مصوبات و اقدامات جلسات، با تأیید الکترونیکی حاضرین" :icon="ClipboardList" tone="teal">
        <Button :href="route('minutes.create', ws.id)"><FilePenLine class="size-4" /> ثبت صورت‌جلسه</Button>
    </PageHeader>

    <div class="mt-6 border-b border-line"><Tabs :tabs="tabs" :active="filters.status || 'all'" /></div>

    <div class="mt-5 flex flex-wrap items-center gap-2">
        <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
            <input v-model="q" class="field h-10 w-64 ps-9" placeholder="جستجوی موضوع صورت‌جلسه..." />
        </div>
        <span class="ms-auto text-xs text-ink-3">{{ faNum(minutes.total) }} صورت‌جلسه</span>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <Link
            v-for="m in minutes.data"
            :key="m.id"
            :href="route('minutes.show', [ws.id, m.id])"
            class="card group p-5 transition-all duration-150 hover:-translate-y-0.5 hover:border-line-strong hover:shadow-card"
        >
            <div class="flex items-center gap-2">
                <span class="num chip bg-surface-3 text-ink-3">جلسه #{{ faNum(m.number) }}</span>
                <span class="chip tone-chip" :class="m.status === 'final' ? 'tone-emerald' : 'tone-amber'">{{ m.status === 'final' ? 'نهایی' : 'پیش‌نویس' }}</span>
                <span class="ms-auto text-xs text-ink-3">{{ jDateTime(m.held_at) || 'بدون تاریخ' }}</span>
            </div>

            <h3 class="mt-3 font-bold leading-7 group-hover:text-accent-ink">{{ m.subject }}</h3>

            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-ink-3">
                <span v-if="m.location" class="flex items-center gap-1"><MapPin class="size-3.5" /> {{ m.location }}</span>
                <span v-if="m.held_at" class="flex items-center gap-1"><CalendarClock class="size-3.5" /> {{ jDateTime(m.held_at) }}</span>
                <span v-if="m.actions_count" class="chip bg-accent-soft text-accent-ink">{{ faNum(m.actions_count) }} اقدام</span>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3 border-t border-line pt-3">
                <div class="flex min-w-0 items-center gap-2 text-xs text-ink-3">
                    <template v-if="m.organizer">
                        <Avatar :user="m.organizer" size="xs" :status="false" />
                        <span class="truncate">مجری: {{ m.organizer.name }}</span>
                    </template>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-1">
                    <span
                        v-for="p in m.participants.slice(0, 5)"
                        :key="p.key"
                        class="flex size-6 items-center justify-center rounded-full text-[0.55rem] font-bold ring-2 ring-surface"
                        :class="[p.external ? 'border border-dashed border-line-strong bg-surface-2 text-ink-2' : 'bg-surface-3 text-ink-2', { 'opacity-50': !p.attended }]"
                        :title="`${p.name}${p.external ? ' (بدون اکانت)' : ''}${p.attended ? '' : ' — غایب'}`"
                    >{{ p.external ? (initialsOf(p.name) || '؟') : p.name?.[0] }}</span>
                    <span v-if="m.participants.length > 5" class="text-[0.65rem] text-ink-3">+{{ faNum(m.participants.length - 5) }}</span>
                </div>
            </div>
        </Link>
    </div>

    <EmptyState v-if="!minutes.data.length" :icon="ClipboardList" title="صورت‌جلسه‌ای نیست" text="جلسه‌ای را که برگزار کرده‌اید اینجا ثبت کنید تا مصوبات و اقداماتش قابل پیگیری باشد." compact />

    <div v-if="minutes.last_page > 1" class="mt-5 flex justify-center gap-1">
        <template v-for="link in minutes.links" :key="link.label">
            <Link v-if="link.url" :href="link.url" preserve-scroll class="num min-w-9 rounded-lg px-3 py-1.5 text-center text-sm" :class="link.active ? 'bg-accent text-on-accent' : 'hover:bg-surface-3'">{{ faNum(link.label.replace(/&laquo;|&raquo;/g, '').trim()) }}</Link>
        </template>
    </div>
</template>
