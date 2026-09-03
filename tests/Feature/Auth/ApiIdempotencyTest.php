<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Models\ApiIdempotencyRecord;
use App\Features\Auth\Support\TokenAbility;
use App\Features\Crew\Models\CrewProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_payloads_are_encrypted_at_rest_and_expired_records_are_pruned(): void
    {
        $user = User::factory()->crew()->create(['onboarding_completed_at' => now()]);
        CrewProfile::factory()->for($user)->create([
            'phone' => '0400 000 000', 'address_line_1' => '1 Test Street', 'suburb' => 'Perth',
            'state' => 'WA', 'postcode' => '6000', 'working_with_children_number' => 'WWC123',
            'working_with_children_expiry' => today()->addYear(),
        ]);
        Sanctum::actingAs($user, [TokenAbility::CrewMobile->value]);

        $key = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $key)
            ->putJson('/api/v1/assignments/not-a-real-assignment/acknowledgement')->assertNotFound();

        // Exception responses do not represent committed mutations and are safe to retry.
        $this->assertDatabaseMissing('api_idempotency_records', ['user_id' => $user->id, 'key' => $key]);

        $record = ApiIdempotencyRecord::query()->create([
            'user_id' => $user->id, 'key' => (string) Str::uuid(), 'request_method' => 'PUT',
            'request_target' => '/api/v1/example', 'request_hash' => hash('sha256', 'request'),
            'response_status' => 200, 'response_body' => '{"private":"content"}',
            'response_headers' => ['Content-Type' => 'application/json'], 'completed_at' => now(),
            'expires_at' => now()->subMinute(),
        ]);
        $rawBody = (string) ApiIdempotencyRecord::query()->getQuery()->where('id', $record->id)->value('response_body');
        $this->assertStringNotContainsString('private', $rawBody);

        $this->artisan('api:prune-idempotency')->expectsOutput('Removed 1 expired API idempotency record(s).')->assertSuccessful();
        $this->assertDatabaseMissing('api_idempotency_records', ['id' => $record->id]);
    }
}
