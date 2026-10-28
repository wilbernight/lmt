<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ArrowRightLeft, Building2, FolderKanban } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Spinner from '@/Components/ui/Spinner.vue';
import http from '@/lib/http';
import { toast } from '@/composables/useToast';

// Generic "move this record elsewhere" dialog: workspace (and optional project)
// pickers fed by transfer.options, then a POST to the given endpoint.
const props = defineProps({
    show: Boolean,
    title: { type: String, default: 'انتقال' },
    withProjects: Boolean,
    endpoint: { type: String, required: true },
    // The task's current project: staying on it in this workspace is not a move.
    excludeProjectId: { type: String, default: null },
});

const emit = defineEmits(['close', 'done']);

const page = usePage();
const ws = computed(() => page.props.workspace);

const loading = ref(false);
const sending = ref(false);
const workspaces = ref([]);
const workspaceId = ref(null);
const projectId = ref('');

const destination = computed(() => workspaces.value.find((w) => w.id === workspaceId.value) ?? null);
const projects = computed(() => destination.value?.projects ?? []);
// Within this workspace only a project move makes sense; otherwise pick another one.
const destinations = computed(() =>
    props.withProjects ? workspaces.value : workspaces.value.filter((w) => w.id !== ws.value.id),
);
const samePlace = computed(() => {
    if (destination.value?.id !== ws.value.id) return false;

    return props.withProjects ? (projectId.value || null) === (props.excludeProjectId || null) : true;
});
const canSubmit = computed(() => destination.value !== null && !samePlace.value);

watch(
    () => props.show,
    async (open) => {
        if (!open) return;
        workspaceId.value = null;
        projectId.value = '';

        if (workspaces.value.length === 0 && !loading.value) {
            loading.value = true;

            try {
                ({ data: { workspaces: workspaces.value } } = await http.get(route('transfer.options', ws.value.id)));
            } catch {
                // Errors are toasted by the http layer.
            } finally {
                loading.value = false;
            }
        }

        // Default: this workspace (a project move) when projects are offered, else the first other one.
        workspaceId.value = props.withProjects ? ws.value.id : destinations.value[0]?.id ?? null;
    },
);

watch(workspaceId, () => {
    const first = projects.value.find((p) => !(workspaceId.value === ws.value.id && p.id === props.excludeProjectId));
    projectId.value = first?.id ?? '';
});

async function submit() {
    if (!canSubmit.value || sending.value) return;
    sending.value = true;

    try {
        const { data } = await http.post(props.endpoint, {
            workspace_id: workspaceId.value,
            project_id: props.withProjects ? projectId.value || null : null,
        });

        toast(data.message || 'انتقال انجام شد.');
        emit('done', data);
        emit('close');
    } catch {
        // Errors are toasted by the http layer.
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <Modal :show="show" :title="title" size="sm" @close="emit('close')">
        <div class="p-5">
            <div v-if="loading" class="flex justify-center py-8"><Spinner class="size-6" /></div>

            <p v-else-if="!destinations.length" class="py-8 text-center text-sm text-ink-3">
                مقصد دیگری برای انتقال ندارید.
            </p>

            <div v-else class="space-y-4">
                <label class="block">
                    <span class="label mb-1.5 flex items-center gap-1.5"><Building2 class="size-3.5" /> میزکار مقصد</span>
                    <select v-model="workspaceId" class="field">
                        <option v-for="w in destinations" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </label>

                <label v-if="withProjects" class="block">
                    <span class="label mb-1.5 flex items-center gap-1.5"><FolderKanban class="size-3.5" /> پروژه مقصد</span>
                    <select v-model="projectId" class="field" :disabled="!projects.length">
                        <option value="">{{ projects.length ? 'بدون پروژه' : 'پروژه‌ای ندارد' }}</option>
                        <option
                            v-for="p in projects"
                            :key="p.id"
                            :value="p.id"
                            :disabled="destination?.id === ws.id && p.id === excludeProjectId"
                        >
                            {{ p.name }}
                        </option>
                    </select>
                </label>
            </div>
        </div>

        <template #footer>
            <Button variant="ghost" @click="emit('close')">انصراف</Button>
            <Button :loading="sending" :disabled="!canSubmit" @click="submit"><ArrowRightLeft class="size-4" /> انتقال</Button>
        </template>
    </Modal>
</template>
