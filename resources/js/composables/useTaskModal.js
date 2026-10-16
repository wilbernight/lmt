import { reactive } from 'vue';

/** Global state for the task detail and quick-create dialogs, opened from any page. */
export const taskModal = reactive({
    id: null,
    create: false,
    defaults: {},
    version: 0, // bumped after a change so lists can refresh
});

export function openTask(id) {
    taskModal.id = id;
}

export function closeTask() {
    taskModal.id = null;
}

export function openCreateTask(defaults = {}) {
    taskModal.defaults = defaults;
    taskModal.create = true;
}

export function taskChanged() {
    taskModal.version++;
}
