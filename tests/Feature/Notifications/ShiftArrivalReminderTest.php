<?php

namespace Tests\Feature\Notifications;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Notifications\Actions\CreateShiftArrivalReminders;
use App\Features\Notifications\Models\MobilePushDevice;
use App\Features\Notifications\Services\MobilePushDelivery;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShiftArrivalReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_creates_and_delivers_one_reminder_an_hour_before_arrival(): void
    {
        Carbon::setTestNow('2026-09-10 07:00:00');
        $user = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($user)->create();
        $role = CrewRole::factory()->create();
        $venue = Venue::query()->create(['name' => 'Perth Convention Centre']);
        $event = SchedulingEvent::query()->create([
            'venue_id' => $venue->id,
            'name' => 'State Championships',
            'event_type' => 'competition',
            'event_date' => '2026-09-10',
        ]);
        $shift = $event->shifts()->create([
            'shift_date' => '2026-09-10',
            'period' => 'morning',
            'posted_arrival_at' => '2026-09-10 08:00:00',
            'starts_at' => '2026-09-10 08:30:00',
        ]);
        $assignment = $shift->assignments()->create([
            'crew_profile_id' => $profile->id,
            'crew_role_id' => $role->id,
            'status' => 'published',
        ]);
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

        $this->assertSame(1, app(CreateShiftArrivalReminders::class)->execute());
        $this->assertSame(0, app(CreateShiftArrivalReminders::class)->execute());

        $this->assertDatabaseHas('crew_notifications', [
            'user_id' => $user->id,
            'type' => 'shift_arrival_reminder',
            'destination_type' => 'assignment',
            'destination_id' => $assignment->uuid,
            'message' => 'Your State Championships shift starts in 1 hour at Perth Convention Centre.',
        ]);

        $notification = CrewNotification::query()
            ->where('type', 'shift_arrival_reminder')
            ->firstOrFail();
        app(MobilePushDelivery::class)->deliver($notification->id);

        Http::assertSent(fn ($request): bool => $request['title'] === 'Shift starts in 1 hour'
            && $request['body'] === 'Your State Championships shift starts in 1 hour at Perth Convention Centre.'
            && $request['destination']['type'] === 'assignment'
            && $request['destination']['id'] === $assignment->uuid);
    }

    public function test_it_does_not_remind_crew_about_a_cancelled_event(): void
    {
        Carbon::setTestNow('2026-09-10 07:00:00');
        $user = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($user)->create();
        $role = CrewRole::factory()->create();
        $event = SchedulingEvent::query()->create([
            'name' => 'Cancelled Event', 'event_type' => 'competition',
            'event_date' => '2026-09-10', 'cancelled_at' => now(),
        ]);
        $shift = $event->shifts()->create([
            'shift_date' => '2026-09-10', 'period' => 'morning',
            'posted_arrival_at' => '2026-09-10 08:00:00',
        ]);
        $shift->assignments()->create([
            'crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published',
        ]);

        $this->assertSame(0, app(CreateShiftArrivalReminders::class)->execute());
        $this->assertDatabaseMissing('crew_notifications', ['type' => 'shift_arrival_reminder']);
    }
}
