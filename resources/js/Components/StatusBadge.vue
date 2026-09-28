<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ status: string }>();

const classes = computed(() => {
    const styles: Record<string, string> = {
        approved: 'bg-emerald-100 text-emerald-800',
        finalized: 'bg-emerald-100 text-emerald-800',
        completed: 'bg-emerald-100 text-emerald-800',
        available: 'bg-emerald-100 text-emerald-800',
        submitted: 'bg-blue-100 text-blue-800',
        confirmed: 'bg-blue-100 text-blue-800',
        issued: 'bg-blue-100 text-blue-800',
        scheduled: 'bg-indigo-100 text-indigo-800',
        draft: 'bg-slate-100 text-slate-700',
        waiting: 'bg-amber-100 text-amber-800',
        called: 'bg-violet-100 text-violet-800',
        in_consultation: 'bg-violet-100 text-violet-800',
        partially_dispensed: 'bg-amber-100 text-amber-800',
        rejected: 'bg-red-100 text-red-800',
        revoked: 'bg-red-100 text-red-800',
        expired: 'bg-red-100 text-red-800',
        quarantine: 'bg-orange-100 text-orange-800',
        cancelled: 'bg-slate-200 text-slate-700',
        no_show: 'bg-slate-200 text-slate-700',
    };
    return styles[props.status] ?? 'bg-slate-100 text-slate-700';
});

const label = computed(() => props.status.replaceAll('_', ' '));
</script>

<template>
    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="classes">
        {{ label }}
    </span>
</template>
