<?php

namespace App\Http\Controllers;

use App\Models\MedicalDocument;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DocumentWorkflowController extends Controller
{
    public function submit(Request $request, MedicalDocument $document, DocumentService $service): RedirectResponse
    {
        $this->ensureOrganization($request, $document);
        $this->ensurePatientSiteAccess($request, (int) $document->patient_id, true);
        $service->transition($document, 'submitted', $request->user());

        return back()->with('success', 'Document soumis pour validation.');
    }

    public function approve(Request $request, MedicalDocument $document, DocumentService $service): RedirectResponse
    {
        $this->ensureOrganization($request, $document);
        $this->ensurePatientSiteAccess($request, (int) $document->patient_id, true);
        if ((int) $document->created_by === (int) $request->user()->id && ! $request->user()->can('documents.self-approve')) {
            abort(403, 'L’auteur ne peut pas approuver son propre document.');
        }
        $service->transition($document, 'approved', $request->user(), $request->string('comment')->toString() ?: null);

        return back()->with('success', 'Document approuvé et PDF émis.');
    }

    public function reject(Request $request, MedicalDocument $document, DocumentService $service): RedirectResponse
    {
        $this->ensureOrganization($request, $document);
        $this->ensurePatientSiteAccess($request, (int) $document->patient_id, true);
        $data = $request->validate(['comment' => ['required', 'string', 'min:5', 'max:2000']]);
        $service->transition($document, 'rejected', $request->user(), $data['comment']);

        return back()->with('success', 'Document rejeté avec motif.');
    }

    public function reopen(Request $request, MedicalDocument $document, DocumentService $service): RedirectResponse
    {
        $this->ensureOrganization($request, $document);
        $this->ensurePatientSiteAccess($request, (int) $document->patient_id, true);
        $service->transition($document, 'draft', $request->user(), $request->string('comment')->toString() ?: null);

        return back()->with('success', 'Une nouvelle révision du brouillon est ouverte.');
    }

    public function revoke(Request $request, MedicalDocument $document, DocumentService $service): RedirectResponse
    {
        $this->ensureOrganization($request, $document);
        $this->ensurePatientSiteAccess($request, (int) $document->patient_id, true);
        $data = $request->validate(['comment' => ['required', 'string', 'min:5', 'max:2000']]);
        $service->transition($document, 'revoked', $request->user(), $data['comment']);

        return back()->with('success', 'Document révoqué.');
    }
}
