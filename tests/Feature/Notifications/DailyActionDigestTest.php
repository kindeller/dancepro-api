<?php

namespace Tests\Feature\Notifications;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Notifications\Actions\CreateDailyActionDigests;
use App\Features\Notifications\Models\MobilePushDevice;
use App\Features\Notifications\Services\MobilePushDelivery;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyActionDigestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_batches_new_actionable_items_into_one_daily_push_without_repeating(): void
    {
        Carbon::setTestNow('2026-09-10 10:00:00');
        $user = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($user)->create();
        $role = CrewRole::factory()->create();

        $assignedEvent = SchedulingEvent::query()->create([
            'name' => 'Assigned Event',
            'event_type' => 'competition',
            'event_date' => today()->addWeek(),
        ]);
        $assignedShift = $assignedEvent->shifts()->create([
            'shift_date' => today()->addWeek(),
            'period' => 'morning',
        ]);
        $assignment = $assignedShift->assignments()->create([
            'crew_profile_id' => $profile->id,
            'crew_role_id' => $role->id,
            'status' => 'published',
            'acknowledgement_status' => 'not_acknowledged',
        ]);
        $availabilityEvent = SchedulingEvent::query()->create([
            'name' => 'Availability Event',
            'event_type' => 'competition',
            'event_date' => today()->addWeeks(2),
            'availability_status' => 'open',
            'availability_deadline' => today()->addWeek(),
        ]);
        $availabilityEvent->shifts()->create([
            'shift_date' => today()->addWeeks(2),
            'period' => 'morning',
        ]);

        $shiftNotification = CrewNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'shift_allocation',
            'title' => 'New shift allocation',
            'message' => 'Please acknowledge.',
            'destination_type' => 'assignment',
            'destination_id' => $assignment->uuid,
        ]);
        $availabilityNotification = CrewNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'availability_request',
            'title' => 'Availability requested',
            'message' => 'Please respond.',
            'destination_type' => 'availability',
            'destination_id' => $availabilityEvent->uuid,
        ]);

        $this->assertSame(1, app(CreateDailyActionDigests::class)->execute());
        $this->assertSame(0, app(CreateDailyActionDigests::class)->execute());
        $digest = CrewNotification::query()->where('type', 'action_digest')->firstOrFail();
        $this->assertSame('You have new items to action.', $digest->message);
        $this->assertNotNull($shiftNotification->refresh()->digest_notified_at);
        $this->assertNotNull($availabilityNotification->refresh()->digest_notified_at);
        $this->assertDatabaseCount('crew_notifications', 3);

        MobilePushDevice::query()->create([
            'user_id' => $user->id,
            'installation_id' => (string) Str::uuid(),
            'platform' => 'ios',
            'push_token' => 'push-token',
            'push_token_hash' => hash('sha256', 'push-token'),
            'environment' => 'production',
            'app_version' => '1.0.0',
            'device_name' => 'Test iPhone',
            'last_registered_at' => now(),
        ]);
        config(['mobile.push.gateway_url' => 'https://push.example.test/send', 'mobile.push.gateway_token' => 'secret']);
        Http::fake(['push.example.test/*' => Http::response([], 202)]);
        app(MobilePushDelivery::class)->deliver($digest->id);

        Http::assertSent(fn ($request): bool => $request['title'] === 'Crew Hub'
            && $request['body'] === 'You have new items to action.');
    }

    public function test_resolved_items_are_consumed_without_sending_a_digest(): void
    {
        $user = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($user)->create();
        $role = CrewRole::factory()->create();
        $event = SchedulingEvent::query()->create([
            'name' => 'Acknowledged Event',
            'event_type' => 'competition',
            'event_date' => today()->addWeek(),
        ]);
        $shift = $event->shifts()->create([
            'shift_date' => today()->addWeek(),
            'period' => 'morning',
        ]);
        $assignment = $shift->assignments()->create([
            'crew_profile_id' => $profile->id,
            'crew_role_id' => $role->id,
            'status' => 'published',
            'acknowledgement_status' => 'acknowledged',
        ]);
        $source = CrewNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'shift_allocation',
            'title' => 'New shift allocation',
            'message' => 'Please acknowledge.',
            'destination_type' => 'assignment',
            'destination_id' => $assignment->uuid,
        ]);

        $this->assertSame(0, app(CreateDailyActionDigests::class)->execute());
        $this->assertNotNull($source->refresh()->digest_notified_at);
        $this->assertDatabaseMissing('crew_notifications', ['type' => 'action_digest']);
    }

    public function test_cancelled_shift_items_are_consumed_without_sending_a_digest(): void
    {
        $user = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($user)->create();
        $role = CrewRole::factory()->create();
        $event = SchedulingEvent::query()->create([
            'name' => 'Cancelled Event', 'event_type' => 'competition',
            'event_date' => today()->addWeek(), 'cancelled_at' => now(),
        ]);
        $assignment = $event->shifts()->create([
            'shift_date' => today()->addWeek(), 'period' => 'morning',
        ])->assignments()->create([
            'crew_profile_id' => $profile->id, 'crew_role_id' => $role->id,
            'status' => 'published', 'acknowledgement_status' => 'not_acknowledged',
        ]);
        $source = CrewNotification::query()->create([
            'user_id' => $user->id, 'type' => 'shift_changed', 'title' => 'Shift changed',
            'message' => 'Please review.', 'destination_type' => 'assignment',
            'destination_id' => $assignment->uuid,
        ]);

        $this->assertSame(0, app(CreateDailyActionDigests::class)->execute());
        $this->assertNotNull($source->refresh()->digest_notified_at);
        $this->assertDatabaseMissing('crew_notifications', ['type' => 'action_digest']);
    }
}
