import { reactive } from 'vue';

export const toasts = reactive([]);
let seq = 0;

function push(type, message, timeout = 3800) {
    if (!message) return;
    const id = ++seq;
    toasts.push({ id, type, message });
    setTimeout(() => dismiss(id), timeout);
}

export function dismiss(id) {
    const i = toasts.findIndex((t) => t.id === id);
    if (i !== -1) toasts.splice(i, 1);
}

export const toast = {
    success: (m) => push('success', m),
    error: (m) => push('error', m, 5500),
    info: (m) => push('info', m),
};

export function useToast() {
    return toast;
}
