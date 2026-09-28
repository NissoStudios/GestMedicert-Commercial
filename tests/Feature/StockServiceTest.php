<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\Site;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispensing_cannot_create_negative_stock(): void
    {
        [$user, $prescription, $warehouse, $batch, $item] = $this->stockScenario(5);

        try {
            app(StockService::class)->dispense($prescription, $warehouse->id, [[
                'prescription_item_id' => $item->id,
                'stock_batch_id' => $batch->id,
                'quantity' => 6,
            ]], $user);

            $this->fail('Expected a stock validation error.');
        } catch (ValidationException) {
            $this->assertSame('5.000', $batch->fresh()->quantity_on_hand);
            $this->assertDatabaseCount('dispensations', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_returned_medication_is_placed_in_quarantine(): void
    {
        [$user, $prescription, $warehouse, $batch, $item] = $this->stockScenario(10);

        $dispensation = app(StockService::class)->dispense($prescription, $warehouse->id, [[
            'prescription_item_id' => $item->id,
            'stock_batch_id' => $batch->id,
            'quantity' => 4,
        ]], $user);

        app(StockService::class)->returnItem($dispensation->items()->firstOrFail(), 2, $user, 'Retour patient scellé');

        $this->assertSame('6.000', $batch->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_batches', [
            'warehouse_id' => $warehouse->id,
            'status' => 'quarantine',
            'quantity_on_hand' => 2,
        ]);
        $this->assertSame('2.000', $item->fresh()->quantity_dispensed);
    }

    /** @return array{User, Prescription, Warehouse, StockBatch, PrescriptionItem} */
    private function stockScenario(float $quantity): array
    {
        $organization = Organization::query()->create(['code' => 'TEST', 'name' => 'Test']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'code' => 'S1', 'name' => 'Site 1']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'default_site_id' => $site->id]);
        $patient = Patient::query()->create([
            'organization_id' => $organization->id,
            'primary_site_id' => $site->id,
            'patient_number' => 'PAT-1',
            'patient_type' => 'employee',
            'last_name' => 'Test',
            'first_name' => 'Patient',
            'normalized_last_name' => 'test',
            'normalized_first_name' => 'patient',
            'sex' => 'unknown',
            'created_by' => $user->id,
        ]);
        $product = Product::query()->create([
            'organization_id' => $organization->id,
            'code' => 'MED-1',
            'type' => 'medication',
            'trade_name' => 'Medication',
            'unit' => 'tablet',
        ]);
        $warehouse = Warehouse::query()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'code' => 'WH-1',
            'name' => 'Pharmacy',
        ]);
        $batch = StockBatch::query()->create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'LOT-1',
            'expires_on' => today()->addYear(),
            'status' => 'available',
            'quantity_on_hand' => $quantity,
        ]);
        $consultation = Consultation::query()->create([
            'organization_id' => $organization->id,
            'patient_id' => $patient->id,
            'site_id' => $site->id,
            'practitioner_id' => $user->id,
            'status' => 'finalized',
            'started_at' => now(),
            'finalized_at' => now(),
            'chief_complaint' => 'Test',
            'created_by' => $user->id,
        ]);
        $prescription = Prescription::query()->create([
            'organization_id' => $organization->id,
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'prescriber_id' => $user->id,
            'status' => 'issued',
            'issued_at' => now(),
        ]);
        $item = $prescription->items()->create([
            'product_id' => $product->id,
            'dosage' => '1 tablet',
            'frequency' => 'twice daily',
            'duration' => '5 days',
            'quantity_prescribed' => 10,
        ]);

        return [$user, $prescription, $warehouse, $batch, $item];
    }
}
