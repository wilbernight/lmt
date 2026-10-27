<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, ArrowRight, CheckCheck, Lock, Printer, Reply, ReplyAll, Share2 } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Modal from '@/Components/ui/Modal.vue';
import Field from '@/Components/ui/Field.vue';
import MemberPicker from '@/Components/ui/MemberPicker.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import AttachmentList from '@/Components/ui/AttachmentList.vue';
import { faNum, jDateTime } from '@/lib/format';

const props = defineProps({ letter: Object, thread: Array, referrals: Array, archived: Boolean, can: Object });

const page = usePage();
const ws = page.props.workspace;
const me = page.props.auth.user.id;
const last = props.thread[props.thread.length - 1];

const replying = ref(false);
const replyForm = useForm({ body: '', all: false, attachments: [] });
const sendReply = () => replyForm.post(route('letters.reply', [ws.id, last.id]), { onSuccess: () => (replying.value = false) });

const referring = ref(false);
const referForm = useForm({ to: [], note: '' });
const sendRefer = () => referForm.post(route('letters.refer', [ws.id, props.letter.id]), { preserveScroll: true, onSuccess: () => { referring.value = false; referForm.reset(); } });

const archive = () => router.post(route('letters.archive', [ws.id, props.letter.id]), {}, { preserveScroll: true });
// Opens the letterhead print view in a new tab and starts printing there.
const printLetter = (id) => window.open(route('letters.print', [ws.id, id, { auto: 1 }]), '_blank', 'noopener');
const priorityTone = { normal: 'slate', urgent: 'amber', instant: 'rose' };
const priorityLabel = { normal: 'عادی', urgent: 'فوری', instant: 'آنی' };
const typeLabel = { to: 'گیرنده', cc: 'رونوشت', referral: 'ارجاع' };
</script>

<template>
    <Head :title="letter.subject" />

    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex flex-wrap items-center gap-3 print:hidden">
            <Link :href="route('letters.index', ws.id)" class="rounded-xl p-2 text-ink-3 hover:bg-surface-3" aria-label="بازگشت"><ArrowRight class="size-5" /></Link>
            <h1 class="min-w-0 flex-1 truncate text-xl font-black">{{ letter.subject }}</h1>
            <Button variant="secondary" @click="printLetter(letter.id)"><Printer class="size-4" /> چاپ با سربرگ</Button>
            <Button variant="secondary" @click="archive"><component :is="archived ? ArchiveRestore : Archive" class="size-4" /> {{ archived ? 'خروج از بایگانی' : 'بایگانی' }}</Button>
            <Button v-if="can.refer" variant="soft" @click="referring = true"><Share2 class="size-4" /> ارجاع / پاراف</Button>
            <Button v-if="can.reply" @click="replying = true"><Reply class="size-4" /> پاسخ</Button>
        </div>

        <div class="space-y-4">
            <article v-for="l in thread" :key="l.id" class="card overflow-hidden">
                <header class="flex flex-wrap items-center gap-3 border-b border-line bg-surface-2/50 px-6 py-4">
                    <Avatar :user="l.sender" />
                    <div class="min-w-0 flex-1">
                        <p class="font-bold">{{ l.sender.name }}</p>
                        <p class="text-xs text-ink-3">{{ jDateTime(l.created_at) }}</p>
                    </div>
                    <span class="num chip bg-surface-3 text-ink-2">شماره {{ faNum(l.number) }}</span>
                    <button class="rounded-lg p-1.5 text-ink-3 hover:bg-surface-3 hover:text-ink print:hidden" :aria-label="`چاپ نامه ${faNum(l.number)}`" @click="printLetter(l.id)"><Printer class="size-4" /></button>
                    <span class="chip tone-chip" :class="`tone-${priorityTone[l.priority]}`">{{ priorityLabel[l.priority] }}</span>
                    <span v-if="l.confidential" class="chip bg-warning-soft text-warning"><Lock class="size-3" /> محرمانه</span>
                </header>
                <div class="px-6 py-5">
                    <div class="mb-4 flex flex-wrap gap-1.5 text-xs">
                        <span v-for="r in l.recipients" :key="r.id" class="flex items-center gap-1.5 rounded-full bg-surface-3 py-0.5 pe-2.5 ps-0.5">
                            <Avatar :user="r" size="xs" :status="false" />
                            <span class="text-ink-3">{{ typeLabel[r.type] }}:</span> {{ r.name }}
                            <CheckCheck v-if="r.read_at" class="size-3.5 text-success" :title="`خوانده شد ${jDateTime(r.read_at)}`" />
                        </span>
                        <span v-for="p in l.people ?? []" :key="p.id" class="flex items-center gap-1.5 rounded-full bg-surface-3 py-0.5 pe-2.5 ps-0.5" :title="`${p.name} (بدون اکانت)`">
                            <span class="flex size-6 items-center justify-center rounded-full border border-dashed border-line-strong text-[0.55rem] font-bold text-ink-2">{{ p.initials }}</span>
                            <span class="text-ink-3">{{ typeLabel[p.type] }}:</span> {{ p.name }}
                        </span>
                    </div>
                    <h2 class="mb-3 text-lg font-bold">{{ l.subject }}</h2>
                    <p class="whitespace-pre-wrap break-words text-[0.95rem] leading-8 text-ink-2">{{ l.body }}</p>
                    <AttachmentList v-if="l.attachments.length" :items="l.attachments" class="mt-5" />
                </div>
            </article>
        </div>

        <section v-if="referrals.length" class="card mt-6 p-6">
            <h3 class="mb-4 flex items-center gap-2 font-bold"><Share2 class="size-4 text-accent" /> سوابق ارجاع و پاراف</h3>
            <ol class="relative space-y-4 border-s border-line ps-5">
                <li v-for="r in referrals" :key="r.id" class="relative">
                    <span class="absolute -start-[1.6rem] top-1.5 size-2.5 rounded-full border-2 border-surface bg-accent" />
                    <p class="text-sm"><b>{{ r.from }}</b> ← <b>{{ r.to }}</b> <span class="text-xs text-ink-3">· {{ jDateTime(r.created_at) }}</span></p>
                    <p v-if="r.note" class="mt-1 rounded-xl bg-surface-2 px-3 py-2 text-sm leading-7 text-ink-2">{{ r.note }}</p>
                </li>
            </ol>
        </section>
    </div>

    <Modal :show="replying" title="پاسخ به نامه" size="lg" @close="replying = false">
        <form class="space-y-4 p-5" @submit.prevent="sendReply">
            <Field :error="replyForm.errors.body"><textarea v-model="replyForm.body" autofocus rows="8" maxlength="20000" class="field leading-8" placeholder="متن پاسخ..." /></Field>
            <FileUpload v-model="replyForm.attachments" />
            <label class="flex items-center gap-2 text-sm"><input v-model="replyForm.all" type="checkbox" class="size-4 accent-[var(--accent)]" /> <ReplyAll class="size-4" /> پاسخ به همه گیرندگان</label>
        </form>
        <template #footer>
            <Button variant="ghost" @click="replying = false">انصراف</Button>
            <Button :loading="replyForm.processing" @click="sendReply">ارسال پاسخ</Button>
        </template>
    </Modal>

    <Modal :show="referring" title="ارجاع نامه (پاراف)" @close="referring = false">
        <form class="space-y-4 p-5" @submit.prevent="sendRefer">
            <Field label="ارجاع به" :error="referForm.errors.to"><MemberPicker v-model="referForm.to" :exclude="[me]" /></Field>
            <Field label="دستور / توضیح" :error="referForm.errors.note"><textarea v-model="referForm.note" rows="4" maxlength="500" class="field" placeholder="مثلاً: لطفاً بررسی و اقدام شود." /></Field>
        </form>
        <template #footer>
            <Button variant="ghost" @click="referring = false">انصراف</Button>
            <Button :loading="referForm.processing" :disabled="!referForm.to.length" @click="sendRefer">ارجاع</Button>
        </template>
    </Modal>
</template>
