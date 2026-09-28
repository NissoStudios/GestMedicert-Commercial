<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { PageProps, Paginated } from '@/types';

const props = defineProps<{ products: Paginated<any>; filters: { search?: string } }>();
const page = usePage<PageProps>();
const can = (permission: string) => page.props.auth.permissions.includes(permission);
const search = ref(props.filters.search ?? '');
const submit = () => router.get(route('products.index'), { search: search.value }, { preserveState: true, replace: true });
</script>

<template>
    <Head title="Catalogue produits" />
    <AuthenticatedLayout>
        <PageHeader title="Catalogue pharmacie" subtitle="Médicaments et consommables médicaux.">
            <Link v-if="can('products.manage')" :href="route('products.create')" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Nouveau produit</Link>
        </PageHeader>
        <form class="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="submit"><input v-model="search" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Code, DCI ou nom commercial" /><button class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Rechercher</button></form>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Code</th><th class="px-5 py-3">Produit</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Stock total</th><th class="px-5 py-3">Seuil</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="product in products.data" :key="product.uuid"><td class="px-5 py-4 font-mono text-xs">{{ product.code }}</td><td class="px-5 py-4"><div class="font-semibold">{{ product.trade_name }} {{ product.strength }}</div><div class="text-xs text-slate-500">{{ product.inn || 'Consommable' }} · {{ product.dosage_form }}</div></td><td class="px-5 py-4 capitalize">{{ product.type }}</td><td class="px-5 py-4 font-semibold">{{ product.total_stock ?? 0 }} {{ product.unit }}</td><td class="px-5 py-4">{{ product.reorder_level }}</td><td class="px-5 py-4"><Link v-if="can('products.manage')" :href="route('products.edit', product.uuid)" class="text-sm font-medium text-teal-700">Modifier</Link></td></tr>
                    <tr v-if="!products.data.length"><td colspan="6" class="px-5 py-12 text-center text-slate-500">Aucun produit.</td></tr>
                </tbody>
            </table></div>
            <div class="border-t border-slate-100 p-4"><Pagination :links="products.links" /></div>
        </section>
    </AuthenticatedLayout>
</template>
