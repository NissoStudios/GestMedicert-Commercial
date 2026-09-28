<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status', 30);
            $table->boolean('dry_run')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('counts')->nullable();
            $table->text('warnings')->nullable();
            $table->text('error_message')->nullable();
        });

        Schema::create('legacy_entity_maps', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50);
            $table->string('legacy_key', 150);
            $table->unsignedBigInteger('target_id');
            $table->string('checksum', 64)->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'legacy_key']);
            $table->index(['entity_type', 'target_id']);
        });

        Schema::create('legacy_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id');
            $table->unsignedBigInteger('legacy_user_id')->nullable();
            $table->string('action', 100);
            $table->text('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->text('raw_data');
            $table->timestamp('imported_at')->useCurrent();

            $table->unique('legacy_id');
        });

        Schema::create('legacy_artifacts', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50);
            $table->string('legacy_key', 150);
            $table->string('artifact_type', 50);
            $table->string('source_path');
            $table->string('storage_path')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('status', 30);
            $table->text('error_message')->nullable();
            $table->timestamp('imported_at')->useCurrent();

            $table->unique(['entity_type', 'legacy_key', 'artifact_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_artifacts');
        Schema::dropIfExists('legacy_audit_logs');
        Schema::dropIfExists('legacy_entity_maps');
        Schema::dropIfExists('legacy_import_runs');
    }
};
