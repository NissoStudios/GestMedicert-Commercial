<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Product::query()
            ->withSum('batches as total_stock', 'quantity_on_hand')
            ->where('organization_id', $this->organizationId($request));

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('trade_name', 'like', "%{$search}%")
                ->orWhere('inn', 'like', "%{$search}%"));
        }

        return Inertia::render('Pharmacy/Products', [
            'products' => $query->orderBy('trade_name')->paginate(20)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Pharmacy/ProductForm', ['product' => null]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $this->validated($request, $organizationId);
        $product = Product::query()->create([...$data, 'organization_id' => $organizationId]);
        $audit->record('product.created', $request->user(), $product, metadata: ['code' => $product->code], request: $request);

        return redirect()->route('products.index')->with('success', 'Produit créé.');
    }

    public function show(Request $request, Product $product): RedirectResponse
    {
        $this->ensureOrganization($request, $product);

        return redirect()->route('stock.index', ['product' => $product->uuid]);
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->ensureOrganization($request, $product);

        return Inertia::render('Pharmacy/ProductForm', ['product' => $product]);
    }

    public function update(Request $request, Product $product, AuditService $audit): RedirectResponse
    {
        $this->ensureOrganization($request, $product);
        $product->update($this->validated($request, $this->organizationId($request), $product));
        $audit->record('product.updated', $request->user(), $product, request: $request);

        return redirect()->route('products.index')->with('success', 'Produit mis à jour.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->ensureOrganization($request, $product);
        $product->update(['active' => false]);

        return back()->with('success', 'Produit désactivé.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int $organizationId, ?Product $product = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('products', 'code')->where('organization_id', $organizationId)->ignore($product?->id)],
            'type' => ['required', Rule::in(['medication', 'consumable'])],
            'inn' => ['nullable', 'string', 'max:255'],
            'trade_name' => ['required', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:100'],
            'dosage_form' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'atc_code' => ['nullable', 'string', 'max:30'],
            'gtin' => ['nullable', 'string', 'max:30'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
