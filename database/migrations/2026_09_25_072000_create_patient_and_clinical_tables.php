<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->foreignId('primary_site_id')->constrained('sites');
            $table->uuid('uuid')->unique();
            $table->string('patient_number', 30);
            $table->string('patient_type', 30);
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('normalized_last_name', 100);
            $table->string('normalized_first_name', 100);
            $table->date('date_of_birth')->nullable();
            $table->string('sex', 20);
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('national_id', 100)->nullable();
            $table->foreignId('related_employee_patient_id')->nullable()->constrained('patients');
            $table->string('relationship_type', 50)->nullable();
            $table->boolean('active')->default(true);
            $table->text('duplicate_override_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'patient_number']);
            $table->index(['primary_site_id', 'active']);
            $table->index(['organization_id', 'normalized_last_name', 'normalized_first_name', 'date_of_birth'], 'patients_duplicate_lookup');
        });

        Schema::create('patient_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->foreignId('patient_id')->constrained()->noActionOnDelete();
            $table->string('system', 100);
            $table->string('value', 150);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'system', 'value']);
        });

        Schema::create('employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->foreignId('patient_id')->constrained()->noActionOnDelete();
            $table->string('employee_number', 50);
            $table->string('employment_type', 30)->default('employee');
            $table->string('job_title')->nullable();
            $table->foreignId('site_id')->constrained();
            $table->foreignId('organizational_unit_id')->nullable()->constrained()->noActionOnDelete();
            $table->string('employment_status', 30)->default('active');
            $table->boolean('is_primary')->default(true);
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'employee_number']);
            $table->index(['site_id', 'organizational_unit_id', 'employment_status']);
        });

        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->noActionOnDelete();
            $table->string('substance');
            $table->string('reaction')->nullable();
            $table->string('severity', 20)->default('unknown');
            $table->boolean('absolute_contraindication')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('patient_id')->constrained()->noActionOnDelete();
            $table->foreignId('site_id')->constrained();
            $table->foreignId('practitioner_id')->nullable()->constrained('users')->noActionOnDelete();
            $table->string('reason');
            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end')->nullable();
            $table->string('status', 30)->default('scheduled');
            $table->string('source', 30)->default('appointment');
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['site_id', 'scheduled_start', 'status']);
        });

        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('patient_id')->constrained()->noActionOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->noActionOnDelete();
            $table->foreignId('site_id')->constrained();
            $table->foreignId('practitioner_id')->constrained('users');
            $table->string('status', 20)->default('draft');
            $table->timestamp('started_at');
            $table->timestamp('finalized_at')->nullable();
            $table->text('chief_complaint');
            $table->text('relevant_history')->nullable();
            $table->text('vitals')->nullable();
            $table->text('clinical_examination')->nullable();
            $table->text('care_plan')->nullable();
            $table->boolean('work_related')->default(false);
            $table->timestamp('follow_up_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['patient_id', 'started_at']);
            $table->index(['site_id', 'status', 'started_at']);
        });

        Schema::create('consultation_addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->noActionOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->noActionOnDelete();
            $table->string('coding_system', 30)->default('ICD-10');
            $table->string('code', 30);
            $table->string('label');
            $table->boolean('is_primary')->default(false);
            $table->boolean('work_related')->default(false);
            $table->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('consultation_id')->constrained()->noActionOnDelete();
            $table->foreignId('patient_id')->constrained()->noActionOnDelete();
            $table->foreignId('prescriber_id')->constrained('users');
            $table->string('status', 30)->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('consultation_id')->constrained()->noActionOnDelete();
            $table->foreignId('patient_id')->constrained()->noActionOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('category', 30);
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('coding_system', 30)->nullable();
            $table->string('status', 30)->default('requested');
            $table->string('external_provider')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('resulted_at')->nullable();
            $table->text('result_summary')->nullable();
            $table->string('result_file_path')->nullable();
            $table->string('result_file_hash', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_requests');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('consultation_addenda');
        Schema::dropIfExists('consultations');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('patient_allergies');
        Schema::dropIfExists('employments');
        Schema::dropIfExists('patient_identifiers');
        Schema::dropIfExists('patients');
    }
};
