<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Clock, ListChecks, Timer, Users } from '@lucide/vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Spinner from '@/Components/ui/Spinner.vue';
import DatePicker from '@/Components/ui/DatePicker.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import http from '@/lib/http';
import { MONTHS, toJalali } from '@/lib/jalali';
import { faNum, formatDuration, hours, jTime } from '@/lib/format';
import { openTask, taskModal } from '@/composables/useTaskModal';

const props = defineProps({ projectId: { type: String, required: true } });

const page = usePage();
const data = ref(null);
const loading = ref(true);
const range = ref({ from: null, to: null });

async function load() {
    loading.value = !data.value;
    try {
        const { data: d } = await http.get(route('projects.time', [page.props.workspace.id, props.projectId]), {
            params: { from: range.value.from?.slice(0, 10), to: range.value.to?.slice(0, 10) },
        });
        data.value = d;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(range, load, { deep: true });
watch(() => taskModal.version, load);

const maxDaily = computed(() => Math.max(1, ...(data.value?.daily ?? []).map((d) => d.seconds)));
const maxMember = computed(() => Math.max(1, ...(data.value?.by_member ?? []).map((m) => m.seconds)));
const dayLabel = (d) => {
    const j = toJalali(new Date(d));
    return `${faNum(j.jd)} ${MONTHS[j.jm - 1]}`;
};
</script>

<template>
    <div class="mt-5">
        <div v-if="loading" class="flex justify-center py-16"><Spinner class="size-6 text-accent" /></div>

        <template v-else-if="data">
            <div class="flex flex-wrap items-end gap-3">
                <div class="card flex items-center gap-4 px-5 py-4">
                    <span class="flex size-11 items-center justify-center rounded-xl tone-chip tone-emerald"><Clock class="size-5" /></span>
                    <div>
                        <p class="text-xs text-ink-3">کل ساعت کار پروژه</p>
                        <p class="num text-2xl font-black">{{ hours(data.total) }} <span class="text-sm font-semibold text-ink-3">ساعت</span></p>
                        <p class="text-xs text-ink-3">{{ formatDuration(data.total) }}</p>
                    </div>
                </div>
                <div class="w-44"><label class="label">از تاریخ</label><DatePicker v-model="range.from" compact placeholder="ابتدای پروژه" /></div>
                <div class="w-44"><label class="label">تا تاریخ</label><DatePicker v-model="range.to" compact placeholder="امروز" /></div>
                <div v-if="data.running.length" class="flex flex-wrap items-center gap-2 rounded-xl border border-success/40 bg-success-soft px-3 py-2 text-xs text-success">
                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60" /><span class="relative size-2 rounded-full bg-success" /></span>
                    الان در حال کار:
                    <span v-for="r in data.running" :key="r.user.id" class="flex items-center gap-1 font-semibold"><Avatar :user="r.user" size="xs" :status="false" /> {{ r.user.name }} <span class="font-normal opacity-80">({{ r.task }} از {{ jTime(r.started_at) }})</span></span>
                </div>
            </div>

            <EmptyState v-if="!data.total" :icon="Timer" title="هنوز ساعتی ثبت نشده" text="از داخل هر وظیفه، دکمه «شروع کار» را بزنید یا زمان را دستی ثبت کنید." class="card mt-5" />

            <template v-else>
                <section class="card mt-5 p-5">
                    <h3 class="mb-4 text-sm font-bold">ساعت کار روزانه (۳۰ روز گذشته)</h3>
                    <div class="flex h-36 items-end gap-1">
                        <div v-for="d in data.daily" :key="d.date" class="group relative flex-1" :title="`${dayLabel(d.date)}: ${formatDuration(d.seconds)}`">
                            <div class="w-full rounded-t-md transition-colors" :class="d.seconds ? 'gradient-accent group-hover:brightness-110' : 'bg-surface-3'" :style="{ height: `${Math.max(3, (d.seconds / maxDaily) * 100)}%` }" />
                        </div>
                    </div>
                    <div class="mt-2 flex justify-between text-[0.65rem] text-ink-3"><span>{{ dayLabel(data.daily[0].date) }}</span><span>امروز</span></div>
                </section>

                <div class="mt-5 grid gap-5 xl:grid-cols-2">
                    <section class="card overflow-hidden">
                        <header class="flex items-center gap-2 border-b border-line px-5 py-4"><Users class="size-4 text-accent" /><h3 class="text-sm font-bold">به تفکیک افراد</h3></header>
                        <ul class="divide-y divide-line">
                            <li v-for="m in data.by_member" :key="m.user.id" class="px-5 py-3">
                                <div class="flex items-center gap-3 text-sm">
                                    <Avatar :user="m.user" size="sm" />
                                    <span class="flex-1 font-semibold">{{ m.user.name }}</span>
                                    <span class="num font-bold">{{ formatDuration(m.seconds) }}</span>
                                    <span class="num w-10 text-end text-xs text-ink-3">{{ faNum(Math.round((m.seconds / data.total) * 100)) }}٪</span>
                                </div>
                                <div class="ms-11 mt-2 h-1.5 overflow-hidden rounded-full bg-surface-3"><div class="h-full rounded-full gradient-accent" :style="{ width: `${(m.seconds / maxMember) * 100}%` }" /></div>
                            </li>
                        </ul>
                    </section>

                    <section class="card overflow-hidden">
                        <header class="flex items-center gap-2 border-b border-line px-5 py-4"><ListChecks class="size-4 text-accent" /><h3 class="text-sm font-bold">به تفکیک وظیفه</h3></header>
                        <ul class="divide-y divide-line">
                            <li v-for="t in data.by_task" :key="t.id">
                                <button class="flex w-full items-center gap-3 px-5 py-3 text-start text-sm hover:bg-surface-2" @click="openTask(t.id)">
                                    <span class="min-w-0 flex-1 truncate font-semibold">{{ t.title }}</span>
                                    <span v-if="t.estimate_seconds" class="chip" :class="t.seconds > t.estimate_seconds ? 'bg-danger-soft text-danger' : 'bg-surface-3 text-ink-2'">تخمین {{ formatDuration(t.estimate_seconds) }}</span>
                                    <span class="num font-bold">{{ formatDuration(t.seconds) }}</span>
                                </button>
                            </li>
                        </ul>
                    </section>
                </div>
            </template>
        </template>
    </div>
</template>
