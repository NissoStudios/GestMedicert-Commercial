<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('timezone', 60)->default('Africa/Douala');
            $table->string('locale', 5)->default('fr');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 30);
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->noActionOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('organizational_units');
            $table->uuid('uuid')->unique();
            $table->string('code', 30);
            $table->string('name');
            $table->string('type', 30);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'type', 'active']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->noActionOnDelete();
            $table->foreignId('default_site_id')->nullable()->after('organization_id')->constrained('sites')->noActionOnDelete();
            $table->string('legacy_username', 50)->nullable()->after('email');
        });

        Schema::create('site_user', function (Blueprint $table) {
            $table->foreignId('site_id')->constrained()->noActionOnDelete();
            $table->foreignId('user_id')->constrained()->noActionOnDelete();
            $table->boolean('can_write')->default(false);
            $table->timestamps();

            $table->primary(['site_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_site_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('legacy_username');
        });

        Schema::dropIfExists('organizational_units');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('organizations');
    }
};
