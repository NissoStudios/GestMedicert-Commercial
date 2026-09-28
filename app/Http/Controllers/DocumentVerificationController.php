<?php

namespace App\Http\Controllers;

use App\Models\MedicalDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentVerificationController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $document = MedicalDocument::query()
            ->with('organization:id,name')
            ->where('verification_token', $token)
            ->firstOrFail();

        $status = $document->status;
        if ($status === 'approved' && $document->expires_on?->isBefore(today())) {
            $status = 'expired';
        }

        return Inertia::render('Documents/Verify', [
            'verification' => [
                'reference' => $document->reference,
                'type' => $document->type,
                'title' => $document->title,
                'organization' => $document->organization->name,
                'status' => $status,
                'approved_at' => $document->approved_at,
                'expires_on' => $document->expires_on,
                'sha256' => $document->sha256,
            ],
        ]);
    }
}
