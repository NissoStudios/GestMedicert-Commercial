<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link } from '@inertiajs/vue3';
import type { Paginated } from '@/types';

defineProps<{ dispensations: Paginated<any> }>();
</script>

<template>
    <Head title="Délivrances" />
    <AuthenticatedLayout>
        <PageHeader title="Délivrances" subtitle="Traçabilité nominative des médicaments délivrés."><Link :href="route('dispensations.create')" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Nouvelle délivrance</Link></PageHeader>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Patient</th><th class="px-5 py-3">Magasin</th><th class="px-5 py-3">Produits</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="item in dispensations.data" :key="item.uuid"><td class="px-5 py-4"><Link :href="route('dispensations.show', item.uuid)" class="font-semibold text-teal-700">{{ new Date(item.dispensed_at).toLocaleString('fr-CM') }}</Link></td><td class="px-5 py-4">{{ item.patient.last_name }} {{ item.patient.first_name }}<div class="text-xs text-slate-500">{{ item.patient.patient_number }}</div></td><td class="px-5 py-4">{{ item.warehouse.name }}</td><td class="px-5 py-4">{{ item.items.map((line: any) => line.product.trade_name).join(', ') }}</td></tr><tr v-if="!dispensations.data.length"><td colspan="4" class="px-5 py-12 text-center text-slate-500">Aucune délivrance.</td></tr></tbody></table></div>
            <div class="border-t border-slate-100 p-4"><Pagination :links="dispensations.links" /></div>
        </section>
    </AuthenticatedLayout>
</template>
