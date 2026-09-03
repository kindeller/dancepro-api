<?php

namespace Tests\Feature\Notifications;

use App\Features\Auth\Support\TokenAbility;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Notifications\Models\MobilePushDevice;
use App\Features\Notifications\Services\MobilePushDelivery;
use App\Features\Scheduling\Models\CrewNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrewMobilePushApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_can_register_rotate_and_remove_an_encrypted_push_device(): void
    {
        $this->authenticatedCrew();
        $installation = (string) Str::uuid();
        $payload = [
            'installation_id' => $installation,
            'platform' => 'ios',
            'push_token' => 'private-apns-token',
            'environment' => 'development',
            'app_version' => '1.0.0',
            'device_name' => 'Test iPhone',
        ];

        $deviceId = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/push-devices', $payload)->assertOk()
            ->assertJsonMissing(['push_token' => 'private-apns-token'])
            ->json('data.id');
        $rawToken = DB::table('mobile_push_devices')->value('push_token');
        $this->assertNotSame('private-apns-token', $rawToken);

        $payload['push_token'] = 'rotated-token';
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/push-devices', $payload)->assertOk()->assertJsonPath('data.id', $deviceId);
        $this->assertDatabaseCount('mobile_push_devices', 1);
        $this->assertSame('rotated-token', MobilePushDevice::query()->firstOrFail()->push_token);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->deleteJson('/api/v1/push-devices/'.$deviceId)->assertOk();
        $this->assertDatabaseCount('mobile_push_devices', 0);
    }

    public function test_push_delivery_uses_a_generic_preview_and_disables_dead_tokens(): void
    {
        $user = $this->authenticatedCrew();
        $device = MobilePushDevice::query()->create([
            'user_id' => $user->id,
            'installation_id' => (string) Str::uuid(),
            'platform' => 'android',
            'push_token' => 'fcm-token',
            'push_token_hash' => hash('sha256', 'fcm-token'),
            'environment' => 'production',
            'app_version' => '1.0.0',
            'device_name' => 'Android',
            'last_registered_at' => now(),
        ]);
        $notification = CrewNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'invoice',
            'title' => 'Invoice updated',
            'message' => 'Private invoice detail that must not appear in push.',
            'destination_type' => 'invoice',
            'destination_id' => (string) Str::uuid(),
        ]);
        config(['mobile.push.gateway_url' => 'https://push.example.test/send', 'mobile.push.gateway_token' => 'gateway-secret']);
        Http::fake(['push.example.test/*' => Http::response([], 410)]);

        app(MobilePushDelivery::class)->deliver($notification->id);

        Http::assertSent(fn ($request): bool => $request['body'] === 'Open DancePro to view this update.'
            && ! str_contains($request->body(), 'Private invoice detail'));
        $this->assertNotNull($device->refresh()->disabled_at);
    }

    public function test_app_config_is_public_and_contains_version_controls(): void
    {
        config(['mobile.app' => [
            'minimum_supported_version' => '1.0.0',
            'latest_version' => '1.2.0',
            'maintenance' => false,
            'support_url' => 'https://example.test/support',
            'privacy_url' => 'https://example.test/privacy',
        ]]);

        $this->getJson('/api/v1/app-config')->assertOk()
            ->assertJsonPath('data.minimum_supported_version', '1.0.0')
            ->assertJsonPath('data.latest_version', '1.2.0')
            ->assertJsonPath('data.maintenance', false);
    }

    private function authenticatedCrew(): User
    {
        $user = User::factory()->crew()->create(['onboarding_completed_at' => now()]);
        CrewProfile::factory()->for($user)->create();
        Sanctum::actingAs($user, [TokenAbility::CrewMobile->value]);

        return $user;
    }
}
