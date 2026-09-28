<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    consultation: any | null;
    patients: any[];
    sites: any[];
    appointments: any[];
    products: any[];
    selectedPatientId?: number | null;
    selectedAppointmentId?: number | null;
}>();

const form = useForm({
    patient_id: props.consultation?.patient_id ?? props.selectedPatientId ?? '',
    appointment_id: props.consultation?.appointment_id ?? props.selectedAppointmentId ?? '',
    site_id: props.consultation?.site_id ?? props.sites[0]?.id ?? '',
    chief_complaint: props.consultation?.chief_complaint ?? '',
    relevant_history: props.consultation?.relevant_history ?? '',
    vitals: {
        temperature: props.consultation?.vitals?.temperature ?? '',
        systolic: props.consultation?.vitals?.systolic ?? '',
        diastolic: props.consultation?.vitals?.diastolic ?? '',
        weight: props.consultation?.vitals?.weight ?? '',
        height: props.consultation?.vitals?.height ?? '',
        pulse: props.consultation?.vitals?.pulse ?? '',
    },
    clinical_examination: props.consultation?.clinical_examination ?? '',
    care_plan: props.consultation?.care_plan ?? '',
    work_related: props.consultation?.work_related ?? false,
    follow_up_at: props.consultation?.follow_up_at?.slice(0, 16) ?? '',
    diagnoses: props.consultation?.diagnoses?.map((item: any) => ({ code: item.code, label: item.label, is_primary: item.is_primary })) ?? [{ code: '', label: '', is_primary: true }],
    prescription_items: props.consultation?.prescription?.items?.map((item: any) => ({ product_id: item.product_id, dosage: item.dosage, frequency: item.frequency, duration: item.duration, quantity_prescribed: item.quantity_prescribed, instructions: item.instructions ?? '' })) ?? [],
    exam_requests: props.consultation?.exam_requests?.map((item: any) => ({ category: item.category, name: item.name, code: item.code ?? '', external_provider: item.external_provider ?? '' })) ?? [],
});

const addDiagnosis = () => form.diagnoses.push({ code: '', label: '', is_primary: false });
const addPrescription = () => form.prescription_items.push({ product_id: '', dosage: '', frequency: '', duration: '', quantity_prescribed: 1, instructions: '' });
const addExam = () => form.exam_requests.push({ category: 'laboratory', name: '', code: '', external_provider: '' });
const submit = () => props.consultation
    ? form.put(route('consultations.update', props.consultation.uuid))
    : form.post(route('consultations.store'));
</script>

<template>
    <Head :title="consultation ? 'Modifier la consultation' : 'Nouvelle consultation'" />
    <AuthenticatedLayout>
        <PageHeader :title="consultation ? 'Modifier la consultation' : 'Nouvelle consultation'" subtitle="Le dossier reste modifiable jusqu’à sa finalisation.">
            <Link :href="consultation ? route('consultations.show', consultation.uuid) : route('consultations.index')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Annuler</Link>
        </PageHeader>
        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Contexte de la consultation</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <label class="space-y-1 text-sm"><span>Patient *</span><select v-model="form.patient_id" required class="w-full rounded-xl border-slate-300"><option value="">Sélectionner</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.last_name }} {{ patient.first_name }} · {{ patient.patient_number }}</option></select><InputError :message="form.errors.patient_id" /></label>
                    <label class="space-y-1 text-sm"><span>Site *</span><select v-model="form.site_id" required class="w-full rounded-xl border-slate-300"><option v-for="site in sites" :key="site.id" :value="site.id">{{ site.name }}</option></select><InputError :message="form.errors.site_id" /></label>
                    <label class="space-y-1 text-sm"><span>Rendez-vous associé</span><select v-model="form.appointment_id" class="w-full rounded-xl border-slate-300"><option value="">Aucun</option><option v-for="item in appointments" :key="item.id" :value="item.id">{{ item.patient.last_name }} {{ item.patient.first_name }} · {{ new Date(item.scheduled_start).toLocaleString('fr-CM') }}</option></select></label>
                    <label class="flex items-center gap-2 pt-6 text-sm"><input v-model="form.work_related" type="checkbox" class="rounded border-slate-300 text-teal-700" /> Cas lié au travail</label>
                    <label class="space-y-1 text-sm md:col-span-2"><span>Motif *</span><textarea v-model="form.chief_complaint" required rows="3" class="w-full rounded-xl border-slate-300"></textarea><InputError :message="form.errors.chief_complaint" /></label>
                    <label class="space-y-1 text-sm md:col-span-2"><span>Antécédents pertinents</span><textarea v-model="form.relevant_history" rows="3" class="w-full rounded-xl border-slate-300"></textarea></label>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Constantes</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <label class="space-y-1 text-sm"><span>Température °C</span><input v-model="form.vitals.temperature" type="number" step="0.1" class="w-full rounded-xl border-slate-300" /></label>
                    <label class="space-y-1 text-sm"><span>Systolique</span><input v-model="form.vitals.systolic" type="number" class="w-full rounded-xl border-slate-300" /></label>
                    <label class="space-y-1 text-sm"><span>Diastolique</span><input v-model="form.vitals.diastolic" type="number" class="w-full rounded-xl border-slate-300" /></label>
                    <label class="space-y-1 text-sm"><span>Poids kg</span><input v-model="form.vitals.weight" type="number" step="0.1" class="w-full rounded-xl border-slate-300" /></label>
                    <label class="space-y-1 text-sm"><span>Taille cm</span><input v-model="form.vitals.height" type="number" step="0.1" class="w-full rounded-xl border-slate-300" /></label>
                    <label class="space-y-1 text-sm"><span>Pouls</span><input v-model="form.vitals.pulse" type="number" class="w-full rounded-xl border-slate-300" /></label>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Examen et conduite à tenir</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <label class="space-y-1 text-sm"><span>Examen clinique</span><textarea v-model="form.clinical_examination" rows="6" class="w-full rounded-xl border-slate-300"></textarea></label>
                    <label class="space-y-1 text-sm"><span>Conduite à tenir</span><textarea v-model="form.care_plan" rows="6" class="w-full rounded-xl border-slate-300"></textarea></label>
                    <label class="space-y-1 text-sm"><span>Prochain suivi</span><input v-model="form.follow_up_at" type="datetime-local" class="w-full rounded-xl border-slate-300" /></label>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between"><h2 class="font-semibold">Diagnostics CIM-10</h2><button type="button" @click="addDiagnosis" class="text-sm font-semibold text-teal-700">+ Ajouter</button></div>
                <div class="mt-4 space-y-3">
                    <div v-for="(diagnosis, index) in form.diagnoses" :key="index" class="grid gap-3 rounded-xl bg-slate-50 p-4 md:grid-cols-[160px_1fr_auto_auto]">
                        <input v-model="diagnosis.code" placeholder="Code CIM-10" class="rounded-xl border-slate-300 text-sm" />
                        <input v-model="diagnosis.label" placeholder="Libellé du diagnostic" class="rounded-xl border-slate-300 text-sm" />
                        <label class="flex items-center gap-2 text-sm"><input v-model="diagnosis.is_primary" type="checkbox" /> Principal</label>
                        <button v-if="form.diagnoses.length > 1" type="button" @click="form.diagnoses.splice(index, 1)" class="text-sm text-red-600">Retirer</button>
                    </div>
                </div>
                <InputError :message="form.errors.diagnoses" />
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between"><h2 class="font-semibold">Ordonnance</h2><button type="button" @click="addPrescription" class="text-sm font-semibold text-teal-700">+ Médicament</button></div>
                <div class="mt-4 space-y-3">
                    <div v-for="(item, index) in form.prescription_items" :key="index" class="grid gap-3 rounded-xl bg-slate-50 p-4 lg:grid-cols-6">
                        <select v-model="item.product_id" class="rounded-xl border-slate-300 text-sm lg:col-span-2"><option value="">Produit</option><option v-for="product in products" :key="product.id" :value="product.id">{{ product.trade_name }} {{ product.strength }}</option></select>
                        <input v-model="item.dosage" placeholder="Dose" class="rounded-xl border-slate-300 text-sm" />
                        <input v-model="item.frequency" placeholder="Fréquence" class="rounded-xl border-slate-300 text-sm" />
                        <input v-model="item.duration" placeholder="Durée" class="rounded-xl border-slate-300 text-sm" />
                        <div class="flex gap-2"><input v-model="item.quantity_prescribed" type="number" min="0.001" step="0.001" placeholder="Qté" class="w-full rounded-xl border-slate-300 text-sm" /><button type="button" @click="form.prescription_items.splice(index, 1)" class="text-red-600">×</button></div>
                    </div>
                </div>
                <InputError :message="form.errors.prescription_items" />
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between"><h2 class="font-semibold">Examens externes</h2><button type="button" @click="addExam" class="text-sm font-semibold text-teal-700">+ Examen</button></div>
                <div class="mt-4 space-y-3">
                    <div v-for="(exam, index) in form.exam_requests" :key="index" class="grid gap-3 rounded-xl bg-slate-50 p-4 md:grid-cols-[160px_1fr_160px_1fr_auto]">
                        <select v-model="exam.category" class="rounded-xl border-slate-300 text-sm"><option value="laboratory">Laboratoire</option><option value="imaging">Imagerie</option><option value="other">Autre</option></select>
                        <input v-model="exam.name" placeholder="Examen demandé" class="rounded-xl border-slate-300 text-sm" />
                        <input v-model="exam.code" placeholder="Code LOINC" class="rounded-xl border-slate-300 text-sm" />
                        <input v-model="exam.external_provider" placeholder="Prestataire externe" class="rounded-xl border-slate-300 text-sm" />
                        <button type="button" @click="form.exam_requests.splice(index, 1)" class="text-red-600">×</button>
                    </div>
                </div>
            </section>

            <div class="flex justify-end"><button :disabled="form.processing" class="rounded-xl bg-teal-700 px-6 py-3 font-semibold text-white disabled:opacity-50">Enregistrer le brouillon</button></div>
        </form>
    </AuthenticatedLayout>
</template>
