<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{ patients: any[]; consultations: any[]; selectedPatientId?: number | null; selectedConsultationId?: number | null }>();
const form = useForm({
    patient_id: props.selectedPatientId ?? '',
    consultation_id: props.selectedConsultationId ?? '',
    type: 'medical',
    title: 'Certificat médical',
    expires_on: '',
    content: { reason: '', details: '', restrictions: '' },
});
const titles: Record<string, string> = {
    medical: 'Certificat médical',
    prolongation: 'Prolongation de repos médical',
    return_to_work: 'Certificat de reprise de travail',
    fitness: 'Avis d’aptitude',
    birth_attestation: 'Attestation interne de naissance',
    death_attestation: 'Attestation interne de décès',
};
const updateTitle = () => form.title = titles[form.type] ?? form.title;
</script>

<template>
    <Head title="Nouveau document" />
    <AuthenticatedLayout>
        <PageHeader title="Nouveau document" subtitle="Le document sera créé en brouillon avant soumission.">
            <Link :href="route('documents.index')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Annuler</Link>
        </PageHeader>
        <form class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="form.post(route('documents.store'))">
            <div class="grid gap-5 md:grid-cols-2">
                <label class="space-y-1 text-sm"><span>Patient *</span><select v-model="form.patient_id" required class="w-full rounded-xl border-slate-300"><option value="">Sélectionner</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.last_name }} {{ patient.first_name }} · {{ patient.patient_number }}</option></select><InputError :message="form.errors.patient_id" /></label>
                <label class="space-y-1 text-sm"><span>Consultation liée</span><select v-model="form.consultation_id" class="w-full rounded-xl border-slate-300"><option value="">Aucune</option><option v-for="item in consultations" :key="item.id" :value="item.id">{{ item.patient.last_name }} {{ item.patient.first_name }} · {{ new Date(item.started_at).toLocaleDateString('fr-CM') }}</option></select></label>
                <label class="space-y-1 text-sm"><span>Type *</span><select v-model="form.type" @change="updateTitle" class="w-full rounded-xl border-slate-300"><option value="medical">Certificat médical</option><option value="prolongation">Prolongation</option><option value="return_to_work">Reprise de travail</option><option value="fitness">Aptitude</option><option value="birth_attestation">Attestation naissance</option><option value="death_attestation">Attestation décès</option></select></label>
                <label class="space-y-1 text-sm"><span>Date d’expiration</span><input v-model="form.expires_on" type="date" class="w-full rounded-xl border-slate-300" /></label>
                <label class="space-y-1 text-sm md:col-span-2"><span>Titre *</span><input v-model="form.title" required class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.title" /></label>
                <label class="space-y-1 text-sm md:col-span-2"><span>Motif / objet *</span><textarea v-model="form.content.reason" required rows="3" class="w-full rounded-xl border-slate-300"></textarea><InputError :message="form.errors['content.reason']" /></label>
                <label class="space-y-1 text-sm md:col-span-2"><span>Détails destinés au document</span><textarea v-model="form.content.details" rows="6" class="w-full rounded-xl border-slate-300"></textarea></label>
                <label class="space-y-1 text-sm md:col-span-2"><span>Restrictions partageables</span><textarea v-model="form.content.restrictions" rows="3" class="w-full rounded-xl border-slate-300"></textarea></label>
            </div>
            <div class="flex justify-end"><button :disabled="form.processing" class="rounded-xl bg-teal-700 px-6 py-3 font-semibold text-white disabled:opacity-50">Créer le brouillon</button></div>
        </form>
    </AuthenticatedLayout>
</template>
