<script setup>
import { CircleAlert, CircleCheck, Info, X } from '@lucide/vue';
import { dismiss, toasts } from '@/composables/useToast';

const icons = { success: CircleCheck, error: CircleAlert, info: Info };
const tones = { success: 'text-success', error: 'text-danger', info: 'text-info' };
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4 sm:bottom-6 sm:start-6 sm:items-start" aria-live="polite">
        <TransitionGroup enter-from-class="opacity-0 translate-y-3" leave-to-class="opacity-0 scale-95" enter-active-class="transition duration-200" leave-active-class="transition duration-150">
            <div
                v-for="t in toasts"
                :key="t.id"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-line bg-surface px-4 py-3 shadow-pop"
                :role="t.type === 'error' ? 'alert' : 'status'"
            >
                <component :is="icons[t.type]" class="mt-0.5 size-5 shrink-0" :class="tones[t.type]" />
                <p class="flex-1 text-sm leading-6 text-ink">{{ t.message }}</p>
                <button type="button" class="rounded p-0.5 text-ink-3 hover:text-ink" aria-label="بستن" @click="dismiss(t.id)">
                    <X class="size-4" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
