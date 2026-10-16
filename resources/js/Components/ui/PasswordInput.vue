<script setup>
import { ref } from 'vue';
import { Eye, EyeOff, Lock } from '@lucide/vue';

const model = defineModel({ type: String, default: '' });
defineProps({
    autocomplete: { type: String, default: 'current-password' },
    invalid: Boolean,
    placeholder: { type: String, default: '••••••••' },
    id: { type: String, default: undefined },
});

const visible = ref(false);
</script>

<template>
    <div class="relative">
        <Lock class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" />
        <input
            :id="id"
            v-model="model"
            :type="visible ? 'text' : 'password'"
            :autocomplete="autocomplete"
            :aria-invalid="invalid"
            :placeholder="placeholder"
            class="field h-11 pe-10 ps-9"
            dir="ltr"
            spellcheck="false"
        />
        <button type="button" class="absolute end-2 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-ink-3 hover:text-ink" :aria-label="visible ? 'پنهان کردن رمز' : 'نمایش رمز'" @click="visible = !visible">
            <component :is="visible ? EyeOff : Eye" class="size-4" />
        </button>
    </div>
</template>
