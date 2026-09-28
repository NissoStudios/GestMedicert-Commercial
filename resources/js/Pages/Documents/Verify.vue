<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{ verification: any }>();
</script>

<template>
    <Head title="Vérification du document" />
    <main class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
        <section class="w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-8 shadow-xl">
            <div class="flex items-center gap-3"><ApplicationLogo class="h-12 w-12 text-teal-700" /><div><h1 class="text-xl font-bold">Vérification GestMedicert</h1><p class="text-sm text-slate-500">{{ verification.organization }}</p></div></div>
            <div class="mt-8 flex items-center justify-between rounded-2xl bg-slate-50 p-5"><div><div class="text-xs uppercase text-slate-500">Référence</div><div class="mt-1 font-mono font-semibold">{{ verification.reference }}</div></div><StatusBadge :status="verification.status" /></div>
            <dl class="mt-6 space-y-4 text-sm">
                <div><dt class="text-slate-500">Type</dt><dd class="font-semibold">{{ verification.title }}</dd></div>
                <div><dt class="text-slate-500">Date d’émission</dt><dd class="font-semibold">{{ verification.approved_at ? new Date(verification.approved_at).toLocaleString('fr-CM') : 'Non émis' }}</dd></div>
                <div><dt class="text-slate-500">Expiration</dt><dd class="font-semibold">{{ verification.expires_on?.slice(0, 10) ?? 'Aucune' }}</dd></div>
                <div v-if="verification.sha256"><dt class="text-slate-500">Empreinte</dt><dd class="break-all font-mono text-xs">{{ verification.sha256 }}</dd></div>
            </dl>
            <p class="mt-8 border-t border-slate-100 pt-5 text-xs leading-5 text-slate-500">Cette page confirme uniquement l’existence et le statut du document dans le registre de l’installation. Aucune identité ni donnée médicale n’est divulguée.</p>
        </section>
    </main>
</template>
