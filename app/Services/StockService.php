<?php

namespace App\Services;

use App\Models\Dispensation;
use App\Models\DispensationItem;
use App\Models\Prescription;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function __construct(private readonly AuditService $audit) {}

    public function receive(
        StockBatch $batch,
        float $quantity,
        ?float $unitCost,
        User $actor,
        ?string $reason = null,
    ): StockBatch {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La quantité doit être supérieure à zéro.']);
        }

        return DB::transaction(function () use ($batch, $quantity, $unitCost, $actor, $reason): StockBatch {
            $lockedBatch = StockBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $before = (float) $lockedBatch->quantity_on_hand;
            $after = $before + $quantity;

            $lockedBatch->update([
                'quantity_on_hand' => $after,
                'unit_cost' => $unitCost ?? $lockedBatch->unit_cost,
            ]);

            $this->movement($lockedBatch, 'entry', $quantity, $before, $after, $actor, null, null, $reason);
            $this->audit->record('stock.received', $actor, $lockedBatch, metadata: [
                'quantity' => $quantity,
                'before' => $before,
                'after' => $after,
            ]);

            return $lockedBatch->fresh();
        }, 3);
    }

    /**
     * @param  list<array{prescription_item_id?: int|null, stock_batch_id: int, quantity: float, substitution_reason?: string|null}>  $items
     */
    public function dispense(
        Prescription $prescription,
        int $warehouseId,
        array $items,
        User $actor,
        ?string $notes = null,
    ): Dispensation {
        return DB::transaction(function () use ($prescription, $warehouseId, $items, $actor, $notes): Dispensation {
            $dispensation = Dispensation::query()->create([
                'organization_id' => $prescription->organization_id,
                'prescription_id' => $prescription->id,
                'patient_id' => $prescription->patient_id,
                'warehouse_id' => $warehouseId,
                'status' => 'completed',
                'dispensed_by' => $actor->id,
                'dispensed_at' => now(),
                'notes' => $notes,
            ]);

            foreach (collect($items)->sortBy('stock_batch_id') as $item) {
                $quantity = (float) $item['quantity'];
                if ($quantity <= 0) {
                    throw ValidationException::withMessages(['items' => 'Chaque quantité délivrée doit être positive.']);
                }

                $batch = StockBatch::query()
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->findOrFail($item['stock_batch_id']);

                if ($batch->status !== 'available' || ($batch->expires_on && $batch->expires_on->isBefore(today()))) {
                    throw ValidationException::withMessages(['items' => "Le lot {$batch->lot_number} est indisponible ou expiré."]);
                }

                $before = (float) $batch->quantity_on_hand;
                if ($before < $quantity) {
                    throw ValidationException::withMessages(['items' => "Stock insuffisant pour le lot {$batch->lot_number}."]);
                }

                $after = $before - $quantity;
                $batch->update(['quantity_on_hand' => $after]);

                $dispensationItem = $dispensation->items()->create([
                    'prescription_item_id' => $item['prescription_item_id'] ?? null,
                    'product_id' => $batch->product_id,
                    'stock_batch_id' => $batch->id,
                    'quantity' => $quantity,
                    'unit_cost' => $batch->unit_cost,
                    'lot_selection_reason' => $item['lot_selection_reason'] ?? null,
                    'substitution_reason' => $item['substitution_reason'] ?? null,
                ]);

                if ($dispensationItem->prescription_item_id) {
                    $prescriptionItem = $dispensationItem->prescriptionItem()->lockForUpdate()->firstOrFail();
                    $prescriptionItem->increment('quantity_dispensed', $quantity);
                }

                $this->movement(
                    $batch,
                    'dispense',
                    -$quantity,
                    $before,
                    $after,
                    $actor,
                    Dispensation::class,
                    $dispensation->id,
                    $notes
                );
            }

            $prescription->load('items');
            $fullyDispensed = $prescription->items->every(
                fn ($item) => (float) $item->quantity_dispensed >= (float) $item->quantity_prescribed
            );
            $prescription->update(['status' => $fullyDispensed ? 'dispensed' : 'partially_dispensed']);

            $this->audit->record('pharmacy.dispensed', $actor, $dispensation, $prescription->patient_id, [
                'item_count' => count($items),
                'warehouse_id' => $warehouseId,
            ]);

            return $dispensation->load('items.batch.product', 'patient', 'warehouse');
        }, 3);
    }

    public function adjust(StockBatch $batch, float $countedQuantity, User $actor, string $reason): StockBatch
    {
        if ($countedQuantity < 0) {
            throw ValidationException::withMessages(['counted_quantity' => 'La quantité comptée ne peut pas être négative.']);
        }

        return DB::transaction(function () use ($batch, $countedQuantity, $actor, $reason): StockBatch {
            $lockedBatch = StockBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $before = (float) $lockedBatch->quantity_on_hand;
            $delta = $countedQuantity - $before;

            if (abs($delta) < 0.0001) {
                return $lockedBatch;
            }

            $lockedBatch->update(['quantity_on_hand' => $countedQuantity]);
            $this->movement($lockedBatch, 'adjustment', $delta, $before, $countedQuantity, $actor, null, null, $reason);
            $this->audit->record('stock.adjusted', $actor, $lockedBatch, metadata: [
                'before' => $before,
                'after' => $countedQuantity,
                'reason' => $reason,
            ]);

            return $lockedBatch->fresh();
        }, 3);
    }

    public function transfer(StockBatch $source, Warehouse $targetWarehouse, float $quantity, User $actor, string $reason): StockBatch
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La quantité transférée doit être positive.']);
        }
        if ((int) $source->warehouse_id === (int) $targetWarehouse->id) {
            throw ValidationException::withMessages(['target_warehouse_id' => 'Le magasin de destination doit être différent.']);
        }

        return DB::transaction(function () use ($source, $targetWarehouse, $quantity, $actor, $reason): StockBatch {
            $lockedSource = StockBatch::query()->lockForUpdate()->findOrFail($source->id);
            $sourceBefore = (float) $lockedSource->quantity_on_hand;
            if ($sourceBefore < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Stock source insuffisant.']);
            }

            $target = StockBatch::query()->firstOrCreate(
                [
                    'product_id' => $lockedSource->product_id,
                    'warehouse_id' => $targetWarehouse->id,
                    'lot_number' => $lockedSource->lot_number,
                ],
                [
                    'organization_id' => $lockedSource->organization_id,
                    'expires_on' => $lockedSource->expires_on,
                    'status' => $lockedSource->status,
                    'unit_cost' => $lockedSource->unit_cost,
                    'quantity_on_hand' => 0,
                ]
            );
            $lockedTarget = StockBatch::query()->lockForUpdate()->findOrFail($target->id);
            $targetBefore = (float) $lockedTarget->quantity_on_hand;

            $lockedSource->update(['quantity_on_hand' => $sourceBefore - $quantity]);
            $lockedTarget->update(['quantity_on_hand' => $targetBefore + $quantity]);

            $this->movement($lockedSource, 'transfer_out', -$quantity, $sourceBefore, $sourceBefore - $quantity, $actor, Warehouse::class, $targetWarehouse->id, $reason);
            $this->movement($lockedTarget, 'transfer_in', $quantity, $targetBefore, $targetBefore + $quantity, $actor, Warehouse::class, $lockedSource->warehouse_id, $reason);
            $this->audit->record('stock.transferred', $actor, $lockedSource, metadata: [
                'target_warehouse_id' => $targetWarehouse->id,
                'quantity' => $quantity,
                'reason' => $reason,
            ]);

            return $lockedTarget->fresh();
        }, 3);
    }

    public function returnItem(DispensationItem $item, float $quantity, User $actor, string $reason): DispensationItem
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La quantité retournée doit être positive.']);
        }

        return DB::transaction(function () use ($item, $quantity, $actor, $reason): DispensationItem {
            $lockedItem = DispensationItem::query()->with('dispensation')->lockForUpdate()->findOrFail($item->id);
            $remainingReturnable = (float) $lockedItem->quantity - (float) $lockedItem->quantity_returned;
            if ($quantity > $remainingReturnable) {
                throw ValidationException::withMessages(['quantity' => 'La quantité retournée dépasse la quantité délivrée restante.']);
            }

            $sourceBatch = StockBatch::query()->lockForUpdate()->findOrFail($lockedItem->stock_batch_id);
            $returnBatch = StockBatch::query()->firstOrCreate(
                [
                    'product_id' => $sourceBatch->product_id,
                    'warehouse_id' => $sourceBatch->warehouse_id,
                    'lot_number' => Str::limit($sourceBatch->lot_number.'-RET-'.$lockedItem->dispensation_id, 100, ''),
                ],
                [
                    'organization_id' => $sourceBatch->organization_id,
                    'expires_on' => $sourceBatch->expires_on,
                    'status' => 'quarantine',
                    'unit_cost' => $sourceBatch->unit_cost,
                    'quantity_on_hand' => 0,
                ]
            );
            $returnBatch = StockBatch::query()->lockForUpdate()->findOrFail($returnBatch->id);
            $before = (float) $returnBatch->quantity_on_hand;
            $returnBatch->increment('quantity_on_hand', $quantity);
            $lockedItem->increment('quantity_returned', $quantity);
            if ($lockedItem->prescription_item_id) {
                $lockedItem->prescriptionItem()->lockForUpdate()->first()?->decrement('quantity_dispensed', $quantity);
            }

            $this->movement($returnBatch, 'return_quarantine', $quantity, $before, $before + $quantity, $actor, Dispensation::class, $lockedItem->dispensation_id, $reason);
            $this->audit->record('pharmacy.item-returned', $actor, $lockedItem, $lockedItem->dispensation->patient_id, [
                'quantity' => $quantity,
                'reason' => $reason,
            ]);

            return $lockedItem->fresh();
        }, 3);
    }

    private function movement(
        StockBatch $batch,
        string $type,
        float $quantity,
        float $before,
        float $after,
        User $actor,
        ?string $referenceType,
        ?int $referenceId,
        ?string $reason,
    ): StockMovement {
        return StockMovement::query()->create([
            'organization_id' => $batch->organization_id,
            'warehouse_id' => $batch->warehouse_id,
            'product_id' => $batch->product_id,
            'stock_batch_id' => $batch->id,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'actor_id' => $actor->id,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }
}
