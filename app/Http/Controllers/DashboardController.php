<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\StockBatch;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AuditService $audit): Response
    {
        $organizationId = $this->organizationId($request);
        $siteIds = $this->user($request)->accessibleSiteIds();

        $metrics = [
            'activePatients' => Patient::query()
                ->where('organization_id', $organizationId)
                ->where('active', true)
                ->whereIn('primary_site_id', $siteIds)
                ->count(),
            'appointmentsToday' => Appointment::query()
                ->where('organization_id', $organizationId)
                ->whereIn('site_id', $siteIds)
                ->whereDate('scheduled_start', today())
                ->count(),
            'openConsultations' => Consultation::query()
                ->where('organization_id', $organizationId)
                ->whereIn('site_id', $siteIds)
                ->where('status', 'draft')
                ->count(),
            'documentsToReview' => MedicalDocument::query()
                ->where('organization_id', $organizationId)
                ->whereHas('patient', fn ($query) => $query->whereIn('primary_site_id', $siteIds))
                ->where('status', 'submitted')
                ->count(),
            'expiringBatches' => StockBatch::query()
                ->where('organization_id', $organizationId)
                ->whereHas('warehouse', fn ($query) => $query->whereIn('site_id', $siteIds))
                ->where('quantity_on_hand', '>', 0)
                ->whereBetween('expires_on', [today(), today()->addDays(90)])
                ->count(),
        ];

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
            'todayQueue' => Appointment::query()
                ->with(['patient:id,uuid,patient_number,last_name,first_name', 'site:id,name'])
                ->where('organization_id', $organizationId)
                ->whereIn('site_id', $siteIds)
                ->whereDate('scheduled_start', today())
                ->orderBy('scheduled_start')
                ->limit(10)
                ->get(),
            'recentDocuments' => MedicalDocument::query()
                ->with('patient:id,uuid,patient_number,last_name,first_name')
                ->where('organization_id', $organizationId)
                ->whereHas('patient', fn ($query) => $query->whereIn('primary_site_id', $siteIds))
                ->latest()
                ->limit(8)
                ->get(),
            'auditIntegrity' => $audit->verify($organizationId),
        ]);
    }
}
