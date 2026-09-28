<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import type { PageProps, Paginated } from '@/types';

const props = defineProps<{ documents: Paginated<any>; filters: { status?: string; type?: string } }>();
const page = usePage<PageProps>();
const can = (permission: string) => page.props.auth.permissions.includes(permission);
const filters = reactive({ status: props.filters.status ?? '', type: props.filters.type ?? '' });
const filter = () => router.get(route('documents.index'), filters, { preserveState: true, replace: true });
const formatDate = (value?: string) => value ? new Intl.DateTimeFormat('fr-CM').format(new Date(value)) : '—';
</script>

<template>
    <Head title="Documents médicaux" />
    <AuthenticatedLayout>
        <PageHeader title="Documents et certificats" subtitle="Création, validation, émission, vérification et révocation.">
            <Link v-if="can('documents.create')" :href="route('documents.create')" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Nouveau document</Link>
        </PageHeader>
        <form class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[220px_260px_auto]" @submit.prevent="filter">
            <select v-model="filters.status" class="rounded-xl border-slate-300 text-sm"><option value="">Tous les statuts</option><option value="draft">Brouillon</option><option value="submitted">Soumis</option><option value="approved">Approuvé</option><option value="rejected">Rejeté</option><option value="revoked">Révoqué</option></select>
            <select v-model="filters.type" class="rounded-xl border-slate-300 text-sm"><option value="">Tous les types</option><option value="medical">Certificat médical</option><option value="prolongation">Prolongation</option><option value="return_to_work">Reprise de travail</option><option value="fitness">Aptitude</option><option value="birth_attestation">Attestation naissance</option><option value="death_attestation">Attestation décès</option></select>
            <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Filtrer</button>
        </form>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Référence</th><th class="px-5 py-3">Patient</th><th class="px-5 py-3">Document</th><th class="px-5 py-3">Créé le</th><th class="px-5 py-3">Statut</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="document in documents.data" :key="document.uuid">
                            <td class="px-5 py-4"><Link :href="route('documents.show', document.uuid)" class="font-semibold text-teal-700">{{ document.reference }}</Link><div class="text-xs text-slate-500">Version {{ document.version }}</div></td>
                            <td class="px-5 py-4">{{ document.patient.last_name }} {{ document.patient.first_name }}<div class="text-xs text-slate-500">{{ document.patient.patient_number }}</div></td>
                            <td class="px-5 py-4">{{ document.title }}<div class="text-xs text-slate-500">{{ document.type }}</div></td>
                            <td class="px-5 py-4">{{ formatDate(document.created_at) }}</td>
                            <td class="px-5 py-4"><StatusBadge :status="document.status" /></td>
                        </tr>
                        <tr v-if="!documents.data.length"><td colspan="5" class="px-5 py-12 text-center text-slate-500">Aucun document.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4"><Pagination :links="documents.links" /></div>
        </section>
    </AuthenticatedLayout>
</template>
