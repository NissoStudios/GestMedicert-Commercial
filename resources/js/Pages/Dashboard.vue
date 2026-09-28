<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

interface Props {
    metrics: Record<string, number>;
    todayQueue: any[];
    recentDocuments: any[];
    auditIntegrity: boolean;
}

defineProps<Props>();

const cards = [
    ['activePatients', 'Patients actifs', 'text-teal-700'],
    ['appointmentsToday', 'Rendez-vous aujourd’hui', 'text-blue-700'],
    ['openConsultations', 'Consultations en cours', 'text-violet-700'],
    ['documentsToReview', 'Documents à valider', 'text-amber-700'],
    ['expiringBatches', 'Lots expirant sous 90 jours', 'text-red-700'],
];

const formatDate = (value: string) => new Intl.DateTimeFormat('fr-CM', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
</script>

<template>
    <Head title="Tableau de bord" />
    <AuthenticatedLayout>
        <PageHeader title="Tableau de bord" subtitle="Vue opérationnelle de la santé, des documents et de la pharmacie." />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div v-for="[key, label, color] in cards" :key="key" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">{{ label }}</div>
                <div class="mt-2 text-3xl font-bold" :class="color">{{ metrics[key] ?? 0 }}</div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h2 class="font-semibold">File d’attente du jour</h2>
                    <Link :href="route('appointments.index')" class="text-sm font-medium text-teal-700">Voir tout</Link>
                </div>
                <div v-if="todayQueue.length" class="divide-y divide-slate-100">
                    <div v-for="item in todayQueue" :key="item.uuid" class="flex items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <div class="font-medium">{{ item.patient.last_name }} {{ item.patient.first_name }}</div>
                            <div class="text-sm text-slate-500">{{ item.patient.patient_number }} · {{ item.site.name }} · {{ formatDate(item.scheduled_start) }}</div>
                        </div>
                        <StatusBadge :status="item.status" />
                    </div>
                </div>
                <div v-else class="p-8 text-center text-sm text-slate-500">Aucun rendez-vous aujourd’hui.</div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Intégrité de l’audit</h2>
                <div class="mt-5 rounded-xl p-4" :class="auditIntegrity ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'">
                    <div class="font-semibold">{{ auditIntegrity ? 'Chaîne valide' : 'Anomalie détectée' }}</div>
                    <p class="mt-1 text-sm">{{ auditIntegrity ? 'Les empreintes du journal sont cohérentes.' : 'Une vérification administrative est requise.' }}</p>
                </div>
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Documents récents</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Référence</th><th class="px-5 py-3">Patient</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Statut</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="document in recentDocuments" :key="document.uuid">
                            <td class="px-5 py-3"><Link :href="route('documents.show', document.uuid)" class="font-medium text-teal-700">{{ document.reference }}</Link></td>
                            <td class="px-5 py-3">{{ document.patient.last_name }} {{ document.patient.first_name }}</td>
                            <td class="px-5 py-3">{{ document.title }}</td>
                            <td class="px-5 py-3"><StatusBadge :status="document.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
