<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps<{ filters: { from: string; to: string }; summary: Record<string, number>; topDiagnoses: any[]; expiringBatches: any[] }>();
const filters = reactive({ ...props.filters });
const apply = () => router.get(route('reports.index'), filters, { preserveState: true, replace: true });
const date = (value?: string) => value ? new Intl.DateTimeFormat('fr-CM').format(new Date(value)) : '—';
</script>

<template>
    <Head title="Rapports" />
    <AuthenticatedLayout>
        <PageHeader title="Rapports opérationnels" subtitle="Indicateurs agrégés selon vos sites autorisés." />
        <form class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="apply">
            <label class="space-y-1 text-sm"><span>Du</span><input v-model="filters.from" type="date" class="block rounded-xl border-slate-300" /></label>
            <label class="space-y-1 text-sm"><span>Au</span><input v-model="filters.to" type="date" class="block rounded-xl border-slate-300" /></label>
            <button class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white">Actualiser</button>
        </form>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Rendez-vous</div><div class="mt-2 text-3xl font-bold text-blue-700">{{ summary.appointments }}</div></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Consultations</div><div class="mt-2 text-3xl font-bold text-teal-700">{{ summary.consultations }}</div></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Documents</div><div class="mt-2 text-3xl font-bold text-violet-700">{{ summary.documents }}</div></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Unités délivrées</div><div class="mt-2 text-3xl font-bold text-amber-700">{{ summary.dispensedUnits }}</div></div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-semibold">Diagnostics les plus fréquents</h2><div class="mt-4 divide-y divide-slate-100"><div v-for="item in topDiagnoses" :key="item.code" class="flex justify-between gap-4 py-3 text-sm"><span><strong>{{ item.code }}</strong> · {{ item.label }}</span><span class="font-bold">{{ item.total }}</span></div><p v-if="!topDiagnoses.length" class="py-6 text-sm text-slate-500">Aucune donnée sur la période.</p></div></section>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-semibold">Lots expirant sous 90 jours</h2><div class="mt-4 divide-y divide-slate-100"><div v-for="batch in expiringBatches" :key="batch.id" class="grid grid-cols-3 gap-3 py-3 text-sm"><span class="font-medium">{{ batch.product.trade_name }}</span><span>{{ batch.warehouse.name }}</span><span class="text-red-700">{{ date(batch.expires_on) }} · {{ batch.quantity_on_hand }} {{ batch.product.unit }}</span></div><p v-if="!expiringBatches.length" class="py-6 text-sm text-slate-500">Aucun lot proche de la péremption.</p></div></section>
        </div>
    </AuthenticatedLayout>
</template>
