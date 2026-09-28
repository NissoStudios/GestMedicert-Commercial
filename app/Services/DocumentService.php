<?php

namespace App\Services;

use App\Models\DocumentTransition;
use App\Models\MedicalDocument;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    public function __construct(private readonly AuditService $audit) {}

    public function transition(
        MedicalDocument $document,
        string $toStatus,
        User $actor,
        ?string $comment = null,
    ): MedicalDocument {
        $allowed = [
            'draft' => ['submitted'],
            'submitted' => ['approved', 'rejected'],
            'rejected' => ['draft'],
            'approved' => ['revoked'],
        ];

        if (! in_array($toStatus, $allowed[$document->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Transition {$document->status} vers {$toStatus} interdite.",
            ]);
        }

        return DB::transaction(function () use ($document, $toStatus, $actor, $comment): MedicalDocument {
            $fromStatus = $document->status;
            $changes = ['status' => $toStatus];

            if ($toStatus === 'submitted') {
                $changes['submitted_at'] = now();
            }

            if ($toStatus === 'approved') {
                $changes['approved_by'] = $actor->id;
                $changes['approved_at'] = now();
            }

            if ($toStatus === 'rejected') {
                if (! $comment) {
                    throw ValidationException::withMessages(['comment' => 'Le motif de rejet est obligatoire.']);
                }
                $changes['rejected_by'] = $actor->id;
                $changes['rejected_at'] = now();
                $changes['rejection_reason'] = $comment;
            }

            if ($toStatus === 'draft') {
                $changes['version'] = $document->version + 1;
                $changes['rejection_reason'] = null;
            }

            $document->update($changes);

            DocumentTransition::query()->create([
                'medical_document_id' => $document->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_id' => $actor->id,
                'comment' => $comment,
                'created_at' => now(),
            ]);

            if ($toStatus === 'approved') {
                $this->generatePdf($document->fresh(['patient', 'creator', 'approver']));
            }

            $this->audit->record(
                "document.{$toStatus}",
                $actor,
                $document,
                $document->patient_id,
                ['from' => $fromStatus, 'to' => $toStatus, 'comment' => $comment]
            );

            return $document->fresh(['patient', 'creator', 'approver', 'transitions.actor']);
        }, 3);
    }

    public function generatePdf(MedicalDocument $document): void
    {
        $verificationUrl = route('documents.verify', $document->verification_token);
        $qrResult = (new Builder(
            writer: new PngWriter,
            data: $verificationUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 220,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        $pdf = Pdf::loadView('pdf.medical-document', [
            'document' => $document,
            'verificationUrl' => $verificationUrl,
            'qrCode' => $qrResult->getDataUri(),
        ]);

        $path = "documents/{$document->organization_id}/{$document->uuid}.pdf";
        $content = $pdf->output();

        if (! Storage::disk('local')->put($path, $content)) {
            throw ValidationException::withMessages(['document' => 'Impossible d’enregistrer le PDF final.']);
        }

        $document->update([
            'pdf_path' => $path,
            'sha256' => hash('sha256', $content),
        ]);
    }
}
