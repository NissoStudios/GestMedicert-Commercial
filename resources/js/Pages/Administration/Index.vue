<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{ sites: any[]; units: any[]; users: any[]; roles: any[] }>();
const siteForm = useForm({ code: '', name: '', address: '' });
const unitForm = useForm({ code: '', name: '', type: 'direction', parent_id: '' });
const userForm = useForm({ name: '', email: '', password: '', role: props.roles[0]?.name ?? '', site_ids: [] as number[] });

const createSite = () => siteForm.post(route('administration.sites.store'), { preserveScroll: true, onSuccess: () => siteForm.reset() });
const createUnit = () => unitForm.post(route('administration.units.store'), { preserveScroll: true, onSuccess: () => unitForm.reset() });
const createUser = () => userForm.post(route('administration.users.store'), { preserveScroll: true, onSuccess: () => userForm.reset() });
const updateUser = (user: any) => {
    const role = window.prompt('Rôle', user.roles[0]?.name ?? '');
    if (!role) return;
    const password = window.prompt('Nouveau mot de passe temporaire (laisser vide pour conserver)') ?? '';
    router.put(route('administration.users.update', user.id), {
        role,
        site_ids: user.sites.map((site: any) => site.id),
        active: user.active,
        password,
    }, { preserveScroll: true });
};
const toggleSite = (siteId: number) => {
    const index = userForm.site_ids.indexOf(siteId);
    if (index >= 0) userForm.site_ids.splice(index, 1);
    else userForm.site_ids.push(siteId);
};
</script>

<template>
    <Head title="Administration" />
    <AuthenticatedLayout>
        <PageHeader title="Administration" subtitle="Sites, structure organisationnelle, utilisateurs et périmètres." />

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Sites</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-3" @submit.prevent="createSite">
                    <input v-model="siteForm.code" required class="rounded-xl border-slate-300 text-sm" placeholder="Code" />
                    <input v-model="siteForm.name" required class="rounded-xl border-slate-300 text-sm" placeholder="Nom du site" />
                    <button class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Ajouter</button>
                    <InputError class="md:col-span-3" :message="siteForm.errors.code || siteForm.errors.name" />
                </form>
                <div class="mt-5 divide-y divide-slate-100"><div v-for="site in sites" :key="site.id" class="flex justify-between py-3 text-sm"><span><strong>{{ site.code }}</strong> · {{ site.name }}</span><span class="text-slate-500">{{ site.users_count }} utilisateur(s)</span></div></div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Directions, services et équipes</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="createUnit">
                    <input v-model="unitForm.code" required class="rounded-xl border-slate-300 text-sm" placeholder="Code" />
                    <input v-model="unitForm.name" required class="rounded-xl border-slate-300 text-sm" placeholder="Nom" />
                    <select v-model="unitForm.type" class="rounded-xl border-slate-300 text-sm"><option value="direction">Direction</option><option value="service">Service</option><option value="team">Équipe</option></select>
                    <select v-model="unitForm.parent_id" class="rounded-xl border-slate-300 text-sm"><option value="">Aucun parent</option><option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option></select>
                    <button class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white md:col-span-2">Ajouter l’unité</button>
                </form>
                <div class="mt-5 max-h-72 divide-y divide-slate-100 overflow-auto"><div v-for="unit in units" :key="unit.id" class="py-3 text-sm"><span class="font-medium">{{ unit.name }}</span><span class="ml-2 text-slate-500">{{ unit.type }}<template v-if="unit.parent"> · {{ unit.parent.name }}</template></span></div></div>
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-semibold">Créer un utilisateur</h2>
            <form class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="createUser">
                <input v-model="userForm.name" required class="rounded-xl border-slate-300 text-sm" placeholder="Nom complet" />
                <input v-model="userForm.email" required type="email" class="rounded-xl border-slate-300 text-sm" placeholder="E-mail" />
                <input v-model="userForm.password" required minlength="12" type="password" class="rounded-xl border-slate-300 text-sm" placeholder="Mot de passe temporaire" />
                <select v-model="userForm.role" required class="rounded-xl border-slate-300 text-sm"><option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option></select>
                <div class="md:col-span-2 xl:col-span-4">
                    <div class="mb-2 text-sm font-medium">Sites autorisés *</div>
                    <div class="flex flex-wrap gap-2"><label v-for="site in sites" :key="site.id" class="flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-sm"><input type="checkbox" :checked="userForm.site_ids.includes(site.id)" @change="toggleSite(site.id)" /> {{ site.name }}</label></div>
                    <InputError class="mt-2" :message="userForm.errors.site_ids" />
                </div>
                <button class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white md:col-span-2 xl:col-span-4">Créer le compte</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Utilisateurs</h2></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Utilisateur</th><th class="px-5 py-3">Rôle</th><th class="px-5 py-3">Sites</th><th class="px-5 py-3">Statut</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="user in users" :key="user.id"><td class="px-5 py-4"><div class="font-semibold">{{ user.name }}</div><div class="text-xs text-slate-500">{{ user.email }}<template v-if="user.legacy_username"> · ancien : {{ user.legacy_username }}</template></div></td><td class="px-5 py-4">{{ user.roles.map((role: any) => role.name).join(', ') }}</td><td class="px-5 py-4">{{ user.sites.map((site: any) => site.name).join(', ') }}</td><td class="px-5 py-4" :class="user.active ? 'text-emerald-700' : 'text-red-700'">{{ user.active ? 'Actif' : 'Inactif' }}</td><td class="px-5 py-4"><button @click="updateUser(user)" class="font-medium text-teal-700">Gérer</button></td></tr></tbody></table></div>
        </section>
    </AuthenticatedLayout>
</template>
