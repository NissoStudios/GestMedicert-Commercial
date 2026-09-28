<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Diagnosis;
use App\Models\MedicalDocument;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $organizationId = $this->organizationId($request);
        $siteIds = $this->user($request)->accessibleSiteIds();
        $from = $request->date('from') ?? today()->startOfMonth();
        $to = $request->date('to') ?? today();

        return Inertia::render('Reports/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'appointments' => Appointment::query()->where('organization_id', $organizationId)->whereIn('site_id', $siteIds)->whereBetween('scheduled_start', [$from->startOfDay(), $to->endOfDay()])->count(),
                'consultations' => Consultation::query()->where('organization_id', $organizationId)->whereIn('site_id', $siteIds)->whereBetween('started_at', [$from->startOfDay(), $to->endOfDay()])->count(),
                'documents' => MedicalDocument::query()
                    ->where('organization_id', $organizationId)
                    ->whereHas('patient', fn ($query) => $query->whereIn('primary_site_id', $siteIds))
                    ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                    ->count(),
                'dispensedUnits' => abs((float) StockMovement::query()->where('organization_id', $organizationId)->whereIn('warehouse_id', function ($query) use ($siteIds) {
                    $query->select('id')->from('warehouses')->whereIn('site_id', $siteIds);
                })->where('type', 'dispense')->whereBetween('occurred_at', [$from->startOfDay(), $to->endOfDay()])->sum('quantity')),
            ],
            'topDiagnoses' => Diagnosis::query()
                ->selectRaw('code, label, COUNT(*) as total')
                ->whereHas('consultation', fn ($query) => $query->where('organization_id', $organizationId)->whereIn('site_id', $siteIds)->whereBetween('started_at', [$from->startOfDay(), $to->endOfDay()]))
                ->groupBy('code', 'label')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'expiringBatches' => StockBatch::query()
                ->with(['product:id,trade_name,unit', 'warehouse:id,name'])
                ->where('organization_id', $organizationId)
                ->whereHas('warehouse', fn ($query) => $query->whereIn('site_id', $siteIds))
                ->where('quantity_on_hand', '>', 0)
                ->whereBetween('expires_on', [today(), today()->addDays(90)])
                ->orderBy('expires_on')
                ->limit(20)
                ->get(),
        ]);
    }
}
