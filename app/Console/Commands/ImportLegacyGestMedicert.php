<?php

namespace App\Console\Commands;

use App\Models\DocumentTransition;
use App\Models\Employment;
use App\Models\MedicalDocument;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Patient;
use App\Models\Site;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportLegacyGestMedicert extends Command
{
    protected $signature = 'gestmedicert:import-legacy
        {--organization=DEMO : Code de l’organisation cible}
        {--dry-run : Contrôler la source sans écrire}
        {--skip-files : Ne pas copier les signatures historiques}';

    protected $description = 'Importe de façon idempotente les données du GestMedicert historique';

    /** @var array<string, int> */
    private array $counts = [];

    /** @var list<string> */
    private array $warnings = [];

    public function handle(): int
    {
        try {
            $this->validateSource();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $sourceCounts = collect(['users', 'employes', 'documents', 'logs'])
            ->mapWithKeys(fn (string $table) => [$table => DB::connection('legacy_sqlsrv')->table($table)->count()])
            ->all();

        $this->table(['Table source', 'Lignes'], collect($sourceCounts)->map(fn ($count, $table) => [$table, $count])->values());
        $this->warnings[] = 'La base historique ne contient ni date de naissance ni sexe pour les employés : ces champs resteront à compléter.';

        if ($this->option('dry-run')) {
            foreach ($this->warnings as $warning) {
                $this->components->warn($warning);
            }
            $this->components->info('Contrôle terminé. Aucune donnée n’a été écrite.');

            return self::SUCCESS;
        }

        $organization = Organization::query()->where('code', $this->option('organization'))->first();
        if (! $organization) {
            $this->components->error("Organisation cible introuvable : {$this->option('organization')}.");

            return self::FAILURE;
        }

        $runUuid = (string) Str::uuid();
        $runId = DB::table('legacy_import_runs')->insertGetId([
            'uuid' => $runUuid,
            'status' => 'running',
            'dry_run' => false,
            'started_at' => now(),
        ]);

        try {
            DB::transaction(function () use ($organization): void {
                $this->importSites($organization);
                $this->importUnits($organization);
                $this->importUsers($organization);
                $this->importPatients($organization);
                $this->importDocuments($organization);
                $this->archiveLogs();
            }, 3);

            if (! $this->option('skip-files')) {
                $this->importSignatureFiles();
            }

            DB::table('legacy_import_runs')->where('id', $runId)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'counts' => json_encode($this->counts, JSON_UNESCAPED_UNICODE),
                'warnings' => json_encode($this->warnings, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $exception) {
            DB::table('legacy_import_runs')->where('id', $runId)->update([
                'status' => 'failed',
                'completed_at' => now(),
                'counts' => json_encode($this->counts, JSON_UNESCAPED_UNICODE),
                'warnings' => json_encode($this->warnings, JSON_UNESCAPED_UNICODE),
                'error_message' => Str::limit($exception->getMessage(), 4000),
            ]);
            report($exception);
            $this->components->error("Import interrompu : {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->table(['Entité', 'Importées ou mises à jour'], collect($this->counts)->map(fn ($count, $entity) => [$entity, $count])->values());
        foreach ($this->warnings as $warning) {
            $this->components->warn($warning);
        }
        $this->components->info("Import terminé. Rapport : {$runUuid}");

        return self::SUCCESS;
    }

    private function validateSource(): void
    {
        DB::connection('legacy_sqlsrv')->getPdo();

        foreach (['users', 'employes', 'documents', 'logs'] as $table) {
            if (! Schema::connection('legacy_sqlsrv')->hasTable($table)) {
                throw new RuntimeException("La table historique {$table} est absente.");
            }
        }
    }

    private function importSites(Organization $organization): void
    {
        $names = DB::connection('legacy_sqlsrv')->table('employes')->whereNotNull('site')->distinct()->pluck('site')
            ->merge(DB::connection('legacy_sqlsrv')->table('users')->whereNotNull('entite')->distinct()->pluck('entite'))
            ->filter()
            ->unique();

        foreach ($names as $name) {
            $site = Site::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'code' => $this->code((string) $name)],
                ['name' => (string) $name, 'active' => true]
            );
            $this->map('site', (string) $name, $site->id, ['name' => $name]);
            $this->increment('sites');
        }
    }

    private function importUnits(Organization $organization): void
    {
        $directions = DB::connection('legacy_sqlsrv')->table('employes')->whereNotNull('direction')->distinct()->pluck('direction')
            ->merge(DB::connection('legacy_sqlsrv')->table('users')->whereNotNull('direction')->distinct()->pluck('direction'))
            ->filter()
            ->unique();

        foreach ($directions as $direction) {
            $unit = OrganizationalUnit::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'code' => $this->code((string) $direction)],
                ['name' => (string) $direction, 'type' => 'direction', 'active' => true]
            );
            $this->map('unit', (string) $direction, $unit->id, ['name' => $direction]);
            $this->increment('units');
        }
    }

    private function importUsers(Organization $organization): void
    {
        DB::connection('legacy_sqlsrv')->table('users')->orderBy('id')->chunkById(100, function ($rows) use ($organization): void {
            foreach ($rows as $row) {
                $source = $this->normalizeSource((array) $row);
                if (! (bool) ($source['actif'] ?? false)) {
                    continue;
                }

                $siteId = $this->mappedId('site', (string) $source['entite']);
                $email = filter_var($source['username'], FILTER_VALIDATE_EMAIL)
                    ? Str::lower($source['username'])
                    : Str::slug((string) $source['username']).'.'.$source['id'].'@legacy.local';

                $user = User::query()->firstOrNew([
                    'organization_id' => $organization->id,
                    'legacy_username' => (string) $source['username'],
                ]);
                $isNew = ! $user->exists;
                $user->fill([
                    'default_site_id' => $siteId,
                    'name' => (string) $source['nom_complet'],
                    'email' => $email,
                    'active' => true,
                    'must_change_password' => true,
                    'locale' => 'fr',
                ]);
                if ($isNew) {
                    $user->password = Hash::make(Str::random(48));
                }
                $user->save();

                $legacyHash = (string) ($source['password_hash'] ?? '');
                if ($isNew && preg_match('/^\$(2[aby]|argon2)/', $legacyHash)) {
                    DB::table('users')->where('id', $user->id)->update(['password' => $legacyHash]);
                } elseif ($isNew) {
                    $this->warnings[] = "Le mot de passe de {$source['username']} n’est pas réutilisable ; une réinitialisation administrative sera nécessaire.";
                }

                $user->syncRoles([$this->legacyRole($source)]);
                if ($siteId) {
                    $user->sites()->syncWithoutDetaching([
                        $siteId => ['can_write' => true],
                    ]);
                }

                $this->map('user', (string) $source['id'], $user->id, $source);
                $this->increment('users');
            }
        });
    }

    private function importPatients(Organization $organization): void
    {
        $fallbackUserId = User::query()->where('organization_id', $organization->id)->oldest('id')->value('id');
        if (! $fallbackUserId) {
            throw new RuntimeException('Aucun utilisateur cible actif ne permet de rattacher les patients importés.');
        }

        DB::connection('legacy_sqlsrv')->table('employes')->orderBy('id')->chunkById(100, function ($rows) use ($organization, $fallbackUserId): void {
            foreach ($rows as $row) {
                $source = $this->normalizeSource((array) $row);
                $siteId = $this->mappedId('site', (string) $source['site']);
                $unitId = $this->mappedId('unit', (string) $source['direction']);

                if (! $siteId) {
                    $this->warnings[] = "Employé {$source['matricule']} ignoré : site introuvable.";

                    continue;
                }

                $patient = Patient::query()->updateOrCreate(
                    ['organization_id' => $organization->id, 'patient_number' => 'PAT-LEG-'.$source['matricule']],
                    [
                        'primary_site_id' => $siteId,
                        'patient_type' => 'employee',
                        'last_name' => (string) $source['nom'],
                        'first_name' => (string) $source['prenom'],
                        'normalized_last_name' => Str::lower(Str::ascii(trim((string) $source['nom']))),
                        'normalized_first_name' => Str::lower(Str::ascii(trim((string) $source['prenom']))),
                        'date_of_birth' => null,
                        'sex' => 'unknown',
                        'active' => (bool) ($source['actif'] ?? true),
                        'created_by' => $fallbackUserId,
                    ]
                );

                Employment::query()->updateOrCreate(
                    ['organization_id' => $organization->id, 'employee_number' => (string) $source['matricule']],
                    [
                        'patient_id' => $patient->id,
                        'employment_type' => 'employee',
                        'job_title' => $source['poste'] ?? null,
                        'site_id' => $siteId,
                        'organizational_unit_id' => $unitId,
                        'employment_status' => (bool) ($source['actif'] ?? true) ? 'active' : 'inactive',
                        'is_primary' => true,
                    ]
                );

                $this->map('patient', (string) $source['matricule'], $patient->id, $source);
                $this->increment('patients');
            }
        }, 'id');
    }

    private function importDocuments(Organization $organization): void
    {
        $fallbackUserId = User::query()->where('organization_id', $organization->id)->oldest('id')->value('id');

        DB::connection('legacy_sqlsrv')->table('documents')->orderBy('id')->chunkById(100, function ($rows) use ($organization, $fallbackUserId): void {
            foreach ($rows as $row) {
                $source = $this->normalizeSource((array) $row);
                $patientId = $this->mappedId('patient', (string) $source['matricule_employe']);
                if (! $patientId) {
                    $this->warnings[] = "Document {$source['reference']} ignoré : patient {$source['matricule_employe']} introuvable.";

                    continue;
                }

                $creatorId = $this->mappedId('user', (string) $source['createur_id']) ?? $fallbackUserId;
                $approverId = isset($source['valide_par'])
                    ? $this->mappedId('user', (string) $source['valide_par'])
                    : null;
                $status = $this->legacyDocumentStatus((string) ($source['statut'] ?? 'brouillon'));
                $data = [
                    'reason' => $source['raison'] ?? '',
                    'details' => $source['details'] ?? null,
                    'legacy' => collect($source)->except([
                        'id', 'reference', 'type', 'matricule_employe', 'statut', 'createur_id',
                        'valide_par', 'date_creation', 'date_modification', 'date_validation',
                    ])->all(),
                    'legacy_hash_sha256' => $source['hash_sha256'] ?? null,
                ];

                $document = MedicalDocument::query()->firstOrNew([
                    'organization_id' => $organization->id,
                    'reference' => (string) $source['reference'],
                ]);
                $document->fill([
                    'patient_id' => $patientId,
                    'type' => $this->legacyDocumentType((string) $source['type']),
                    'title' => $this->legacyDocumentTitle((string) $source['type']),
                    'version' => 1,
                    'status' => $status,
                    'data' => $data,
                    'confidential' => true,
                    'created_by' => $creatorId,
                    'submitted_at' => in_array($status, ['submitted', 'approved', 'rejected'], true) ? ($source['date_creation'] ?? now()) : null,
                    'approved_by' => $status === 'approved' ? $approverId : null,
                    'approved_at' => $status === 'approved' ? ($source['date_validation'] ?? null) : null,
                    'rejected_by' => $status === 'rejected' ? $approverId : null,
                    'rejected_at' => $status === 'rejected' ? ($source['date_modification'] ?? null) : null,
                    'rejection_reason' => $source['motif_rejet'] ?? null,
                    'created_at' => $source['date_creation'] ?? now(),
                    'updated_at' => $source['date_modification'] ?? now(),
                ]);
                if (! $document->verification_token) {
                    $document->verification_token = Str::random(64);
                }
                $document->save();

                DocumentTransition::query()->updateOrCreate(
                    ['medical_document_id' => $document->id, 'to_status' => $status],
                    [
                        'from_status' => null,
                        'actor_id' => $approverId ?? $creatorId,
                        'comment' => 'État repris depuis GestMedicert historique',
                        'metadata' => ['legacy_document_id' => $source['id']],
                        'created_at' => $source['date_modification'] ?? $source['date_creation'] ?? now(),
                    ]
                );

                $this->map('document', (string) $source['id'], $document->id, $source);
                $this->increment('documents');
            }
        });
    }

    private function archiveLogs(): void
    {
        DB::connection('legacy_sqlsrv')->table('logs')->orderBy('id')->chunkById(250, function ($rows): void {
            foreach ($rows as $row) {
                $source = $this->normalizeSource((array) $row);
                DB::table('legacy_audit_logs')->updateOrInsert(
                    ['legacy_id' => $source['id']],
                    [
                        'legacy_user_id' => $source['user_id'] ?? null,
                        'action' => (string) $source['action'],
                        'details' => $source['details'] ?? null,
                        'ip_address' => $source['ip'] ?? null,
                        'occurred_at' => $source['date_action'] ?? null,
                        'raw_data' => json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'imported_at' => now(),
                    ]
                );
                $this->increment('legacy_logs');
            }
        });
    }

    private function importSignatureFiles(): void
    {
        $root = realpath((string) env('LEGACY_FILES_ROOT'));
        if (! $root) {
            $this->warnings[] = 'Répertoire de fichiers historiques introuvable : signatures non copiées.';

            return;
        }

        DB::connection('legacy_sqlsrv')->table('users')->whereNotNull('signature_file')->orderBy('id')->chunkById(100, function ($rows) use ($root): void {
            foreach ($rows as $row) {
                $source = $this->normalizeSource((array) $row);
                $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $source['signature_file']);
                $candidate = realpath($root.DIRECTORY_SEPARATOR.ltrim($relative, DIRECTORY_SEPARATOR));
                $status = 'missing';
                $storagePath = null;
                $hash = null;
                $error = null;

                if ($candidate && Str::startsWith(Str::lower($candidate), Str::lower($root.DIRECTORY_SEPARATOR)) && is_file($candidate)) {
                    $content = file_get_contents($candidate);
                    if ($content === false) {
                        $status = 'failed';
                        $error = 'Lecture impossible.';
                    } else {
                        $storagePath = 'legacy/signatures/'.basename($candidate);
                        Storage::disk('local')->put($storagePath, $content);
                        $hash = hash('sha256', $content);
                        $status = 'imported';
                    }
                }

                DB::table('legacy_artifacts')->updateOrInsert(
                    ['entity_type' => 'user', 'legacy_key' => (string) $source['id'], 'artifact_type' => 'signature'],
                    [
                        'source_path' => $relative,
                        'storage_path' => $storagePath,
                        'sha256' => $hash,
                        'status' => $status,
                        'error_message' => $error,
                        'imported_at' => now(),
                    ]
                );
                $this->increment('signature_files');
            }
        });
    }

    /** @param array<string, mixed> $source */
    private function legacyRole(array $source): string
    {
        return match ($source['role'] ?? null) {
            'admin' => 'organization-admin',
            'medecin' => 'doctor',
            'secretaire', 'secretaire_general' => 'nurse',
            'direction' => ($source['direction'] ?? null) === 'DRH' ? 'rh-hse' : 'manager',
            default => 'manager',
        };
    }

    private function legacyDocumentStatus(string $status): string
    {
        return match ($status) {
            'en_attente' => 'submitted',
            'valide' => 'approved',
            'rejete' => 'rejected',
            'archive' => 'revoked',
            default => 'draft',
        };
    }

    private function legacyDocumentType(string $type): string
    {
        return match ($type) {
            'prolongation' => 'prolongation',
            'reprise' => 'return_to_work',
            'naissance' => 'birth_attestation',
            'deces' => 'death_attestation',
            default => 'medical',
        };
    }

    private function legacyDocumentTitle(string $type): string
    {
        return match ($type) {
            'prolongation' => 'Prolongation de repos médical',
            'reprise' => 'Certificat de reprise de travail',
            'naissance' => 'Attestation interne de naissance',
            'deces' => 'Attestation interne de décès',
            'regularisation' => 'Régularisation médicale',
            default => 'Certificat médical',
        };
    }

    /** @param array<string, mixed> $source */
    private function map(string $entityType, string $legacyKey, int $targetId, array $source): void
    {
        DB::table('legacy_entity_maps')->updateOrInsert(
            ['entity_type' => $entityType, 'legacy_key' => $legacyKey],
            [
                'target_id' => $targetId,
                'checksum' => hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function mappedId(string $entityType, string $legacyKey): ?int
    {
        $id = DB::table('legacy_entity_maps')
            ->where('entity_type', $entityType)
            ->where('legacy_key', $legacyKey)
            ->value('target_id');

        return $id ? (int) $id : null;
    }

    private function code(string $value): string
    {
        $code = Str::upper(Str::slug($value, ''));

        return Str::limit($code ?: 'LEGACY', 30, '');
    }

    private function increment(string $entity): void
    {
        $this->counts[$entity] = ($this->counts[$entity] ?? 0) + 1;
    }

    /** @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function normalizeSource(array $source): array
    {
        return collect($source)
            ->map(function ($value) {
                if (! is_string($value) || mb_check_encoding($value, 'UTF-8')) {
                    return $value;
                }

                return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            })
            ->all();
    }
}
