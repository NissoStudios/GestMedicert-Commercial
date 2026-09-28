<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const props = defineProps<{ document: any }>();
const page = usePage<PageProps>();
const can = (permission: string) => page.props.auth.permissions.includes(permission);
const rejectForm = useForm({ comment: '' });
const revokeForm = useForm({ comment: '' });
const editForm = useForm({
    title: props.document.title,
    expires_on: props.document.expires_on?.slice(0, 10) ?? '',
    content: { reason: props.document.data?.reason ?? '', details: props.document.data?.details ?? '', restrictions: props.document.data?.restrictions ?? '' },
});
const submit = () => router.post(route('documents.submit', props.document.uuid));
const approve = () => window.confirm('Approuver et émettre le PDF définitif ?') && router.post(route('documents.approve', props.document.uuid));
const reopen = () => router.post(route('documents.reopen', props.document.uuid));
const reject = () => rejectForm.post(route('documents.reject', props.document.uuid), { onSuccess: () => rejectForm.reset() });
const revoke = () => revokeForm.post(route('documents.revoke', props.document.uuid), { onSuccess: () => revokeForm.reset() });
const save = () => editForm.put(route('documents.update', props.document.uuid));
const formatDate = (value?: string) => value ? new Intl.DateTimeFormat('fr-CM', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
</script>

<template>
    <Head :title="document.reference" />
    <AuthenticatedLayout>
        <PageHeader :title="document.title" :subtitle="`${document.reference} · version ${document.version}`">
            <StatusBadge :status="document.status" />
            <button v-if="document.status === 'draft' && can('documents.submit')" @click="submit" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Soumettre</button>
            <button v-if="document.status === 'submitted' && can('documents.approve')" @click="approve" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Approuver et émettre</button>
            <Link v-if="document.status === 'approved' && can('documents.download')" :href="route('documents.download', document.uuid)" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Télécharger le PDF</Link>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                <form v-if="['draft','rejected'].includes(document.status) && can('documents.update')" class="space-y-4" @submit.prevent="save">
                    <label class="block space-y-1 text-sm"><span>Titre</span><input v-model="editForm.title" class="w-full rounded-xl border-slate-300" /></label>
                    <label class="block space-y-1 text-sm"><span>Motif / objet</span><textarea v-model="editForm.content.reason" rows="3" class="w-full rounded-xl border-slate-300"></textarea><InputError :message="editForm.errors['content.reason']" /></label>
                    <label class="block space-y-1 text-sm"><span>Détails</span><textarea v-model="editForm.content.details" rows="7" class="w-full rounded-xl border-slate-300"></textarea></label>
                    <label class="block space-y-1 text-sm"><span>Restrictions partageables</span><textarea v-model="editForm.content.restrictions" rows="3" class="w-full rounded-xl border-slate-300"></textarea></label>
                    <label class="block max-w-xs space-y-1 text-sm"><span>Expiration</span><input v-model="editForm.expires_on" type="date" class="w-full rounded-xl border-slate-300" /></label>
                    <button class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Enregistrer</button>
                </form>
                <div v-else class="space-y-5">
                    <div><div class="text-xs font-semibold uppercase text-slate-500">Objet</div><p class="mt-2 whitespace-pre-wrap">{{ document.data?.reason }}</p></div>
                    <div><div class="text-xs font-semibold uppercase text-slate-500">Détails</div><p class="mt-2 whitespace-pre-wrap">{{ document.data?.details || '—' }}</p></div>
                    <div><div class="text-xs font-semibold uppercase text-slate-500">Restrictions partageables</div><p class="mt-2 whitespace-pre-wrap">{{ document.data?.restrictions || 'Aucune' }}</p></div>
                </div>
            </section>
            <aside class="space-y-5">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-semibold">Informations</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-slate-500">Patient</dt><dd><Link :href="route('patients.show', document.patient.uuid)" class="font-semibold text-teal-700">{{ document.patient.full_name }}</Link></dd></div>
                        <div><dt class="text-slate-500">Créateur</dt><dd class="font-medium">{{ document.creator.name }}</dd></div>
                        <div><dt class="text-slate-500">Approbateur</dt><dd class="font-medium">{{ document.approver?.name ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">Émis le</dt><dd class="font-medium">{{ formatDate(document.approved_at) }}</dd></div>
                        <div><dt class="text-slate-500">Expiration</dt><dd class="font-medium">{{ document.expires_on?.slice(0, 10) ?? '—' }}</dd></div>
                        <div v-if="document.sha256"><dt class="text-slate-500">Empreinte SHA-256</dt><dd class="break-all font-mono text-xs">{{ document.sha256 }}</dd></div>
                    </dl>
                </section>
                <section v-if="document.status === 'submitted' && can('documents.reject')" class="rounded-2xl border border-red-100 bg-red-50 p-5">
                    <h2 class="font-semibold text-red-900">Rejeter</h2>
                    <form class="mt-3" @submit.prevent="reject"><textarea v-model="rejectForm.comment" required minlength="5" rows="3" class="w-full rounded-xl border-red-200" placeholder="Motif obligatoire"></textarea><button class="mt-2 text-sm font-semibold text-red-700">Confirmer le rejet</button></form>
                </section>
                <button v-if="document.status === 'rejected' && can('documents.update')" @click="reopen" class="w-full rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">Ouvrir une nouvelle révision</button>
                <section v-if="document.status === 'approved' && can('documents.revoke')" class="rounded-2xl border border-red-100 bg-red-50 p-5">
                    <h2 class="font-semibold text-red-900">Révoquer</h2>
                    <form class="mt-3" @submit.prevent="revoke"><textarea v-model="revokeForm.comment" required minlength="5" rows="3" class="w-full rounded-xl border-red-200" placeholder="Motif obligatoire"></textarea><button class="mt-2 text-sm font-semibold text-red-700">Révoquer le document</button></form>
                </section>
            </aside>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-semibold">Historique du workflow</h2>
            <div class="mt-4 space-y-3">
                <div v-for="transition in document.transitions" :key="transition.id" class="flex gap-4 border-l-2 border-teal-200 pl-4 text-sm">
                    <div><div class="font-medium">{{ transition.actor.name }} · {{ transition.from_status || 'création' }} → {{ transition.to_status }}</div><div class="text-slate-500">{{ formatDate(transition.created_at) }}<span v-if="transition.comment"> · {{ transition.comment }}</span></div></div>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
