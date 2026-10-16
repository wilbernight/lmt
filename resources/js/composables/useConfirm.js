import { reactive } from 'vue';

/** Promise-based confirm dialog (never the browser's blocking window.confirm). */
export const confirmState = reactive({
    open: false,
    title: '',
    text: '',
    confirmText: 'تأیید',
    danger: false,
    resolve: null,
});

export function confirmDialog({ title, text = '', confirmText = 'تأیید', danger = false }) {
    return new Promise((resolve) => {
        Object.assign(confirmState, { open: true, title, text, confirmText, danger, resolve });
    });
}

export function settleConfirm(value) {
    confirmState.resolve?.(value);
    Object.assign(confirmState, { open: false, resolve: null });
}
