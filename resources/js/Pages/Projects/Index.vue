<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Archive, CalendarClock, Clock, FolderKanban, Globe, Lock, Plus, Search } from '@lucide/vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Field from '@/Components/ui/Field.vue';
import ColorSwatches from '@/Components/ui/ColorSwatches.vue';
import MemberPicker from '@/Components/ui/MemberPicker.vue';
import DatePicker from '@/Components/ui/DatePicker.vue';
import AvatarStack from '@/Components/ui/AvatarStack.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { faNum, hours, jDate } from '@/lib/format';

const props = defineProps({ projects: Array, archived: Boolean, filters: Object, colors: Array, canCreate: Boolean });

const page = usePage();
const ws = page.props.workspace;
const creating = ref(false);
const q = ref(props.filters.q ?? '');

const form = useForm({ name: '', description: '', color: 'indigo', visibility: 'members', starts_on: null, due_on: null, members: [] });

const submit = () => form.post(route('projects.store', ws.id), { onSuccess: () => { creating.value = false; form.reset(); } });

const search = useDebounceFn(() => router.get(route('projects.index', ws.id), { q: q.value || undefined, archived: props.archived ? 1 : undefined }, { preserveState: true, replace: true }), 300);
watch(q, search);

const percent = (p) => (p.tasks_count ? Math.round((p.done_count / p.tasks_count) * 100) : 0);
</script>

<template>
    <Head title="پروژه‌ها" />

    <PageHeader title="پروژه‌ها" :subtitle="archived ? 'پروژه‌های بایگانی‌شده' : `${faNum(projects.length)} پروژه فعال`" :icon="FolderKanban" tone="indigo">
        <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
            <input v-model="q" class="field h-10 w-56 ps-9" placeholder="فیلتر عنوان پروژه..." />
        </div>
        <Button variant="secondary" :href="route('projects.index', [ws.id, archived ? {} : { archived: 1 }])"><Archive class="size-4" /> {{ archived ? 'پروژه‌های فعال' : 'بایگانی' }}</Button>
        <Button v-if="canCreate" @click="creating = true"><Plus class="size-4" /> پروژه جدید</Button>
    </PageHeader>

    <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        <button v-if="canCreate && !archived" class="group flex min-h-52 flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-line-strong text-accent-ink transition-all hover:border-accent hover:bg-accent-soft" @click="creating = true">
            <span class="flex size-12 items-center justify-center rounded-2xl bg-accent-soft transition-transform group-hover:scale-110"><Plus class="size-6" /></span>
            <span class="font-bold">پروژه جدید</span>
        </button>

        <Link
            v-for="p in projects"
            :key="p.id"
            :href="route('projects.show', [ws.id, p.id])"
            class="group relative flex min-h-52 flex-col overflow-hidden rounded-2xl border border-line bg-surface p-5 shadow-soft transition-all hover:-translate-y-1 hover:shadow-card"
            :class="`tone-${p.color}`"
        >
            <span class="absolute inset-x-0 top-0 h-1 tone-bar" />
            <div class="flex items-start gap-3">
                <span class="flex size-11 items-center justify-center rounded-xl tone-chip text-lg font-black">{{ p.name.slice(0, 1) }}</span>
                <div class="min-w-0 flex-1">
                    <h3 class="truncate font-bold group-hover:text-accent-ink">{{ p.name }}</h3>
                    <p class="mt-0.5 flex items-center gap-1 text-xs text-ink-3">
                        <component :is="p.visibility === 'workspace' ? Globe : Lock" class="size-3" />
                        {{ p.visibility === 'workspace' ? 'قابل مشاهده برای همه' : 'فقط اعضای پروژه' }}
                    </p>
                </div>
            </div>
            <p class="mt-3 line-clamp-2 flex-1 text-sm leading-6 text-ink-2">{{ p.description || 'بدون توضیحات' }}</p>
            <div class="mt-4">
                <div class="mb-1.5 flex justify-between text-xs text-ink-3">
                    <span class="num">{{ faNum(p.done_count) }} از {{ faNum(p.tasks_count) }} وظیفه</span>
                    <span class="num font-bold text-ink-2">{{ faNum(percent(p)) }}٪</span>
                </div>
                <ProgressBar :value="percent(p)" />
            </div>
            <div class="mt-4 flex items-center gap-2 text-xs text-ink-3">
                <AvatarStack :users="p.members" :total="p.members_count" :max="4" />
                <span v-if="p.overdue_count" class="chip bg-danger-soft text-danger">{{ faNum(p.overdue_count) }} تأخیر</span>
                <span v-if="p.tracked_seconds" class="flex items-center gap-1"><Clock class="size-3.5" /> {{ hours(p.tracked_seconds) }} ساعت</span>
                <span v-if="p.due_on" class="ms-auto flex items-center gap-1"><CalendarClock class="size-3.5" /> {{ jDate(p.due_on) }}</span>
            </div>
        </Link>
    </div>

    <EmptyState v-if="!projects.length && (archived || !canCreate)" :icon="FolderKanban" :title="archived ? 'پروژه بایگانی‌شده‌ای نیست' : 'پروژه‌ای برای نمایش نیست'" />

    <Modal :show="creating" title="پروژه جدید" size="md" @close="creating = false">
        <form class="space-y-4 p-5" @submit.prevent="submit">
            <Field label="نام پروژه" :error="form.errors.name">
                <input v-model="form.name" autofocus maxlength="120" class="field" placeholder="مثلاً: وبسایت کلینیک" />
            </Field>
            <Field label="توضیحات" :error="form.errors.description">
                <textarea v-model="form.description" rows="3" maxlength="2000" class="field" />
            </Field>
            <Field label="رنگ"><ColorSwatches v-model="form.color" :colors="colors" /></Field>
            <div class="grid gap-3 sm:grid-cols-2">
                <Field label="شروع" :error="form.errors.starts_on"><DatePicker v-model="form.starts_on" /></Field>
                <Field label="پایان" :error="form.errors.due_on"><DatePicker v-model="form.due_on" /></Field>
            </div>
            <Field label="اعضای پروژه"><MemberPicker v-model="form.members" :exclude="[page.props.auth.user.id]" /></Field>
            <Field label="دسترسی">
                <div class="grid grid-cols-2 gap-2">
                    <button v-for="v in [{ k: 'members', l: 'فقط اعضای پروژه', i: Lock }, { k: 'workspace', l: 'همه اعضای میزکار', i: Globe }]" :key="v.k" type="button" class="flex items-center gap-2 rounded-xl border p-3 text-sm font-semibold transition-colors" :class="form.visibility === v.k ? 'border-accent bg-accent-soft text-accent-ink' : 'border-line-strong text-ink-2 hover:bg-surface-2'" @click="form.visibility = v.k">
                        <component :is="v.i" class="size-4" /> {{ v.l }}
                    </button>
                </div>
            </Field>
        </form>
        <template #footer>
            <Button variant="ghost" @click="creating = false">انصراف</Button>
            <Button :loading="form.processing" @click="submit">ساخت پروژه</Button>
        </template>
    </Modal>
</template>
