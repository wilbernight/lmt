<script setup>
import { TriangleAlert } from '@lucide/vue';
import Modal from './Modal.vue';
import Button from './Button.vue';
import { confirmState, settleConfirm } from '@/composables/useConfirm';
</script>

<template>
    <Modal :show="confirmState.open" size="sm" @close="settleConfirm(false)">
        <div class="flex gap-4 p-6">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-full" :class="confirmState.danger ? 'bg-danger-soft text-danger' : 'bg-accent-soft text-accent'">
                <TriangleAlert class="size-5" />
            </div>
            <div>
                <h3 class="font-bold">{{ confirmState.title }}</h3>
                <p v-if="confirmState.text" class="mt-1.5 text-sm leading-6 text-ink-2">{{ confirmState.text }}</p>
            </div>
        </div>
        <template #footer>
            <Button variant="ghost" @click="settleConfirm(false)">انصراف</Button>
            <Button :variant="confirmState.danger ? 'danger' : 'primary'" autofocus @click="settleConfirm(true)">{{ confirmState.confirmText }}</Button>
        </template>
    </Modal>
</template>
