<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Site;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConsultationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Consultation::query()
            ->with(['patient:id,uuid,patient_number,last_name,first_name', 'site:id,name', 'practitioner:id,name'])
            ->where('organization_id', $this->organizationId($request))
            ->whereIn('site_id', $this->user($request)->accessibleSiteIds());

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return Inertia::render('Consultations/Index', [
            'consultations' => $query->latest('started_at')->paginate(20)->withQueryString(),
            'filters' => $request->only('status'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Consultations/Form', [
            'consultation' => null,
            'selectedPatientId' => $request->integer('patient') ?: null,
            'selectedAppointmentId' => $request->integer('appointment') ?: null,
            ...$this->formOptions($request),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $this->validateConsultation($request, $organizationId);
        $this->ensureSiteAccess($request, (int) $data['site_id'], true);

        if (! empty($data['prescription_items']) && ! $request->user()->can('prescriptions.issue')) {
            throw ValidationException::withMessages(['prescription_items' => 'Seul un médecin habilité peut finaliser une prescription.']);
        }

        $consultation = DB::transaction(function () use ($data, $organizationId, $request): Consultation {
            $consultation = Consultation::query()->create([
                ...collect($data)->except(['diagnoses', 'prescription_items', 'exam_requests'])->all(),
                'organization_id' => $organizationId,
                'practitioner_id' => $request->user()->id,
                'status' => 'draft',
                'started_at' => now(),
                'created_by' => $request->user()->id,
            ]);

            $this->syncClinicalChildren($consultation, $data, $request);

            if ($consultation->appointment_id) {
                Appointment::query()->whereKey($consultation->appointment_id)->update(['status' => 'in_consultation']);
            }

            return $consultation;
        });

        $audit->record('consultation.created', $request->user(), $consultation, $consultation->patient_id, request: $request);

        return redirect()->route('consultations.show', $consultation)->with('success', 'Consultation enregistrée en brouillon.');
    }

    public function show(Request $request, Consultation $consultation, AuditService $audit): Response
    {
        $this->ensureOrganization($request, $consultation);
        $this->ensureSiteAccess($request, (int) $consultation->site_id);
        $consultation->load([
            'patient.allergies',
            'patient.employments.site',
            'site',
            'practitioner:id,name',
            'diagnoses',
            'addenda.author:id,name',
            'prescription.items.product',
            'examRequests',
            'documents',
        ]);
        $audit->record('consultation.viewed', $request->user(), $consultation, $consultation->patient_id, request: $request);

        return Inertia::render('Consultations/Show', ['consultation' => $consultation]);
    }

    public function edit(Request $request, Consultation $consultation): Response
    {
        $this->ensureOrganization($request, $consultation);
        abort_unless($consultation->status === 'draft', 422, 'Une consultation finalisée est immuable.');
        $consultation->load('diagnoses', 'prescription.items', 'examRequests');

        return Inertia::render('Consultations/Form', [
            'consultation' => $consultation,
            'selectedPatientId' => null,
            'selectedAppointmentId' => null,
            ...$this->formOptions($request),
        ]);
    }

    public function update(Request $request, Consultation $consultation, AuditService $audit): RedirectResponse
    {
        $this->ensureOrganization($request, $consultation);
        abort_unless($consultation->status === 'draft', 422, 'Une consultation finalisée est immuable.');
        $data = $this->validateConsultation($request, $this->organizationId($request));

        DB::transaction(function () use ($consultation, $data, $request): void {
            $consultation->update(collect($data)->except(['diagnoses', 'prescription_items', 'exam_requests'])->all());
            $consultation->diagnoses()->delete();
            $consultation->examRequests()->delete();
            $consultation->prescription?->items()->delete();
            $consultation->prescription?->delete();
            $this->syncClinicalChildren($consultation, $data, $request);
        });

        $audit->record('consultation.updated', $request->user(), $consultation, $consultation->patient_id, request: $request);

        return redirect()->route('consultations.show', $consultation)->with('success', 'Brouillon mis à jour.');
    }

    public function destroy(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->ensureOrganization($request, $consultation);
        abort_unless($consultation->status === 'draft', 422);
        $consultation->delete();

        return redirect()->route('consultations.index')->with('success', 'Brouillon supprimé.');
    }

    /** @return array<string, mixed> */
    private function formOptions(Request $request): array
    {
        $organizationId = $this->organizationId($request);
        $siteIds = $this->user($request)->accessibleSiteIds();

        return [
            'patients' => Patient::query()->where('organization_id', $organizationId)->whereIn('primary_site_id', $siteIds)->where('active', true)->orderBy('last_name')->get(['id', 'uuid', 'patient_number', 'last_name', 'first_name', 'primary_site_id']),
            'sites' => Site::query()->where('organization_id', $organizationId)->whereIn('id', $siteIds)->where('active', true)->orderBy('name')->get(['id', 'name']),
            'appointments' => Appointment::query()->with('patient:id,last_name,first_name')->where('organization_id', $organizationId)->whereIn('site_id', $siteIds)->whereIn('status', ['scheduled', 'confirmed', 'waiting', 'called'])->orderBy('scheduled_start')->get(['id', 'patient_id', 'site_id', 'scheduled_start']),
            'products' => Product::query()->where('organization_id', $organizationId)->where('type', 'medication')->where('active', true)->orderBy('trade_name')->get(['id', 'code', 'inn', 'trade_name', 'strength', 'dosage_form', 'unit']),
        ];
    }

    /** @return array<string, mixed> */
    private function validateConsultation(Request $request, int $organizationId): array
    {
        return $request->validate([
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('organization_id', $organizationId)],
            'appointment_id' => ['nullable', Rule::exists('appointments', 'id')->where('organization_id', $organizationId)],
            'site_id' => ['required', Rule::exists('sites', 'id')->where('organization_id', $organizationId)],
            'chief_complaint' => ['required', 'string', 'max:4000'],
            'relevant_history' => ['nullable', 'string', 'max:8000'],
            'vitals' => ['nullable', 'array'],
            'vitals.temperature' => ['nullable', 'numeric', 'between:30,45'],
            'vitals.systolic' => ['nullable', 'integer', 'between:40,300'],
            'vitals.diastolic' => ['nullable', 'integer', 'between:20,200'],
            'vitals.weight' => ['nullable', 'numeric', 'between:0.5,500'],
            'vitals.height' => ['nullable', 'numeric', 'between:20,250'],
            'vitals.pulse' => ['nullable', 'integer', 'between:20,250'],
            'clinical_examination' => ['nullable', 'string', 'max:12000'],
            'care_plan' => ['nullable', 'string', 'max:12000'],
            'work_related' => ['boolean'],
            'follow_up_at' => ['nullable', 'date', 'after_or_equal:today'],
            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*.code' => ['required', 'string', 'max:30'],
            'diagnoses.*.label' => ['required', 'string', 'max:255'],
            'diagnoses.*.is_primary' => ['boolean'],
            'prescription_items' => ['nullable', 'array'],
            'prescription_items.*.product_id' => ['required', Rule::exists('products', 'id')->where('organization_id', $organizationId)],
            'prescription_items.*.dosage' => ['required', 'string', 'max:255'],
            'prescription_items.*.frequency' => ['required', 'string', 'max:100'],
            'prescription_items.*.duration' => ['required', 'string', 'max:100'],
            'prescription_items.*.quantity_prescribed' => ['required', 'numeric', 'gt:0'],
            'prescription_items.*.instructions' => ['nullable', 'string', 'max:1000'],
            'exam_requests' => ['nullable', 'array'],
            'exam_requests.*.category' => ['required', Rule::in(['laboratory', 'imaging', 'other'])],
            'exam_requests.*.name' => ['required', 'string', 'max:255'],
            'exam_requests.*.code' => ['nullable', 'string', 'max:50'],
            'exam_requests.*.external_provider' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function syncClinicalChildren(Consultation $consultation, array $data, Request $request): void
    {
        foreach ($data['diagnoses'] ?? [] as $index => $diagnosis) {
            $consultation->diagnoses()->create([
                ...$diagnosis,
                'coding_system' => 'ICD-10',
                'is_primary' => $diagnosis['is_primary'] ?? $index === 0,
                'work_related' => $data['work_related'] ?? false,
            ]);
        }

        if (! empty($data['prescription_items'])) {
            $prescription = $consultation->prescription()->create([
                'organization_id' => $consultation->organization_id,
                'patient_id' => $consultation->patient_id,
                'prescriber_id' => $request->user()->id,
                'status' => 'draft',
            ]);
            $prescription->items()->createMany($data['prescription_items']);
        }

        foreach ($data['exam_requests'] ?? [] as $exam) {
            $consultation->examRequests()->create([
                ...$exam,
                'organization_id' => $consultation->organization_id,
                'patient_id' => $consultation->patient_id,
                'requested_by' => $request->user()->id,
                'status' => 'requested',
                'requested_at' => now(),
                'coding_system' => empty($exam['code']) ? null : 'LOINC',
            ]);
        }
    }
}
