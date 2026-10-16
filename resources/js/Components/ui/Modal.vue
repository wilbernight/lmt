<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { X } from '@lucide/vue';

const props = defineProps({
    show: Boolean,
    title: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm md lg xl full
    closeable: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);
const panel = ref(null);
let lastFocus = null;

const close = () => props.closeable && emit('close');
const onKey = (e) => e.key === 'Escape' && close();

watch(
    () => props.show,
    async (open) => {
        if (open) {
            lastFocus = document.activeElement;
            document.addEventListener('keydown', onKey);
            document.body.style.overflow = 'hidden';
            await nextTick();
            panel.value?.querySelector('[autofocus], input, textarea, button')?.focus();
        } else {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
            lastFocus?.focus?.();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});

const widths = { sm: 'max-w-md', md: 'max-w-xl', lg: 'max-w-3xl', xl: 'max-w-5xl', full: 'max-w-[min(96vw,1400px)]' };
</script>

<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition duration-200" leave-active-class="transition duration-150">
            <div v-if="show" class="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto bg-[var(--scrim)] p-0 backdrop-blur-[2px] sm:items-start sm:p-6 sm:pt-[8vh]" @mousedown.self="close">
                <div
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                    :class="widths[size]"
                    class="animate-pop relative w-full rounded-t-2xl border border-line bg-surface shadow-pop sm:rounded-2xl"
                >
                    <header v-if="title || $slots.header" class="flex items-center gap-3 border-b border-line px-5 py-4">
                        <slot name="header">
                            <h2 class="text-base font-bold">{{ title }}</h2>
                        </slot>
                        <button v-if="closeable" type="button" class="ms-auto rounded-lg p-1.5 text-ink-3 hover:bg-surface-3 hover:text-ink" aria-label="بستن" @click="close">
                            <X class="size-5" />
                        </button>
                    </header>
                    <slot />
                    <footer v-if="$slots.footer" class="flex items-center justify-end gap-2 border-t border-line bg-surface-2/60 px-5 py-3.5 sm:rounded-b-2xl">
                        <slot name="footer" />
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
