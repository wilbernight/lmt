import { ref, watch } from 'vue';
import { route } from 'ziggy-js';
import http from '@/lib/http';
import { toast } from '@/composables/useToast';

/**
 * Drag-to-reorder for task lists. `tasks` is a getter returning the tasks in
 * their stored order. On drop (or nudge) the new arrangement of the tasks
 * currently shown is posted, so it also works for partial lists like the
 * dashboard — the server keeps them inside the slots they already occupy.
 */
export function useManualOrder(tasks, workspaceId) {
    const shown = ref([]);
    const dragId = ref(null);

    watch(tasks, (list) => { shown.value = [...list]; }, { immediate: true });

    function onDragStart(t, e) {
        dragId.value = t.id;
        e.dataTransfer.effectAllowed = 'move';
    }

    // Live preview: while dragging over a row, the dragged task is placed before it.
    function onDragOver(t, e) {
        if (!dragId.value || dragId.value === t.id) return;
        e.preventDefault();
        const di = shown.value.findIndex((x) => x.id === dragId.value);
        const ti = shown.value.findIndex((x) => x.id === t.id);
        if (di === -1 || ti === -1) return;
        const [moved] = shown.value.splice(di, 1);
        const target = shown.value.findIndex((x) => x.id === t.id);
        shown.value.splice(target === -1 ? shown.value.length : target, 0, moved);
    }

    async function saveOrder() {
        dragId.value = null;
        try {
            await http.post(route('tasks.reorder', workspaceId), { ids: shown.value.map((t) => t.id) });
        } catch {
            shown.value = [...tasks()];
            toast.error('جابه‌جایی بعضی وظیفه‌ها مجاز نبود.');
        }
    }

    function nudge(t, dir) {
        const i = shown.value.findIndex((x) => x.id === t.id);
        const j = i + dir;
        if (j < 0 || j >= shown.value.length) return;
        [shown.value[i], shown.value[j]] = [shown.value[j], shown.value[i]];
        saveOrder();
    }

    return { shown, dragId, onDragStart, onDragOver, onDrop: saveOrder, nudge };
}
