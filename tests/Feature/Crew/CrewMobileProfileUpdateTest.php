<?php

namespace Tests\Feature\Crew;

use App\Features\Auth\Support\TokenAbility;
use App\Features\Crew\Models\CrewProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrewMobileProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_crew_can_update_their_profile_and_complete_profile_onboarding(): void
    {
        [$user, $profile] = $this->authenticatedCrew(incomplete: true);
        $vehicle = $profile->vehicles()->create([
            'make' => 'Toyota', 'model' => 'Corolla', 'registration' => '1ABC234',
        ]);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/profile', $this->profilePayload())
            ->assertOk()
            ->assertJsonPath('data.profile.preferred_name', 'Morgan')
            ->assertJsonPath('data.profile.address.suburb', 'Perth')
            ->assertJsonPath('data.onboarding_complete', true)
            ->assertJsonPath('data.onboarding_missing', []);

        $this->assertSame('Morgan', $user->refresh()->name);
        $this->assertNotNull($user->onboarding_completed_at);
        $this->assertSame('Perth', $profile->refresh()->suburb);
        $this->assertDatabaseHas('crew_vehicles', ['id' => $vehicle->id]);
    }

    public function test_payment_detail_changes_require_the_current_password_and_remain_redacted(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $payload = $this->profilePayload() + [
            'payment_details' => [
                'account_name' => 'Morgan Vale',
                'bank_name' => 'Example Bank',
                'bsb' => '123-456',
                'account_number' => '123456789',
            ],
        ];

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/profile', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
        $this->assertNull($profile->refresh()->bank_account_number);

        $emptyPaymentPayload = $this->profilePayload() + ['payment_details' => []];
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/profile', $emptyPaymentPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $payload['password'] = 'wrong-password';
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/profile', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
        $this->assertNull($profile->refresh()->bank_account_number);

        $payload['password'] = 'secret-password';
        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.profile.payment_details.bsb_last_four', '3456')
            ->assertJsonPath('data.profile.payment_details.account_number_last_four', '6789');

        $this->assertSame('123456789', $profile->refresh()->bank_account_number);
        $this->assertStringNotContainsString('123-456', $response->getContent());
        $this->assertStringNotContainsString('123456789', $response->getContent());
    }

    public function test_profile_update_requires_idempotency_and_never_accepts_another_crews_vehicle(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $other = User::factory()->crew()->create();
        $otherProfile = CrewProfile::factory()->for($other)->create();
        $otherVehicle = $otherProfile->vehicles()->create([
            'make' => 'Ford', 'model' => 'Ranger', 'registration' => 'OTHER',
        ]);
        $payload = $this->profilePayload() + ['vehicles' => [[
            'uuid' => $otherVehicle->uuid,
            'make' => 'Changed', 'model' => 'Changed', 'registration' => 'CHANGED',
        ]]];

        $this->putJson('/api/v1/profile', $payload)->assertUnprocessable();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/profile', $payload)
            ->assertNotFound();

        $this->assertSame('Ford', $otherVehicle->refresh()->make);
        $this->assertSame($profile->id, $this->app['auth']->user()->crewProfile->id);
    }

    /** @return array{User, CrewProfile} */
    private function authenticatedCrew(bool $incomplete = false): array
    {
        $user = User::factory()->crew()->create([
            'password' => Hash::make('secret-password'),
            'onboarding_completed_at' => $incomplete ? null : now(),
        ]);
        $profile = CrewProfile::factory()->for($user)->create($incomplete ? [
            'phone' => null, 'address_line_1' => null, 'suburb' => null, 'state' => null,
            'postcode' => null, 'working_with_children_number' => null,
            'working_with_children_expiry' => null,
        ] : []);
        Sanctum::actingAs($user, [TokenAbility::CrewMobile->value]);

        return [$user, $profile];
    }

    /** @return array<string, mixed> */
    private function profilePayload(): array
    {
        return [
            'preferred_name' => 'Morgan',
            'legal_name' => 'Morgan Vale',
            'email' => 'morgan@example.com',
            'phone' => '0400 111 222',
            'address' => [
                'line_1' => '1 Test Street', 'line_2' => null, 'suburb' => 'Perth',
                'state' => 'WA', 'postcode' => '6000',
            ],
            'emergency_contact' => [
                'name' => 'Alex Vale', 'relationship' => 'Partner', 'phone' => '0400 333 444',
            ],
            'compliance' => [
                'working_with_children_number' => 'WWC123',
                'working_with_children_expiry' => now()->addYear()->toDateString(),
            ],
        ];
    }
}
