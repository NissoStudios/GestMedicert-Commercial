<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

defineProps<{ consultation: any }>();
const page = usePage<PageProps>();
const can = (permission: string) => page.props.auth.permissions.includes(permission);
const addendum = useForm({ content: '' });
const finalize = (uuid: string) => {
    if (window.confirm('Finaliser la consultation ? Elle deviendra immuable.')) router.post(route('consultations.finalize', uuid));
};
const submitAddendum = (uuid: string) => addendum.post(route('consultations.addenda.store', uuid), { onSuccess: () => addendum.reset() });
const recordResult = (consultationUuid: string, examUuid: string) => {
    const result = window.prompt('Saisissez le résumé du résultat :');
    if (result && result.trim().length > 0) {
        router.post(route('consultations.exams.result', [consultationUuid, examUuid]), { result_summary: result }, { preserveScroll: true });
    }
};
const formatDate = (value?: string) => value ? new Intl.DateTimeFormat('fr-CM', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
</script>

<template>
    <Head title="Consultation" />
    <AuthenticatedLayout>
        <PageHeader :title="`Consultation de ${consultation.patient.full_name}`" :subtitle="`${formatDate(consultation.started_at)} · ${consultation.site.name}`">
            <StatusBadge :status="consultation.status" />
            <Link v-if="consultation.status === 'draft' && can('consultations.update')" :href="route('consultations.edit', consultation.uuid)" class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Modifier</Link>
            <button v-if="consultation.status === 'draft' && can('consultations.finalize')" @click="finalize(consultation.uuid)" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Finaliser</button>
            <Link v-if="consultation.status === 'finalized' && can('documents.create')" :href="route('documents.create', { patient: consultation.patient_id, consultation: consultation.id })" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Créer un certificat</Link>
        </PageHeader>

        <div v-if="consultation.patient.allergies?.length" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-900">
            <div class="font-semibold">Allergies actives</div>
            <div class="mt-2 text-sm">{{ consultation.patient.allergies.map((a: any) => `${a.substance} (${a.severity})`).join(' · ') }}</div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-2">
                <div><h2 class="text-sm font-semibold uppercase text-slate-500">Motif</h2><p class="mt-2 whitespace-pre-wrap">{{ consultation.chief_complaint }}</p></div>
                <div><h2 class="text-sm font-semibold uppercase text-slate-500">Antécédents pertinents</h2><p class="mt-2 whitespace-pre-wrap">{{ consultation.relevant_history || 'Non renseignés' }}</p></div>
                <div><h2 class="text-sm font-semibold uppercase text-slate-500">Examen clinique</h2><p class="mt-2 whitespace-pre-wrap">{{ consultation.clinical_examination || 'Non renseigné' }}</p></div>
                <div><h2 class="text-sm font-semibold uppercase text-slate-500">Conduite à tenir</h2><p class="mt-2 whitespace-pre-wrap">{{ consultation.care_plan || 'Non renseignée' }}</p></div>
            </section>
            <aside class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-semibold">Constantes</h2>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div v-for="(value, key) in consultation.vitals" :key="key"><dt class="capitalize text-slate-500">{{ key }}</dt><dd class="font-semibold">{{ value }}</dd></div>
                    </dl>
                </section>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-semibold">Diagnostics</h2>
                    <div class="mt-3 space-y-2"><div v-for="item in consultation.diagnoses" :key="item.id" class="rounded-xl bg-slate-50 p-3 text-sm"><strong>{{ item.code }}</strong> · {{ item.label }}</div></div>
                </section>
            </aside>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Ordonnance</h2>
                <div v-if="consultation.prescription" class="mt-4 space-y-3">
                    <div v-for="item in consultation.prescription.items" :key="item.id" class="rounded-xl bg-slate-50 p-4 text-sm">
                        <div class="font-semibold">{{ item.product.trade_name }} {{ item.product.strength }}</div>
                        <div class="mt-1 text-slate-600">{{ item.dosage }} · {{ item.frequency }} · {{ item.duration }} · Qté {{ item.quantity_prescribed }}</div>
                    </div>
                    <StatusBadge :status="consultation.prescription.status" />
                </div>
                <p v-else class="mt-3 text-sm text-slate-500">Aucune prescription.</p>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Examens demandés</h2>
                <div class="mt-4 space-y-3"><div v-for="exam in consultation.exam_requests" :key="exam.uuid" class="rounded-xl bg-slate-50 p-4 text-sm"><div class="font-semibold">{{ exam.name }}</div><div class="text-slate-500">{{ exam.category }} · {{ exam.external_provider || 'Prestataire non défini' }}</div><p v-if="exam.result_summary" class="mt-2 whitespace-pre-wrap">{{ exam.result_summary }}</p><div class="mt-2 flex items-center gap-3"><StatusBadge :status="exam.status" /><button v-if="exam.status !== 'resulted' && can('consultations.addendum')" @click="recordResult(consultation.uuid, exam.uuid)" class="font-semibold text-teal-700">Saisir le résultat</button></div></div></div>
                <p v-if="!consultation.exam_requests.length" class="mt-3 text-sm text-slate-500">Aucun examen.</p>
            </section>
        </div>

        <section v-if="consultation.status === 'finalized'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-semibold">Addendums</h2>
            <div class="mt-4 space-y-3"><div v-for="item in consultation.addenda" :key="item.id" class="rounded-xl border border-slate-200 p-4"><div class="text-xs text-slate-500">{{ item.author.name }} · {{ formatDate(item.created_at) }}</div><p class="mt-2 whitespace-pre-wrap text-sm">{{ item.content }}</p></div></div>
            <form v-if="can('consultations.addendum')" class="mt-5" @submit.prevent="submitAddendum(consultation.uuid)">
                <textarea v-model="addendum.content" required minlength="10" rows="3" class="w-full rounded-xl border-slate-300" placeholder="Ajouter une correction ou précision sans modifier la note finalisée"></textarea>
                <button class="mt-2 rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Ajouter l’addendum</button>
            </form>
        </section>
    </AuthenticatedLayout>
</template>
