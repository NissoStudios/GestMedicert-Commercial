<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $event,
        ?User $user = null,
        ?Model $subject = null,
        ?int $patientId = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditEvent {
        $organizationId = $user?->organization_id
            ?? $subject?->getAttribute('organization_id');

        return DB::transaction(function () use (
            $event,
            $user,
            $subject,
            $patientId,
            $metadata,
            $request,
            $organizationId,
        ): AuditEvent {
            $previousHash = AuditEvent::query()
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->latest('id')
                ->value('hash');

            ksort($metadata);
            $occurredAt = now()->startOfSecond();
            $payload = implode('|', [
                $previousHash ?? '',
                $organizationId ?? '',
                $user?->id ?? '',
                $event,
                $subject ? $subject::class : '',
                $subject?->getKey() ?? '',
                $patientId ?? '',
                $occurredAt->format('Y-m-d H:i:s'),
                json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return AuditEvent::query()->create([
                'organization_id' => $organizationId,
                'user_id' => $user?->id,
                'patient_id' => $patientId,
                'event' => $event,
                'auditable_type' => $subject ? $subject::class : null,
                'auditable_id' => $subject?->getKey(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'metadata' => $metadata,
                'previous_hash' => $previousHash,
                'hash' => hash('sha256', $payload),
                'occurred_at' => $occurredAt,
            ]);
        }, 3);
    }

    public function verify(?int $organizationId): bool
    {
        $previousHash = null;

        foreach (AuditEvent::query()->where('organization_id', $organizationId)->orderBy('id')->cursor() as $event) {
            $metadata = $event->metadata ?? [];
            ksort($metadata);
            $payload = implode('|', [
                $previousHash ?? '',
                $event->organization_id ?? '',
                $event->user_id ?? '',
                $event->event,
                $event->auditable_type ?? '',
                $event->auditable_id ?? '',
                $event->patient_id ?? '',
                $event->occurred_at->format('Y-m-d H:i:s'),
                json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            if ($event->previous_hash !== $previousHash || ! hash_equals($event->hash, hash('sha256', $payload))) {
                return false;
            }

            $previousHash = $event->hash;
        }

        return true;
    }
}
