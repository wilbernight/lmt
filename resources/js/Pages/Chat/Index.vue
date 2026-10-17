<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight, Bell, BellOff, Check, CheckCheck, CornerUpLeft, LogOut, MessageSquarePlus, MessagesSquare, MoreVertical,
    Paperclip, Pencil, Search, Send, Settings2, Trash2, Users, X,
} from '@lucide/vue';
import Avatar from '@/Components/ui/Avatar.vue';
import AvatarStack from '@/Components/ui/AvatarStack.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Field from '@/Components/ui/Field.vue';
import Dropdown from '@/Components/ui/Dropdown.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MemberPicker from '@/Components/ui/MemberPicker.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import AttachmentList from '@/Components/ui/AttachmentList.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Spinner from '@/Components/ui/Spinner.vue';
import http from '@/lib/http';
import { faNum, isSameDay, jDate, jTime, relative } from '@/lib/format';
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps({ conversations: Array, active: Object, messages: Array });

const page = usePage();
const ws = page.props.workspace;
const me = page.props.auth.user;

const filter = ref('');
const list = ref([...props.messages]);
const body = ref('');
const replyTo = ref(null);
const editing = ref(null);
const files = ref([]);
const uploader = ref(null);
const sending = ref(false);
const scroller = ref(null);
const loadingOlder = ref(false);
const hasMore = ref(props.messages.length >= 50);

watch(() => props.messages, (m) => {
    list.value = [...m];
    hasMore.value = m.length >= 50;
    scrollDown();
});

const conversations = computed(() => props.conversations.filter((c) => !filter.value || c.name?.includes(filter.value.trim())));

const scrollDown = async (smooth = false) => {
    await nextTick();
    scroller.value?.scrollTo({ top: scroller.value.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
};

const upsert = (m) => {
    if (!m?.id) return;
    m.attachments ??= [];
    const i = list.value.findIndex((x) => x.id === m.id);
    if (i === -1) list.value.push(m);
    else list.value[i] = m;
};

async function send() {
    const text = body.value.trim();
    if ((!text && !files.value.length) || sending.value) return;
    sending.value = true;
    try {
        if (editing.value) {
            const { data } = await http.patch(route('chat.messages.update', [ws.id, props.active.id, editing.value.id]), { body: text });
            upsert(data);
            editing.value = null;
        } else {
            const { data } = await http.post(route('chat.send', [ws.id, props.active.id]), { body: text || null, reply_to_id: replyTo.value?.id, attachments: files.value });
            upsert(data);
            replyTo.value = null;
            uploader.value?.reset();
            scrollDown(true);
        }
        body.value = '';
    } finally {
        sending.value = false;
    }
}

const startEdit = (m) => {
    editing.value = m;
    replyTo.value = null;
    body.value = m.body;
};

async function remove(m) {
    if (!(await confirmDialog({ title: 'حذف پیام', text: 'این پیام برای همه حذف شود؟', confirmText: 'حذف', danger: true }))) return;
    await http.delete(route('chat.messages.destroy', [ws.id, props.active.id, m.id]));
    upsert({ ...m, deleted: true, body: null, attachments: [] });
}

async function loadOlder() {
    if (loadingOlder.value || !hasMore.value || !list.value.length) return;
    loadingOlder.value = true;
    const el = scroller.value;
    const before = el.scrollHeight;
    try {
        const { data } = await http.get(route('chat.messages', [ws.id, props.active.id]), { params: { before: list.value[0].id } });
        list.value = [...data, ...list.value];
        hasMore.value = data.length >= 50;
        await nextTick();
        el.scrollTop = el.scrollHeight - before;
    } finally {
        loadingOlder.value = false;
    }
}

const onScroll = (e) => e.target.scrollTop < 80 && loadOlder();

const onKey = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    } else if (e.key === 'Escape') {
        editing.value = replyTo.value = null;
        body.value = '';
    }
};

// Realtime, with polling as a fallback when WebSockets are unavailable.
let poll = null;
const subscribe = () => {
    if (!props.active) return;
    if (window.Echo) {
        window.Echo.private(`conversations.${props.active.id}`)
            .listen('.message.created', (m) => { upsert(m); scrollDown(true); http.post(route('chat.read', [ws.id, props.active.id])).catch(() => {}); })
            .listen('.message.updated', upsert)
            .listen('.message.deleted', ({ id }) => {
                const m = list.value.find((x) => x.id === id);
                if (m) upsert({ ...m, deleted: true, body: null, attachments: [] });
            });
    } else {
        poll = setInterval(() => router.reload({ only: ['messages', 'conversations'] }), 8000);
    }
};
const unsubscribe = (id) => {
    if (id) window.Echo?.leave(`conversations.${id}`);
    clearInterval(poll);
};

onMounted(() => { subscribe(); scrollDown(); });
onBeforeUnmount(() => unsubscribe(props.active?.id));
watch(() => props.active?.id, (id, old) => { unsubscribe(old); subscribe(); editing.value = replyTo.value = null; });

const showDay = (m, i) => i === 0 || !isSameDay(list.value[i - 1].created_at, m.created_at);
const grouped = (m, i) => i > 0 && list.value[i - 1].user?.id === m.user?.id && !showDay(m, i) && new Date(m.created_at) - new Date(list.value[i - 1].created_at) < 5 * 60000;

// Group management ----------------------------------------------------
const groupModal = ref(false);
const groupForm = ref({ name: '', members: [] });
const openGroup = (existing = false) => {
    groupForm.value = existing ? { name: props.active.name, members: props.active.participants.map((p) => p.id).filter((id) => id !== me.id) } : { name: '', members: [] };
    groupModal.value = existing ? 'edit' : 'create';
};
const saveGroup = () => {
    const opts = { preserveScroll: true, onSuccess: () => (groupModal.value = false) };
    groupModal.value === 'edit' ? router.put(route('chat.groups.update', [ws.id, props.active.id]), groupForm.value, opts) : router.post(route('chat.groups.store', ws.id), groupForm.value, opts);
};
const leave = async () => {
    if (await confirmDialog({ title: 'خروج از گروه', text: `از «${props.active.name}» خارج شوید؟`, confirmText: 'خروج', danger: true })) router.post(route('chat.leave', [ws.id, props.active.id]));
};
const toggleMute = async () => {
    await http.post(route('chat.mute', [ws.id, props.active.id]));
    router.reload({ only: ['active'] });
};

const directModal = ref(false);
const startDirect = (ids) => {
    if (!ids.length) return;
    directModal.value = false;
    router.post(route('chat.direct', ws.id), { user_id: ids[0] });
};
</script>

<template>
    <Head title="گفتگو" />

    <div class="card -my-2 grid h-[calc(100dvh-8.5rem)] overflow-hidden md:grid-cols-[20rem_1fr]">
        <!-- Conversation list -->
        <aside class="flex min-h-0 flex-col border-e border-line" :class="active && 'hidden md:flex'">
            <div class="flex items-center gap-2 border-b border-line p-3">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
                    <input v-model="filter" class="field h-9 ps-9 text-sm" placeholder="فیلتر گفتگوها" />
                </div>
                <Dropdown align="end" width="w-48">
                    <template #trigger="{ toggle }"><Button size="icon" aria-label="گفتگوی جدید" @click="toggle"><MessageSquarePlus class="size-4" /></Button></template>
                    <template #default="{ close }">
                        <MenuItem @click="directModal = true; close()"><MessagesSquare class="size-4" /> پیام خصوصی</MenuItem>
                        <MenuItem v-if="ws.role !== 'guest'" @click="openGroup(); close()"><Users class="size-4" /> گروه جدید</MenuItem>
                    </template>
                </Dropdown>
            </div>
            <ul class="flex-1 overflow-y-auto p-2">
                <li v-for="c in conversations" :key="c.id">
                    <Link
                        :href="route('chat.show', [ws.id, c.id])"
                        preserve-scroll
                        class="flex items-center gap-3 rounded-xl p-2.5 transition-colors"
                        :class="active?.id === c.id ? 'bg-accent-soft' : 'hover:bg-surface-2'"
                    >
                        <Avatar v-if="c.type === 'direct'" :user="c.other" />
                        <span v-else class="flex size-10 items-center justify-center rounded-full tone-chip tone-sky"><Users class="size-5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="truncate text-sm font-bold">{{ c.name }}</span>
                                <BellOff v-if="c.muted" class="size-3 text-ink-3" />
                                <span v-if="c.last_message_at" class="ms-auto shrink-0 text-[0.65rem] text-ink-3">{{ relative(c.last_message_at) }}</span>
                            </span>
                            <span class="mt-0.5 flex items-center gap-2">
                                <span class="truncate text-xs text-ink-3">{{ c.last_message || 'هنوز پیامی نیست' }}</span>
                                <span v-if="c.unread" class="ms-auto flex min-w-5 items-center justify-center rounded-full bg-accent px-1.5 text-[0.65rem] font-bold text-on-accent">{{ faNum(c.unread) }}</span>
                            </span>
                        </span>
                    </Link>
                </li>
                <li v-if="!conversations.length" class="px-3 py-10 text-center text-xs leading-6 text-ink-3">هنوز گفتگویی ندارید. از دکمه بالا یک گفتگو شروع کنید.</li>
            </ul>
        </aside>

        <!-- Thread -->
        <section v-if="active" class="flex min-h-0 flex-col">
            <header class="flex items-center gap-3 border-b border-line px-4 py-3">
                <Link :href="route('chat.index', ws.id)" class="rounded-lg p-1.5 text-ink-3 hover:bg-surface-3 md:hidden" aria-label="بازگشت"><ArrowRight class="size-5" /></Link>
                <Avatar v-if="active.type === 'direct'" :user="active.other" />
                <span v-else class="flex size-10 items-center justify-center rounded-full tone-chip tone-sky"><Users class="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-bold">{{ active.name }}</p>
                    <p class="text-xs text-ink-3">
                        <template v-if="active.type === 'direct'">{{ active.other?.online ? 'آنلاین' : 'آفلاین' }}</template>
                        <template v-else>{{ faNum(active.participants.length) }} عضو</template>
                    </p>
                </div>
                <AvatarStack v-if="active.type === 'group'" :users="active.participants" :max="4" class="hidden sm:flex" />
                <Dropdown align="end" width="w-48">
                    <template #trigger="{ toggle }"><Button variant="ghost" size="icon" aria-label="گزینه‌ها" @click="toggle"><MoreVertical class="size-4" /></Button></template>
                    <template #default="{ close }">
                        <MenuItem @click="toggleMute(); close()"><component :is="active.muted ? Bell : BellOff" class="size-4" /> {{ active.muted ? 'روشن کردن اعلان' : 'بی‌صدا کردن' }}</MenuItem>
                        <MenuItem v-if="active.can_manage" @click="openGroup(true); close()"><Settings2 class="size-4" /> مدیریت گروه</MenuItem>
                        <MenuItem v-if="active.type === 'group'" danger @click="leave(); close()"><LogOut class="size-4" /> خروج از گروه</MenuItem>
                    </template>
                </Dropdown>
            </header>

            <div ref="scroller" class="flex-1 overflow-y-auto bg-bg-subtle/50 px-4 py-4 sm:px-6" @scroll.passive="onScroll">
                <div v-if="loadingOlder" class="flex justify-center py-2"><Spinner class="size-5 text-ink-3" /></div>
                <EmptyState v-if="!list.length" :icon="MessagesSquare" title="شروع گفتگو" text="اولین پیام را بفرستید." compact />
                <template v-for="(m, i) in list" :key="m.id">
                    <div v-if="showDay(m, i)" class="my-4 flex justify-center">
                        <span class="rounded-full bg-surface px-3 py-1 text-[0.7rem] font-semibold text-ink-3 shadow-soft">{{ jDate(m.created_at, { weekday: true }) }}</span>
                    </div>
                    <div class="group flex items-end gap-2" :class="[m.user?.id === me.id ? 'flex-row' : 'flex-row-reverse', grouped(m, i) ? 'mt-0.5' : 'mt-3']">
                        <div class="w-8 shrink-0">
                            <Avatar v-if="m.user?.id !== me.id && !grouped(list[i + 1] ?? {}, i + 1) " :user="m.user" size="sm" :status="false" />
                        </div>
                        <div class="flex max-w-[78%] flex-col" :class="m.user?.id === me.id ? 'items-start' : 'items-end'">
                            <span v-if="active.type === 'group' && m.user?.id !== me.id && !grouped(m, i)" class="mb-1 px-1 text-[0.7rem] font-bold text-accent-ink">{{ m.user?.name }}</span>
                            <div
                                class="relative rounded-2xl px-3.5 py-2 text-sm leading-7 shadow-soft"
                                :class="m.deleted ? 'border border-dashed border-line bg-transparent italic text-ink-3' : m.user?.id === me.id ? 'bg-accent rounded-ss-md text-on-accent' : 'rounded-se-md bg-surface text-ink'"
                            >
                                <div v-if="m.reply_to" class="mb-1.5 rounded-lg border-s-2 px-2 py-1 text-xs" :class="m.user?.id === me.id ? 'border-white/60 bg-white/15' : 'border-accent bg-surface-3'">
                                    <span class="block font-bold">{{ m.reply_to.user }}</span>
                                    <span class="line-clamp-1 opacity-80">{{ m.reply_to.body ?? 'پیام حذف‌شده' }}</span>
                                </div>
                                <p v-if="m.deleted">این پیام حذف شد</p>
                                <p v-else-if="m.body" class="whitespace-pre-wrap break-words">{{ m.body }}</p>
                                <AttachmentList v-if="m.attachments.length" :items="m.attachments" class="mt-1.5" />
                                <span class="mt-0.5 flex items-center gap-1 text-[0.62rem]" :class="m.user?.id === me.id ? 'text-white/75' : 'text-ink-3'">
                                    {{ jTime(m.created_at) }}<template v-if="m.edited"> · ویرایش‌شده</template>
                                    <CheckCheck v-if="m.user?.id === me.id" class="size-3" />
                                </span>
                            </div>
                        </div>
                        <div v-if="!m.deleted" class="flex gap-0.5 self-center opacity-0 transition-opacity group-hover:opacity-100">
                            <button class="rounded-md p-1 text-ink-3 hover:bg-surface-3 hover:text-ink" aria-label="پاسخ" @click="replyTo = m; editing = null"><CornerUpLeft class="size-3.5" /></button>
                            <button v-if="m.user?.id === me.id" class="rounded-md p-1 text-ink-3 hover:bg-surface-3 hover:text-ink" aria-label="ویرایش" @click="startEdit(m)"><Pencil class="size-3.5" /></button>
                            <button v-if="m.user?.id === me.id || active.can_manage" class="rounded-md p-1 text-ink-3 hover:bg-danger-soft hover:text-danger" aria-label="حذف" @click="remove(m)"><Trash2 class="size-3.5" /></button>
                        </div>
                    </div>
                </template>
            </div>

            <footer class="border-t border-line p-3">
                <div v-if="replyTo || editing" class="mb-2 flex items-center gap-2 rounded-xl bg-surface-2 px-3 py-2 text-xs">
                    <component :is="editing ? Pencil : CornerUpLeft" class="size-4 text-accent" />
                    <span class="flex-1 truncate">
                        <b>{{ editing ? 'ویرایش پیام' : `پاسخ به ${replyTo.user?.name}` }}:</b> {{ (editing ?? replyTo).body }}
                    </span>
                    <button class="text-ink-3 hover:text-ink" aria-label="لغو" @click="replyTo = editing = null; body = ''"><X class="size-4" /></button>
                </div>
                <FileUpload ref="uploader" v-model="files" compact />
                <div class="flex items-end gap-2">
                    <Button variant="ghost" size="icon" aria-label="پیوست فایل" @click="uploader.open()"><Paperclip class="size-5" /></Button>
                    <textarea v-model="body" rows="1" maxlength="4000" class="field max-h-40 min-h-10 flex-1 resize-none py-2.5 leading-6" placeholder="پیام خود را بنویسید... (Enter برای ارسال، Shift+Enter خط جدید)" @keydown="onKey" />
                    <Button size="icon" :loading="sending" :disabled="!body.trim() && !files.length" aria-label="ارسال" @click="send">
                        <component :is="editing ? Check : Send" v-if="!sending" class="size-4 -scale-x-100" />
                    </Button>
                </div>
            </footer>
        </section>

        <section v-else class="hidden items-center justify-center md:flex">
            <EmptyState :icon="MessagesSquare" title="برای شروع مکالمه یک مخاطب را انتخاب کنید" text="پیام‌ها به‌صورت لحظه‌ای برای همکاران ارسال می‌شوند." />
        </section>
    </div>

    <Modal :show="!!groupModal" :title="groupModal === 'edit' ? 'مدیریت گروه' : 'گروه جدید'" size="sm" @close="groupModal = false">
        <form class="space-y-4 p-5" @submit.prevent="saveGroup">
            <Field label="نام گروه"><input v-model="groupForm.name" autofocus maxlength="80" class="field" /></Field>
            <Field label="اعضا"><MemberPicker v-model="groupForm.members" :exclude="[me.id]" /></Field>
        </form>
        <template #footer>
            <Button variant="ghost" @click="groupModal = false">انصراف</Button>
            <Button :disabled="!groupForm.name || !groupForm.members.length" @click="saveGroup">ذخیره</Button>
        </template>
    </Modal>

    <Modal :show="directModal" title="پیام خصوصی به..." size="sm" @close="directModal = false">
        <div class="p-5"><MemberPicker :model-value="[]" single :exclude="[me.id]" placeholder="انتخاب همکار" @update:model-value="startDirect" /></div>
    </Modal>
</template>
