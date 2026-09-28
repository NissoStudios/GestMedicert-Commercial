<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{ patient: any | null; sites: any[]; units: any[]; employees: any[] }>();
const employment = props.patient?.employments?.find((item: any) => item.is_primary) ?? {};
const form = useForm({
    patient_type: props.patient?.patient_type ?? 'employee',
    last_name: props.patient?.last_name ?? '',
    first_name: props.patient?.first_name ?? '',
    date_of_birth: props.patient?.date_of_birth?.slice(0, 10) ?? '',
    sex: props.patient?.sex ?? 'unknown',
    primary_site_id: props.patient?.primary_site_id ?? props.sites[0]?.id ?? '',
    phone: props.patient?.phone ?? '',
    email: props.patient?.email ?? '',
    national_id: props.patient?.national_id ?? '',
    related_employee_patient_id: props.patient?.related_employee_patient_id ?? '',
    relationship_type: props.patient?.relationship_type ?? '',
    employee_number: employment.employee_number ?? '',
    job_title: employment.job_title ?? '',
    organizational_unit_id: employment.organizational_unit_id ?? '',
    duplicate_override_reason: '',
});

const submit = () => {
    if (props.patient) {
        form.put(route('patients.update', props.patient.uuid));
    } else {
        form.post(route('patients.store'));
    }
};
</script>

<template>
    <Head :title="patient ? 'Modifier le patient' : 'Nouveau patient'" />
    <AuthenticatedLayout>
        <PageHeader :title="patient ? 'Modifier le patient' : 'Nouveau patient'" subtitle="L’identité patient est distincte du profil professionnel.">
            <Link :href="patient ? route('patients.show', patient.uuid) : route('patients.index')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium">Annuler</Link>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Identité</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <label class="space-y-1 text-sm"><span>Profil *</span><select v-model="form.patient_type" class="w-full rounded-xl border-slate-300"><option value="employee">Employé</option><option value="contractor">Prestataire</option><option value="trainee">Stagiaire</option><option value="dependent">Ayant droit</option></select><InputError :message="form.errors.patient_type" /></label>
                    <label class="space-y-1 text-sm"><span>Nom *</span><input v-model="form.last_name" required class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.last_name" /></label>
                    <label class="space-y-1 text-sm"><span>Prénom *</span><input v-model="form.first_name" required class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.first_name" /></label>
                    <label class="space-y-1 text-sm"><span>Date de naissance *</span><input v-model="form.date_of_birth" required type="date" class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.date_of_birth" /></label>
                    <label class="space-y-1 text-sm"><span>Sexe *</span><select v-model="form.sex" class="w-full rounded-xl border-slate-300"><option value="female">Féminin</option><option value="male">Masculin</option><option value="other">Autre</option><option value="unknown">Non renseigné</option></select><InputError :message="form.errors.sex" /></label>
                    <label class="space-y-1 text-sm"><span>Site principal *</span><select v-model="form.primary_site_id" class="w-full rounded-xl border-slate-300"><option v-for="site in sites" :key="site.id" :value="site.id">{{ site.name }}</option></select><InputError :message="form.errors.primary_site_id" /></label>
                    <label class="space-y-1 text-sm"><span>Téléphone</span><input v-model="form.phone" class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.phone" /></label>
                    <label class="space-y-1 text-sm"><span>E-mail</span><input v-model="form.email" type="email" class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.email" /></label>
                    <label class="space-y-1 text-sm"><span>Pièce d’identité</span><input v-model="form.national_id" class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.national_id" /></label>
                </div>
            </section>

            <section v-if="form.patient_type !== 'dependent'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Profil professionnel</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-3">
                    <label class="space-y-1 text-sm"><span>Matricule *</span><input v-model="form.employee_number" class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.employee_number" /></label>
                    <label class="space-y-1 text-sm"><span>Poste</span><input v-model="form.job_title" class="w-full rounded-xl border-slate-300" /></label>
                    <label class="space-y-1 text-sm"><span>Unité</span><select v-model="form.organizational_unit_id" class="w-full rounded-xl border-slate-300"><option value="">Non renseignée</option><option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }} ({{ unit.type }})</option></select></label>
                </div>
            </section>

            <section v-else class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Lien avec l’employé</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <label class="space-y-1 text-sm"><span>Employé principal</span><select v-model="form.related_employee_patient_id" class="w-full rounded-xl border-slate-300"><option value="">Sélectionner</option><option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.last_name }} {{ employee.first_name }} · {{ employee.patient_number }}</option></select></label>
                    <label class="space-y-1 text-sm"><span>Lien familial</span><input v-model="form.relationship_type" placeholder="Conjoint, enfant…" class="w-full rounded-xl border-slate-300" /></label>
                </div>
            </section>

            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <label class="space-y-1 text-sm"><span class="font-medium">Justification de dérogation en cas d’homonyme exact</span><textarea v-model="form.duplicate_override_reason" rows="2" class="w-full rounded-xl border-amber-300 bg-white" placeholder="Réservé aux utilisateurs habilités"></textarea><InputError :message="form.errors.duplicate_override_reason" /></label>
            </section>

            <div class="flex justify-end">
                <button :disabled="form.processing" class="rounded-xl bg-teal-700 px-6 py-3 font-semibold text-white disabled:opacity-50">{{ patient ? 'Enregistrer' : 'Créer le patient' }}</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
