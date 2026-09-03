<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Support\TokenAbility;
use App\Features\Crew\Models\CrewProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrewMobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_crew_can_login_before_completing_onboarding(): void
    {
        $user = User::factory()->crew()->create([
            'email' => 'mobile@example.com', 'password' => Hash::make('secret-password'),
            'onboarding_completed_at' => null,
        ]);
        $profile = CrewProfile::factory()->for($user)->create([
            'phone' => null, 'address_line_1' => null, 'suburb' => null, 'state' => null,
            'postcode' => null, 'working_with_children_number' => null,
            'working_with_children_expiry' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'secret-password', 'device_name' => 'David iPhone',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $profile->uuid)
            ->assertJsonPath('data.user.onboarding_complete', false)
            ->assertJsonFragment(['profile.phone'])
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user']]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'crew-mobile:David iPhone',
            'abilities' => json_encode([TokenAbility::CrewMobile->value]),
        ]);
    }

    public function test_non_crew_and_inactive_crew_cannot_login_to_the_crew_app(): void
    {
        $staff = User::factory()->staff()->create(['password' => Hash::make('secret-password')]);
        $inactive = User::factory()->crew()->inactive()->create(['password' => Hash::make('secret-password')]);
        CrewProfile::factory()->for($inactive)->create();

        foreach ([$staff, $inactive] as $user) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email, 'password' => 'secret-password', 'device_name' => 'iPhone',
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logging_in_again_on_the_same_device_replaces_its_token(): void
    {
        $user = $this->completeCrewUser();
        $credentials = ['email' => $user->email, 'password' => 'secret-password', 'device_name' => 'iPhone 16'];

        $first = $this->postJson('/api/v1/auth/login', $credentials)->json('data.token');
        $second = $this->postJson('/api/v1/auth/login', $credentials)->json('data.token');

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->withToken($first)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withToken($second)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_existing_mobile_token_stops_working_when_crew_is_deactivated(): void
    {
        $user = $this->completeCrewUser();
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'secret-password', 'device_name' => 'iPhone',
        ])->json('data.token');

        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_me_returns_dynamic_onboarding_status_and_logout_revokes_token(): void
    {
        $user = $this->completeCrewUser();
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'secret-password', 'device_name' => 'iPhone',
        ])->json('data.token');

        $user->crewProfile->update(['phone' => null]);

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.onboarding_complete', false)
            ->assertJsonFragment(['profile.phone']);
        $this->assertNotNull($user->refresh()->onboarding_completed_at);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function completeCrewUser(): User
    {
        $user = User::factory()->crew()->create([
            'password' => Hash::make('secret-password'), 'onboarding_completed_at' => now(),
        ]);
        CrewProfile::factory()->for($user)->create([
            'phone' => '0400 000 000', 'address_line_1' => '1 Test Street', 'suburb' => 'Perth',
            'state' => 'WA', 'postcode' => '6000', 'working_with_children_number' => 'WWC123',
            'working_with_children_expiry' => now()->addYear()->toDateString(),
        ]);

        return $user->refresh()->load('crewProfile');
    }
}
