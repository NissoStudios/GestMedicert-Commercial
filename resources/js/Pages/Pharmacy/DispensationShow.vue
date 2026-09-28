<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
defineProps<{ dispensation: any }>();
const returnItem = (dispensationUuid: string, item: any) => {
    const quantity = window.prompt(`Quantité à retourner (maximum ${Number(item.quantity) - Number(item.quantity_returned)}):`);
    if (!quantity) return;
    const reason = window.prompt('Motif du retour :');
    if (!reason) return;
    router.post(route('dispensations.items.return', [dispensationUuid, item.id]), { quantity: Number(quantity), reason }, { preserveScroll: true });
};
</script>
<template>
    <Head title="Délivrance" />
    <AuthenticatedLayout>
        <PageHeader title="Délivrance enregistrée" :subtitle="new Date(dispensation.dispensed_at).toLocaleString('fr-CM')"><Link :href="route('dispensations.index')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Retour</Link></PageHeader>
        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2"><h2 class="font-semibold">Produits délivrés</h2><div class="mt-4 divide-y divide-slate-100"><div v-for="item in dispensation.items" :key="item.id" class="grid gap-2 py-4 text-sm md:grid-cols-4"><div><strong>{{ item.product.trade_name }}</strong><div class="text-slate-500">{{ item.product.inn }} {{ item.product.strength }}</div></div><div>Lot {{ item.batch.lot_number }}</div><div class="font-bold">{{ item.quantity }} {{ item.product.unit }}<div v-if="Number(item.quantity_returned)" class="text-xs font-normal text-amber-700">Retourné : {{ item.quantity_returned }}</div></div><button v-if="Number(item.quantity_returned) < Number(item.quantity)" @click="returnItem(dispensation.uuid, item)" class="text-left text-sm font-semibold text-teal-700">Enregistrer un retour</button></div></div></section>
            <aside class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-semibold">Contexte</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">Patient</dt><dd class="font-semibold">{{ dispensation.patient.full_name }}</dd></div><div><dt class="text-slate-500">Magasin</dt><dd class="font-semibold">{{ dispensation.warehouse.name }}</dd></div><div><dt class="text-slate-500">Site</dt><dd class="font-semibold">{{ dispensation.warehouse.site.name }}</dd></div><div><dt class="text-slate-500">Prescripteur</dt><dd class="font-semibold">{{ dispensation.prescription?.prescriber?.name ?? '—' }}</dd></div></dl></aside>
        </div>
    </AuthenticatedLayout>
</template>
