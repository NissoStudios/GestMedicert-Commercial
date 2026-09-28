<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

defineProps<{ patient: any }>();
const page = usePage<PageProps>();
const can = (permission: string) => page.props.auth.permissions.includes(permission);
const date = (value?: string) => value ? new Intl.DateTimeFormat('fr-CM', { dateStyle: 'medium' }).format(new Date(value)) : '—';
const allergyForm = useForm({ substance: '', reaction: '', severity: 'unknown', absolute_contraindication: false });
const addAllergy = (uuid: string) => allergyForm.post(route('patients.allergies.store', uuid), { preserveScroll: true, onSuccess: () => allergyForm.reset() });
const deactivateAllergy = (patientUuid: string, allergyId: number) => window.confirm('Désactiver cette alerte ?') && router.delete(route('patients.allergies.destroy', [patientUuid, allergyId]), { preserveScroll: true });
</script>

<template>
    <Head :title="patient.full_name" />
    <AuthenticatedLayout>
        <PageHeader :title="patient.full_name" :subtitle="`${patient.patient_number} · ${patient.primary_site.name}`">
            <Link v-if="can('patients.update')" :href="route('patients.edit', patient.uuid)" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium">Modifier</Link>
            <Link v-if="can('consultations.create')" :href="route('consultations.create', { patient: patient.id })" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Nouvelle consultation</Link>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Identité</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-slate-500">Profil</dt><dd class="font-medium capitalize">{{ patient.patient_type }}</dd></div>
                    <div><dt class="text-slate-500">Naissance</dt><dd class="font-medium">{{ date(patient.date_of_birth) }}</dd></div>
                    <div><dt class="text-slate-500">Sexe</dt><dd class="font-medium capitalize">{{ patient.sex }}</dd></div>
                    <div><dt class="text-slate-500">Téléphone</dt><dd class="font-medium">{{ patient.phone || '—' }}</dd></div>
                    <div><dt class="text-slate-500">E-mail</dt><dd class="font-medium">{{ patient.email || '—' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                <h2 class="font-semibold">Affectations professionnelles</h2>
                <div v-if="patient.employments.length" class="mt-4 divide-y divide-slate-100">
                    <div v-for="employment in patient.employments" :key="employment.id" class="py-3 text-sm">
                        <div class="font-medium">{{ employment.employee_number }} · {{ employment.job_title || 'Poste non renseigné' }}</div>
                        <div class="text-slate-500">{{ employment.site.name }}<span v-if="employment.organizational_unit"> · {{ employment.organizational_unit.name }}</span></div>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">Aucune affectation professionnelle.</p>
            </section>
        </div>

        <section v-if="can('clinical-data.view')" class="rounded-2xl border border-red-100 bg-white p-6 shadow-sm">
            <h2 class="font-semibold">Allergies et alertes cliniques</h2>
            <div v-if="patient.allergies.length" class="mt-4 flex flex-wrap gap-2">
                <span v-for="allergy in patient.allergies" :key="allergy.id" class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-2 text-sm text-red-800">{{ allergy.substance }} · {{ allergy.severity }}<button v-if="can('patients.update')" @click="deactivateAllergy(patient.uuid, allergy.id)" class="font-bold" aria-label="Désactiver">×</button></span>
            </div>
            <p v-else class="mt-3 text-sm text-slate-500">Aucune allergie active enregistrée.</p>
            <form v-if="can('patients.update')" class="mt-5 grid gap-3 md:grid-cols-4" @submit.prevent="addAllergy(patient.uuid)">
                <input v-model="allergyForm.substance" required placeholder="Substance ou produit" class="rounded-xl border-red-200 text-sm" />
                <input v-model="allergyForm.reaction" placeholder="Réaction observée" class="rounded-xl border-red-200 text-sm" />
                <select v-model="allergyForm.severity" class="rounded-xl border-red-200 text-sm"><option value="unknown">Sévérité inconnue</option><option value="mild">Légère</option><option value="moderate">Modérée</option><option value="severe">Sévère</option></select>
                <button class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white">Ajouter l’alerte</button>
                <label class="flex items-center gap-2 text-sm md:col-span-4"><input v-model="allergyForm.absolute_contraindication" type="checkbox" /> Contre-indication absolue</label>
                <InputError class="md:col-span-4" :message="allergyForm.errors.substance" />
            </form>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Rendez-vous récents</h2></div>
                <div class="divide-y divide-slate-100">
                    <div v-for="item in patient.appointments" :key="item.id" class="flex items-center justify-between px-5 py-4 text-sm">
                        <div><div class="font-medium">{{ item.reason }}</div><div class="text-slate-500">{{ date(item.scheduled_start) }} · {{ item.site.name }}</div></div>
                        <StatusBadge :status="item.status" />
                    </div>
                    <div v-if="!patient.appointments.length" class="p-6 text-sm text-slate-500">Aucun rendez-vous.</div>
                </div>
            </section>

            <section v-if="can('clinical-data.view')" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Consultations récentes</h2></div>
                <div class="divide-y divide-slate-100">
                    <Link v-for="item in patient.consultations" :key="item.uuid" :href="route('consultations.show', item.uuid)" class="flex items-center justify-between px-5 py-4 text-sm hover:bg-slate-50">
                        <div><div class="font-medium">{{ item.chief_complaint }}</div><div class="text-slate-500">{{ date(item.started_at) }} · {{ item.practitioner.name }}</div></div>
                        <StatusBadge :status="item.status" />
                    </Link>
                    <div v-if="!patient.consultations.length" class="p-6 text-sm text-slate-500">Aucune consultation.</div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
