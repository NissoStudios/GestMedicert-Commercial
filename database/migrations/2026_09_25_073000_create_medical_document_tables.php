<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('reference', 50);
            $table->foreignId('patient_id')->constrained()->noActionOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->noActionOnDelete();
            $table->foreignId('parent_document_id')->nullable()->constrained('medical_documents');
            $table->foreignId('replaces_document_id')->nullable()->constrained('medical_documents');
            $table->string('type', 40);
            $table->string('title');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 30)->default('draft');
            $table->text('data')->nullable();
            $table->boolean('confidential')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('verification_token', 64)->unique();
            $table->string('pdf_path')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'reference']);
            $table->index(['patient_id', 'status', 'type']);
        });

        Schema::create('document_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_document_id')->constrained()->noActionOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_id')->constrained('users');
            $table->text('comment')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_transitions');
        Schema::dropIfExists('medical_documents');
    }
};
