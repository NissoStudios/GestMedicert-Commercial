<?php

namespace App\Http\Controllers;

use App\Models\OrganizationalUnit;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Site;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $this->accessiblePatients($request)
            ->with(['primarySite:id,name', 'employments' => fn ($query) => $query->where('is_primary', true)]);

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('patient_number', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhereHas('employments', fn (Builder $employment) => $employment->where('employee_number', 'like', "%{$search}%"));
            });
        }

        if ($type = $request->query('type')) {
            $query->where('patient_type', $type);
        }

        return Inertia::render('Patients/Index', [
            'patients' => $query->orderBy('last_name')->orderBy('first_name')->paginate(15)->withQueryString(),
            'filters' => $request->only('search', 'type'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Patients/Form', [
            'patient' => null,
            ...$this->formOptions($request),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $this->validatePatient($request, $organizationId);
        $this->ensureSiteAccess($request, (int) $data['primary_site_id'], true);
        $normalized = $this->normalizedNames($data);

        $duplicateExists = Patient::query()
            ->where('organization_id', $organizationId)
            ->where($normalized)
            ->whereDate('date_of_birth', $data['date_of_birth'])
            ->exists();

        if ($duplicateExists && (! $request->user()->can('patients.override-duplicates') || empty($data['duplicate_override_reason']))) {
            throw ValidationException::withMessages([
                'duplicate_override_reason' => 'Un patient avec les mêmes nom, prénom et date de naissance existe déjà. Une dérogation habilitée et motivée est requise.',
            ]);
        }

        $patient = DB::transaction(function () use ($data, $normalized, $organizationId, $request): Patient {
            $patient = Patient::query()->create([
                ...collect($data)->except(['employee_number', 'job_title', 'organizational_unit_id'])->all(),
                ...$normalized,
                'organization_id' => $organizationId,
                'patient_number' => 'PAT-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
                'created_by' => $request->user()->id,
            ]);

            if (! empty($data['employee_number'])) {
                $patient->employments()->create([
                    'organization_id' => $organizationId,
                    'employee_number' => $data['employee_number'],
                    'employment_type' => $data['patient_type'] === 'employee' ? 'employee' : 'contractor',
                    'job_title' => $data['job_title'] ?? null,
                    'site_id' => $data['primary_site_id'],
                    'organizational_unit_id' => $data['organizational_unit_id'] ?? null,
                    'employment_status' => 'active',
                    'is_primary' => true,
                    'started_on' => today(),
                ]);
            }

            return $patient;
        });

        $audit->record('patient.created', $request->user(), $patient, $patient->id, request: $request);

        return redirect()->route('patients.show', $patient)->with('success', 'Patient créé avec succès.');
    }

    public function show(Request $request, Patient $patient, AuditService $audit): Response
    {
        $this->ensurePatientAccess($request, $patient);
        $patient->load([
            'primarySite:id,name',
            'relatedEmployee:id,uuid,patient_number,last_name,first_name',
            'employments.site:id,name',
            'employments.organizationalUnit:id,name,type',
            'appointments' => fn ($query) => $query->with('site:id,name')->latest('scheduled_start')->limit(10),
        ]);

        if ($request->user()->can('clinical-data.view')) {
            $patient->load([
                'allergies' => fn ($query) => $query->where('active', true),
                'consultations' => fn ($query) => $query->with('practitioner:id,name')->latest('started_at')->limit(10),
                'documents' => fn ($query) => $query->latest()->limit(10),
            ]);
        } else {
            $patient->appointments->each(function ($appointment): void {
                $appointment->reason = 'Rendez-vous médical';
                $appointment->notes = null;
                $appointment->practitioner_id = null;
            });
            $patient->setRelation('allergies', collect());
            $patient->setRelation('consultations', collect());
            $patient->setRelation('documents', collect());
        }

        $audit->record('patient.viewed', $request->user(), $patient, $patient->id, request: $request);

        return Inertia::render('Patients/Show', ['patient' => $patient]);
    }

    public function edit(Request $request, Patient $patient): Response
    {
        $this->ensurePatientAccess($request, $patient);
        $patient->load('employments');

        return Inertia::render('Patients/Form', [
            'patient' => $patient,
            ...$this->formOptions($request),
        ]);
    }

    public function update(Request $request, Patient $patient, AuditService $audit): RedirectResponse
    {
        $this->ensurePatientAccess($request, $patient);
        $data = $this->validatePatient($request, $this->organizationId($request), $patient);
        $this->ensureSiteAccess($request, (int) $data['primary_site_id'], true);

        $patient->update([
            ...collect($data)->except(['employee_number', 'job_title', 'organizational_unit_id'])->all(),
            ...$this->normalizedNames($data),
        ]);
        if (! empty($data['employee_number'])) {
            $patient->employments()->updateOrCreate(
                ['is_primary' => true],
                [
                    'organization_id' => $this->organizationId($request),
                    'employee_number' => $data['employee_number'],
                    'employment_type' => $data['patient_type'] === 'employee' ? 'employee' : 'contractor',
                    'job_title' => $data['job_title'] ?? null,
                    'site_id' => $data['primary_site_id'],
                    'organizational_unit_id' => $data['organizational_unit_id'] ?? null,
                    'employment_status' => 'active',
                ]
            );
        }

        $audit->record('patient.updated', $request->user(), $patient, $patient->id, request: $request);

        return redirect()->route('patients.show', $patient)->with('success', 'Patient mis à jour.');
    }

    public function destroy(Request $request, Patient $patient, AuditService $audit): RedirectResponse
    {
        $this->ensurePatientAccess($request, $patient);
        $patient->update(['active' => false]);
        $audit->record('patient.archived', $request->user(), $patient, $patient->id, request: $request);

        return redirect()->route('patients.index')->with('success', 'Patient archivé.');
    }

    public function storeAllergy(Request $request, Patient $patient, AuditService $audit): RedirectResponse
    {
        $this->ensurePatientAccess($request, $patient);
        $data = $request->validate([
            'substance' => ['required', 'string', 'max:255'],
            'reaction' => ['nullable', 'string', 'max:1000'],
            'severity' => ['required', Rule::in(['unknown', 'mild', 'moderate', 'severe'])],
            'absolute_contraindication' => ['boolean'],
        ]);
        $allergy = $patient->allergies()->create([
            ...$data,
            'recorded_by' => $request->user()->id,
            'active' => true,
        ]);
        $audit->record('patient.allergy-created', $request->user(), $allergy, $patient->id, request: $request);

        return back()->with('success', 'Allergie ou alerte ajoutée.');
    }

    public function deactivateAllergy(Request $request, Patient $patient, PatientAllergy $allergy, AuditService $audit): RedirectResponse
    {
        $this->ensurePatientAccess($request, $patient);
        abort_unless((int) $allergy->patient_id === (int) $patient->id, 404);
        $allergy->update(['active' => false]);
        $audit->record('patient.allergy-deactivated', $request->user(), $allergy, $patient->id, request: $request);

        return back()->with('success', 'Alerte désactivée.');
    }

    private function accessiblePatients(Request $request): Builder
    {
        return Patient::query()
            ->where('organization_id', $this->organizationId($request))
            ->whereIn('primary_site_id', $this->user($request)->accessibleSiteIds());
    }

    private function ensurePatientAccess(Request $request, Patient $patient): void
    {
        $this->ensureOrganization($request, $patient);
        $this->ensureSiteAccess($request, (int) $patient->primary_site_id);
    }

    /** @return array<string, mixed> */
    private function formOptions(Request $request): array
    {
        $organizationId = $this->organizationId($request);
        $siteIds = $this->user($request)->accessibleSiteIds();

        return [
            'sites' => Site::query()->where('organization_id', $organizationId)->whereIn('id', $siteIds)->where('active', true)->orderBy('name')->get(['id', 'name']),
            'units' => OrganizationalUnit::query()->where('organization_id', $organizationId)->where('active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'employees' => Patient::query()->where('organization_id', $organizationId)->where('patient_type', 'employee')->where('active', true)->orderBy('last_name')->get(['id', 'uuid', 'patient_number', 'last_name', 'first_name']),
        ];
    }

    /** @return array<string, mixed> */
    private function validatePatient(Request $request, int $organizationId, ?Patient $patient = null): array
    {
        return $request->validate([
            'patient_type' => ['required', Rule::in(['employee', 'contractor', 'trainee', 'dependent'])],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::in(['female', 'male', 'other', 'unknown'])],
            'primary_site_id' => ['required', 'integer', Rule::exists('sites', 'id')->where('organization_id', $organizationId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:100'],
            'related_employee_patient_id' => ['nullable', 'integer', Rule::exists('patients', 'id')->where('organization_id', $organizationId)],
            'relationship_type' => ['nullable', 'string', 'max:50'],
            'employee_number' => [
                Rule::requiredIf(in_array($request->input('patient_type'), ['employee', 'contractor', 'trainee'], true)),
                'nullable',
                'string',
                'max:50',
                Rule::unique('employments', 'employee_number')
                    ->where('organization_id', $organizationId)
                    ->ignore($patient?->employments()->where('is_primary', true)->value('id')),
            ],
            'job_title' => ['nullable', 'string', 'max:255'],
            'organizational_unit_id' => ['nullable', 'integer', Rule::exists('organizational_units', 'id')->where('organization_id', $organizationId)],
            'duplicate_override_reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array{normalized_last_name: string, normalized_first_name: string}
     */
    private function normalizedNames(array $data): array
    {
        return [
            'normalized_last_name' => Str::lower(Str::ascii(trim((string) $data['last_name']))),
            'normalized_first_name' => Str::lower(Str::ascii(trim((string) $data['first_name']))),
        ];
    }
}
