<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import type { PageProps, Paginated } from '@/types';

const props = defineProps<{ patients: Paginated<any>; filters: { search?: string; type?: string } }>();
const page = usePage<PageProps>();
const filters = reactive({ search: props.filters.search ?? '', type: props.filters.type ?? '' });
const can = (permission: string) => page.props.auth.permissions.includes(permission);

const search = () => router.get(route('patients.index'), filters, { preserveState: true, replace: true });
const formatDate = (value?: string | null) => value ? new Intl.DateTimeFormat('fr-CM').format(new Date(value)) : 'À compléter';
</script>

<template>
    <Head title="Patients" />
    <AuthenticatedLayout>
        <PageHeader title="Patients" subtitle="Employés, prestataires, stagiaires et ayants droit.">
            <Link v-if="can('patients.create')" :href="route('patients.create')" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Nouveau patient</Link>
        </PageHeader>

        <form class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_220px_auto]" @submit.prevent="search">
            <input v-model="filters.search" class="rounded-xl border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600" placeholder="Nom, numéro patient ou matricule" />
            <select v-model="filters.type" class="rounded-xl border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600">
                <option value="">Tous les profils</option>
                <option value="employee">Employé</option>
                <option value="contractor">Prestataire</option>
                <option value="trainee">Stagiaire</option>
                <option value="dependent">Ayant droit</option>
            </select>
            <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Rechercher</button>
        </form>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-3">Patient</th><th class="px-5 py-3">Profil</th><th class="px-5 py-3">Site</th><th class="px-5 py-3">Naissance</th><th class="px-5 py-3">Statut</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="patient in patients.data" :key="patient.uuid" class="hover:bg-slate-50">
                            <td class="px-5 py-4">
                                <Link :href="route('patients.show', patient.uuid)" class="font-semibold text-teal-700">{{ patient.last_name }} {{ patient.first_name }}</Link>
                                <div class="text-xs text-slate-500">{{ patient.patient_number }}<span v-if="patient.employments?.[0]"> · {{ patient.employments[0].employee_number }}</span></div>
                            </td>
                            <td class="px-5 py-4 capitalize">{{ patient.patient_type }}</td>
                            <td class="px-5 py-4">{{ patient.primary_site?.name }}</td>
                            <td class="px-5 py-4">{{ formatDate(patient.date_of_birth) }}</td>
                            <td class="px-5 py-4"><span :class="patient.active ? 'text-emerald-700' : 'text-slate-400'">{{ patient.active ? 'Actif' : 'Archivé' }}</span></td>
                        </tr>
                        <tr v-if="!patients.data.length"><td colspan="5" class="px-5 py-12 text-center text-slate-500">Aucun patient trouvé.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t border-slate-100 px-5 py-4">
                <span class="text-sm text-slate-500">{{ patients.total }} patient(s)</span>
                <Pagination :links="patients.links" />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
