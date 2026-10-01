<?php

namespace Tests\Feature\Customers;

use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Features\Tracking\Services\ConcertTracker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioMessageReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_inbox_only_counts_unread_incoming_messages_and_reading_is_individual(): void
    {
        [$user, $studio, $message, $delivery] = $this->fixture();
        $this->message($studio, ['studio_read_at' => now()]);
        $this->message($studio, ['direction' => 'to_dancepro']);
        $queued = $delivery->replicate(['uuid']);
        $queued->status = 'queued';
        $queued->save();
        $this->message(Studio::factory()->create());
        $this->actingAs($user);
        foreach (['events', 'messages'] as $tab) {
            $this->get(route('customer.studio-communications.index', [$studio, 'tab' => $tab]))
                ->assertOk()->assertViewHas('hub', fn ($hub) => $hub['messageCount'] === 2);
        }
        $this->get(route('customer.studio-invoices.index', $studio))->assertOk();
        $this->assertNull($message->fresh()->studio_read_at);
        $this->assertNull($delivery->fresh()->studio_read_at);
        $page = $this->get(route('customer.studio-communications.index', [$studio, 'tab' => 'messages']))->assertOk();
        $page->assertSeeInOrder(['Send a message</span>', '>Studio messages</h2>'], false)
            ->assertSee('Mark as read')->assertSee('unread messages');
        $this->assertDoesNotMatchRegularExpression('/<details[^>]*\bopen\b/', $page->getContent());
        $url = route('customer.studio-communications.read', [$studio, 'message', $message->uuid]);
        $this->postJson($url)->assertOk()->assertJsonPath('data.counts.studio', 1);
        $readAt = $message->fresh()->studio_read_at;
        $this->travel(1)->minutes();
        $this->postJson($url)->assertOk()->assertJsonPath('data.counts.studio', 1);
        $this->assertTrue($readAt->equalTo($message->fresh()->studio_read_at));
        $this->postJson(route('customer.studio-communications.read', [$studio, 'email', $delivery->uuid]))
            ->assertOk()->assertJsonPath('data.counts.studio', 0);
        $this->assertNotNull($delivery->fresh()->studio_read_at);
        $this->assertSame(0, $delivery->fresh()->open_count);
        $this->assertSame(0, app(StudioCommunicationHistory::class)->forUser($user, $studio)['hub']['messageCount']);
    }

    public function test_read_endpoint_rejects_other_studios_outgoing_unsent_and_revoked_access(): void
    {
        [$user, $studio, $message, $delivery] = $this->fixture();
        $other = $this->message(Studio::factory()->create());
        $outgoing = $this->message($studio, ['direction' => 'to_dancepro']);
        $delivery->update(['status' => 'failed']);
        $this->actingAs($user);
        foreach ([['message', $other->uuid], ['message', $outgoing->uuid], ['email', $delivery->uuid], ['invalid', $message->uuid]] as [$source, $uuid]) {
            $this->postJson(route('customer.studio-communications.read', [$studio, $source, $uuid]))->assertNotFound();
        }
        $url = route('customer.studio-communications.read', [$studio, 'message', $message->uuid]);
        $this->from(route('customer.studio-communications.index', $studio))->post($url)->assertRedirect();
        StudioAccountAccess::where('studio_id', $studio->id)->update(['status' => 'revoked']);
        $this->postJson($url)->assertForbidden();
        $this->assertNull($other->fresh()->studio_read_at);
        $this->assertNull($outgoing->fresh()->studio_read_at);
        $this->assertNull($delivery->fresh()->studio_read_at);
    }

    private function fixture(): array
    {
        $user = User::factory()->customer()->create();
        $studio = Studio::factory()->create();
        StudioAccountAccess::create(['studio_id' => $studio->id, 'user_id' => $user->id, 'email' => $user->email, 'status' => 'active', 'role' => 'director']);
        $event = SchedulingEvent::create(['studio_id' => $studio->id, 'name' => 'Summer concert', 'event_type' => 'concert', 'event_date' => today()->addMonth()]);
        $task = app(ConcertTracker::class)->detail($event, 'concert_week_email')['task'];
        $delivery = ConcertTrackerEmailDelivery::create([
            'scheduling_event_id' => $event->id, 'concert_tracker_task_id' => $task->id,
            'concert_tracker_email_template_id' => ConcertTrackerEmailTemplate::where('task_key', 'concert_week_email')->firstOrFail()->id,
            'recipient_email' => $user->email, 'subject' => 'Your concert reminder', 'body' => 'Email message body', 'status' => 'sent', 'sent_at' => now(),
        ]);

        return [$user, $studio, $this->message($studio), $delivery];
    }

    private function message(Studio $studio, array $attributes = []): StudioConversationMessage
    {
        return StudioConversationMessage::create($attributes + ['studio_id' => $studio->id, 'direction' => 'from_dancepro', 'channel' => 'web', 'subject' => 'Booking update', 'body' => 'Message body', 'sent_at' => now()]);
    }
}
