<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Archive, Inbox, Lock, Mailbox, Paperclip, PenSquare, Search, Send, Share2 } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { faNum, relative } from '@/lib/format';

const props = defineProps({ box: String, letters: Object, filters: Object, labels: Array, priorities: Object });

const page = usePage();
const ws = page.props.workspace;
const q = ref(props.filters.q ?? '');

const tabs = computed(() => [
    { key: 'inbox', label: 'صندوق ورودی', icon: Inbox, href: route('letters.index', [ws.id, { box: 'inbox' }]), count: page.props.counters?.letters },
    { key: 'sent', label: 'صندوق خروجی', icon: Send, href: route('letters.index', [ws.id, { box: 'sent' }]) },
    { key: 'referrals', label: 'پاراف‌های من', icon: Share2, href: route('letters.index', [ws.id, { box: 'referrals' }]) },
    { key: 'archived', label: 'بایگانی', icon: Archive, href: route('letters.index', [ws.id, { box: 'archived' }]) },
]);

const apply = (extra = {}) => router.get(route('letters.index', ws.id), { ...props.filters, box: props.box, q: q.value || undefined, ...extra }, { preserveState: true, replace: true });
watch(q, useDebounceFn(() => apply(), 300));

const priorityTone = { normal: 'slate', urgent: 'amber', instant: 'rose' };
</script>

<template>
    <Head title="کارتابل نامه‌ها" />

    <PageHeader title="کارتابل" subtitle="مکاتبات داخلی، ارجاع و پاراف" :icon="Mailbox" tone="orange">
        <Button :href="route('letters.create', ws.id)"><PenSquare class="size-4" /> ایجاد نامه</Button>
    </PageHeader>

    <div class="mt-6 border-b border-line"><Tabs :tabs="tabs" :active="box" /></div>

    <div class="mt-5 flex flex-wrap items-center gap-2">
        <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
            <input v-model="q" class="field h-10 w-64 ps-9" placeholder="موضوع یا شماره نامه..." />
        </div>
        <select class="field h-10 w-auto" :value="filters.priority ?? ''" @change="apply({ priority: $event.target.value || undefined })">
            <option value="">همه فوریت‌ها</option>
            <option v-for="(label, key) in priorities" :key="key" :value="key">{{ label }}</option>
        </select>
        <select v-if="labels.length" class="field h-10 w-auto" :value="filters.label ?? ''" @change="apply({ label: $event.target.value || undefined })">
            <option value="">همه برچسب‌ها</option>
            <option v-for="l in labels" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
        <span class="ms-auto text-xs text-ink-3">نامه‌های {{ faNum(letters.total) }} مورد</span>
    </div>

    <div class="card mt-5 overflow-hidden">
        <Link
            v-for="l in letters.data"
            :key="l.id"
            :href="route('letters.show', [ws.id, l.id])"
            class="flex items-center gap-4 border-b border-line px-5 py-4 transition-colors last:border-0 hover:bg-surface-2"
            :class="l.unread && 'bg-accent-soft/30'"
        >
            <span v-if="l.unread" class="size-2 shrink-0 rounded-full bg-accent" />
            <Avatar :user="box === 'sent' ? null : l.sender" size="md" />
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="truncate text-sm" :class="l.unread ? 'font-extrabold' : 'font-semibold'">{{ box === 'sent' ? `به: ${l.recipients.join('، ')}` : l.sender.name }}</span>
                    <span class="num chip bg-surface-3 text-ink-3">#{{ faNum(l.number) }}</span>
                    <span v-if="l.priority !== 'normal'" class="chip tone-chip" :class="`tone-${priorityTone[l.priority]}`">{{ priorities[l.priority] }}</span>
                    <Lock v-if="l.confidential" class="size-3.5 text-warning" />
                </div>
                <p class="mt-0.5 truncate text-sm" :class="l.unread ? 'font-bold text-ink' : 'text-ink-2'">{{ l.subject }} <span class="font-normal text-ink-3">— {{ l.excerpt }}</span></p>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1.5">
                <span class="text-xs text-ink-3">{{ relative(l.created_at) }}</span>
                <div class="flex items-center gap-1">
                    <span v-for="lb in l.labels" :key="lb.id" class="chip tone-chip" :class="`tone-${lb.color}`">{{ lb.name }}</span>
                    <Paperclip v-if="l.attachments_count" class="size-3.5 text-ink-3" />
                </div>
            </div>
        </Link>
        <EmptyState v-if="!letters.data.length" :icon="Mailbox" title="نامه‌ای در این صندوق نیست" compact />
    </div>

    <div v-if="letters.last_page > 1" class="mt-5 flex justify-center gap-1">
        <template v-for="link in letters.links" :key="link.label">
            <Link v-if="link.url" :href="link.url" preserve-scroll class="num min-w-9 rounded-lg px-3 py-1.5 text-center text-sm" :class="link.active ? 'gradient-accent text-on-accent' : 'hover:bg-surface-3'">{{ faNum(link.label.replace(/&laquo;|&raquo;/g, '').trim()) }}</Link>
        </template>
    </div>
</template>
