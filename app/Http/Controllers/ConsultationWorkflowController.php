<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\ExamRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConsultationWorkflowController extends Controller
{
    public function finalize(Request $request, Consultation $consultation, AuditService $audit): RedirectResponse
    {
        $this->ensureOrganization($request, $consultation);
        abort_unless($consultation->status === 'draft', 422);
        abort_unless(
            (int) $consultation->practitioner_id === (int) $request->user()->id
            || $request->user()->can('consultations.finalize-any'),
            403
        );

        if (! $consultation->diagnoses()->exists()) {
            throw ValidationException::withMessages(['diagnoses' => 'Au moins un diagnostic est requis avant finalisation.']);
        }

        $consultation->update(['status' => 'finalized', 'finalized_at' => now()]);
        $consultation->prescription()->where('status', 'draft')->update(['status' => 'issued', 'issued_at' => now()]);
        $consultation->appointment()->update(['status' => 'completed']);
        $audit->record('consultation.finalized', $request->user(), $consultation, $consultation->patient_id, request: $request);

        return back()->with('success', 'Consultation finalisée. Toute correction devra être ajoutée sous forme d’addendum.');
    }

    public function addendum(Request $request, Consultation $consultation, AuditService $audit): RedirectResponse
    {
        $this->ensureOrganization($request, $consultation);
        abort_unless($consultation->status === 'finalized', 422);
        $data = $request->validate(['content' => ['required', 'string', 'min:10', 'max:12000']]);

        $consultation->addenda()->create([
            'author_id' => $request->user()->id,
            'content' => $data['content'],
            'created_at' => now(),
        ]);
        $audit->record('consultation.addendum-created', $request->user(), $consultation, $consultation->patient_id, request: $request);

        return back()->with('success', 'Addendum ajouté.');
    }

    public function recordExamResult(Request $request, Consultation $consultation, ExamRequest $examRequest, AuditService $audit): RedirectResponse
    {
        $this->ensureOrganization($request, $consultation);
        abort_unless((int) $examRequest->consultation_id === (int) $consultation->id, 404);
        $data = $request->validate([
            'result_summary' => ['required', 'string', 'max:12000'],
            'result_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $path = $examRequest->result_file_path;
        $hash = $examRequest->result_file_hash;
        if ($request->hasFile('result_file')) {
            $file = $request->file('result_file');
            $path = $file->store("exam-results/{$consultation->organization_id}", 'local');
            $hash = hash_file('sha256', $file->getRealPath());
        }

        $examRequest->update([
            'status' => 'resulted',
            'result_summary' => $data['result_summary'],
            'resulted_at' => now(),
            'result_file_path' => $path,
            'result_file_hash' => $hash,
        ]);
        $audit->record('exam.result-recorded', $request->user(), $examRequest, $consultation->patient_id, request: $request);

        return back()->with('success', 'Résultat d’examen enregistré.');
    }
}
