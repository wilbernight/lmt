import { onBeforeUnmount, onMounted, ref } from 'vue';

/** A reactive "now" that ticks every second, for live timers. */
export function useNow(interval = 1000) {
    const now = ref(Date.now());
    let id = null;

    onMounted(() => (id = setInterval(() => (now.value = Date.now()), interval)));
    onBeforeUnmount(() => clearInterval(id));

    return now;
}
