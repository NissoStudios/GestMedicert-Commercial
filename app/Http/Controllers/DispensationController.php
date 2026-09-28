<?php

namespace App\Http\Controllers;

use App\Models\Dispensation;
use App\Models\DispensationItem;
use App\Models\Prescription;
use App\Models\StockBatch;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DispensationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Pharmacy/Dispensations', [
            'dispensations' => Dispensation::query()
                ->with(['patient:id,uuid,patient_number,last_name,first_name', 'warehouse:id,name', 'items.product:id,trade_name'])
                ->where('organization_id', $this->organizationId($request))
                ->whereHas('warehouse', fn ($query) => $query->whereIn('site_id', $this->user($request)->accessibleSiteIds()))
                ->latest('dispensed_at')
                ->paginate(20),
        ]);
    }

    public function create(Request $request): Response
    {
        $organizationId = $this->organizationId($request);
        $siteIds = $this->user($request)->accessibleSiteIds();

        return Inertia::render('Pharmacy/Dispense', [
            'prescriptions' => Prescription::query()
                ->with(['patient:id,patient_number,last_name,first_name', 'items.product:id,trade_name,inn,strength,unit'])
                ->where('organization_id', $organizationId)
                ->whereIn('status', ['issued', 'partially_dispensed'])
                ->latest('issued_at')
                ->get(),
            'warehouses' => Warehouse::query()->where('organization_id', $organizationId)->whereIn('site_id', $siteIds)->where('active', true)->with('site:id,name')->get(),
            'batches' => StockBatch::query()
                ->with('product:id,trade_name,inn,strength,unit')
                ->where('organization_id', $organizationId)
                ->where('status', 'available')
                ->where('quantity_on_hand', '>', 0)
                ->where(fn ($query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))
                ->whereHas('warehouse', fn ($query) => $query->whereIn('site_id', $siteIds))
                ->orderBy('expires_on')
                ->get(),
        ]);
    }

    public function store(Request $request, StockService $stock): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validate([
            'prescription_id' => ['required', Rule::exists('prescriptions', 'id')->where('organization_id', $organizationId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('organization_id', $organizationId)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.prescription_item_id' => ['required', 'integer', 'exists:prescription_items,id'],
            'items.*.stock_batch_id' => ['required', 'integer', 'exists:stock_batches,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.lot_selection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $prescription = Prescription::query()->with('items')->where('organization_id', $organizationId)->findOrFail($data['prescription_id']);
        $warehouse = Warehouse::query()->where('organization_id', $organizationId)->findOrFail($data['warehouse_id']);
        $this->ensureSiteAccess($request, (int) $warehouse->site_id, true);

        foreach ($data['items'] as $item) {
            $prescriptionItem = $prescription->items->firstWhere('id', (int) $item['prescription_item_id']);
            $batch = StockBatch::query()->where('organization_id', $organizationId)->findOrFail($item['stock_batch_id']);
            if (! $prescriptionItem || (int) $prescriptionItem->product_id !== (int) $batch->product_id) {
                throw ValidationException::withMessages(['items' => 'Le lot sélectionné ne correspond pas au produit prescrit.']);
            }
            $remaining = (float) $prescriptionItem->quantity_prescribed - (float) $prescriptionItem->quantity_dispensed;
            if ((float) $item['quantity'] > $remaining) {
                throw ValidationException::withMessages(['items' => 'La quantité dépasse le reliquat prescrit.']);
            }

            $earliestBatchId = StockBatch::query()
                ->where('organization_id', $organizationId)
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $prescriptionItem->product_id)
                ->where('status', 'available')
                ->where('quantity_on_hand', '>', 0)
                ->where(fn ($query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))
                ->orderByRaw('CASE WHEN expires_on IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_on')
                ->value('id');

            if (
                $earliestBatchId
                && (int) $earliestBatchId !== (int) $batch->id
                && empty($item['lot_selection_reason'])
            ) {
                throw ValidationException::withMessages([
                    'items' => "Le lot {$batch->lot_number} n’est pas le premier à expirer. Une justification est obligatoire.",
                ]);
            }
        }

        $dispensation = $stock->dispense($prescription, (int) $warehouse->id, $data['items'], $request->user(), $data['notes'] ?? null);

        return redirect()->route('dispensations.show', $dispensation)->with('success', 'Délivrance enregistrée.');
    }

    public function show(Request $request, Dispensation $dispensation): Response
    {
        $this->ensureOrganization($request, $dispensation);
        $dispensation->load(['patient', 'warehouse.site', 'prescription.prescriber:id,name', 'items.product', 'items.batch']);

        return Inertia::render('Pharmacy/DispensationShow', ['dispensation' => $dispensation]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Dispensation $dispensation)
    {
        abort(405);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Dispensation $dispensation)
    {
        abort(405);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Dispensation $dispensation)
    {
        abort(405);
    }

    public function returnItem(Request $request, Dispensation $dispensation, DispensationItem $item, StockService $stock): RedirectResponse
    {
        $this->ensureOrganization($request, $dispensation);
        abort_unless((int) $item->dispensation_id === (int) $dispensation->id, 404);
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $stock->returnItem($item, (float) $data['quantity'], $request->user(), $data['reason']);

        return back()->with('success', 'Retour réintégré dans le lot d’origine.');
    }
}
