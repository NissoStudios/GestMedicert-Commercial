<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { PageProps, Paginated } from '@/types';

const props = defineProps<{ consultations: Paginated<any>; filters: { status?: string } }>();
const page = usePage<PageProps>();
const can = (permission: string) => page.props.auth.permissions.includes(permission);
const status = ref(props.filters.status ?? '');
const filter = () => router.get(route('consultations.index'), { status: status.value }, { preserveState: true, replace: true });
const formatDate = (value: string) => new Intl.DateTimeFormat('fr-CM', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
</script>

<template>
    <Head title="Consultations" />
    <AuthenticatedLayout>
        <PageHeader title="Consultations" subtitle="Dossier clinique longitudinal et suivi des actes.">
            <Link v-if="can('consultations.create')" :href="route('consultations.create')" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Nouvelle consultation</Link>
        </PageHeader>
        <form class="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="filter">
            <select v-model="status" class="rounded-xl border-slate-300 text-sm"><option value="">Tous les statuts</option><option value="draft">Brouillon</option><option value="finalized">Finalisée</option></select>
            <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Filtrer</button>
        </form>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Patient</th><th class="px-5 py-3">Motif</th><th class="px-5 py-3">Praticien</th><th class="px-5 py-3">Statut</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in consultations.data" :key="item.uuid" class="hover:bg-slate-50">
                            <td class="px-5 py-4">{{ formatDate(item.started_at) }}<div class="text-xs text-slate-500">{{ item.site.name }}</div></td>
                            <td class="px-5 py-4"><Link :href="route('consultations.show', item.uuid)" class="font-semibold text-teal-700">{{ item.patient.last_name }} {{ item.patient.first_name }}</Link><div class="text-xs text-slate-500">{{ item.patient.patient_number }}</div></td>
                            <td class="max-w-md truncate px-5 py-4">{{ item.chief_complaint }}</td>
                            <td class="px-5 py-4">{{ item.practitioner.name }}</td>
                            <td class="px-5 py-4"><StatusBadge :status="item.status" /></td>
                        </tr>
                        <tr v-if="!consultations.data.length"><td colspan="5" class="px-5 py-12 text-center text-slate-500">Aucune consultation.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4"><Pagination :links="consultations.links" /></div>
        </section>
    </AuthenticatedLayout>
</template>
