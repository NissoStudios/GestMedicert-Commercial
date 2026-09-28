<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 50);
            $table->string('type', 30);
            $table->string('inn')->nullable();
            $table->string('trade_name');
            $table->string('strength')->nullable();
            $table->string('dosage_form')->nullable();
            $table->string('unit', 30);
            $table->string('atc_code', 30)->nullable();
            $table->string('gtin', 30)->nullable();
            $table->decimal('reorder_level', 18, 3)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'type', 'active']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->foreignId('site_id')->constrained();
            $table->uuid('uuid')->unique();
            $table->string('code', 50);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->foreignId('product_id')->constrained()->noActionOnDelete();
            $table->foreignId('warehouse_id')->constrained()->noActionOnDelete();
            $table->string('lot_number', 100);
            $table->date('expires_on')->nullable();
            $table->string('status', 30)->default('available');
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('quantity_on_hand', 18, 3)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id', 'lot_number']);
            $table->index(['warehouse_id', 'status', 'expires_on']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('stock_batch_id')->constrained();
            $table->string('type', 30);
            $table->decimal('quantity', 18, 3);
            $table->decimal('quantity_before', 18, 3);
            $table->decimal('quantity_after', 18, 3);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->constrained('users');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['stock_batch_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->noActionOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('dosage');
            $table->string('route', 50)->nullable();
            $table->string('frequency', 100);
            $table->string('duration', 100);
            $table->decimal('quantity_prescribed', 18, 3);
            $table->decimal('quantity_dispensed', 18, 3)->default(0);
            $table->text('instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('dispensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('prescription_id')->nullable()->constrained()->noActionOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('status', 30)->default('completed');
            $table->foreignId('dispensed_by')->constrained('users');
            $table->timestamp('dispensed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('dispensation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispensation_id')->constrained()->noActionOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained()->noActionOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('stock_batch_id')->constrained();
            $table->decimal('quantity', 18, 3);
            $table->decimal('quantity_returned', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->text('lot_selection_reason')->nullable();
            $table->foreignId('substitution_authorized_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->text('substitution_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensation_items');
        Schema::dropIfExists('dispensations');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('products');
    }
};
