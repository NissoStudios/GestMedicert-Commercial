<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicalDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = MedicalDocument::query()
            ->with(['patient:id,uuid,patient_number,last_name,first_name', 'creator:id,name', 'approver:id,name'])
            ->where('organization_id', $this->organizationId($request))
            ->whereHas('patient', fn ($query) => $query->whereIn('primary_site_id', $this->user($request)->accessibleSiteIds()));

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return Inertia::render('Documents/Index', [
            'documents' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only('status', 'type'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Documents/Form', [
            'selectedPatientId' => $request->integer('patient') ?: null,
            'selectedConsultationId' => $request->integer('consultation') ?: null,
            'patients' => Patient::query()
                ->where('organization_id', $this->organizationId($request))
                ->whereIn('primary_site_id', $this->user($request)->accessibleSiteIds())
                ->where('active', true)
                ->orderBy('last_name')
                ->get(['id', 'uuid', 'patient_number', 'last_name', 'first_name']),
            'consultations' => Consultation::query()
                ->with('patient:id,last_name,first_name')
                ->where('organization_id', $this->organizationId($request))
                ->where('status', 'finalized')
                ->latest('started_at')
                ->limit(100)
                ->get(['id', 'uuid', 'patient_id', 'started_at']),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validate([
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('organization_id', $organizationId)],
            'consultation_id' => ['nullable', Rule::exists('consultations', 'id')->where('organization_id', $organizationId)],
            'type' => ['required', Rule::in(['medical', 'prolongation', 'return_to_work', 'birth_attestation', 'death_attestation', 'fitness'])],
            'title' => ['required', 'string', 'max:255'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
            'content' => ['required', 'array'],
            'content.reason' => ['required', 'string', 'max:4000'],
            'content.details' => ['nullable', 'string', 'max:8000'],
            'content.restrictions' => ['nullable', 'string', 'max:4000'],
        ]);

        $patient = Patient::query()->where('organization_id', $organizationId)->findOrFail($data['patient_id']);
        $this->ensureSiteAccess($request, (int) $patient->primary_site_id, true);

        $document = MedicalDocument::query()->create([
            ...collect($data)->except('content')->all(),
            'data' => $data['content'],
            'organization_id' => $organizationId,
            'reference' => 'GMC-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'status' => 'draft',
            'verification_token' => Str::random(64),
            'created_by' => $request->user()->id,
        ]);
        $document->transitions()->create([
            'to_status' => 'draft',
            'actor_id' => $request->user()->id,
            'comment' => 'Création du document',
            'created_at' => now(),
        ]);
        $audit->record('document.created', $request->user(), $document, $document->patient_id, request: $request);

        return redirect()->route('documents.show', $document)->with('success', 'Document créé en brouillon.');
    }

    public function show(Request $request, MedicalDocument $document, AuditService $audit): Response
    {
        $this->ensureOrganization($request, $document);
        $this->ensurePatientSiteAccess($request, (int) $document->patient_id);
        $document->load(['patient.primarySite', 'consultation', 'creator:id,name', 'approver:id,name', 'transitions.actor:id,name']);
        if (! $request->user()->can('clinical-data.view')) {
            $document->data = [
                'restrictions' => $document->data['restrictions'] ?? null,
            ];
            $document->setRelation('consultation', null);
        }
        $audit->record('document.viewed', $request->user(), $document, $document->patient_id, request: $request);

        return Inertia::render('Documents/Show', ['document' => $document]);
    }

    public function edit(Request $request, MedicalDocument $document): RedirectResponse
    {
        $this->ensureOrganization($request, $document);
        $this->ensurePatientSiteAccess($request, (int) $document->patient_id, true);
        abort_unless(in_array($document->status, ['draft', 'rejected'], true), 422);

        return redirect()->route('documents.show', $document);
    }

    public function update(Request $request, MedicalDocument $document, AuditService $audit): RedirectResponse
    {
        $this->ensureOrganization($request, $document);
        $this->ensurePatientSiteAccess($request, (int) $document->patient_id, true);
        abort_unless(in_array($document->status, ['draft', 'rejected'], true), 422, 'Ce document ne peut plus être modifié.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'expires_on' => ['nullable', 'date'],
            'content' => ['required', 'array'],
            'content.reason' => ['required', 'string', 'max:4000'],
            'content.details' => ['nullable', 'string', 'max:8000'],
            'content.restrictions' => ['nullable', 'string', 'max:4000'],
        ]);
        $document->update([
            ...collect($data)->except('content')->all(),
            'data' => $data['content'],
            'status' => 'draft',
        ]);
        $audit->record('document.updated', $request->user(), $document, $document->patient_id, request: $request);

        return back()->with('success', 'Document mis à jour.');
    }

    public function destroy(Request $request, MedicalDocument $document, AuditService $audit): RedirectResponse
    {
        $this->ensureOrganization($request, $document);
        $this->ensurePatientSiteAccess($request, (int) $document->patient_id, true);
        abort_unless($document->status === 'draft', 422);
        $audit->record('document.deleted', $request->user(), $document, $document->patient_id, request: $request);
        $document->delete();

        return redirect()->route('documents.index')->with('success', 'Brouillon supprimé.');
    }

    public function download(Request $request, MedicalDocument $document, AuditService $audit): StreamedResponse
    {
        $this->ensureOrganization($request, $document);
        $this->ensurePatientSiteAccess($request, (int) $document->patient_id);
        abort_unless($document->status === 'approved' && $document->pdf_path, 404);
        abort_unless(Storage::disk('local')->exists($document->pdf_path), 404);
        $audit->record('document.downloaded', $request->user(), $document, $document->patient_id, request: $request);

        return Storage::disk('local')->download($document->pdf_path, "{$document->reference}.pdf");
    }
}
