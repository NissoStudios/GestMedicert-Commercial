<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function user(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user && $user->active, 403);

        return $user;
    }

    protected function organizationId(Request $request): int
    {
        $organizationId = $this->user($request)->organization_id;
        abort_unless($organizationId, 403, 'Aucune organisation n’est associée à cet utilisateur.');

        return (int) $organizationId;
    }

    protected function ensureOrganization(Request $request, Model $model): void
    {
        abort_unless(
            (int) $model->getAttribute('organization_id') === $this->organizationId($request),
            404
        );
    }

    protected function ensureSiteAccess(Request $request, int $siteId, bool $write = false): void
    {
        $user = $this->user($request);

        if ($user->hasRole('system-admin')) {
            return;
        }

        $site = $user->sites()->whereKey($siteId)->first();
        $allowed = $site || (int) $user->default_site_id === $siteId;

        if ($write && $site) {
            $allowed = (bool) $site->pivot->can_write;
        }

        abort_unless($allowed, 403, 'Vous n’êtes pas habilité pour ce site.');
    }

    protected function ensurePatientSiteAccess(Request $request, int $patientId, bool $write = false): void
    {
        $siteId = Patient::query()
            ->where('organization_id', $this->organizationId($request))
            ->whereKey($patientId)
            ->value('primary_site_id');

        abort_unless($siteId, 404);
        $this->ensureSiteAccess($request, (int) $siteId, $write);
    }
}
