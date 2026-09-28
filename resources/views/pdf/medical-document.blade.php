<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $document->reference }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 12px; line-height: 1.55; }
        .header { border-bottom: 3px solid #0f766e; padding-bottom: 16px; margin-bottom: 28px; }
        .brand { color: #0f766e; font-size: 22px; font-weight: bold; }
        .meta { color: #64748b; margin-top: 5px; }
        h1 { text-align: center; font-size: 20px; margin: 30px 0; }
        .block { margin: 18px 0; }
        .label { color: #64748b; font-size: 10px; text-transform: uppercase; font-weight: bold; }
        .value { margin-top: 4px; }
        .footer { position: fixed; bottom: 20px; left: 0; right: 0; border-top: 1px solid #cbd5e1; padding-top: 12px; }
        .qr { float: right; width: 105px; text-align: center; font-size: 8px; color: #64748b; }
        .qr img { width: 90px; height: 90px; }
        .signature { margin-top: 35px; }
        .hash { font-family: monospace; font-size: 8px; word-break: break-all; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $document->organization->name }}</div>
        <div class="meta">Document émis par GestMedicert ERP Santé · {{ $document->reference }}</div>
    </div>

    <h1>{{ $document->title }}</h1>

    <div class="block">
        <div class="label">Patient</div>
        <div class="value"><strong>{{ $document->patient->full_name }}</strong> · {{ $document->patient->patient_number }}</div>
    </div>
    <div class="block">
        <div class="label">Objet</div>
        <div class="value">{!! nl2br(e($document->data['reason'] ?? '')) !!}</div>
    </div>
    <div class="block">
        <div class="label">Détails</div>
        <div class="value">{!! nl2br(e($document->data['details'] ?? '')) !!}</div>
    </div>
    @if (!empty($document->data['restrictions']))
        <div class="block">
            <div class="label">Restrictions partageables</div>
            <div class="value">{!! nl2br(e($document->data['restrictions'])) !!}</div>
        </div>
    @endif

    <div class="signature">
        <div class="label">Validé par</div>
        <div class="value"><strong>{{ $document->approver->name }}</strong>, le {{ $document->approved_at->format('d/m/Y à H:i') }}</div>
    </div>

    <div class="footer">
        <div class="qr"><img src="{{ $qrCode }}" alt="QR"><br>Vérifier l’authenticité</div>
        <div><strong>Statut :</strong> approuvé</div>
        <div><strong>Expiration :</strong> {{ $document->expires_on?->format('d/m/Y') ?? 'aucune' }}</div>
        <div class="hash">URL de vérification : {{ $verificationUrl }}</div>
    </div>
</body>
</html>
