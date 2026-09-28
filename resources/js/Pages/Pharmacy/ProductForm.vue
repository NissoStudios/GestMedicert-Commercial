<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{ product: any | null }>();
const form = useForm({
    code: props.product?.code ?? '',
    type: props.product?.type ?? 'medication',
    inn: props.product?.inn ?? '',
    trade_name: props.product?.trade_name ?? '',
    strength: props.product?.strength ?? '',
    dosage_form: props.product?.dosage_form ?? '',
    unit: props.product?.unit ?? 'unité',
    atc_code: props.product?.atc_code ?? '',
    gtin: props.product?.gtin ?? '',
    reorder_level: props.product?.reorder_level ?? 0,
    active: props.product?.active ?? true,
});
const submit = () => props.product ? form.put(route('products.update', props.product.uuid)) : form.post(route('products.store'));
</script>

<template>
    <Head :title="product ? 'Modifier le produit' : 'Nouveau produit'" />
    <AuthenticatedLayout>
        <PageHeader :title="product ? 'Modifier le produit' : 'Nouveau produit'" subtitle="Catalogue local DCI, ATC et traçabilité produit."><Link :href="route('products.index')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Annuler</Link></PageHeader>
        <form class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <label class="space-y-1 text-sm"><span>Code *</span><input v-model="form.code" required class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.code" /></label>
                <label class="space-y-1 text-sm"><span>Type *</span><select v-model="form.type" class="w-full rounded-xl border-slate-300"><option value="medication">Médicament</option><option value="consumable">Consommable</option></select></label>
                <label class="space-y-1 text-sm"><span>Nom commercial *</span><input v-model="form.trade_name" required class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.trade_name" /></label>
                <label class="space-y-1 text-sm"><span>DCI</span><input v-model="form.inn" class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm"><span>Dosage</span><input v-model="form.strength" class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm"><span>Forme</span><input v-model="form.dosage_form" class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm"><span>Unité *</span><input v-model="form.unit" required class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm"><span>Code ATC</span><input v-model="form.atc_code" class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm"><span>GTIN</span><input v-model="form.gtin" class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm"><span>Seuil d’alerte *</span><input v-model="form.reorder_level" type="number" min="0" step="0.001" class="w-full rounded-xl border-slate-300" /></label>
                <label class="flex items-center gap-2 pt-7 text-sm"><input v-model="form.active" type="checkbox" class="rounded text-teal-700" /> Produit actif</label>
            </div>
            <div class="mt-6 flex justify-end"><button class="rounded-xl bg-teal-700 px-6 py-3 font-semibold text-white">{{ product ? 'Enregistrer' : 'Créer' }}</button></div>
        </form>
    </AuthenticatedLayout>
</template>
