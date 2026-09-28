<?php

namespace App\Http\Controllers;

use App\Models\OrganizationalUnit;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdministrationController extends Controller
{
    public function index(Request $request): Response
    {
        $organizationId = $this->organizationId($request);

        return Inertia::render('Administration/Index', [
            'sites' => Site::query()
                ->where('organization_id', $organizationId)
                ->withCount('users')
                ->orderBy('name')
                ->get(),
            'units' => OrganizationalUnit::query()
                ->where('organization_id', $organizationId)
                ->with('parent:id,name')
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
            'users' => User::query()
                ->where('organization_id', $organizationId)
                ->with(['roles:id,name', 'sites:id,name'])
                ->orderBy('name')
                ->get(),
            'roles' => $this->assignableRoles($request),
        ]);
    }

    public function storeSite(Request $request, AuditService $audit): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('sites', 'code')->where('organization_id', $organizationId)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $site = Site::query()->create([...$data, 'organization_id' => $organizationId, 'active' => true]);
        $audit->record('administration.site-created', $request->user(), $site, metadata: $data, request: $request);

        return back()->with('success', 'Site créé.');
    }

    public function storeUnit(Request $request, AuditService $audit): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('organizational_units', 'code')->where('organization_id', $organizationId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['direction', 'service', 'team'])],
            'parent_id' => ['nullable', Rule::exists('organizational_units', 'id')->where('organization_id', $organizationId)],
        ]);
        $unit = OrganizationalUnit::query()->create([...$data, 'organization_id' => $organizationId, 'active' => true]);
        $audit->record('administration.unit-created', $request->user(), $unit, metadata: $data, request: $request);

        return back()->with('success', 'Unité organisationnelle créée.');
    }

    public function storeUser(Request $request, AuditService $audit): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $allowedRoles = $this->assignableRoles($request)->pluck('name')->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'role' => ['required', Rule::in($allowedRoles)],
            'site_ids' => ['required', 'array', 'min:1'],
            'site_ids.*' => ['integer', Rule::exists('sites', 'id')->where('organization_id', $organizationId)],
        ]);

        $user = User::query()->create([
            'organization_id' => $organizationId,
            'default_site_id' => $data['site_ids'][0],
            'name' => $data['name'],
            'email' => $data['email'],
            'email_verified_at' => now(),
            'password' => Hash::make($data['password']),
            'locale' => 'fr',
            'active' => true,
            'must_change_password' => true,
        ]);
        $user->syncRoles([$data['role']]);
        $user->sites()->sync(collect($data['site_ids'])->mapWithKeys(fn ($siteId) => [$siteId => ['can_write' => true]])->all());
        $audit->record('administration.user-created', $request->user(), $user, metadata: [
            'role' => $data['role'],
            'site_ids' => $data['site_ids'],
        ], request: $request);

        return back()->with('success', 'Utilisateur créé. Il devra changer son mot de passe à la première connexion.');
    }

    public function updateUser(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $this->ensureOrganization($request, $user);
        $organizationId = $this->organizationId($request);
        $allowedRoles = $this->assignableRoles($request)->pluck('name')->all();
        $data = $request->validate([
            'role' => ['required', Rule::in($allowedRoles)],
            'site_ids' => ['required', 'array', 'min:1'],
            'site_ids.*' => ['integer', Rule::exists('sites', 'id')->where('organization_id', $organizationId)],
            'active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:12'],
        ]);

        if ((int) $request->user()->id === (int) $user->id && ! $data['active']) {
            throw ValidationException::withMessages(['active' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }

        $changes = [
            'default_site_id' => $data['site_ids'][0],
            'active' => $data['active'],
        ];
        if (! empty($data['password'])) {
            $changes['password'] = Hash::make($data['password']);
            $changes['must_change_password'] = true;
        }

        $user->update($changes);
        $user->syncRoles([$data['role']]);
        $user->sites()->sync(collect($data['site_ids'])->mapWithKeys(fn ($siteId) => [$siteId => ['can_write' => true]])->all());
        $audit->record('administration.user-updated', $request->user(), $user, metadata: [
            'role' => $data['role'],
            'site_ids' => $data['site_ids'],
            'active' => $data['active'],
            'password_reset' => ! empty($data['password']),
        ], request: $request);

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    /** @return Collection<int, Role> */
    private function assignableRoles(Request $request)
    {
        return Role::query()
            ->when(! $request->user()->hasRole('system-admin'), fn ($query) => $query->where('name', '!=', 'system-admin'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
