<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_chain_detects_tampering(): void
    {
        $organization = Organization::query()->create([
            'code' => 'TEST',
            'name' => 'Test Organization',
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $audit = app(AuditService::class);

        $first = $audit->record('test.first', $user, metadata: ['value' => 1]);
        $audit->record('test.second', $user, metadata: ['value' => 2]);

        $this->assertTrue($audit->verify($organization->id));

        DB::table('audit_events')->where('id', $first->id)->update(['metadata' => json_encode(['value' => 999])]);

        $this->assertFalse($audit->verify($organization->id));
    }
}
