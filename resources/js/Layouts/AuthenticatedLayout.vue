<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const mobileOpen = ref(false);
const can = (permission: string) => page.props.auth.permissions.includes(permission);

const links = computed(() => [
    { label: 'Tableau de bord', route: 'dashboard', pattern: 'dashboard', show: can('dashboard.view') },
    { label: 'Patients', route: 'patients.index', pattern: 'patients.*', show: can('patients.view') },
    { label: 'Rendez-vous', route: 'appointments.index', pattern: 'appointments.*', show: can('appointments.view') },
    { label: 'Consultations', route: 'consultations.index', pattern: 'consultations.*', show: can('consultations.view') },
    { label: 'Documents', route: 'documents.index', pattern: 'documents.*', show: can('documents.view') },
    { label: 'Produits', route: 'products.index', pattern: 'products.*', show: can('pharmacy.view') },
    { label: 'Stocks', route: 'stock.index', pattern: 'stock.*', show: can('pharmacy.view') },
    { label: 'Délivrances', route: 'dispensations.index', pattern: 'dispensations.*', show: can('pharmacy.dispense') },
    { label: 'Rapports', route: 'reports.index', pattern: 'reports.*', show: can('reports.view') },
    { label: 'Administration', route: 'administration.index', pattern: 'administration.*', show: can('administration.manage') },
].filter((link) => link.show));
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 border-r border-slate-800 bg-slate-950 lg:block">
            <div class="flex h-20 items-center gap-3 px-6 text-white">
                <ApplicationLogo class="h-10 w-10 text-teal-400" />
                <div>
                    <div class="font-bold">GestMedicert</div>
                    <div class="text-xs text-slate-400">ERP Santé</div>
                </div>
            </div>
            <nav class="space-y-1 px-3">
                <Link
                    v-for="link in links"
                    :key="link.route"
                    :href="route(link.route)"
                    class="block rounded-xl px-4 py-3 text-sm font-medium transition"
                    :class="route().current(link.pattern) ? 'bg-teal-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                >
                    {{ link.label }}
                </Link>
            </nav>
            <div class="absolute inset-x-0 bottom-0 border-t border-slate-800 p-4">
                <div class="text-sm font-semibold text-white">{{ page.props.auth.user.name }}</div>
                <div class="truncate text-xs text-slate-400">{{ page.props.auth.user.email }}</div>
                <div class="mt-3 flex gap-3 text-xs">
                    <Link :href="route('profile.edit')" class="text-teal-300 hover:text-white">Profil</Link>
                    <Link :href="route('logout')" method="post" as="button" class="text-slate-400 hover:text-white">Déconnexion</Link>
                </div>
            </div>
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6">
                <button class="rounded-lg p-2 text-slate-600 lg:hidden" @click="mobileOpen = !mobileOpen" aria-label="Menu">
                    <span class="block h-0.5 w-6 bg-current"></span>
                    <span class="mt-1.5 block h-0.5 w-6 bg-current"></span>
                    <span class="mt-1.5 block h-0.5 w-6 bg-current"></span>
                </button>
                <div class="text-sm text-slate-500">
                    {{ page.props.auth.user.default_site?.name ?? 'Tous les sites autorisés' }}
                </div>
                <div class="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700">
                    {{ page.props.auth.roles.join(', ') }}
                </div>
            </header>

            <div v-if="mobileOpen" class="border-b border-slate-200 bg-white p-3 lg:hidden">
                <Link v-for="link in links" :key="link.route" :href="route(link.route)" class="block rounded-lg px-3 py-2 text-sm text-slate-700" @click="mobileOpen = false">
                    {{ link.label }}
                </Link>
            </div>

            <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <FlashMessages />
                <slot />
            </main>
        </div>
    </div>
</template>
