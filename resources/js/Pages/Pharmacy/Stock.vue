<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps, Paginated } from '@/types';

defineProps<{ batches: Paginated<any>; warehouses: any[]; products: any[]; recentMovements: any[]; batchOptions: any[] }>();
const page = usePage<PageProps>();
const can = (permission: string) => page.props.auth.permissions.includes(permission);
const form = useForm({ product_id: '', warehouse_id: '', lot_number: '', expires_on: '', status: 'available', quantity: 1, unit_cost: '', reason: '' });
const adjustment = useForm({ stock_batch_id: '', counted_quantity: 0, reason: '' });
const transfer = useForm({ stock_batch_id: '', target_warehouse_id: '', quantity: 1, reason: '' });
const receive = () => form.post(route('stock.receive'), { preserveScroll: true, onSuccess: () => form.reset('lot_number', 'expires_on', 'quantity', 'unit_cost', 'reason') });
const adjust = () => adjustment.post(route('stock.adjust'), { preserveScroll: true, onSuccess: () => adjustment.reset() });
const transferStock = () => transfer.post(route('stock.transfer'), { preserveScroll: true, onSuccess: () => transfer.reset() });
const date = (value?: string) => value ? new Intl.DateTimeFormat('fr-CM').format(new Date(value)) : '—';
</script>

<template>
    <Head title="Stocks" />
    <AuthenticatedLayout>
        <PageHeader title="Stocks multi-sites" subtitle="Lots, péremptions et journal immuable des mouvements." />
        <section v-if="can('stock.receive')" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-semibold">Nouvelle entrée de stock</h2>
            <form class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="receive">
                <label class="space-y-1 text-sm"><span>Produit *</span><select v-model="form.product_id" required class="w-full rounded-xl border-slate-300"><option value="">Sélectionner</option><option v-for="product in products" :key="product.id" :value="product.id">{{ product.trade_name }} · {{ product.code }}</option></select><InputError :message="form.errors.product_id" /></label>
                <label class="space-y-1 text-sm"><span>Magasin *</span><select v-model="form.warehouse_id" required class="w-full rounded-xl border-slate-300"><option value="">Sélectionner</option><option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }} · {{ warehouse.site.name }}</option></select></label>
                <label class="space-y-1 text-sm"><span>Lot *</span><input v-model="form.lot_number" required class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm"><span>Péremption</span><input v-model="form.expires_on" type="date" class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm"><span>Quantité *</span><input v-model="form.quantity" type="number" min="0.001" step="0.001" required class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm"><span>Coût unitaire indicatif</span><input v-model="form.unit_cost" type="number" min="0" step="0.0001" class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm"><span>Statut</span><select v-model="form.status" class="w-full rounded-xl border-slate-300"><option value="available">Disponible</option><option value="quarantine">Quarantaine</option></select></label>
                <label class="space-y-1 text-sm"><span>Motif / bordereau</span><input v-model="form.reason" class="w-full rounded-xl border-slate-300" /></label>
                <div class="xl:col-span-4 flex justify-end"><button class="rounded-xl bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white">Enregistrer l’entrée</button></div>
            </form>
        </section>
        <div v-if="can('stock.receive')" class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Ajustement d’inventaire</h2>
                <form class="mt-4 space-y-3" @submit.prevent="adjust">
                    <select v-model="adjustment.stock_batch_id" required class="w-full rounded-xl border-slate-300 text-sm"><option value="">Lot inventorié</option><option v-for="batch in batchOptions" :key="batch.id" :value="batch.id">{{ batch.product.trade_name }} · {{ batch.warehouse.name }} · {{ batch.lot_number }} ({{ batch.quantity_on_hand }})</option></select>
                    <input v-model="adjustment.counted_quantity" required type="number" min="0" step="0.001" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Quantité réellement comptée" />
                    <textarea v-model="adjustment.reason" required minlength="5" rows="2" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Motif obligatoire"></textarea>
                    <button class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Enregistrer l’ajustement</button>
                </form>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Transfert inter-magasins</h2>
                <form class="mt-4 space-y-3" @submit.prevent="transferStock">
                    <select v-model="transfer.stock_batch_id" required class="w-full rounded-xl border-slate-300 text-sm"><option value="">Lot source</option><option v-for="batch in batchOptions" :key="batch.id" :value="batch.id">{{ batch.product.trade_name }} · {{ batch.warehouse.name }} · {{ batch.lot_number }} ({{ batch.quantity_on_hand }})</option></select>
                    <select v-model="transfer.target_warehouse_id" required class="w-full rounded-xl border-slate-300 text-sm"><option value="">Magasin destinataire</option><option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }} · {{ warehouse.site.name }}</option></select>
                    <input v-model="transfer.quantity" required type="number" min="0.001" step="0.001" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Quantité" />
                    <textarea v-model="transfer.reason" required minlength="5" rows="2" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Motif obligatoire"></textarea>
                    <button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Transférer</button>
                </form>
            </section>
        </div>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Produit</th><th class="px-5 py-3">Magasin</th><th class="px-5 py-3">Lot</th><th class="px-5 py-3">Péremption</th><th class="px-5 py-3">Quantité</th><th class="px-5 py-3">Statut</th></tr></thead>
                <tbody class="divide-y divide-slate-100"><tr v-for="batch in batches.data" :key="batch.id"><td class="px-5 py-4"><div class="font-semibold">{{ batch.product.trade_name }}</div><div class="text-xs text-slate-500">{{ batch.product.code }}</div></td><td class="px-5 py-4">{{ batch.warehouse.name }}<div class="text-xs text-slate-500">{{ batch.warehouse.site.name }}</div></td><td class="px-5 py-4 font-mono">{{ batch.lot_number }}</td><td class="px-5 py-4" :class="batch.expires_on && new Date(batch.expires_on) < new Date() ? 'font-semibold text-red-700' : ''">{{ date(batch.expires_on) }}</td><td class="px-5 py-4 font-bold">{{ batch.quantity_on_hand }} {{ batch.product.unit }}</td><td class="px-5 py-4"><StatusBadge :status="batch.status" /></td></tr></tbody>
            </table></div>
            <div class="border-t border-slate-100 p-4"><Pagination :links="batches.links" /></div>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-semibold">Derniers mouvements</h2>
            <div class="mt-4 divide-y divide-slate-100"><div v-for="movement in recentMovements" :key="movement.id" class="grid gap-2 py-3 text-sm md:grid-cols-5"><span>{{ movement.batch.product.trade_name }}</span><span>{{ movement.batch.warehouse.name }}</span><span class="capitalize">{{ movement.type }}</span><span :class="Number(movement.quantity) < 0 ? 'text-red-700' : 'text-emerald-700'">{{ movement.quantity }}</span><span class="text-slate-500">{{ movement.actor.name }}</span></div></div>
        </section>
    </AuthenticatedLayout>
</template>
