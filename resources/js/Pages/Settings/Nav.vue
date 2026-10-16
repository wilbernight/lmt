<script setup>
import { Link } from '@inertiajs/vue3';
import { ShieldCheck, UserRound } from '@lucide/vue';

defineProps({ active: String });
</script>

<template>
    <nav class="flex gap-1 lg:flex-col">
        <Link :href="route('settings.profile')" class="flex items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-sm font-semibold" :class="active === 'profile' ? 'bg-accent-soft text-accent-ink' : 'text-ink-2 hover:bg-surface-2'"><UserRound class="size-4" /> حساب کاربری</Link>
        <Link :href="route('settings.security')" class="flex items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-sm font-semibold" :class="active === 'security' ? 'bg-accent-soft text-accent-ink' : 'text-ink-2 hover:bg-surface-2'"><ShieldCheck class="size-4" /> امنیت و ورود</Link>
    </nav>
</template>
