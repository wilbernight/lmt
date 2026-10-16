<script setup>
import { computed, ref, watch } from 'vue';
import { CalendarDays, ChevronLeft, ChevronRight, X } from '@lucide/vue';
import Dropdown from './Dropdown.vue';
import { MONTHS, WEEKDAYS_SHORT, monthGrid, toJalali } from '@/lib/jalali';
import { faNum, isSameDay, jDate, jTime } from '@/lib/format';

/** Jalali date (and optional time) picker. v-model is an ISO string or null. */
const model = defineModel({ type: String, default: null });

const props = defineProps({
    placeholder: { type: String, default: 'انتخاب تاریخ' },
    time: Boolean,
    compact: Boolean,
});

const dropdown = ref(null);
const initial = toJalali(model.value ? new Date(model.value) : new Date());
const view = ref({ jy: initial.jy, jm: initial.jm });
const hour = ref(model.value ? new Date(model.value).getHours() : 17);
const minute = ref(model.value ? new Date(model.value).getMinutes() : 0);

watch(model, (v) => {
    if (!v) return;
    const j = toJalali(new Date(v));
    view.value = { jy: j.jy, jm: j.jm };
});

const days = computed(() => monthGrid(view.value.jy, view.value.jm));
const selected = computed(() => (model.value ? new Date(model.value) : null));
const today = new Date();

const step = (delta) => {
    let { jy, jm } = view.value;
    jm += delta;
    if (jm < 1) { jm = 12; jy -= 1; }
    if (jm > 12) { jm = 1; jy += 1; }
    view.value = { jy, jm };
};

const inMonth = (d) => toJalali(d).jm === view.value.jm;

const pick = (d, close) => {
    const date = new Date(d);
    if (props.time) date.setHours(hour.value, minute.value, 0, 0);
    else date.setHours(23, 59, 0, 0);
    model.value = date.toISOString();
    if (!props.time) close();
};

const applyTime = () => {
    if (!model.value) return;
    const date = new Date(model.value);
    date.setHours(hour.value, minute.value, 0, 0);
    model.value = date.toISOString();
};

const quick = (offsetDays, close) => {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    pick(d, close);
    close();
};

const label = computed(() => (model.value ? (props.time ? `${jDate(model.value)} · ${jTime(model.value)}` : jDate(model.value)) : ''));
</script>

<template>
    <Dropdown ref="dropdown" width="w-72">
        <template #trigger="{ toggle }">
            <button
                type="button"
                class="field flex items-center gap-2 text-start"
                :class="[compact && 'h-9 py-1.5 text-xs', !model && 'text-ink-3']"
                @click="toggle"
            >
                <CalendarDays class="size-4 shrink-0 text-ink-3" />
                <span class="flex-1 truncate">{{ label || placeholder }}</span>
                <span v-if="model" role="button" tabindex="0" class="rounded p-0.5 text-ink-3 hover:text-danger" aria-label="پاک کردن تاریخ" @click.stop="model = null" @keydown.enter.stop="model = null">
                    <X class="size-3.5" />
                </span>
            </button>
        </template>

        <template #default="{ close }">
            <div class="p-2">
                <div class="mb-2 flex items-center justify-between">
                    <button type="button" class="rounded-lg p-1.5 hover:bg-surface-3" aria-label="ماه قبل" @click="step(-1)"><ChevronRight class="size-4" /></button>
                    <span class="text-sm font-bold">{{ MONTHS[view.jm - 1] }} {{ faNum(view.jy) }}</span>
                    <button type="button" class="rounded-lg p-1.5 hover:bg-surface-3" aria-label="ماه بعد" @click="step(1)"><ChevronLeft class="size-4" /></button>
                </div>
                <div class="grid grid-cols-7 gap-0.5 text-center text-[0.7rem] font-semibold text-ink-3">
                    <span v-for="w in WEEKDAYS_SHORT" :key="w" class="py-1">{{ w }}</span>
                </div>
                <div class="grid grid-cols-7 gap-0.5">
                    <button
                        v-for="d in days"
                        :key="d.toISOString()"
                        type="button"
                        class="num aspect-square rounded-lg text-xs font-medium transition-colors"
                        :class="[
                            selected && isSameDay(d, selected) ? 'gradient-accent text-on-accent' : 'hover:bg-surface-3',
                            !inMonth(d) && 'text-ink-3/50',
                            isSameDay(d, today) && !(selected && isSameDay(d, selected)) && 'ring-1 ring-accent text-accent-ink',
                            d.getDay() === 5 && inMonth(d) && !(selected && isSameDay(d, selected)) && 'text-danger',
                        ]"
                        @click="pick(d, close)"
                    >
                        {{ faNum(toJalali(d).jd) }}
                    </button>
                </div>
                <div v-if="time" class="mt-3 flex items-center gap-2 border-t border-line pt-3 text-sm" dir="ltr">
                    <select v-model.number="hour" class="field h-8 flex-1 py-0 text-center" @change="applyTime">
                        <option v-for="h in 24" :key="h" :value="h - 1">{{ faNum(String(h - 1).padStart(2, '0')) }}</option>
                    </select>
                    <span>:</span>
                    <select v-model.number="minute" class="field h-8 flex-1 py-0 text-center" @change="applyTime">
                        <option v-for="m in [0, 15, 30, 45]" :key="m" :value="m">{{ faNum(String(m).padStart(2, '0')) }}</option>
                    </select>
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5 border-t border-line pt-3">
                    <button type="button" class="chip bg-surface-3 text-ink-2 hover:bg-accent-soft hover:text-accent-ink" @click="quick(0, close)">امروز</button>
                    <button type="button" class="chip bg-surface-3 text-ink-2 hover:bg-accent-soft hover:text-accent-ink" @click="quick(1, close)">فردا</button>
                    <button type="button" class="chip bg-surface-3 text-ink-2 hover:bg-accent-soft hover:text-accent-ink" @click="quick(7, close)">هفته بعد</button>
                </div>
            </div>
        </template>
    </Dropdown>
</template>
