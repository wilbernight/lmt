<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import ColorSwatches from '@/Components/ui/ColorSwatches.vue';

defineProps({ colors: Array });
const form = useForm({ name: '', color: 'amber' });
</script>

<template>
    <Head title="میزکار جدید" />
    <div class="mx-auto max-w-lg py-10">
        <div class="card overflow-hidden">
            <div class="relative h-32 gradient-accent">
                <div class="absolute inset-0 [background-image:linear-gradient(rgba(255,255,255,.08)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.08)_1px,transparent_1px)] [background-size:28px_28px]" />
                <span class="absolute -bottom-8 start-6 flex size-16 items-center justify-center rounded-2xl bg-surface text-accent shadow-card"><Building2 class="size-8" /></span>
            </div>
            <form class="space-y-5 p-6 pt-12" @submit.prevent="form.post(route('workspaces.store'))">
                <div>
                    <h1 class="text-2xl font-black">ساخت میزکار جدید</h1>
                    <p class="mt-1 text-sm text-ink-3">هر میزکار فضای مستقلی با اعضا، پروژه‌ها و نامه‌های خودش است.</p>
                </div>
                <Field label="نام میزکار" :error="form.errors.name"><input v-model="form.name" autofocus maxlength="80" class="field h-11" placeholder="مثلاً: برنامه‌نویسی، حسابداری..." /></Field>
                <Field label="رنگ شاخص"><ColorSwatches v-model="form.color" :colors="colors" /></Field>
                <Button type="submit" size="lg" block :loading="form.processing">ساخت میزکار</Button>
            </form>
        </div>
    </div>
</template>
