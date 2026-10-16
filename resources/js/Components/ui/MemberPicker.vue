<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Check, Search, UserPlus } from '@lucide/vue';
import Dropdown from './Dropdown.vue';
import Avatar from './Avatar.vue';
import AvatarStack from './AvatarStack.vue';

/** Multi-select of workspace members. v-model is an array of user ids. */
const model = defineModel({ type: Array, default: () => [] });

const props = defineProps({
    placeholder: { type: String, default: 'انتخاب افراد' },
    exclude: { type: Array, default: () => [] },
    single: Boolean,
    compact: Boolean,
    up: Boolean,
});

const page = usePage();
const q = ref('');

const members = computed(() => (page.props.workspace?.members ?? []).filter((m) => !props.exclude.includes(m.id)));
const filtered = computed(() => members.value.filter((m) => !q.value || m.name.includes(q.value.trim())));
const chosen = computed(() => members.value.filter((m) => model.value.includes(m.id)));

const toggle = (id, close) => {
    if (props.single) {
        model.value = model.value[0] === id ? [] : [id];
        close();
        return;
    }
    model.value = model.value.includes(id) ? model.value.filter((x) => x !== id) : [...model.value, id];
};
</script>

<template>
    <Dropdown width="w-64" :up="up">
        <template #trigger="{ toggle: open }">
            <button type="button" class="field flex items-center gap-2 text-start" :class="compact && 'h-9 py-1.5 text-xs'" @click="open">
                <template v-if="chosen.length">
                    <AvatarStack :users="chosen" :max="4" />
                    <span class="truncate text-xs text-ink-2">{{ chosen.length === 1 ? chosen[0].name : `${chosen.length} نفر` }}</span>
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
                <input v-model="q" class="field h-9 ps-8 text-xs" placeholder="جستجوی نام..." />
            </div>
            <div class="max-h-64 overflow-y-auto">
                <button
                    v-for="m in filtered"
                    :key="m.id"
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-start text-sm hover:bg-surface-3"
                    @click="toggle(m.id, close)"
                >
                    <Avatar :user="m" size="sm" />
                    <span class="flex-1 truncate">
                        {{ m.name }}
                        <span v-if="m.job_title" class="block text-[0.7rem] text-ink-3">{{ m.job_title }}</span>
                    </span>
                    <Check v-if="model.includes(m.id)" class="size-4 text-accent" />
                </button>
                <p v-if="!filtered.length" class="px-2 py-4 text-center text-xs text-ink-3">کسی پیدا نشد</p>
            </div>
        </template>
    </Dropdown>
</template>
