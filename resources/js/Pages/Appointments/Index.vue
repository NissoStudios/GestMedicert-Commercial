<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import type { PageProps, Paginated } from '@/types';

const props = defineProps<{ appointments: Paginated<any>; filters: { date?: string; status?: string } }>();
const page = usePage<PageProps>();
const can = (permission: string) => page.props.auth.permissions.includes(permission);
const filters = reactive({ date: props.filters.date ?? '', status: props.filters.status ?? '' });
const filter = () => router.get(route('appointments.index'), filters, { preserveState: true, replace: true });
const updateStatus = (item: any, status: string) => router.put(route('appointments.update', item.uuid), { status, notes: item.notes }, { preserveScroll: true });
const dateTime = (value: string) => new Intl.DateTimeFormat('fr-CM', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
</script>

<template>
    <Head title="Rendez-vous" />
    <AuthenticatedLayout>
        <PageHeader title="Rendez-vous et file d’attente" subtitle="Planification, accueil et progression vers la consultation.">
            <Link v-if="can('appointments.manage')" :href="route('appointments.create')" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Nouveau rendez-vous</Link>
        </PageHeader>

        <form class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[220px_220px_auto]" @submit.prevent="filter">
            <input v-model="filters.date" type="date" class="rounded-xl border-slate-300 text-sm" />
            <select v-model="filters.status" class="rounded-xl border-slate-300 text-sm">
                <option value="">Tous les statuts</option><option value="scheduled">Planifié</option><option value="confirmed">Confirmé</option><option value="waiting">En attente</option><option value="called">Appelé</option><option value="completed">Terminé</option><option value="cancelled">Annulé</option><option value="no_show">Absent</option>
            </select>
            <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium">Filtrer</button>
        </form>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Horaire</th><th class="px-5 py-3">Patient</th><th class="px-5 py-3">Motif</th><th class="px-5 py-3">Praticien</th><th class="px-5 py-3">Statut</th><th class="px-5 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in appointments.data" :key="item.uuid">
                            <td class="whitespace-nowrap px-5 py-4">{{ dateTime(item.scheduled_start) }}<div class="text-xs text-slate-500">{{ item.site.name }}</div></td>
                            <td class="px-5 py-4"><Link :href="route('patients.show', item.patient.uuid)" class="font-semibold text-teal-700">{{ item.patient.last_name }} {{ item.patient.first_name }}</Link><div class="text-xs text-slate-500">{{ item.patient.patient_number }}</div></td>
                            <td class="px-5 py-4">{{ item.reason }}</td>
                            <td class="px-5 py-4">{{ item.practitioner?.name ?? 'Non affecté' }}</td>
                            <td class="px-5 py-4"><StatusBadge :status="item.status" /></td>
                            <td class="px-5 py-4">
                                <div v-if="can('appointments.manage')" class="flex flex-wrap gap-2">
                                    <button v-if="['scheduled','confirmed'].includes(item.status)" @click="updateStatus(item, 'waiting')" class="text-xs font-medium text-amber-700">Arrivée</button>
                                    <button v-if="item.status === 'waiting'" @click="updateStatus(item, 'called')" class="text-xs font-medium text-violet-700">Appeler</button>
                                    <Link v-if="['waiting','called'].includes(item.status) && can('consultations.create')" :href="route('consultations.create', { appointment: item.id })" class="text-xs font-medium text-teal-700">Consulter</Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!appointments.data.length"><td colspan="6" class="px-5 py-12 text-center text-slate-500">Aucun rendez-vous.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4"><Pagination :links="appointments.links" /></div>
        </section>
    </AuthenticatedLayout>
</template>
