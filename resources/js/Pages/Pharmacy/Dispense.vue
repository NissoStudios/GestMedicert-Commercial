<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps<{ prescriptions: any[]; warehouses: any[]; batches: any[] }>();
const form = useForm<{ prescription_id: number | string; warehouse_id: number | string; notes: string; items: any[] }>({ prescription_id: '', warehouse_id: '', notes: '', items: [] });
const prescription = computed(() => props.prescriptions.find((item) => item.id === Number(form.prescription_id)));
const availableBatches = (productId: number) => props.batches.filter((batch) => batch.product_id === productId && batch.warehouse_id === Number(form.warehouse_id));
watch(() => form.prescription_id, () => {
    form.items = prescription.value?.items.map((item: any) => ({ prescription_item_id: item.id, stock_batch_id: '', quantity: Number(item.quantity_prescribed) - Number(item.quantity_dispensed), lot_selection_reason: '' })) ?? [];
});
</script>

<template>
    <Head title="Nouvelle délivrance" />
    <AuthenticatedLayout>
        <PageHeader title="Nouvelle délivrance" subtitle="La délivrance peut être partielle ; les reliquats restent suivis."><Link :href="route('dispensations.index')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Annuler</Link></PageHeader>
        <form class="space-y-6" @submit.prevent="form.post(route('dispensations.store'))">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="space-y-1 text-sm"><span>Ordonnance *</span><select v-model="form.prescription_id" required class="w-full rounded-xl border-slate-300"><option value="">Sélectionner</option><option v-for="item in prescriptions" :key="item.id" :value="item.id">{{ item.patient.last_name }} {{ item.patient.first_name }} · {{ new Date(item.issued_at).toLocaleDateString('fr-CM') }}</option></select><InputError :message="form.errors.prescription_id" /></label>
                    <label class="space-y-1 text-sm"><span>Magasin *</span><select v-model="form.warehouse_id" required class="w-full rounded-xl border-slate-300"><option value="">Sélectionner</option><option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }} · {{ warehouse.site.name }}</option></select><InputError :message="form.errors.warehouse_id" /></label>
                </div>
            </section>
            <section v-if="prescription" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Produits prescrits</h2>
                <div class="mt-4 space-y-4">
                    <div v-for="(line, index) in prescription.items" :key="line.id" class="grid gap-4 rounded-xl bg-slate-50 p-4 lg:grid-cols-[1.4fr_1fr_180px]">
                        <div><div class="font-semibold">{{ line.product.trade_name }} {{ line.product.strength }}</div><div class="text-sm text-slate-500">Prescrit {{ line.quantity_prescribed }} · déjà délivré {{ line.quantity_dispensed }}</div></div>
                        <select v-model="form.items[index].stock_batch_id" required class="rounded-xl border-slate-300 text-sm"><option value="">Choisir un lot</option><option v-for="batch in availableBatches(line.product_id)" :key="batch.id" :value="batch.id">{{ batch.lot_number }} · stock {{ batch.quantity_on_hand }} · exp. {{ batch.expires_on?.slice(0, 10) ?? '—' }}</option></select>
                        <input v-model="form.items[index].quantity" type="number" min="0.001" step="0.001" required class="rounded-xl border-slate-300 text-sm" />
                        <input v-model="form.items[index].lot_selection_reason" class="rounded-xl border-slate-300 text-sm lg:col-span-3" placeholder="Justification obligatoire si le lot choisi n’est pas le premier à expirer" />
                    </div>
                </div>
                <InputError :message="form.errors.items" />
            </section>
            <label class="block space-y-1 text-sm"><span>Notes de délivrance</span><textarea v-model="form.notes" rows="3" class="w-full rounded-xl border-slate-300"></textarea></label>
            <div class="flex justify-end"><button :disabled="form.processing || !prescription" class="rounded-xl bg-teal-700 px-6 py-3 font-semibold text-white disabled:opacity-50">Confirmer la délivrance</button></div>
        </form>
    </AuthenticatedLayout>
</template>
