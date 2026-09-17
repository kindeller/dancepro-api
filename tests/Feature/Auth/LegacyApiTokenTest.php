<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Support\ApiTokenAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_wildcard_token_still_serves_competition_and_download_routes(): void
    {
        config(['sanctum.expiration' => null]);
        Storage::fake('s3_competitions');
        $user = User::factory()->staff()->create();
        $token = $user->createToken('existing client', ['*']);
        $token->accessToken->forceFill(['created_at' => now()->subDays(90)])->save();

        $this->withToken($token->plainTextToken)->getJson('/api/competitions/objects')->assertOk();
        $this->getJson('/api/download-links')->assertOk();
        $this->postJson('/api/download-links', ['disk' => 's3_competitions', 'keys' => ['concert/video.mp4'], 'days' => 1])->assertCreated();
        $this->getJson('/api/staff/concerts')->assertForbidden();
        $this->getJson('/api/staff/studios')->assertForbidden();
    }

    public function test_expired_legacy_token_is_not_resurrected(): void
    {
        $user = User::factory()->staff()->create();
        $token = $user->createToken('expired client', ['*'], now()->subMinute());

        $this->withToken($token->plainTextToken)->getJson('/api/download-links')->assertUnauthorized();
    }

    public function test_new_staff_token_expiry_is_enforced_without_a_global_expiration(): void
    {
        config(['sanctum.expiration' => null]);
        $user = User::factory()->staff()->create();
        $token = $user->createToken('new client', ApiTokenAbility::staffAbilities(), now()->subMinute());

        $this->withToken($token->plainTextToken)->getJson('/api/download-links')->assertUnauthorized();
    }

    public function test_explicit_global_expiration_policy_is_preserved(): void
    {
        config(['sanctum.expiration' => 60]);
        $user = User::factory()->staff()->create();
        $token = $user->createToken('old client', ['*']);
        $token->accessToken->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->withToken($token->plainTextToken)->getJson('/api/download-links')->assertUnauthorized();
    }

    public function test_wildcard_token_does_not_bypass_staff_account_checks(): void
    {
        $user = User::factory()->customer()->create();
        $token = $user->createToken('customer client', ['*']);

        $this->withToken($token->plainTextToken)->getJson('/api/download-links')->assertForbidden();
        $this->getJson('/api/competitions/objects')->assertForbidden();
    }

    public function test_inactive_staff_and_unrelated_abilities_remain_forbidden(): void
    {
        $user = User::factory()->staff()->inactive()->create();
        $token = $user->createToken('inactive client', ['*']);
        $this->withToken($token->plainTextToken)->getJson('/api/download-links')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $active = User::factory()->staff()->create();
        $token = $active->createToken('media client', ['concert-media:read']);
        $this->withToken($token->plainTextToken)->getJson('/api/download-links')->assertForbidden();
    }
}
