<?php

namespace Tests\Feature;

use App\Models\MedicalDocument;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentConfidentialityTest extends TestCase
{
    use RefreshDatabase;

    public function test_rh_user_only_receives_shareable_document_restrictions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $organization = Organization::query()->create(['code' => 'TEST', 'name' => 'Test']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'code' => 'S1', 'name' => 'Site 1']);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'default_site_id' => $site->id,
        ]);
        $user->assignRole('rh-hse');
        $user->sites()->attach($site->id, ['can_write' => true]);
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
        $document = MedicalDocument::query()->create([
            'organization_id' => $organization->id,
            'reference' => 'DOC-1',
            'patient_id' => $patient->id,
            'type' => 'fitness',
            'title' => 'Fitness certificate',
            'status' => 'approved',
            'data' => [
                'reason' => 'Confidential diagnosis',
                'details' => 'Highly confidential clinical note',
                'restrictions' => 'No night work',
            ],
            'created_by' => $user->id,
            'verification_token' => str_repeat('a', 64),
        ]);

        $this->actingAs($user)
            ->get(route('documents.show', $document))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.data.restrictions', 'No night work')
                ->missing('document.data.reason')
                ->missing('document.data.details')
            );
    }

    public function test_user_cannot_open_a_document_outside_their_site_scope(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $organization = Organization::query()->create(['code' => 'TEST', 'name' => 'Test']);
        $authorizedSite = Site::query()->create(['organization_id' => $organization->id, 'code' => 'S1', 'name' => 'Site 1']);
        $otherSite = Site::query()->create(['organization_id' => $organization->id, 'code' => 'S2', 'name' => 'Site 2']);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'default_site_id' => $authorizedSite->id,
        ]);
        $user->assignRole('rh-hse');
        $user->sites()->attach($authorizedSite->id, ['can_write' => true]);
        $patient = Patient::query()->create([
            'organization_id' => $organization->id,
            'primary_site_id' => $otherSite->id,
            'patient_number' => 'PAT-2',
            'patient_type' => 'employee',
            'last_name' => 'Other',
            'first_name' => 'Site',
            'normalized_last_name' => 'other',
            'normalized_first_name' => 'site',
            'sex' => 'unknown',
            'created_by' => $user->id,
        ]);
        $document = MedicalDocument::query()->create([
            'organization_id' => $organization->id,
            'reference' => 'DOC-2',
            'patient_id' => $patient->id,
            'type' => 'fitness',
            'title' => 'Fitness certificate',
            'status' => 'approved',
            'data' => ['restrictions' => 'No night work'],
            'created_by' => $user->id,
            'verification_token' => str_repeat('b', 64),
        ]);

        $this->actingAs($user)
            ->get(route('documents.show', $document))
            ->assertForbidden();
    }
}
