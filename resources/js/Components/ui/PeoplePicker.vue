<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Check, Search, UserPlus } from '@lucide/vue';
import Dropdown from './Dropdown.vue';
import Avatar from './Avatar.vue';
import AvatarStack from './AvatarStack.vue';

/**
 * Combined picker: workspace members + colleagues without an account (external people).
 * v-model is an array of entries:
 *   member  -> { user_id, name, external: false, ...extra }
 *   person  -> { person_id, name, external: true, ...extra }   (person_id null = free-typed, not saved yet)
 * Extra keys on entries (e.g. attended) are preserved, so callers can store their own flags.
 */
const model = defineModel({ type: Array, default: () => [] });

const props = defineProps({
    placeholder: { type: String, default: 'انتخاب افراد' },
    exclude: { type: Array, default: () => [] }, // member (user) ids hidden from the list
    single: Boolean,
    compact: Boolean,
    up: Boolean,
});

const page = usePage();
const q = ref('');

const members = computed(() => (page.props.workspace?.members ?? []).filter((m) => !props.exclude.includes(m.id)));
const people = computed(() => page.props.workspace?.people ?? []);

const query = computed(() => q.value.trim());
const matches = (name) => !query.value || name.includes(query.value);
const norm = (s) => s.replace(/\u200c/g, ' ').replace(/\s+/g, ' ').trim();

const filteredMembers = computed(() => members.value.filter((m) => matches(m.name)));
const filteredPeople = computed(() => people.value.filter((p) => matches(p.name)));

// Show the "add by name" row only when nothing already carries that exact name.
const exact = computed(() => query.value !== '' && [...filteredMembers.value, ...filteredPeople.value].some((x) => norm(x.name) === norm(query.value)));

const isMember = (id) => model.value.some((e) => !e.external && e.user_id === id);
const isPerson = (id) => model.value.some((e) => e.external && e.person_id === id);

const chosenUsers = computed(() => model.value.filter((e) => !e.external).map((e) => ({ ...e, id: e.user_id })));

function pick(fields, close) {
    if (props.single) {
        model.value = [fields];
        close();
        return;
    }
    model.value = [...model.value, fields];
}

function toggleMember(m, close) {
    if (props.single) {
        model.value = isMember(m.id) ? [] : [{ user_id: m.id, name: m.name, external: false }];
        close();
        return;
    }
    model.value = isMember(m.id) ? model.value.filter((e) => e.external || e.user_id !== m.id) : [...model.value, { user_id: m.id, name: m.name, external: false }];
}

function togglePerson(p, close) {
    if (props.single) {
        model.value = isPerson(p.id) ? [] : [{ person_id: p.id, name: p.name, external: true }];
        close();
        return;
    }
    model.value = isPerson(p.id) ? model.value.filter((e) => !e.external || e.person_id !== p.id) : [...model.value, { person_id: p.id, name: p.name, external: true }];
}

/** A free-typed name becomes an external entry; the server matches/creates it on save. */
function addFree(close) {
    const name = query.value;
    if (!name) return;
    pick({ person_id: null, name, external: true }, close);
    q.value = '';
}

const initialsOf = (name) => name.split(/\s+/).slice(0, 2).map((w) => w[0] ?? '').join('');
const label = computed(() => {
    if (!model.value.length) return '';
    return model.value.length === 1 ? model.value[0].name : `${model.value.length} نفر`;
});
</script>

<template>
    <Dropdown width="w-72" :up="up">
        <template #trigger="{ toggle: open }">
            <button type="button" class="field flex items-center gap-2 text-start" :class="compact && 'h-9 py-1.5 text-xs'" @click="open">
                <template v-if="model.length">
                    <AvatarStack :users="chosenUsers" :max="4" />
                    <span
                        v-for="p in model.filter((e) => e.external).slice(0, 4 - chosenUsers.length)"
                        :key="p.name"
                        class="flex size-6 items-center justify-center rounded-full border border-dashed border-line-strong bg-surface-2 text-[0.55rem] font-bold text-ink-2 ring-2 ring-surface"
                        :title="`${p.name} (بدون اکانت)`"
                    >{{ initialsOf(p.name) || '؟' }}</span>
                    <span class="truncate text-xs text-ink-2">{{ label }}</span>
                </template>
                <template v-else>
                    <UserPlus class="size-4 text-ink-3" />
                    <span class="text-ink-3">{{ placeholder }}</span>
                </template>
            </button>
        </template>
        <template #default="{ close }">
            <div class="relative mb-1.5">
                <Search class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
                <input v-model="q" class="field h-9 ps-8 text-xs" placeholder="جستجوی نام یا نوشتن نام جدید..." />
            </div>
            <div class="max-h-64 overflow-y-auto">
                <button
                    v-for="m in filteredMembers"
                    :key="m.id"
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-start text-sm hover:bg-surface-3"
                    @click="toggleMember(m, close)"
                >
                    <Avatar :user="m" size="sm" />
                    <span class="flex-1 truncate">
                        {{ m.name }}
                        <span v-if="m.job_title" class="block text-[0.7rem] text-ink-3">{{ m.job_title }}</span>
                    </span>
                    <Check v-if="isMember(m.id)" class="size-4 text-accent" />
                </button>

                <p v-if="filteredPeople.length" class="px-2 pb-1 pt-2 text-[0.65rem] font-bold text-ink-3">بدون اکانت</p>
                <button
                    v-for="p in filteredPeople"
                    :key="p.id"
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-start text-sm hover:bg-surface-3"
                    @click="togglePerson(p, close)"
                >
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full border border-dashed border-line-strong bg-surface-2 text-[0.65rem] font-bold text-ink-2">{{ p.initials }}</span>
                    <span class="flex-1 truncate">{{ p.name }}</span>
                    <Check v-if="isPerson(p.id)" class="size-4 text-accent" />
                </button>

                <button
                    v-if="query && !exact"
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-lg border border-dashed border-line-strong px-2 py-2 text-start text-sm font-semibold text-accent-ink hover:bg-accent-soft"
                    @click="addFree(close)"
                >
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-accent"><UserPlus class="size-4" /></span>
                    <span class="flex-1">افزودن «{{ query }}» به‌عنوان شخص بدون اکانت</span>
                </button>

                <p v-if="!filteredMembers.length && !filteredPeople.length && !query" class="px-2 py-4 text-center text-xs text-ink-3">کسی پیدا نشد؛ نام جدید را همین‌جا بنویسید.</p>
            </div>
        </template>
    </Dropdown>
</template>
