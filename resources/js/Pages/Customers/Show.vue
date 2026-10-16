<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight, Briefcase, Building2, CalendarClock, Globe, Mail, MapPin, MessageCircle, Pencil, Phone, PhoneCall, StickyNote, Trash2, User, Users,
} from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import DatePicker from '@/Components/ui/DatePicker.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import CustomerForm from './CustomerForm.vue';
import { formatMoney, jDate, jDateTime, relative } from '@/lib/format';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({ customer: Object, stages: Object, types: Object, labels: Array, can: Object });

const page = usePage();
const ws = page.props.workspace;
const c = props.customer;

const editing = ref(false);
const form = useForm({
    kind: c.kind, name: c.name, company: c.company, job_title: c.job_title, phone: c.phone, mobile: c.mobile, email: c.email,
    website: c.website, city: c.city, address: c.address, stage: c.stage, deal_value: c.deal_value, notes: c.notes,
    owner_id: c.owner_id, labels: c.labels.map((l) => l.id),
});
const save = () => form.put(route('customers.update', [ws.id, c.id]), { preserveScroll: true, onSuccess: () => (editing.value = false) });

const interaction = useForm({ type: 'call', body: '', happened_at: null });
const addInteraction = () => interaction.post(route('customers.interactions.store', [ws.id, c.id]), { preserveScroll: true, onSuccess: () => interaction.reset('body', 'happened_at') });
const removeInteraction = (i) => router.delete(route('customers.interactions.destroy', [ws.id, c.id, i.id]), { preserveScroll: true });

const destroy = async () => {
    if (await confirmDialog({ title: 'حذف پرونده مشتری', text: `پرونده «${c.name}» حذف شود؟`, confirmText: 'حذف', danger: true })) router.delete(route('customers.destroy', [ws.id, c.id]));
};

const typeIcon = { call: PhoneCall, meeting: Users, email: Mail, note: StickyNote };
const stageTone = { lead: 'sky', negotiation: 'amber', customer: 'emerald', lost: 'slate' };
// Only render http(s) links (already validated server-side).
const safeUrl = (u) => (/^https?:\/\//i.test(u ?? '') ? u : null);
</script>

<template>
    <Head :title="customer.name" />

    <div class="flex flex-wrap items-center gap-4">
        <Link :href="route('customers.index', ws.id)" class="rounded-xl p-2 text-ink-3 hover:bg-surface-3" aria-label="بازگشت"><ArrowRight class="size-5" /></Link>
        <span class="flex size-14 items-center justify-center rounded-2xl tone-chip tone-teal"><component :is="customer.kind === 'company' ? Building2 : User" class="size-7" /></span>
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-2xl font-black">{{ customer.name }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-ink-3">
                <span class="chip tone-chip" :class="`tone-${stageTone[customer.stage]}`">{{ stages[customer.stage] }}</span>
                <span v-if="customer.company">{{ customer.company }}</span>
                <span v-for="l in customer.labels" :key="l.id" class="chip tone-chip" :class="`tone-${l.color}`">{{ l.name }}</span>
            </p>
        </div>
        <Button variant="secondary" @click="editing = true"><Pencil class="size-4" /> ویرایش</Button>
        <Button v-if="can.delete" variant="danger-ghost" size="icon" aria-label="حذف" @click="destroy"><Trash2 class="size-4" /></Button>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[20rem_1fr]">
        <aside class="card h-fit space-y-4 p-5 text-sm">
            <div v-if="customer.deal_value" class="rounded-xl bg-success-soft p-4 text-center">
                <p class="text-xs text-success">ارزش معامله</p>
                <p class="num mt-1 text-lg font-black text-success">{{ formatMoney(customer.deal_value) }}</p>
            </div>
            <p v-if="customer.job_title" class="flex items-center gap-3"><Briefcase class="size-4 text-ink-3" /> {{ customer.job_title }}</p>
            <a v-if="customer.mobile" :href="`tel:${customer.mobile}`" class="flex items-center gap-3 hover:text-accent-ink"><Phone class="size-4 text-ink-3" /><span dir="ltr">{{ customer.mobile }}</span></a>
            <a v-if="customer.phone" :href="`tel:${customer.phone}`" class="flex items-center gap-3 hover:text-accent-ink"><PhoneCall class="size-4 text-ink-3" /><span dir="ltr">{{ customer.phone }}</span></a>
            <a v-if="customer.email" :href="`mailto:${customer.email}`" class="flex items-center gap-3 hover:text-accent-ink"><Mail class="size-4 text-ink-3" /><span dir="ltr" class="truncate">{{ customer.email }}</span></a>
            <a v-if="safeUrl(customer.website)" :href="safeUrl(customer.website)" target="_blank" rel="noopener noreferrer nofollow" class="flex items-center gap-3 hover:text-accent-ink"><Globe class="size-4 text-ink-3" /><span dir="ltr" class="truncate">{{ customer.website }}</span></a>
            <p v-if="customer.city || customer.address" class="flex items-start gap-3"><MapPin class="mt-0.5 size-4 shrink-0 text-ink-3" /> {{ [customer.city, customer.address].filter(Boolean).join('، ') }}</p>
            <div class="border-t border-line pt-4">
                <p class="text-xs text-ink-3">مسئول پیگیری</p>
                <p v-if="customer.owner" class="mt-1.5 flex items-center gap-2 font-semibold"><Avatar :user="customer.owner" size="sm" /> {{ customer.owner.name }}</p>
                <p v-else class="mt-1 text-ink-3">—</p>
            </div>
            <p class="text-xs text-ink-3">ثبت‌شده توسط {{ customer.creator }} · {{ jDate(customer.created_at) }}</p>
            <div v-if="customer.notes" class="rounded-xl bg-surface-2 p-3 text-sm leading-7 whitespace-pre-wrap text-ink-2">{{ customer.notes }}</div>
        </aside>

        <section>
            <form class="card p-4" @submit.prevent="addInteraction">
                <div class="mb-3 flex flex-wrap gap-1.5">
                    <button v-for="(label, key) in types" :key="key" type="button" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors" :class="interaction.type === key ? 'bg-accent-soft text-accent-ink' : 'text-ink-3 hover:bg-surface-3'" @click="interaction.type = key">
                        <component :is="typeIcon[key]" class="size-3.5" /> {{ label }}
                    </button>
                </div>
                <textarea v-model="interaction.body" rows="3" maxlength="5000" class="field" placeholder="شرح تماس، جلسه یا پیگیری..." />
                <div class="mt-3 flex items-center gap-2">
                    <div class="w-56"><DatePicker v-model="interaction.happened_at" time compact placeholder="الان" /></div>
                    <Button type="submit" size="sm" class="ms-auto" :loading="interaction.processing" :disabled="!interaction.body.trim()">ثبت در پرونده</Button>
                </div>
            </form>

            <ol class="relative mt-6 space-y-4 border-s-2 border-line ps-6">
                <li v-for="i in customer.interactions" :key="i.id" class="relative">
                    <span class="absolute -start-[2.15rem] top-3 flex size-7 items-center justify-center rounded-full border-2 border-surface bg-accent-soft text-accent-ink"><component :is="typeIcon[i.type]" class="size-3.5" /></span>
                    <div class="card p-4">
                        <div class="flex items-center gap-2 text-xs">
                            <Avatar :user="i.user" size="xs" />
                            <b>{{ i.user.name }}</b>
                            <span class="chip bg-surface-3 text-ink-2">{{ types[i.type] }}</span>
                            <span class="flex items-center gap-1 text-ink-3" :title="jDateTime(i.happened_at)"><CalendarClock class="size-3.5" /> {{ relative(i.happened_at) }}</span>
                            <button v-if="i.can_delete" class="ms-auto text-ink-3 hover:text-danger" aria-label="حذف" @click="removeInteraction(i)"><Trash2 class="size-3.5" /></button>
                        </div>
                        <p class="mt-2 whitespace-pre-wrap text-sm leading-7 text-ink-2">{{ i.body }}</p>
                    </div>
                </li>
            </ol>
            <EmptyState v-if="!customer.interactions.length" :icon="MessageCircle" title="سابقه‌ای ثبت نشده" text="تماس‌ها و جلسات با این مشتری را اینجا ثبت کنید." compact />
        </section>
    </div>

    <Modal :show="editing" title="ویرایش پرونده" size="lg" @close="editing = false">
        <form class="p-5" @submit.prevent="save"><CustomerForm :form="form" :stages="stages" :labels="labels" /></form>
        <template #footer>
            <Button variant="ghost" @click="editing = false">انصراف</Button>
            <Button :loading="form.processing" @click="save">ذخیره</Button>
        </template>
    </Modal>
</template>
