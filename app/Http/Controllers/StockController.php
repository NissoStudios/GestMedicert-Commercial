<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $organizationId = $this->organizationId($request);
        $siteIds = $this->user($request)->accessibleSiteIds();
        $batches = StockBatch::query()
            ->with(['product:id,uuid,code,trade_name,inn,unit,reorder_level', 'warehouse.site:id,name'])
            ->where('organization_id', $organizationId)
            ->whereHas('warehouse', fn ($query) => $query->whereIn('site_id', $siteIds));

        if ($request->filled('warehouse_id')) {
            $batches->where('warehouse_id', $request->integer('warehouse_id'));
        }
        if ($request->boolean('low_stock')) {
            $batches->whereHas('product', fn ($query) => $query->whereColumn('stock_batches.quantity_on_hand', '<=', 'products.reorder_level'));
        }

        return Inertia::render('Pharmacy/Stock', [
            'batches' => $batches->orderBy('expires_on')->paginate(25)->withQueryString(),
            'warehouses' => Warehouse::query()->where('organization_id', $organizationId)->whereIn('site_id', $siteIds)->where('active', true)->with('site:id,name')->orderBy('name')->get(),
            'products' => Product::query()->where('organization_id', $organizationId)->where('active', true)->orderBy('trade_name')->get(['id', 'code', 'trade_name', 'inn', 'unit']),
            'recentMovements' => StockMovement::query()->with(['batch.product:id,trade_name', 'batch.warehouse:id,name', 'actor:id,name'])->where('organization_id', $organizationId)->latest('occurred_at')->limit(15)->get(),
            'batchOptions' => StockBatch::query()->with(['product:id,trade_name,unit', 'warehouse:id,name'])->where('organization_id', $organizationId)->whereHas('warehouse', fn ($query) => $query->whereIn('site_id', $siteIds))->where('quantity_on_hand', '>', 0)->orderBy('lot_number')->get(),
            'filters' => $request->only('warehouse_id', 'low_stock'),
        ]);
    }

    public function receive(Request $request, StockService $stock): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('organization_id', $organizationId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('organization_id', $organizationId)],
            'lot_number' => ['required', 'string', 'max:100'],
            'expires_on' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['available', 'quarantine'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $warehouse = Warehouse::query()->where('organization_id', $organizationId)->findOrFail($data['warehouse_id']);
        $this->ensureSiteAccess($request, (int) $warehouse->site_id, true);

        $batch = StockBatch::query()->firstOrCreate(
            [
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'lot_number' => $data['lot_number'],
            ],
            [
                'organization_id' => $organizationId,
                'expires_on' => $data['expires_on'] ?? null,
                'status' => $data['status'],
                'unit_cost' => $data['unit_cost'] ?? null,
                'quantity_on_hand' => 0,
            ]
        );

        $stock->receive($batch, (float) $data['quantity'], isset($data['unit_cost']) ? (float) $data['unit_cost'] : null, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Entrée de stock enregistrée.');
    }

    public function adjust(Request $request, StockService $stock): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validate([
            'stock_batch_id' => ['required', Rule::exists('stock_batches', 'id')->where('organization_id', $organizationId)],
            'counted_quantity' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $batch = StockBatch::query()->where('organization_id', $organizationId)->findOrFail($data['stock_batch_id']);
        $this->ensureSiteAccess($request, (int) $batch->warehouse()->value('site_id'), true);
        $stock->adjust($batch, (float) $data['counted_quantity'], $request->user(), $data['reason']);

        return back()->with('success', 'Ajustement d’inventaire enregistré.');
    }

    public function transfer(Request $request, StockService $stock): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validate([
            'stock_batch_id' => ['required', Rule::exists('stock_batches', 'id')->where('organization_id', $organizationId)],
            'target_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('organization_id', $organizationId)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $batch = StockBatch::query()->where('organization_id', $organizationId)->findOrFail($data['stock_batch_id']);
        $target = Warehouse::query()->where('organization_id', $organizationId)->findOrFail($data['target_warehouse_id']);
        $this->ensureSiteAccess($request, (int) $batch->warehouse()->value('site_id'), true);
        $this->ensureSiteAccess($request, (int) $target->site_id, true);
        $stock->transfer($batch, $target, (float) $data['quantity'], $request->user(), $data['reason']);

        return back()->with('success', 'Transfert de stock enregistré.');
    }
}
