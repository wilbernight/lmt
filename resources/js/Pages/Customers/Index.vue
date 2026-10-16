<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Building2, Contact, Download, Mail, Phone, Plus, Search, User } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import CustomerForm from './CustomerForm.vue';
import { faNum, formatMoney, formatNumber, relative } from '@/lib/format';

const props = defineProps({ customers: Object, stages: Object, pipeline: Array, labels: Array, filters: Object });

const page = usePage();
const ws = page.props.workspace;
const q = ref(props.filters.q ?? '');
const creating = ref(false);

const form = useForm({ kind: 'person', name: '', company: '', job_title: '', phone: '', mobile: '', email: '', website: '', city: '', address: '', stage: 'lead', deal_value: null, notes: '', owner_id: page.props.auth.user.id, labels: [] });

const apply = (extra = {}) => router.get(route('customers.index', ws.id), { ...props.filters, q: q.value || undefined, ...extra }, { preserveState: true, replace: true });
watch(q, useDebounceFn(() => apply(), 300));

const stageTone = { lead: 'sky', negotiation: 'amber', customer: 'emerald', lost: 'slate' };
const exportUrl = () => route('customers.export', [ws.id, props.filters]);
</script>

<template>
    <Head title="پرونده مشتریان" />

    <PageHeader title="پرونده مشتریان" :subtitle="`${faNum(customers.total)} پرونده`" :icon="Contact" tone="teal">
        <a :href="exportUrl()" class="inline-flex h-10 items-center gap-2 rounded-xl border border-line-strong bg-surface px-4 text-sm font-semibold shadow-soft hover:bg-surface-2"><Download class="size-4" /> خروجی اکسل</a>
        <Button @click="creating = true"><Plus class="size-4" /> ثبت مشتری جدید</Button>
    </PageHeader>

    <!-- Pipeline -->
    <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <button
            v-for="s in pipeline"
            :key="s.key"
            class="card p-4 text-start transition-all hover:-translate-y-0.5 hover:shadow-card"
            :class="[`tone-${stageTone[s.key]}`, filters.stage === s.key && 'ring-2 ring-[var(--tone)]']"
            @click="apply({ stage: filters.stage === s.key ? undefined : s.key })"
        >
            <div class="flex items-center gap-2">
                <span class="size-2.5 rounded-full tone-dot" />
                <span class="text-sm font-bold">{{ s.label }}</span>
            </div>
            <p class="num mt-3 text-2xl font-black">{{ formatNumber(s.total) }}</p>
            <p class="mt-0.5 text-xs text-ink-3">{{ formatMoney(s.value) }}</p>
        </button>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-2">
        <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
            <input v-model="q" class="field h-10 w-72 ps-9" placeholder="نام، شرکت، تلفن یا ایمیل..." />
        </div>
        <select class="field h-10 w-auto" :value="filters.owner ?? ''" @change="apply({ owner: $event.target.value || undefined })">
            <option value="">همه مسئولان</option>
            <option v-for="m in ws.members" :key="m.id" :value="m.id">{{ m.name }}</option>
        </select>
        <select v-if="labels.length" class="field h-10 w-auto" :value="filters.label ?? ''" @change="apply({ label: $event.target.value || undefined })">
            <option value="">همه برچسب‌ها</option>
            <option v-for="l in labels" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
    </div>

    <div class="card mt-5 overflow-x-auto">
        <table class="w-full min-w-[48rem] text-sm">
            <thead class="border-b border-line bg-surface-2/60 text-xs text-ink-3">
                <tr>
                    <th class="px-5 py-3 text-start font-semibold">مشتری</th>
                    <th class="px-3 py-3 text-start font-semibold">تماس</th>
                    <th class="px-3 py-3 text-start font-semibold">مرحله</th>
                    <th class="px-3 py-3 text-start font-semibold">ارزش</th>
                    <th class="px-3 py-3 text-start font-semibold">مسئول</th>
                    <th class="px-5 py-3 text-start font-semibold">آخرین تغییر</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="c in customers.data" :key="c.id" class="cursor-pointer border-b border-line transition-colors last:border-0 hover:bg-surface-2" @click="router.visit(route('customers.show', [ws.id, c.id]))">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-xl tone-chip tone-teal"><component :is="c.kind === 'company' ? Building2 : User" class="size-5" /></span>
                            <div class="min-w-0">
                                <Link :href="route('customers.show', [ws.id, c.id])" class="block truncate font-bold hover:text-accent-ink" @click.stop>{{ c.name }}</Link>
                                <p class="truncate text-xs text-ink-3">{{ c.company || c.city || '—' }}</p>
                            </div>
                            <span v-for="l in c.labels" :key="l.id" class="chip tone-chip" :class="`tone-${l.color}`">{{ l.name }}</span>
                        </div>
                    </td>
                    <td class="px-3 py-3.5 text-xs text-ink-2">
                        <p v-if="c.mobile || c.phone" class="flex items-center gap-1.5" dir="ltr"><Phone class="size-3.5 text-ink-3" />{{ c.mobile || c.phone }}</p>
                        <p v-if="c.email" class="mt-1 flex items-center gap-1.5 truncate" dir="ltr"><Mail class="size-3.5 text-ink-3" />{{ c.email }}</p>
                    </td>
                    <td class="px-3 py-3.5"><span class="chip tone-chip" :class="`tone-${stageTone[c.stage]}`">{{ stages[c.stage] }}</span></td>
                    <td class="num px-3 py-3.5 text-xs">{{ formatMoney(c.deal_value) }}</td>
                    <td class="px-3 py-3.5"><span v-if="c.owner" class="flex items-center gap-2 text-xs"><Avatar :user="c.owner" size="xs" /> {{ c.owner.name }}</span></td>
                    <td class="px-5 py-3.5 text-xs text-ink-3">{{ relative(c.updated_at) }}</td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-if="!customers.data.length" :icon="Contact" title="هنوز مشتری ثبت نشده" text="اطلاعات مشتریان، تماس‌ها و جلسات را یک‌جا نگه دارید.">
            <Button @click="creating = true"><Plus class="size-4" /> ثبت اولین مشتری</Button>
        </EmptyState>
    </div>

    <div v-if="customers.last_page > 1" class="mt-5 flex justify-center gap-1">
        <template v-for="link in customers.links" :key="link.label">
            <Link v-if="link.url" :href="link.url" class="num min-w-9 rounded-lg px-3 py-1.5 text-center text-sm" :class="link.active ? 'gradient-accent text-on-accent' : 'hover:bg-surface-3'">{{ faNum(link.label.replace(/&laquo;|&raquo;/g, '').trim()) }}</Link>
        </template>
    </div>

    <Modal :show="creating" title="ثبت اطلاعات مشتری جدید" size="lg" @close="creating = false">
        <form class="p-5" @submit.prevent="form.post(route('customers.store', ws.id))"><CustomerForm :form="form" :stages="stages" :labels="labels" /></form>
        <template #footer>
            <Button variant="ghost" @click="creating = false">انصراف</Button>
            <Button :loading="form.processing" @click="form.post(route('customers.store', ws.id))">ثبت مشتری</Button>
        </template>
    </Modal>
</template>
