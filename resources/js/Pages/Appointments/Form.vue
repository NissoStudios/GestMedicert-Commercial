<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{ appointment: any | null; patients: any[]; sites: any[]; practitioners: any[] }>();
const form = useForm({
    patient_id: props.appointment?.patient_id ?? '',
    site_id: props.appointment?.site_id ?? props.sites[0]?.id ?? '',
    practitioner_id: props.appointment?.practitioner_id ?? '',
    reason: props.appointment?.reason ?? '',
    scheduled_start: props.appointment?.scheduled_start?.slice(0, 16) ?? '',
    scheduled_end: props.appointment?.scheduled_end?.slice(0, 16) ?? '',
    source: props.appointment?.source ?? 'appointment',
    notes: props.appointment?.notes ?? '',
});
const submit = () => props.appointment
    ? form.put(route('appointments.update', props.appointment.uuid))
    : form.post(route('appointments.store'));
</script>

<template>
    <Head title="Nouveau rendez-vous" />
    <AuthenticatedLayout>
        <PageHeader title="Nouveau rendez-vous" subtitle="Créez un créneau planifié ou une arrivée spontanée.">
            <Link :href="route('appointments.index')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Annuler</Link>
        </PageHeader>
        <form class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-5 md:grid-cols-2">
                <label class="space-y-1 text-sm"><span>Patient *</span><select v-model="form.patient_id" required class="w-full rounded-xl border-slate-300"><option value="">Sélectionner</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.last_name }} {{ patient.first_name }} · {{ patient.patient_number }}</option></select><InputError :message="form.errors.patient_id" /></label>
                <label class="space-y-1 text-sm"><span>Site *</span><select v-model="form.site_id" required class="w-full rounded-xl border-slate-300"><option v-for="site in sites" :key="site.id" :value="site.id">{{ site.name }}</option></select><InputError :message="form.errors.site_id" /></label>
                <label class="space-y-1 text-sm"><span>Praticien</span><select v-model="form.practitioner_id" class="w-full rounded-xl border-slate-300"><option value="">Non affecté</option><option v-for="user in practitioners" :key="user.id" :value="user.id">{{ user.name }}</option></select></label>
                <label class="space-y-1 text-sm"><span>Type d’arrivée *</span><select v-model="form.source" class="w-full rounded-xl border-slate-300"><option value="appointment">Rendez-vous</option><option value="walk_in">Sans rendez-vous</option></select></label>
                <label class="space-y-1 text-sm md:col-span-2"><span>Motif *</span><input v-model="form.reason" required class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.reason" /></label>
                <label class="space-y-1 text-sm"><span>Début *</span><input v-model="form.scheduled_start" required type="datetime-local" class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.scheduled_start" /></label>
                <label class="space-y-1 text-sm"><span>Fin</span><input v-model="form.scheduled_end" type="datetime-local" class="w-full rounded-xl border-slate-300" /><InputError :message="form.errors.scheduled_end" /></label>
                <label class="space-y-1 text-sm md:col-span-2"><span>Notes administratives</span><textarea v-model="form.notes" rows="3" class="w-full rounded-xl border-slate-300"></textarea></label>
            </div>
            <div class="mt-6 flex justify-end"><button :disabled="form.processing" class="rounded-xl bg-teal-700 px-6 py-3 font-semibold text-white disabled:opacity-50">Enregistrer</button></div>
        </form>
    </AuthenticatedLayout>
</template>
