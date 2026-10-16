<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { AtSign, Bell, CheckCheck, CircleCheck, Mailbox, MessageSquare, MessagesSquare, Share2, UserCheck, X } from '@lucide/vue';
import Avatar from './ui/Avatar.vue';
import Spinner from './ui/Spinner.vue';
import EmptyState from './ui/EmptyState.vue';
import http from '@/lib/http';
import { relative } from '@/lib/format';

const props = defineProps({ show: Boolean });
const emit = defineEmits(['close']);

const items = ref([]);
const loading = ref(false);

const icons = {
    'task.assigned': UserCheck,
    'task.commented': MessageSquare,
    'task.mentioned': AtSign,
    'task.completed': CircleCheck,
    'letter.received': Mailbox,
    'letter.referred': Share2,
    'chat.message': MessagesSquare,
};

watch(
    () => props.show,
    async (open) => {
        if (!open) return;
        loading.value = true;
        try {
            items.value = (await http.get(route('notifications.index'))).data;
        } finally {
            loading.value = false;
        }
    },
);

async function go(n) {
    if (!n.read) http.post(route('notifications.read', n.id)).catch(() => {});
    emit('close');
    // Only follow same-origin links stored by the server.
    try {
        const url = new URL(n.url, window.location.origin);
        if (url.origin === window.location.origin) router.visit(url.pathname + url.search);
    } catch { /* ignore malformed */ }
}

async function readAll() {
    await http.post(route('notifications.read-all'));
    items.value = items.value.map((n) => ({ ...n, read: true }));
    router.reload({ only: ['counters'] });
}
</script>

<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition" leave-active-class="transition">
            <div v-if="show" class="fixed inset-0 z-50 bg-[var(--scrim)]" @click="emit('close')" />
        </Transition>
        <Transition enter-from-class="-translate-x-full" leave-to-class="-translate-x-full" enter-active-class="transition duration-300" leave-active-class="transition duration-200">
            <aside v-if="show" class="fixed inset-y-0 end-0 z-50 flex w-full max-w-md flex-col border-s border-line bg-surface shadow-pop" role="dialog" aria-label="اعلان‌ها">
                <header class="flex h-16 items-center gap-3 border-b border-line px-5">
                    <Bell class="size-5 text-accent" />
                    <h2 class="font-bold">اعلان‌ها</h2>
                    <button class="ms-auto flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-accent-ink hover:bg-accent-soft" @click="readAll">
                        <CheckCheck class="size-4" /> خواندن همه
                    </button>
                    <button class="rounded-lg p-1.5 text-ink-3 hover:bg-surface-3" aria-label="بستن" @click="emit('close')"><X class="size-5" /></button>
                </header>
                <div class="flex-1 overflow-y-auto">
                    <div v-if="loading" class="flex justify-center py-16"><Spinner class="size-6 text-accent" /></div>
                    <EmptyState v-else-if="!items.length" :icon="Bell" title="اعلانی ندارید" text="وقتی کاری به شما سپرده شود یا نامه‌ای برسد، اینجا می‌بینید." />
                    <button
                        v-for="n in items"
                        v-else
                        :key="n.id"
                        class="flex w-full gap-3 border-b border-line px-5 py-4 text-start transition-colors hover:bg-surface-2"
                        :class="!n.read && 'bg-accent-soft/40'"
                        @click="go(n)"
                    >
                        <div class="relative">
                            <Avatar :user="n.actor" size="md" :status="false" />
                            <span class="absolute -bottom-1 -end-1 flex size-5 items-center justify-center rounded-full bg-surface text-accent shadow-soft">
                                <component :is="icons[n.kind] || Bell" class="size-3" />
                            </span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm leading-6" :class="n.read ? 'text-ink-2' : 'font-semibold text-ink'">{{ n.title }}</p>
                            <p v-if="n.body" class="mt-0.5 line-clamp-2 text-xs leading-5 text-ink-3">{{ n.body }}</p>
                            <p class="mt-1 text-[0.7rem] text-ink-3">{{ relative(n.created_at) }}</p>
                        </div>
                        <span v-if="!n.read" class="mt-2 size-2 shrink-0 rounded-full bg-accent" />
                    </button>
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>
