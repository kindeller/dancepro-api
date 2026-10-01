<?php

namespace Tests\Feature\Tracking;

use App\Features\Bookings\Actions\ApproveConcertBooking;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Concerts\Models\Concert;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Media\Models\MediaCollection;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeSource;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Actions\SaveStudioGalleryCode;
use App\Features\Studios\Actions\StoreStudioEventFiles;
use App\Features\Studios\Models\ConcertEventRoleSeats;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Studios\Models\StudioBookingChangeRequest;
use App\Features\Studios\Models\StudioContact;
use App\Features\Studios\Models\StudioEventAcknowledgement;
use App\Features\Studios\Models\StudioEventFile;
use App\Features\Studios\Notifications\StudioAccountInvitation;
use App\Features\Tracking\Actions\ConsolidateConcertEmailTasks;
use App\Features\Tracking\Actions\ConsolidateConcertWeekTasks;
use App\Features\Tracking\Actions\CreateConcertTrackerAlerts;
use App\Features\Tracking\Actions\UpdateConcertTrackerTask;
use App\Features\Tracking\Jobs\SendConcertTrackerEmail;
use App\Features\Tracking\Mail\ConcertTrackerMessage;
use App\Features\Tracking\Mail\StudioConversationReply;
use App\Features\Tracking\Models\ConcertTrackerAlert;
use App\Features\Tracking\Models\ConcertTrackerAutomationRule;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Models\ConcertTrackerInboundEmail;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Models\StudioConversationAttachment;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Tracking\Services\ConcertTrackerAutomation;
use App\Features\Tracking\Services\EmailContentFormatter;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use App\Features\Tracking\Support\SeatsFamiliesEmailContent;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ConcertTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_collapsed_studio_events_show_overdue_actions_and_clear_them_when_completed(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
        [, $event, , $booking] = $this->fixture();
        ConcertBookingItem::where('concert_booking_id', $booking->id)->update(['approval_status' => 'approved']);
        ConcertTrackerTask::create(['scope_type' => 'booking', 'scope_id' => $booking->id, 'task_key' => 'final_numbers_received', 'status' => 'not_started', 'due_date_override' => '2026-10-14']);
        $director = User::factory()->customer()->create();
        StudioAccountAccess::create(['studio_id' => $event->studio_id, 'user_id' => $director->id,
            'email' => $director->email, 'role' => 'director', 'status' => 'active']);
        $hub = fn () => app(StudioCommunicationHistory::class)->forUser($director, $event->studio)['hub'];
        $card = fn () => $hub()['events']->firstWhere('event.id', $event->id);
        $this->assertSame(['Junior Concert', 'Senior Concert'], $hub()['events']->pluck('title')->all());
        $this->assertSame(5, $card()['actionCount']);
        $this->assertSame(4, $card()['overdueCount']);
        $this->assertSame(8, $hub()['overdueCount']);
        $this->assertSame(['Confirm event details', 'Confirm final family numbers', 'Enter reserved seats', 'Upload programme'], $card()['overdueTasks']->all());
        $response = $this->actingAs($director)->get(route('customer.studio-communications.index', $event->studio))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $summary = $xpath->query('//details[contains(@class,"hub-card")]/summary[contains(.,"Junior Concert")]')->item(0);
        $this->assertNotNull($summary);
        $this->assertFalse($summary->parentNode->hasAttribute('open'));
        $this->assertStringContainsString('4 overdue', $summary->textContent);
        $this->assertStringContainsString('Upload programme', $summary->textContent);
        $expanded = $this->get(route('customer.studio-communications.index', [$event->studio, 'actions' => 1]))->assertOk();
        @$dom->loadHTML($expanded->getContent());
        $this->assertSame(2, (new \DOMXPath($dom))->query('//details[contains(@class,"hub-card")][@open]')->length);

        foreach (['programme', 'studio_video'] as $kind) {
            StudioEventFile::create(['scheduling_event_id' => $event->id, 'kind' => $kind, 'status' => 'submitted',
                'is_current' => true, 'disk' => 'local', 'storage_key' => 'example/'.$kind,
                'original_name' => $kind, 'mime_type' => 'application/octet-stream', 'size_bytes' => 100,
                'uploaded_by_user_id' => $director->id, 'uploaded_at' => now()]);
        }
        $this->assertSame(3, $card()['actionCount']);
        $this->assertSame(3, $card()['overdueCount']);
        foreach ($card()['seatedRoles'] as $requirement) {
            $seatCount = str_contains($requirement->crewRole->code, 'videographer') ? 5 : 2;
            ConcertEventRoleSeats::create(['scheduling_event_id' => $event->id, 'crew_role_id' => $requirement->crew_role_id,
                'submitted_by_user_id' => $director->id, 'submitted_at' => now(),
                'seats' => collect(range(1, $seatCount))->map(fn ($seat) => ['row' => 'A', 'seat' => (string) $seat])->all()]);
        }
        $this->post(route('customer.studio-events.requirements.store', [$event->studio, $event]), ['group' => 'event_details', 'requirements' => ['event_details']])->assertSessionHasNoErrors();
        $booking->update(['final_family_count' => 90, 'family_count_confirmed_at' => now()]);
        $this->assertSame(0, $card()['actionCount'], $card()['checklist']->toJson());
        $this->assertSame(0, $card()['overdueCount']);
        $this->assertSame(3, $hub()['overdueCount']);
        $this->get(route('customer.studio-communications.index', $event->studio))->assertOk()->assertSee('Your actions complete');
    }

    public function test_email_formatting_is_rendered_safely_in_the_branded_layout(): void
    {
        $formatter = app(EmailContentFormatter::class);
        $html = $formatter->html("## Event details\n\n**Arrival:** 5:30 pm\n\n- Programme\n- Sound feed\n\n> Please reply by Friday.\n\n<script>alert('x')</script>");

        $this->assertStringContainsString('<h2>Event details</h2>', $html);
        $this->assertStringContainsString('<strong>Arrival:</strong>', $html);
        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringNotContainsString('<script>', $html);

        $email = new ConcertTrackerMessage('Formatted email', "## Event details\n\n**Arrival:** 5:30 pm", null, 'https://example.test/account');
        $rendered = $email->render();
        $this->assertStringContainsString('email-content', $rendered);
        $this->assertStringContainsString('<h2>Event details</h2>', $rendered);
        $this->assertStringContainsString('Log into your DancePro account', $rendered);
    }

    public function test_tracker_shows_all_planned_phases_and_merges_shared_booking_tasks(): void
    {
        [$staff, $first, $second] = $this->fixture();

        $this->actingAs($staff)->get(route('admin.concert-tracker.index', [
            'view' => 'studio', 'year' => 2026, 'phase' => 'booking',
        ]))->assertOk()
            ->assertSee('Booking &amp; services', false)
            ->assertSee('Seats &amp; families numbers', false)
            ->assertSee('rowspan="2"', false)
            ->assertSeeInOrder([$first->name, $second->name]);

        foreach (['preparation' => 'Post-concert email', 'editing' => 'Director corrections', 'completion' => 'Invoice & payment'] as $phase => $task) {
            $this->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => $phase]))
                ->assertOk()->assertSee($task);
        }

        $this->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => 'all']))
            ->assertOk()
            ->assertSee('Approval status follows the unified booking record; email delivery is logged alongside it.')
            ->assertDontSee('Video holds/missing media');
    }

    public function test_tracker_opens_on_all_tasks_by_default(): void
    {
        [$staff] = $this->fixture();

        $this->actingAs($staff)->get(route('admin.concert-tracker.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee('All concert tasks')
            ->assertSee('phase-all', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_summary_counts_every_event_shared_tasks_once_and_pending_events_in_both_views(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01'));
        [$staff, $first, $second, $booking] = $this->fixture();
        $second->update(['event_date' => '2026-11-21']);
        $second->concertBookingItem->update(['event_date' => '2026-11-21']);
        $overdue = ConcertTrackerTask::create([
            'scope_type' => 'event', 'scope_id' => $first->id, 'task_key' => 'concert_week_email',
            'status' => 'waiting', 'due_date_override' => '2026-08-31',
        ]);
        ConcertTrackerTask::create([
            'scope_type' => 'event', 'scope_id' => $second->id, 'task_key' => 'concert_week_email',
            'status' => 'waiting', 'due_date_override' => '2026-09-03',
        ]);
        $pending = $booking->replicate(['uuid']);
        $pending->fill(['status' => 'pending', 'studio_id' => $first->studio_id])->save();
        foreach (['2026-10-18', '2026-10-19'] as $date) {
            $item = $first->concertBookingItem->replicate(['uuid']);
            $item->fill(['concert_booking_id' => $pending->id, 'scheduling_event_id' => null, 'approval_status' => 'pending', 'event_date' => $date])->save();
        }
        $tracker = app(ConcertTracker::class);
        foreach (['date', 'studio'] as $view) {
            $page = $tracker->page($view, 2026, 'booking');
            $this->assertSame(['attention' => 3, 'near_due' => 1, 'complete' => 1, 'open' => 7], $page['summary']);
            $this->assertCount(4, $page['rows']);
            $this->assertCount(3, $tracker->page($view, 2026, 'booking', true)['rows']);
        }
        ConcertTrackerAlert::create(['user_id' => $staff->id, 'type' => 'test', 'dedupe_key' => 'old-alert', 'title' => 'Old alert', 'body' => 'Independent notification', 'url' => '/admin/concert-tracker']);
        $response = $this->actingAs($staff)->get(route('admin.concert-tracker.index', ['year' => 2026]))->assertOk();
        $doc = new \DOMDocument;
        @$doc->loadHTML($response->getContent());
        $xpath = new \DOMXPath($doc);
        $badge = $xpath->query('//span[@class="nav-notification"][contains(@title,"Tasks and pending events")]')->item(0);
        $this->assertSame((string) $response->viewData('summary')['attention'], $badge->textContent);
        $this->assertSame(1, $xpath->query('//*[@id="tracker-sheet"]//nav[@aria-label="Tracker view"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="tracker-sheet"]//nav[@aria-label="Tracker phase"]')->length);
        $response->assertDontSee('Automatic tasks read their source records')->assertDontSee('pending events awaiting booking confirmation');

        $overdue->update(['status' => 'complete', 'completed_at' => now()]);
        $this->assertSame(['attention' => 2, 'near_due' => 1, 'complete' => 2, 'open' => 6], $tracker->page('date', 2026, 'booking')['summary']);
        $pending->update(['status' => 'cancelled']);
        $this->assertSame(0, $tracker->page('date', 2026, 'booking')['summary']['attention']);
        $this->assertSame(0, $tracker->page('date', 2027, 'all')['summary']['open']);
    }

    public function test_programme_cell_opens_upload_directly_and_replaces_due_date_with_upload_audit(): void
    {
        [$staff, $event] = $this->fixture();
        $programme = Programme::create(['scheduling_event_id' => $event->id, 'updated_by_user_id' => $staff->id]);

        $empty = app(ConcertTracker::class)->page('date', 2026, 'all')['rows']->firstWhere(fn (array $row): bool => $row['event']->is($event))['tasks']['programme_prepared'];
        $this->assertSame(route('admin.concert-bookings.show', [$event->concertBookingItem->booking, 'tab' => 'programme', 'event' => $event->uuid]), $empty['detailUrl']);
        $this->assertNotNull($empty['dueDate']);
        $this->actingAs($staff)->get($empty['detailUrl'])->assertOk()
            ->assertSee('open ><summary>Upload source PDF', false);

        $source = ProgrammeSource::create([
            'programme_id' => $programme->id,
            'disk' => 'local',
            'path' => 'test/programme.pdf',
            'name' => 'Junior programme.pdf',
            'created_by_user_id' => $staff->id,
        ]);
        $programme->update(['programme_source_id' => $source->id]);

        $received = app(ConcertTracker::class)->page('date', 2026, 'all')['rows']->firstWhere(fn (array $row): bool => $row['event']->is($event))['tasks']['programme_prepared'];
        $this->assertSame('Confirm', $received['label']);
        $this->assertNull($received['dueDate']);
        $this->assertStringContainsString('Uploaded', $received['activity']);
        $this->assertStringContainsString($staff->name, $received['activity']);
        $this->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => 'all']))
            ->assertOk()->assertSee('Confirm')->assertSee('Uploaded')->assertSee($staff->name);
    }

    public function test_manual_task_supports_status_assignment_due_override_and_notes(): void
    {
        [$staff, $event] = $this->fixture();

        $this->actingAs($staff)->patch(route('admin.concert-tracker.tasks.update', [$event, 'final_numbers_received']), [
            'status' => 'in_progress',
            'assignee_user_id' => $staff->id,
            'due_date_override' => '2026-10-01',
            'follow_up_date' => '2026-10-02',
            'note' => 'Waiting for the studio to confirm the final family count.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $task = ConcertTrackerTask::where('task_key', 'final_numbers_received')->firstOrFail();
        $this->assertSame('booking', $task->scope_type);
        $this->assertSame('in_progress', $task->status);
        $this->assertSame($staff->id, $task->assignee_user_id);
        $this->assertDatabaseHas('concert_tracker_task_notes', [
            'concert_tracker_task_id' => $task->id,
            'body' => 'Waiting for the studio to confirm the final family count.',
        ]);
        $this->assertDatabaseHas('concert_tracker_task_changes', ['concert_tracker_task_id' => $task->id, 'action' => 'updated']);
    }

    public function test_tracker_email_template_previews_and_queues_a_frozen_message(): void
    {
        Queue::fake();
        [$staff, $event] = $this->fixture();

        $this->actingAs($staff)->get(route('admin.concert-tracker.tasks.show', [$event, 'concert_week_email']))
            ->assertOk()->assertSee('Aurora Dance')->assertSee('Concert Week requirements');
        $this->post(route('admin.concert-tracker.tasks.email.send', [$event, 'concert_week_email']), [
            'recipient_name' => 'Alex Director',
            'recipient_email' => 'alex@example.test',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $delivery = ConcertTrackerEmailDelivery::firstOrFail();
        $this->assertStringContainsString($event->studio->name, $delivery->subject);
        $this->assertSame('queued', $delivery->status);
        Queue::assertPushed(SendConcertTrackerEmail::class, fn ($job) => $job->deliveryId === $delivery->id);
    }

    public function test_one_off_edits_are_frozen_without_changing_templates_and_defaults_still_work(): void
    {
        Queue::fake();
        Mail::fake();
        [$staff, $event] = $this->fixture();
        $this->actingAs($staff);
        foreach (['confirmation_information_email', 'final_numbers_email', 'concert_week_email', 'post_concert_email'] as $key) {
            $data = app(ConcertTracker::class)->detail($event, $key);
            $original = $data['template']->only('subject', 'body');
            $this->post(route('admin.concert-tracker.tasks.email.send', [$event, $key]), [
                'recipient_email' => 'alex@example.test',
                'email_subject' => 'One-off '.$key,
                'email_body' => "Hi Alex,\n\n**Just for this send.**",
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])->assertSessionHasNoErrors();
            $delivery = ConcertTrackerEmailDelivery::latest('id')->firstOrFail();
            $this->assertSame('One-off '.$key, $delivery->subject);
            $this->assertSame("Hi Alex,\n\n**Just for this send.**", $delivery->body);
            $this->assertSame($original, $data['template']->fresh()->only('subject', 'body'));
            $data['template']->update(['body' => 'Changed future template']);
            $this->travelTo($delivery->scheduled_at->copy()->addMinute());
            (new SendConcertTrackerEmail($delivery->id))->handle();
            $this->assertSame('sent', $delivery->fresh()->status);
            Mail::assertSent(ConcertTrackerMessage::class, fn ($mail) => $mail->messageSubject === 'One-off '.$key && $mail->messageBody === $delivery->body);
        }
    }

    public function test_one_off_email_fields_reject_empty_messages_and_subject_header_injection(): void
    {
        Queue::fake();
        [$staff, $event] = $this->fixture();
        $url = route('admin.concert-tracker.tasks.email.send', [$event, 'concert_week_email']);
        $this->actingAs($staff)->post($url, ['recipient_email' => 'alex@example.test', 'email_subject' => "Subject\r\nBcc: other@example.test", 'email_body' => ' '])
            ->assertSessionHasErrors(['email_subject', 'email_body']);
        $this->assertDatabaseCount('concert_tracker_email_deliveries', 0);
        Queue::assertNothingPushed();
    }

    public function test_successful_tracker_email_completes_the_manual_task(): void
    {
        Mail::fake();
        [$staff, $event] = $this->fixture();
        Queue::fake();
        $this->actingAs($staff)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'concert_week_email']), [
            'recipient_email' => 'alex@example.test',
        ])->assertRedirect();
        $delivery = ConcertTrackerEmailDelivery::firstOrFail();

        (new SendConcertTrackerEmail($delivery->id))->handle();

        Mail::assertSent(ConcertTrackerMessage::class);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame('complete', $delivery->task->fresh()->status);
        $this->assertDatabaseHas('concert_tracker_task_changes', ['action' => 'email_sent']);
        $this->get(route('concert-tracker.email-open', $delivery))->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get(route('concert-tracker.email-open', $delivery))->assertOk();
        $this->assertSame(2, $delivery->fresh()->open_count);
    }

    public function test_studio_contact_account_can_read_studio_email_history(): void
    {
        Storage::fake('local');
        Mail::fake();
        Queue::fake();
        [$staff, $event] = $this->fixture();
        $studio = $event->studio;
        $customer = User::factory()->customer()->create(['email' => 'director@example.test']);
        StudioContact::create(['studio_id' => $studio->id, 'name' => 'Studio Director', 'role' => 'Director', 'emails' => [$customer->email]]);
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);

        $this->actingAs($staff)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'concert_week_email']), [
            'recipient_name' => 'Studio Director', 'recipient_email' => $customer->email,
        ])->assertRedirect();
        $delivery = ConcertTrackerEmailDelivery::firstOrFail();
        (new SendConcertTrackerEmail($delivery->id))->handle();
        ConcertTrackerInboundEmail::create([
            'scheduling_event_id' => $event->id, 'reply_to_delivery_id' => $delivery->id,
            'sender_name' => 'Studio Director', 'sender_email' => $customer->email,
            'recipient_email' => 'admin@example.test', 'subject' => 'Re: '.$delivery->subject,
            'body' => 'The studio details are confirmed.', 'received_at' => now(),
        ]);

        Mail::assertSent(ConcertTrackerMessage::class, fn (ConcertTrackerMessage $mail): bool => str_contains((string) $mail->accountUrl, $studio->uuid));
        $this->actingAs($customer)->get(route('customer.studio-communications.index', [$studio, 'tab' => 'messages']))
            ->assertOk()->assertSee($delivery->subject)->assertSee('The studio details are confirmed.')
            ->assertSee('From DancePro')->assertSee('Sent to DancePro');
        $this->post(route('customer.studio-communications.store', $studio), [
            'subject' => 'Updated arrival details', 'body' => 'Please arrive through the stage door.',
            'attachment' => UploadedFile::fake()->create('stage-door-map.pdf', 120, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('studio_conversation_messages', [
            'studio_id' => $studio->id, 'direction' => 'to_dancepro', 'subject' => 'Updated arrival details',
        ]);

        $this->actingAs($staff)->get(route('admin.messages.index', ['channel' => 'studio']))
            ->assertOk()->assertSee($studio->name)->assertSee('Updated arrival details');
        $this->get(route('admin.messages.index', ['channel' => 'studio', 'thread' => $studio->uuid]))
            ->assertOk()->assertSee('Please arrive through the stage door.')->assertSee('stage-door-map.pdf');
        $attachment = StudioConversationAttachment::firstOrFail();
        $this->get(route('admin.concert-tracker.studio-communications.attachments.show', $attachment))->assertDownload('stage-door-map.pdf');
        $this->patch(route('admin.concert-tracker.studio-communications.assign', $studio), ['assigned_user_id' => $staff->id])
            ->assertRedirect()->assertSessionHas('status', 'Studio conversation owner updated.');
        $this->assertDatabaseHas('studio_conversation_threads', ['studio_id' => $studio->id, 'assigned_user_id' => $staff->id]);
        $this->post(route('admin.concert-tracker.studio-communications.reply', $studio), [
            'recipient_email' => $customer->email, 'subject' => 'Re: Updated arrival details',
            'body' => 'Thank you, we have updated the crew notes.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertSent(StudioConversationReply::class, fn (StudioConversationReply $mail): bool => $mail->hasTo($customer->email));
        $this->assertDatabaseHas('studio_conversation_messages', [
            'studio_id' => $studio->id, 'direction' => 'from_dancepro', 'subject' => 'Re: Updated arrival details',
        ]);

        $unrelated = User::factory()->customer()->create();
        CustomerStudio::create(['user_id' => $unrelated->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);
        $this->actingAs($unrelated)->get(route('customer.studio-communications.index', $studio))->assertForbidden();
    }

    public function test_management_can_invite_link_and_revoke_studio_account_access(): void
    {
        Notification::fake();
        [$staff, $event] = $this->fixture();
        $studio = $event->studio;
        $customer = User::factory()->customer()->create(['email' => 'owner@example.test']);

        $this->actingAs($staff)->post(route('admin.studios.account-access.store', $studio), [
            'email' => $customer->email, 'role' => 'owner',
        ])->assertRedirect()->assertSessionHas('status', 'Existing account linked to the studio.');
        $access = StudioAccountAccess::firstOrFail();
        $this->assertSame('active', $access->status);
        $this->assertSame($customer->id, $access->user_id);
        $this->assertDatabaseHas('customer_studios', ['user_id' => $customer->id, 'studio_id' => $studio->id]);
        Notification::assertSentOnDemand(StudioAccountInvitation::class);
        $this->actingAs($customer)->get(route('customer.studio-hub'))
            ->assertRedirect(route('customer.studio-communications.index', $studio));

        $this->actingAs($staff)->delete(route('admin.studios.account-access.revoke', [$studio, $access]))
            ->assertRedirect()->assertSessionHas('status', 'Studio account access revoked. Customer media and purchases are unchanged.');
        $this->assertSame('revoked', $access->fresh()->status);
        $this->actingAs($customer)->get(route('customer.studio-communications.index', $studio))->assertForbidden();

        $this->actingAs($staff)->post(route('admin.studios.account-access.store', $studio), [
            'email' => 'new-director@example.test', 'role' => 'director',
        ])->assertRedirect()->assertSessionHas('status', 'Studio account invitation sent.');
        $this->assertDatabaseHas('studio_account_accesses', ['email' => 'new-director@example.test', 'status' => 'pending']);
    }

    public function test_studio_account_can_save_role_seats_for_each_concert_event(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        [, $event, , $booking] = $this->fixture();
        $booking->update(['wants_concert_photography' => true]);
        $customer = User::factory()->customer()->create(['email' => 'director@example.test']);
        StudioAccountAccess::create([
            'studio_id' => $event->studio_id, 'user_id' => $customer->id, 'email' => $customer->email,
            'role' => 'director', 'status' => 'active', 'activated_at' => now(),
        ]);
        $photo = CrewRole::query()->where('code', 'concert-photographer-p1')->firstOrFail();
        $video = CrewRole::query()->where('code', 'concert-videographer')->firstOrFail();
        $event->roleRequirements()->createMany([
            ['crew_role_id' => $photo->id, 'quantity' => 1],
            ['crew_role_id' => $video->id, 'quantity' => 1],
        ]);

        $this->actingAs($customer)->get(route('customer.studio-communications.index', [$event->studio, 'tab' => 'files']))
            ->assertOk()->assertSee('Concert events')->assertSee('Photographer P1')->assertSee('Videographer')
            ->assertSeeInOrder(['Tue 20 Oct 2026', '6:00 pm', '8:00 pm', 'Theatre'])
            ->assertSee('J16-20 or J16, 18');
        $this->post(route('customer.studio-events.seats.store', [$event->studio, $event]), [
            'roles' => [
                'concert-photographer-p1' => [['row' => 'H', 'seat' => '12']],
                'concert-videographer' => [
                    ['row' => 'J', 'seat' => '20'], ['row' => 'J', 'seat' => '21'],
                    ['row' => 'J', 'seat' => '22'], ['row' => 'J', 'seat' => '23'],
                    ['row' => 'J', 'seat' => '24'],
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([['row' => 'H', 'seat' => '12']], ConcertEventRoleSeats::where('crew_role_id', $photo->id)->firstOrFail()->seats);
        $this->assertCount(5, ConcertEventRoleSeats::where('crew_role_id', $video->id)->firstOrFail()->seats);

        $this->post(route('customer.studio-events.seats.store', [$event->studio, $event]), [
            'roles' => [
                'concert-photographer-p1' => ['seating' => 'Row H: 12, 13'],
                'concert-videographer' => ['seating' => "Row J: 20, 21, 22\nRow K: 1, 2"],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([
            ['row' => 'J', 'seat' => '20'], ['row' => 'J', 'seat' => '21'], ['row' => 'J', 'seat' => '22'],
            ['row' => 'K', 'seat' => '1'], ['row' => 'K', 'seat' => '2'],
        ], ConcertEventRoleSeats::where('crew_role_id', $video->id)->firstOrFail()->seats);

        foreach (['j16-20', 'J16–20', 'Row J: 16-20', 'J16-J20', 'J16-18, 19, 20'] as $input) {
            $this->post(route('customer.studio-events.seats.store', [$event->studio, $event]), [
                'roles' => ['concert-videographer' => ['seating' => $input]],
            ])->assertSessionHasNoErrors();
            $this->assertSame(collect(range(16, 20))->map(fn ($seat) => ['row' => 'J', 'seat' => (string) $seat])->all(), ConcertEventRoleSeats::where('crew_role_id', $video->id)->firstOrFail()->seats);
        }
        $this->post(route('customer.studio-events.seats.store', [$event->studio, $event]), [
            'roles' => ['concert-photographer-p1' => ['seating' => 'j16, 18']],
        ])->assertSessionHasNoErrors();
        $this->assertSame([['row' => 'J', 'seat' => '16'], ['row' => 'J', 'seat' => '18']], ConcertEventRoleSeats::where('crew_role_id', $photo->id)->firstOrFail()->seats);
        foreach (['J20-16', 'J1-999999999', 'J16-K20', 'J16-', 'J16-18, 16-17'] as $input) {
            $this->post(route('customer.studio-events.seats.store', [$event->studio, $event]), [
                'roles' => ['concert-videographer' => ['seating' => $input]],
            ])->assertSessionHasErrors();
            $this->assertCount(5, ConcertEventRoleSeats::where('crew_role_id', $video->id)->firstOrFail()->seats);
        }
        $this->post(route('customer.studio-events.seats.store', [$event->studio, $event]), [
            'roles' => ['concert-videographer' => [['row' => 'J', 'seat' => '20']]],
        ])->assertSessionHasErrors('roles.concert-videographer');
    }

    public function test_studio_can_confirm_dress_rehearsal_portrait_requirements(): void
    {
        [, $event, $otherEvent, $booking] = $this->fixture();
        $otherEvent->concertBookingItem()->delete();
        $otherEvent->delete();
        $event->concertBookingItem->update(['item_type' => ConcertBookingItemType::DressRehearsal]);
        $booking->update(['wants_concert_videography' => false, 'wants_portrait_photography' => true]);
        $customer = User::factory()->customer()->create(['email' => 'portrait-director@example.test']);
        StudioAccountAccess::create([
            'studio_id' => $event->studio_id, 'user_id' => $customer->id, 'email' => $customer->email,
            'role' => 'director', 'status' => 'active', 'activated_at' => now(),
        ]);

        $this->actingAs($customer)->get(route('customer.studio-communications.index', $event->studio))
            ->assertOk()->assertSee('Shoot time')->assertDontSee('Curtain up')
            ->assertSee('Provide a minimum 8 × 10 metre space')
            ->assertSee('Ensure all students are brought by their teacher or minder');
        $this->post(route('customer.studio-events.requirements.store', [$event->studio, $event]), [
            'requirements' => ['portrait_space', 'portrait_dancers'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('studio_event_acknowledgements', 2);
        $card = fn () => app(StudioCommunicationHistory::class)->forUser($customer, $event->studio)['hub']['events']->first();
        $this->assertSame(2, $card()['totalCount']);
        $this->assertSame(1, $card()['completeCount']);
        ConcertTrackerTask::create(['scope_type' => 'booking', 'scope_id' => $booking->id, 'task_key' => 'final_numbers_received', 'status' => 'not_started', 'due_date_override' => '2026-10-14']);
        $this->assertSame('2026-10-14', $card()['checklist']->firstWhere('key', 'event_details')['dueDate']->toDateString());
        $this->post(route('customer.studio-events.requirements.store', [$event->studio, $event]), ['group' => 'event_details', 'requirements' => ['event_details']])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('studio_event_acknowledgements', 3);
        $this->assertSame(2, $card()['completeCount']);
        $this->post(route('customer.studio-events.requirements.store', [$event->studio, $event]), ['requirements' => ['portrait_space', 'portrait_dancers']])->assertSessionHasNoErrors();
        $this->assertSame(2, $card()['completeCount']);
        $this->post(route('customer.studio-events.requirements.store', [$event->studio, $event]), ['group' => 'event_details'])->assertSessionHasNoErrors();
        $this->assertSame(1, $card()['completeCount']);
        $this->assertSame($customer->id, StudioEventAcknowledgement::firstOrFail()->completed_by_user_id);
        $this->get(route('customer.studio-communications.index', $event->studio))
            ->assertOk()->assertSee('Confirm portrait shoot requirements')->assertSee('Complete');
    }

    public function test_studio_can_request_a_structured_booking_change_and_management_can_resolve_it(): void
    {
        [$staff, $event] = $this->fixture();
        $customer = User::factory()->customer()->create(['email' => 'change-request@example.test']);
        StudioAccountAccess::create([
            'studio_id' => $event->studio_id, 'user_id' => $customer->id, 'email' => $customer->email,
            'role' => 'director', 'status' => 'active', 'activated_at' => now(),
        ]);

        $item = $event->concertBookingItem;
        $this->actingAs($customer)->post(route('customer.studio-events.booking-changes.store', [$event->studio, $event]), [
            'booking' => [
                'event_date' => $event->event_date->format('Y-m-d'),
                'venue_name' => $item->venue_name,
                'starts_at' => '18:30',
                'finishes_at' => Carbon::parse($item->finishes_at)->format('H:i'),
                'access_time' => null,
                'services_present' => true,
                'services' => ['photography', 'videography'],
            ],
            'details' => 'Please confirm the later curtain-up time.',
        ])->assertRedirect(route('customer.studio-communications.index', [$event->studio, 'tab' => 'events']));

        $change = StudioBookingChangeRequest::firstOrFail();
        $this->assertSame('requested', $change->status);
        $this->assertDatabaseHas('concert_tracker_alerts', ['scheduling_event_id' => $event->id, 'type' => 'studio_booking_change']);
        $this->get(route('customer.studio-communications.index', [$event->studio, 'tab' => 'events']))
            ->assertOk()->assertSee('Change requested')->assertSee('Curtain up: 18:00 → 18:30')
            ->assertSee('Please confirm the later curtain-up time.');

        $this->actingAs($staff)->patch(route('admin.concert-tracker.booking-changes.review', $change), ['status' => 'completed'])
            ->assertRedirect()->assertSessionHasErrors('status');
        $this->assertSame('requested', $change->fresh()->status);
    }

    public function test_studio_can_submit_one_current_programme_and_multiple_event_videos_for_review(): void
    {
        Storage::fake('local');
        [$staff, $event] = $this->fixture();
        $studio = $event->studio;
        $customer = User::factory()->customer()->create(['email' => 'uploads@example.test']);
        StudioAccountAccess::create([
            'studio_id' => $studio->id, 'user_id' => $customer->id, 'email' => $customer->email,
            'role' => 'director', 'status' => 'active', 'activated_at' => now(),
        ]);

        $this->actingAs($customer)->post(route('customer.studio-events.files.store', [$studio, $event]), [
            'kind' => 'programme',
            'files' => [UploadedFile::fake()->create('junior-programme.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $programme = StudioEventFile::where('kind', 'programme')->firstOrFail();
        $this->assertSame('needs_conversion', $programme->status);
        $this->assertSame($programme->storage_key, $event->fresh()->programme_path);
        $this->assertDatabaseHas('concert_tracker_alerts', ['scheduling_event_id' => $event->id, 'type' => 'studio_file_received']);

        $this->post(route('customer.studio-events.files.store', [$studio, $event]), [
            'kind' => 'programme', 'files' => [UploadedFile::fake()->create('replacement.pages', 40, 'application/octet-stream')],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, StudioEventFile::where('kind', 'programme')->where('is_current', true)->count());
        $this->assertDatabaseHas('studio_event_files', ['id' => $programme->id, 'status' => 'superseded', 'is_current' => false]);

        $this->post(route('customer.studio-events.files.store', [$studio, $event]), [
            'kind' => 'studio_video', 'files' => [
                UploadedFile::fake()->create('opening.mp4', 80, 'video/mp4'),
                UploadedFile::fake()->create('awards.mov', 90, 'video/quicktime'),
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $videos = StudioEventFile::where('kind', 'studio_video')->get();
        $this->assertCount(2, $videos);
        $this->assertDatabaseHas('concert_tracker_tasks', ['scope_id' => $event->id, 'task_key' => 'slideshow_received', 'status' => 'in_progress']);

        ConcertTrackerTask::where('scope_id', $event->id)->where('task_key', 'slideshow_received')->update(['status' => 'complete']);
        $status = app(ConcertTracker::class)->page('date', 2026, 'all')['rows']->first()['tasks']['slideshow_received'];
        $this->assertSame('in_progress', $status['key']);
        $this->assertSame('0 of 2 studio videos approved.', $status['detail']);
        $this->assertTrue($status['sourceDriven']);
        ConcertTrackerTask::where('scope_id', $event->id)->where('task_key', 'slideshow_received')->update(['status' => 'in_progress']);

        $this->actingAs($staff)->patch(route('admin.concert-tracker.studio-event-files.review', $videos[0]), ['status' => 'approved'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('concert_tracker_tasks', ['scope_id' => $event->id, 'task_key' => 'slideshow_received', 'status' => 'in_progress']);
        $this->patch(route('admin.concert-tracker.studio-event-files.review', $videos[1]), ['status' => 'approved'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('concert_tracker_tasks', ['scope_id' => $event->id, 'task_key' => 'slideshow_received', 'status' => 'complete']);
        $this->get(route('studio-event-files.download', $videos[0]))->assertDownload('opening.mp4');
    }

    public function test_email_can_be_scheduled_viewed_cancelled_and_resent(): void
    {
        Queue::fake();
        [$staff, $event] = $this->fixture();

        $this->actingAs($staff)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'concert_week_email']), [
            'recipient_name' => 'Alex Director',
            'recipient_email' => 'alex@example.test',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHas('status', 'Email scheduled. The task will complete when delivery succeeds.');

        $delivery = ConcertTrackerEmailDelivery::firstOrFail();
        $this->assertSame($event->id, $delivery->scheduling_event_id);
        $this->assertSame('scheduled', $delivery->status);
        ConcertTrackerInboundEmail::create([
            'scheduling_event_id' => $event->id,
            'reply_to_delivery_id' => $delivery->id,
            'sender_name' => 'Alex Director',
            'sender_email' => 'alex@example.test',
            'recipient_email' => 'concerts@example.test',
            'subject' => 'Re: '.$delivery->subject,
            'body' => 'Thanks, the information is confirmed.',
            'received_at' => now(),
        ]);
        Mail::fake();
        (new SendConcertTrackerEmail($delivery->id))->handle();
        Mail::assertNothingSent();
        $this->assertSame('scheduled', $delivery->fresh()->status);
        $this->get(route('admin.concert-tracker.communications.show', $event))
            ->assertOk()->assertSee('Message history')->assertSee($delivery->subject)
            ->assertSee('Thanks, the information is confirmed.');

        $this->post(route('admin.concert-tracker.deliveries.cancel', $delivery))
            ->assertRedirect()->assertSessionHas('status', 'Scheduled email cancelled.');
        $this->assertSame('cancelled', $delivery->fresh()->status);

        $this->post(route('admin.concert-tracker.deliveries.resend', $delivery))
            ->assertRedirect()->assertSessionHasErrors('delivery');
        $this->post(route('admin.concert-tracker.tasks.email.send', [$event, 'concert_week_email']), ['recipient_email' => 'alex@example.test'])->assertSessionHasNoErrors();
        $this->assertSame(2, ConcertTrackerEmailDelivery::count());
        $this->assertSame('queued', ConcertTrackerEmailDelivery::latest('id')->first()->status);
    }

    public function test_master_email_template_library_is_editable(): void
    {
        [$staff] = $this->fixture();
        $template = ConcertTrackerEmailTemplate::firstOrFail();

        $this->actingAs($staff)->get(route('admin.concert-tracker.templates.index'))
            ->assertOk()->assertSee('Master wording for Tracker communications')->assertSee($template->name);
        $this->patch(route('admin.concert-tracker.templates.update', $template), [
            'name' => 'Updated booking message',
            'subject' => 'Updated subject',
            'body' => 'Updated message for {{ studio_name }}.',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Updated subject', $template->fresh()->subject);
    }

    public function test_communication_schedule_previews_dates_and_prevents_duplicates(): void
    {
        Queue::fake();
        [$staff, $event] = $this->fixture();

        $this->actingAs($staff)->get(route('admin.concert-tracker.automation.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Review queue')
            ->assertSee('Booking confirmation')
            ->assertSee('Seats &amp; families email', false)
            ->assertSee($event->name);

        $route = route('admin.concert-tracker.automation.schedule', [$event, 'confirmation_information_email']);
        $this->post($route)->assertRedirect()->assertSessionHas('status', 'Email added to the schedule.');
        $this->assertDatabaseHas('concert_tracker_email_deliveries', [
            'scheduling_event_id' => $event->id,
            'status' => 'scheduled',
            'recipient_email' => 'alex@example.test',
        ]);

        $this->post($route)->assertRedirect()->assertSessionHasErrors('email');
        $this->assertSame(1, ConcertTrackerEmailDelivery::count());
    }

    public function test_tracker_alerts_are_assigned_deduplicated_and_marked_read(): void
    {
        [$staff, $event] = $this->fixture();
        $this->actingAs($staff)->patch(route('admin.concert-tracker.tasks.update', [$event, 'final_numbers_received']), [
            'status' => 'waiting',
            'assignee_user_id' => $staff->id,
            'follow_up_date' => today()->toDateString(),
        ])->assertRedirect();

        $alerts = app(CreateConcertTrackerAlerts::class);
        $alerts->execute();
        $alerts->execute();

        $alert = ConcertTrackerAlert::where('user_id', $staff->id)->where('type', 'follow_up_due')->firstOrFail();
        $this->assertSame(1, ConcertTrackerAlert::where('user_id', $staff->id)->where('dedupe_key', $alert->dedupe_key)->count());
        $this->get(route('admin.concert-tracker.alerts.index'))->assertOk()->assertSee('Seats &amp; families numbers', false);
        $this->post(route('admin.concert-tracker.alerts.read', $alert))->assertRedirect();
        $this->assertNotNull($alert->fresh()->read_at);
    }

    public function test_automation_settings_control_timing_targets_and_automatic_scheduling(): void
    {
        Queue::fake();
        [$staff, $event] = $this->fixture();
        $rules = ConcertTrackerAutomationRule::all()->mapWithKeys(fn ($rule) => [$rule->task_key => [
            'mode' => $rule->task_key === 'confirmation_information_email' ? 'automatic' : 'review',
            'offset_days' => $rule->offset_days,
            'send_time' => '10:30',
        ]])->all();

        $this->actingAs($staff)->patch(route('admin.concert-tracker.automation.settings.update'), [
            'rules' => $rules,
            'unassigned_alert_user_id' => $staff->id,
            'escalation_after_days' => 3,
            'editing_target_days' => 10,
            'approval_target_days' => 12,
            'email_business_name' => 'DancePro Photography & Video',
            'email_logo_url' => 'https://example.test/dancepro-logo.png',
            'email_phone' => '08 9000 0000',
            'email_address' => 'Perth WA',
            'email_website' => 'https://www.dancepro.com.au',
            'email_reply_address' => 'admin@dancepro.com.au',
            'email_legal_footer' => 'Private studio communication.',
            'signature_display_name' => 'Morgan Vale',
            'signature_position' => 'Concert Coordinator',
            'signature_phone' => '0400 000 000',
            'signature_sign_off' => 'Warm regards,',
            'signature_is_enabled' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get(route('admin.concert-tracker.automation.settings'))->assertOk()->assertSee('Production targets')->assertSee('Email branding')->assertSee('Concert Coordinator');
        $this->assertDatabaseHas('staff_email_signatures', ['user_id' => $staff->id, 'display_name' => 'Morgan Vale', 'position' => 'Concert Coordinator']);
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'concert_edited']))
            ->assertOk()->assertSee('Tracker task controls')->assertSee('30 Oct 2026');
        $this->assertSame(1, app(ConcertTrackerAutomation::class)->scheduleAutomatic());
        $this->assertSame(0, app(ConcertTrackerAutomation::class)->scheduleAutomatic());
        $this->assertDatabaseHas('concert_tracker_email_deliveries', ['status' => 'scheduled']);
    }

    public function test_administrator_can_send_a_branded_email_preview_to_their_account(): void
    {
        Mail::fake();
        [$staff] = $this->fixture();

        $this->actingAs($staff)
            ->post(route('admin.concert-tracker.automation.settings.test-email'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Test email sent to '.$staff->email.'.');

        Mail::assertSent(ConcertTrackerMessage::class, function (ConcertTrackerMessage $mail) use ($staff): bool {
            return $mail->hasTo($staff->email)
                && $mail->messageSubject === 'DancePro branded email preview'
                && $mail->senderUserId === $staff->id
                && str_contains($mail->messageBody, 'Spring Concert 2026');
        });
    }

    public function test_imported_templates_fill_event_fields_and_remove_irrelevant_service_sections(): void
    {
        [$staff, $event, , $booking] = $this->fixture();
        $booking->update(['wants_portrait_photography' => true]);
        ConcertBookingItem::create([
            'concert_booking_id' => $booking->id,
            'item_type' => 'dress_rehearsal',
            'title' => 'Dress rehearsal portraits',
            'venue_name' => 'Studio Hall',
            'venue_address' => 'Example street',
            'starts_at' => '16:00',
            'finishes_at' => '18:00',
            'event_date' => '2026-10-19',
        ]);
        $photoRole = CrewRole::query()->where('code', 'concert-photographer-p1')->firstOrFail();
        $videoRole = CrewRole::query()->where('code', 'concert-videographer')->firstOrFail();
        ConcertEventRoleSeats::create([
            'scheduling_event_id' => $event->id, 'crew_role_id' => $photoRole->id,
            'seats' => [['row' => 'H', 'seat' => '12']], 'submitted_by_user_id' => $staff->id, 'submitted_at' => now(),
        ]);
        ConcertEventRoleSeats::create([
            'scheduling_event_id' => $event->id, 'crew_role_id' => $videoRole->id,
            'seats' => [['row' => 'J', 'seat' => '20'], ['row' => 'J', 'seat' => '21']],
            'submitted_by_user_id' => $staff->id, 'submitted_at' => now(),
        ]);
        $tracker = app(ConcertTracker::class);
        $template = ConcertTrackerEmailTemplate::where('task_key', 'confirmation_information_email')->firstOrFail();

        $video = $tracker->renderTemplate($template->subject, $template->body, $event);
        $this->assertStringContainsString('VIDEO DELIVERY', $video['body']);
        $this->assertStringContainsString('Junior Concert', $video['body']);
        $this->assertStringContainsString('**Dress rehearsal portraits**', $video['body']);
        $this->assertStringContainsString('- **Portrait session starts:** 4:00 pm', $video['body']);
        $this->assertStringContainsString('- **Venue access/setup:** 3:00 pm', $video['body']);
        $this->assertStringContainsString('- **Services:** Portrait photography', $video['body']);
        $this->assertStringContainsString('**Junior Concert**', $video['body']);
        $this->assertStringContainsString('**Senior Concert**', $video['body']);
        $this->assertStringContainsString('- **Curtain up:** 6:00 pm', $video['body']);
        $this->assertStringContainsString('- **Venue access/setup:** 4:30 pm', $video['body']);
        $this->assertStringContainsString("- **Reserved DancePro seats:**\n  - **Photographer P1:** H12\n  - **Videographer V:** J20-21", $video['body']);
        $this->assertSame(2, substr_count($video['body'], '- **Reserved DancePro seats:**'));
        $weekTemplate = ConcertTrackerEmailTemplate::where('task_key', 'concert_week_email')->firstOrFail();
        $week = $tracker->renderTemplate($weekTemplate->subject, $weekTemplate->body, $event, 'concert_week_email');
        $this->assertStringContainsString('- **Photographer P1:** H12', $week['body']);
        $this->assertStringContainsString('- **Videographer V:** J20-21', $week['body']);
        $this->assertStringNotContainsString('Row J, Seat 20', $week['body']);
        $weekHtml = app(EmailContentFormatter::class)->html($week['body']);
        $this->assertStringContainsString('<li><strong>Videographer V:</strong> J20-21</li>', $weekHtml);

        $this->assertStringNotContainsString("**Dress rehearsal portraits**\n\n- **Date:** Monday 19 October 2026\n- **Venue access/setup:** 3:00 pm\n- **Reserved DancePro seats:**", $video['body']);
        $this->assertStringContainsString('- **Services:** Photography and videography', $video['body']);
        $this->assertStringNotContainsString('concert photography', $video['body']);
        $this->assertStringNotContainsString('{{#if', $video['body']);

        $booking->update(['wants_concert_videography' => false]);
        $photoOnly = $tracker->renderTemplate($template->subject, $template->body, $event->fresh());
        $this->assertStringNotContainsString('VIDEO DELIVERY', $photoOnly['body']);
        $this->assertStringNotContainsString('XLR feed', $photoOnly['body']);
        $this->assertStringContainsString('PHOTOGRAPHY', $photoOnly['body']);
    }

    public function test_concert_week_email_lists_each_events_crew_and_links_to_current_upload_actions(): void
    {
        Mail::fake();
        Queue::fake();
        [$staff, $event, $second, $booking] = $this->fixture();
        $role = CrewRole::where('code', 'concert-videographer')->firstOrFail();
        foreach ([[$event, 'Alex', '15:30'], [$second, 'Casey', '16:15']] as [$concert, $name, $arrival]) {
            $shift = $concert->shifts()->create(['period' => 'afternoon', 'shift_date' => $concert->event_date, 'posted_arrival_at' => $concert->event_date->format('Y-m-d').' '.$arrival]);
            $shift->assignments()->create(['crew_role_id' => $role->id, 'crew_profile_id' => CrewProfile::factory()->create(['preferred_name' => $name])->id, 'status' => 'assigned']);
            $shift->assignments()->create(['crew_role_id' => CrewRole::where('code', 'concert-photographer-p1')->value('id'), 'crew_profile_id' => CrewProfile::factory()->create(['preferred_name' => 'Removed crew'])->id, 'status' => 'cancelled']);
        }
        $template = ConcertTrackerEmailTemplate::where('task_key', 'concert_week_email')->firstOrFail();
        $tracker = app(ConcertTracker::class);
        $body = $tracker->renderTemplate($template->subject, $template->body, $event, 'concert_week_email')['body'];
        [$junior, $senior] = explode('**Senior Concert**', $body, 2);
        $this->assertStringContainsString("You'll have Alex capturing your event.", $junior);
        $this->assertStringContainsString('**Venue access/setup:** 3:30 pm', $junior);
        $this->assertStringNotContainsString('Casey', $junior);
        $this->assertStringContainsString("You'll have Casey capturing your event.", $senior);
        $this->assertStringContainsString('**Venue access/setup:** 4:15 pm', $senior);
        $this->assertStringNotContainsString('Removed crew', $body);
        $this->assertSame($body, $tracker->renderTemplate($template->subject, $template->body, $second, 'concert_week_email')['body']);
        foreach (['Your actions', 'Upload programme', 'Replace programme', 'Upload videos & slideshows', 'Upload studio files', 'Submitted files', 'due dates shown'] as $instruction) {
            $this->assertStringContainsString($instruction, $body);
        }
        $this->assertStringNotContainsString('Planned arrival:', $body);
        $this->assertStringNotContainsString('Please send the final programme', $body);
        $this->actingAs($staff)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'concert_week_email']), [
            'recipient_name' => 'Studio Director', 'recipient_email' => 'director@example.test',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::sole();
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $url = route('customer.studio-communications.index', [$event->studio, 'tab' => 'events', 'actions' => 1]).'#events';
        Mail::assertSent(ConcertTrackerMessage::class, function (ConcertTrackerMessage $mail) use ($url): bool {
            $this->assertStringContainsString('Open Concert events', $mail->render());

            return $mail->accountUrl === $url && $mail->accountLabel === 'Open Concert events';
        });
        $booking->update(['wants_concert_videography' => false]);
        $photoOnly = $tracker->renderTemplate($template->subject, $template->body, $event->fresh(), 'concert_week_email')['body'];
        $this->assertStringNotContainsString('## Videos & slideshows', $photoOnly);
        $this->assertStringNotContainsString('XLR', $photoOnly);
        $this->assertStringContainsString('## Programme', $photoOnly);
    }

    public function test_every_tracker_task_has_a_working_action_page(): void
    {
        [$staff, $event] = $this->fixture();
        $this->actingAs($staff);
        $row = app(ConcertTracker::class)->page('date', 2026, 'all')['rows']->first();
        $this->assertCount(18, $row['tasks']);
        foreach ($row['tasks'] as $task) {
            $this->followingRedirects()->get($task['detailUrl'])->assertOk()
                ->assertSee('Task navigation')->assertSee('Back to tracker')->assertSee('Back to event')
                ->assertSee('Task instructions')->assertSee($task['definition']['description']);
            if ($task['sourceUrl']) {
                $this->followingRedirects()->get(strtok($task['sourceUrl'], '#'))->assertOk();
            } elseif (in_array($task['definition']['key'], ['final_numbers_email', 'concert_week_email', 'post_concert_email', 'approval_link_email'], true)) {
                $this->get($task['detailUrl'])->assertSee('Tracker task controls')->assertSee('Email action');
            } elseif ($task['definition']['key'] === 'concert_edited') {
                $this->get($task['detailUrl'])->assertSee('Tracker task controls')->assertSee('Mark editing complete');
            } elseif (in_array($task['definition']['key'], ['director_corrections', 'final_release'], true)) {
                $this->get($task['detailUrl'])->assertSee('Tracker task controls');
            } else {
                $this->get($task['detailUrl'])->assertSee('Save task');
            }
        }
    }

    public function test_only_booked_services_and_required_approvals_generate_work(): void
    {
        [, $event, , $booking] = $this->fixture();
        $booking->update(['wants_concert_photography' => false]);
        $tracker = app(ConcertTracker::class);
        $tasks = $tracker->page('date', 2026, 'all')['rows']->first()['tasks'];
        $this->assertSame('not_required', $tasks['photos_uploaded']['key']);
        $this->assertSame('not_required', $tasks['photo_release']['key']);
        $this->assertSame('not_started', $tasks['concert_edited']['key']);

        $booking->update(['wants_concert_videography' => false, 'wants_concert_photography' => true]);
        $tasks = $tracker->page('date', 2026, 'all')['rows']->first()['tasks'];
        foreach (['concert_edited', 'quality_check', 'video_uploaded', 'slideshow_received', 'final_release'] as $key) {
            $this->assertSame('not_required', $tasks[$key]['key']);
            $this->assertNull($tasks[$key]['dueDate']);
            $this->assertFalse($tasks[$key]['overdue']);
        }
        $this->assertSame('waiting', $tasks['photos_uploaded']['key']);
        $booking->update(['wants_concert_videography' => true]);
        Concert::factory()->create([
            'studio_id' => $event->studio_id, 'concert_booking_item_id' => $event->concertBookingItem->id,
            'requires_approval' => false,
        ]);
        $tasks = $tracker->page('date', 2026, 'all')['rows']->first()['tasks'];
        foreach (['approval_link_email', 'director_corrections'] as $key) {
            $this->assertSame('not_required', $tasks[$key]['key']);
        }
    }

    public function test_attention_filter_rebuilds_shared_cells_and_overdue_dates_are_red(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01'));
        [$staff, $first, $second] = $this->fixture();
        $second->update(['event_date' => '2026-11-21']);
        $second->concertBookingItem->update(['event_date' => '2026-11-21']);
        ConcertTrackerTask::create([
            'scope_type' => 'event', 'scope_id' => $second->id, 'task_key' => 'concert_week_email',
            'status' => 'waiting', 'due_date_override' => '2026-08-31',
        ]);
        $rows = app(ConcertTracker::class)->page('studio', 2026, 'booking', true)['rows'];
        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()['event']->is($second));
        $this->assertFalse($rows->first()['tasks']['booking_received']['skip']);
        $this->assertSame(1, $rows->first()['tasks']['booking_received']['rowspan']);
        $this->actingAs($staff)->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => 'booking', 'attention' => 1]))
            ->assertOk()->assertSee('class="tracker-due-overdue">Due 31 Aug', false);
    }

    public function test_requesting_studio_video_does_not_complete_receipt(): void
    {
        Mail::fake();
        Queue::fake();
        [$staff, $event] = $this->fixture();
        $this->actingAs($staff)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'slideshow_received']), [
            'recipient_name' => 'Alex Director', 'recipient_email' => 'alex@example.test',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::firstOrFail();
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertNotSame('complete', $delivery->task->fresh()->status);
        $task = app(ConcertTracker::class)->page('date', 2026, 'all')['rows']->first()['tasks']['slideshow_received'];
        $this->assertSame(route('admin.concert-bookings.show', [$event->concertBookingItem->booking, 'tab' => 'studio-media', 'event' => $event->uuid]), $task['sourceUrl']);
        $this->assertNotSame('complete', $task['key']);
    }

    public function test_photo_release_workspace_is_in_the_task_and_scoped_to_its_studio(): void
    {
        [$staff, $event, $related] = $this->fixture();
        [, $other] = $this->fixture('OTHER');
        foreach ([[$event, 'Current studio album'], [$related, 'Related concert album'], [$other, 'Other studio album']] as [$sourceEvent, $name]) {
            $concert = Concert::factory()->create([
                'studio_id' => $sourceEvent->studio_id,
                'concert_booking_item_id' => $sourceEvent->concertBookingItem->id,
            ]);
            MediaCollection::factory()->create(['concert_id' => $concert->id, 'name' => $name]);
        }
        $this->actingAs($staff)->get(route('admin.concert-tracker.index', ['year' => 2026]))
            ->assertOk()->assertDontSee('Parent album name')->assertDontSee('Current studio album');
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'photo_release']))
            ->assertOk()->assertSee('Release photos together')->assertSee($event->uuid)->assertSee($related->uuid)
            ->assertDontSee($other->uuid)->assertDontSee('Other studio album');
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'concert_edited']))
            ->assertOk()->assertDontSee('Parent album name');
    }

    public function test_date_view_includes_pending_bookings_and_replaces_them_on_approval(): void
    {
        Queue::fake();
        Notification::fake();
        [$staff, $first, , $booking] = $this->fixture();
        $pending = $booking->replicate(['uuid']);
        $pending->fill(['status' => 'pending', 'studio_id' => $first->studio_id, 'studio_name' => 'Pending studio']);
        $pending->save();
        $item = $first->concertBookingItem->replicate(['uuid']);
        $venue = Venue::create(['name' => 'Pending theatre', 'address_line_1' => '1 Example Road']);
        $item->fill(['concert_booking_id' => $pending->id, 'scheduling_event_id' => null, 'venue_id' => $venue->id, 'title' => 'Pending opening night', 'event_date' => '2026-10-19']);
        $item->save();
        $tracker = app(ConcertTracker::class);
        $before = SchedulingEvent::count();
        $page = $tracker->page('date', 2026, 'all');
        $this->assertSame(1, $page['pendingCount']);
        $this->assertTrue($page['rows']->first()['pending']);
        $this->assertTrue($page['rows']->first()['bookingItem']->is($item));
        $this->assertCount(3, $tracker->page('studio', 2026, 'all')['rows']);
        $this->actingAs($staff)->get(route('admin.concert-tracker.index', ['year' => 2026, 'view' => 'date']))
            ->assertOk()->assertSee('Pending opening night')
            ->assertSee('class="tracker-event-heading tracker-pending-heading"', false)
            ->assertSee(route('admin.concert-bookings.show', $pending), false);
        $this->assertSame($before, SchedulingEvent::count());

        $event = app(ApproveConcertBooking::class)->approveItem($item, $staff);
        $page = $tracker->page('date', 2026, 'all');
        $this->assertSame(0, $page['pendingCount']);
        $this->assertCount(3, $page['rows']);
        $this->assertTrue($page['rows']->first()['event']->is($event));
        $this->get(route('admin.concert-tracker.index', ['year' => 2026, 'view' => 'date']))
            ->assertOk()->assertDontSee('class="tracker-event-heading tracker-pending-heading"', false)
            ->assertSee(route('admin.scheduling-events.show', $event), false);
    }

    public function test_pending_tracker_rows_exclude_cancelled_declined_and_other_year_items(): void
    {
        [, $first, , $booking] = $this->fixture();
        foreach ([
            ['title' => 'Pending valid', 'event_date' => '2026-10-18'],
            ['title' => 'Cancelled', 'event_date' => '2026-10-18', 'cancelled_at' => now()],
            ['title' => 'Declined', 'event_date' => '2026-10-18', 'approval_status' => 'declined'],
            ['title' => 'Other year', 'event_date' => '2027-10-18'],
            ['title' => 'Portraits pending', 'event_date' => '2026-10-17', 'item_type' => 'dress_rehearsal'],
        ] as $attributes) {
            $item = $first->concertBookingItem->replicate(['uuid']);
            $item->fill(['scheduling_event_id' => null, ...$attributes])->save();
        }
        $tracker = app(ConcertTracker::class);
        $this->assertSame(['Portraits pending', 'Pending valid'], $tracker->page('date', 2026, 'all')['rows']->where('pending', true)->pluck('bookingItem.title')->all());
        foreach (['declined', 'cancelled'] as $status) {
            $booking->update(['status' => $status]);
            $this->assertSame(0, $tracker->page('date', 2026, 'all')['pendingCount']);
        }
    }

    public function test_tracker_task_numbers_stay_consistent_between_phase_tabs_and_detail(): void
    {
        [$staff, $event] = $this->fixture();
        $catalog = app(ConcertTrackerTaskCatalog::class);
        $this->assertSame('1.1 Booking & services', $catalog->find('booking_received')['numbered_label']);
        $this->assertSame('2.1 Crew ready', $catalog->forPhase('preparation')->first()['numbered_label']);
        $this->assertSame('4.3', $catalog->find('final_release')['number']);
        $this->assertCount($catalog->all()->count(), $catalog->all()->pluck('number')->unique());
        $this->actingAs($staff);
        foreach (['all', 'preparation'] as $phase) {
            $this->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => $phase]))
                ->assertOk()->assertSee('1. Booking')->assertSee('2. Preparation &amp; assembly', false)->assertSee('2.1 Crew ready');
        }
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'crew_ready']))->assertOk()->assertSee('2.1 Crew ready');
    }

    public function test_studio_availability_filter_and_isolated_crew_page_share_roster_status(): void
    {
        [$staff, $event, $second] = $this->fixture();
        $otherStudio = Studio::factory()->create(['name' => 'Other studio']);
        $other = SchedulingEvent::create(['studio_id' => $otherStudio->id, 'name' => 'Other concert', 'event_type' => 'concert', 'event_date' => '2026-10-22']);
        foreach ([$event, $second, $other] as $item) {
            $item->shifts()->create(['period' => 'morning', 'shift_date' => $item->event_date]);
        }
        $this->actingAs($staff)->get(route('admin.scheduling-events.index', ['studio' => $event->studio->uuid]))->assertOk()->assertViewHas('shifts', fn ($shifts) => $shifts->pluck('scheduling_event_id')->sort()->values()->all() === collect([$event->id, $second->id])->sort()->values()->all());
        $page = route('admin.concert-tracker.tasks.show', [$event, 'crew_ready']);
        $this->get($page.'?studio='.$otherStudio->uuid)->assertOk()->assertSee('Tracker task controls')->assertSee('Full event availability')->assertViewHas('shifts', fn ($shifts) => ! $shifts->contains('scheduling_event_id', $other->id));
        foreach (['draft' => 'Draft', 'open' => 'Availability open', 'closed' => 'Rostering'] as $state => $label) {
            $event->update(['availability_status' => $state]);
            $detail = app(ConcertTracker::class)->detail($event->fresh(), 'crew_ready');
            $this->assertSame($label, $detail['status']['label']);
            $this->get(route('admin.scheduling-events.index', ['studio' => $event->studio->uuid]))->assertOk()->assertSee($label);
        }
        $role = CrewRole::where('code', 'concert-videographer')->firstOrFail();
        $profile = CrewProfile::factory()->create(['preferred_name' => 'Alex']);
        $event->roleRequirements()->create(['crew_role_id' => $role->id]);
        $assignment = $event->shifts()->first()->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published', 'acknowledgement_status' => 'pending']);
        $event->update(['roster_status' => 'published', 'roster_published_at' => now()]);
        $this->assertSame('Assigned', app(ConcertTracker::class)->detail($event->fresh(), 'crew_ready')['status']['label']);
        $assignment->update(['acknowledgement_status' => 'acknowledged']);
        $status = app(ConcertTracker::class)->detail($event->fresh(), 'crew_ready')['status'];
        $this->assertSame('READY', $status['label']);
        $this->assertSame('complete', $status['tone']);
        $this->assertSame(['V: Alex'], $status['crewSummary']->all());
        $this->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => 'preparation']))->assertOk()->assertSee('V: Alex');
        $update = route('admin.concert-tracker.source-bar.update', [$event, 'crew_ready']);
        $this->patchJson($update, ['panel' => 'follow_up', 'follow_up_status' => 'open', 'follow_up_date' => null, 'note' => 'Checked with crew.'])->assertOk();
        $this->patchJson($update, ['panel' => 'status', 'status_override' => 'complete'])->assertUnprocessable();
        $this->actingAs(User::factory()->customer()->create())->get($page)->assertForbidden();
    }

    public function test_programme_booking_tab_uses_attached_pdf_and_scopes_event_selection(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['operations.filesystem_disk' => 'local']);
        [$staff, $event, $second, $booking] = $this->fixture();
        $pdf = UploadedFile::fake()->createWithContent('Studio programme.pdf', "%PDF-1.4\nProgramme from studio\n%%EOF");
        app(StoreStudioEventFiles::class)->execute($event, $staff, 'programme', [$pdf]);
        $url = route('admin.concert-bookings.show', [$booking, 'tab' => 'programme', 'event' => $event->uuid]);
        $this->actingAs($staff)->get($url)->assertOk()->assertSee('2.2 Prepare programme')->assertSee('Tracker task controls')->assertSee('Use attached programme')->assertSee('Studio programme.pdf')->assertSee('Back to event availability');
        $this->get(route('admin.programmes.show', $event))->assertRedirect($url);
        $this->post(route('admin.programmes.start', $event))->assertSessionHasNoErrors();
        $programme = Programme::where('scheduling_event_id', $event->id)->sole();
        $originalSource = $programme->source;
        $this->assertNotNull($originalSource);
        $this->assertSame(Storage::disk('local')->get($event->fresh()->programme_path), Storage::disk('local')->get($originalSource->path));
        $this->get($url)->assertOk()->assertSee('Source programme PDF')->assertSee('Use a replacement PDF');
        $this->post(route('admin.programmes.use-attached', $event), ['version' => $programme->draft_version])->assertSessionHasNoErrors();
        $this->assertNotSame($originalSource->id, $programme->fresh()->programme_source_id);
        Storage::disk('local')->assertExists($originalSource->path);
        $other = SchedulingEvent::create(['name' => 'Unrelated concert', 'event_type' => 'concert', 'event_date' => '2026-10-23']);
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'programme', 'event' => $other->uuid]))->assertNotFound();
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'programme', 'event' => $second->uuid]))->assertOk()->assertSee('Upload your programme')->assertDontSee('Studio programme.pdf');
    }

    public function test_reordered_tasks_and_studio_media_tab_share_uploads_reviews_and_status(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['operations.filesystem_disk' => 'local']);
        [$staff, $event, $second, $booking] = $this->fixture();
        $event->concertBookingItem->update(['approval_status' => 'approved']);
        $tracker = app(ConcertTracker::class);
        $catalog = app(ConcertTrackerTaskCatalog::class);
        $this->assertSame('3.1', $catalog->find('photos_uploaded')['number']);
        $this->assertSame('3.2', $catalog->find('concert_edited')['number']);
        $this->assertSame('3.5', $catalog->find('approval_link_email')['number']);
        $this->assertSame('3.6', $catalog->find('director_corrections')['number']);
        $this->assertSame('4.3', $catalog->find('final_release')['number']);
        $this->assertSame('2.3', $catalog->find('video_titles')['number']);
        $this->assertSame('2.4', $catalog->find('post_concert_email')['number']);
        $this->assertSame('2.5', $catalog->find('slideshow_received')['number']);
        $url = route('admin.concert-bookings.show', [$booking, 'tab' => 'studio-media', 'event' => $event->uuid]);
        $bar = route('admin.concert-tracker.source-bar.update', [$event, 'slideshow_received']);
        $this->assertSame($url, $tracker->detail($event, 'slideshow_received')['status']['detailUrl']);
        $this->actingAs($staff)->get(route('admin.concert-tracker.tasks.show', [$event, 'slideshow_received']))->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('2.5 Studio videos & slideshows')->assertSee('Tracker task controls')->assertSee('Upload studio files');
        $this->patchJson($bar, ['panel' => 'status', 'status_override' => 'not_required'])->assertOk();
        $this->assertSame('not_required', $tracker->detail($event->fresh(), 'slideshow_received')['status']['key']);
        $this->patchJson($bar, ['panel' => 'status', 'status_override' => 'waiting'])->assertOk();
        app(StoreStudioEventFiles::class)->execute($event, $staff, 'studio_video', [UploadedFile::fake()->create('Studio opening.mp4', 50, 'video/mp4')]);
        $file = StudioEventFile::where('kind', 'studio_video')->sole();
        $this->get($url)->assertOk()->assertSee('Studio opening.mp4')->assertSee('Save file review');
        $activity = $tracker->detail($event->fresh(), 'slideshow_received')['status']['activity'];
        $this->assertStringContainsString('Uploaded '.now()->format('j M Y'), $activity);
        $this->assertStringContainsString($staff->name, $activity);
        $this->patchJson($bar, ['panel' => 'status', 'status_override' => 'complete'])->assertUnprocessable();
        $this->from($url)->patch(route('admin.concert-tracker.studio-event-files.review', $file), ['status' => 'approved'])->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertSame('complete', $tracker->detail($event->fresh(), 'slideshow_received')['status']['key']);
        $this->assertStringContainsString('Reviewed '.now()->format('j M Y'), $tracker->detail($event->fresh(), 'slideshow_received')['status']['activity']);
        $this->from($url)->post(route('admin.concert-booking-events.files', $event->concertBookingItem), [
            'kind' => 'studio_video', 'replace_uuid' => $file->uuid,
            'files' => [UploadedFile::fake()->create('Corrected opening.mp4', 50, 'video/mp4')],
        ])->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertFalse($file->fresh()->is_current);
        $this->assertSame('in_progress', $tracker->detail($event->fresh(), 'slideshow_received')['status']['key']);
        $this->get($url)->assertOk()->assertSee('Corrected opening.mp4')->assertDontSee('Studio opening.mp4');
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'studio-media', 'event' => $second->uuid]))->assertOk()->assertDontSee('Corrected opening.mp4');
        $other = SchedulingEvent::create(['name' => 'Other booking', 'event_type' => 'concert', 'event_date' => '2026-10-23']);
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'studio-media', 'event' => $other->uuid]))->assertNotFound();
        $this->actingAs(User::factory()->customer()->create())->get($url)->assertForbidden();
        $this->patchJson($bar, ['panel' => 'status', 'status_override' => 'complete'])->assertForbidden();
    }

    public function test_event_column_shows_studio_title_date_and_actual_gallery_login_in_both_views(): void
    {
        [$staff, $event] = $this->fixture('INTERNAL-ONLY');
        $studio = $event->studio;
        app(SaveStudioGalleryCode::class)->execute($studio, $staff, 'PHOTO-LOGIN', 0);
        $event->concertBookingItem->booking->update(['wants_concert_photography' => true]);
        foreach (['date', 'studio'] as $view) {
            $page = app(ConcertTracker::class)->page($view, 2026, 'booking');
            $html = view('admin.tracking._event-column', ['row' => $page['rows']->first(), 'galleryLogins' => $page['galleryLogins']])->render();
            $this->assertStringContainsString('<strong>Aurora Dance</strong>', $html);
            $this->assertStringContainsString('class="tracker-event-title">Junior Concert', $html);
            $this->assertStringContainsString('Tue 20 Oct 2026', $html);
            $this->assertStringContainsString('PHOTO-LOGIN', $html);
            $this->assertStringNotContainsString('INTERNAL-ONLY', $html);
            $this->assertStringContainsString('aria-label="Communications for Junior Concert"', $html);
            $this->assertStringContainsString(route('admin.concert-tracker.communications.show', $event), $html);
            $response = $this->actingAs($staff)->get(route('admin.concert-tracker.index', ['year' => 2026, 'view' => $view, 'phase' => 'completion']))->assertOk();
            $doc = new \DOMDocument;
            @$doc->loadHTML($response->getContent());
            $codes = (new \DOMXPath($doc))->query('//td[@data-task-key="photo_release"]//span[@class="tracker-access-code"]');
            $this->assertCount(2, $codes);
            $this->assertStringContainsString('Access Code: PHOTO-LOGIN', $codes->item(0)->textContent);
        }
        $this->actingAs(User::factory()->customer()->create())->get(route('admin.concert-tracker.index', ['year' => 2026]))->assertForbidden()->assertDontSee('PHOTO-LOGIN');
    }

    public function test_inactive_email_template_is_explained_before_sending(): void
    {
        [$staff, $event] = $this->fixture();
        $detail = app(ConcertTracker::class)->detail($event, 'concert_week_email');
        $detail['template']->update(['is_active' => false]);
        $this->actingAs($staff)->get(route('admin.concert-tracker.tasks.show', [$event, 'concert_week_email']))
            ->assertOk()->assertSee('Not ready to send')
            ->assertSee('The email template is missing or inactive.')
            ->assertDontSee('action="'.route('admin.concert-tracker.tasks.email.send', [$event, 'concert_week_email']).'"', false);
    }

    public function test_missing_booking_stays_on_an_explanatory_task_page(): void
    {
        [$staff] = $this->fixture();
        $event = SchedulingEvent::create(['name' => 'Unlinked concert', 'event_type' => 'concert', 'event_date' => '2026-10-23']);
        $this->actingAs($staff);
        foreach (['invoice_sent', 'booking_received', 'final_numbers_received', 'slideshow_received'] as $key) {
            $this->get(route('admin.concert-tracker.tasks.show', [$event, $key]))->assertOk()
                ->assertSee('This task cannot be completed yet')->assertSee('Task navigation')
                ->assertSee('Back to tracker')->assertSee('Back to event')->assertSee('Task instructions');
        }
    }

    public function test_seats_families_email_links_to_hub_actions_and_studio_can_confirm_the_shared_total(): void
    {
        Mail::fake();
        Queue::fake();
        [$staff, $event, $second, $booking] = $this->fixture();
        $studio = $event->studio;
        $booking->forceFill(['studio_id' => $studio->id, 'submission_snapshot' => [], 'approximate_family_count' => 80])->save();
        $customer = User::factory()->customer()->create();
        StudioAccountAccess::create(['studio_id' => $studio->id, 'user_id' => $customer->id, 'email' => $customer->email, 'role' => 'director', 'status' => 'active', 'activated_at' => now()]);
        $this->actingAs($staff)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'final_numbers_email']), [
            'recipient_name' => 'Studio Director', 'recipient_email' => $customer->email,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::firstOrFail();
        $this->assertStringContainsString('within 10 days', $delivery->body);
        $this->assertStringContainsString('Save seating details', $delivery->body);
        $this->assertStringNotContainsString('Please reply with the final details', $delivery->body);
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $url = route('customer.studio-communications.index', [$studio, 'tab' => 'events', 'actions' => 1]).'#events';
        Mail::assertSent(ConcertTrackerMessage::class, function (ConcertTrackerMessage $mail) use ($url): bool {
            $this->assertStringContainsString('Open Concert events', $mail->render());

            return $mail->accountUrl === $url && $mail->accountLabel === 'Open Concert events';
        });
        $this->actingAs($customer)->get($url)->assertOk()->assertSee('Confirm final family numbers')->assertSee('Unique performing families')->assertSee('Confirm final numbers');
        $this->post(route('customer.studio-bookings.families', [$studio, $booking]), ['final_family_count' => 92, 'confirm_unique_families' => 1])
            ->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertSame(92, $booking->fresh()->final_family_count);
        $this->get($url)->assertOk()->assertSee('92 families confirmed')->assertSee('Update confirmed numbers');
    }

    public function test_concert_action_counts_exclude_unbooked_services_and_details_are_confirmed_per_event(): void
    {
        [, $event, $other, $booking] = $this->fixture();
        $customer = User::factory()->customer()->create();
        StudioAccountAccess::create(['studio_id' => $event->studio_id, 'user_id' => $customer->id, 'email' => $customer->email, 'role' => 'director', 'status' => 'active']);
        $cards = fn () => app(StudioCommunicationHistory::class)->forUser($customer, $event->studio)['hub']['events'];
        $this->assertSame(['event_details', 'families', 'seating', 'programme', 'video'], $cards()->first()['checklist']->pluck('key')->all());
        $this->assertSame(5, $cards()->first()['totalCount']);
        $this->assertSame(0, $cards()->first()['completeCount']);
        $this->actingAs($customer)->post(route('customer.studio-events.requirements.store', [$event->studio, $event]), ['group' => 'event_details', 'requirements' => ['event_details']])->assertSessionHasNoErrors();
        $this->assertSame(1, $cards()->first()['completeCount']);
        $this->assertSame(0, $cards()->firstWhere('event.id', $other->id)['completeCount']);
        $this->post(route('customer.studio-events.requirements.store', [$event->studio, $event]), ['group' => 'event_details', 'requirements' => ['portrait_space']])->assertSessionHasErrors('requirements');
        $this->assertSame(1, $cards()->first()['completeCount']);
        $booking->update(['wants_concert_videography' => false, 'wants_concert_photography' => true]);
        $this->assertSame(['event_details', 'families', 'seating', 'programme'], $cards()->first()['checklist']->pluck('key')->all());
        $this->assertSame(4, $cards()->first()['totalCount']);
        $this->get(route('customer.studio-communications.index', $event->studio))->assertOk()->assertDontSee('Upload videos &amp; slideshows', false);
        $outsider = User::factory()->customer()->create();
        $this->actingAs($outsider)->post(route('customer.studio-events.requirements.store', [$event->studio, $event]), ['group' => 'event_details', 'requirements' => ['event_details']])->assertForbidden();
    }

    public function test_concert_week_groups_use_twenty_day_windows_and_distinct_email_and_programme_deadlines(): void
    {
        Queue::fake();
        Mail::fake();
        [$staff, $event, $second, $booking] = $this->fixture();
        $add = function (string $date, string $title, string $type = 'concert') use ($event, $booking): SchedulingEvent {
            $added = SchedulingEvent::create(['studio_id' => $event->studio_id, 'name' => $title, 'event_type' => 'concert', 'event_date' => $date]);
            ConcertBookingItem::create(['concert_booking_id' => $booking->id, 'scheduling_event_id' => $added->id, 'item_type' => $type,
                'title' => $title, 'event_date' => $date, 'starts_at' => '18:00', 'finishes_at' => '20:00', 'venue_name' => 'Theatre', 'venue_address' => 'Example street']);

            return $added;
        };
        $dr = $add('2026-10-18', 'Dress rehearsal', 'dress_rehearsal');
        $boundary = $add('2026-11-07', 'Within twenty days');
        $later = $add('2026-11-08', 'Next event group');
        $cancelled = $add('2026-10-01', 'Cancelled rehearsal', 'dress_rehearsal');
        $cancelled->concertBookingItem->update(['cancelled_at' => now()]);
        $tracker = app(ConcertTracker::class);
        foreach ([$dr, $event, $second, $boundary] as $included) {
            $detail = $tracker->detail($included->fresh(), 'concert_week_email');
            $this->assertSame($dr->id, $detail['task']->scope_id);
            $this->assertSame('2026-10-08', $detail['status']['dueDate']->toDateString());
            $this->assertStringContainsString('Within twenty days', $detail['renderedTemplate']['body']);
            $this->assertStringNotContainsString('Next event group', $detail['renderedTemplate']['body']);
            $this->assertStringNotContainsString('Cancelled rehearsal', $detail['renderedTemplate']['body']);
            $this->assertStringContainsString('Tuesday 13 October 2026', $detail['renderedTemplate']['body']);
        }
        $drProgramme = $tracker->detail($dr->fresh(), 'programme_prepared')['status'];
        $this->assertSame('not_required', $drProgramme['key']);
        $this->assertSame('not_required', $drProgramme['tone']);
        $this->assertNull($drProgramme['dueDate']);
        $this->actingAs($staff)->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'programme', 'event' => $dr->uuid]))
            ->assertOk()->assertSee('Programmes are not required for dress rehearsals.')->assertViewHas('programmeWorkspace', null);
        foreach ([$event, $second, $boundary] as $concert) {
            $this->assertSame('2026-10-13', $tracker->detail($concert->fresh(), 'programme_prepared')['status']['dueDate']->toDateString());
        }
        $this->assertSame('2026-10-29', $tracker->detail($later, 'concert_week_email')['status']['dueDate']->toDateString());
        $this->assertSame('2026-11-01', $tracker->detail($later, 'programme_prepared')['status']['dueDate']->toDateString());
        $director = User::factory()->customer()->create();
        StudioAccountAccess::create(['studio_id' => $event->studio_id, 'user_id' => $director->id, 'email' => $director->email, 'role' => 'director', 'status' => 'active', 'activated_at' => now()]);
        $cards = app(StudioCommunicationHistory::class)->forUser($director, $event->studio)['hub']['events'];
        foreach ([$event, $second, $boundary] as $concert) {
            $card = $cards->first(fn ($card) => $card['event']->id === $concert->id);
            $this->assertSame('2026-10-13', $card['checklist']->firstWhere('key', 'programme')['dueDate']->toDateString());
        }
        $candidates = app(ConcertTrackerAutomation::class)->candidatesForYear(2026)->where('task_key', 'concert_week_email')->values();
        $this->assertCount(2, $candidates);
        $this->assertSame(['2026-10-08', '2026-10-29'], $candidates->map(fn ($candidate) => $candidate['planned_at']->toDateString())->all());

        $this->actingAs($staff)->post(route('admin.concert-tracker.tasks.email.send', [$second, 'concert_week_email']), ['recipient_email' => 'director@example.test'])->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::sole();
        $this->post(route('admin.concert-tracker.tasks.email.send', [$event, 'concert_week_email']), ['recipient_email' => 'director@example.test'])->assertSessionHasErrors('email');
        (new SendConcertTrackerEmail($delivery->id))->handle();
        foreach ([$dr, $event, $second, $boundary] as $included) {
            $detail = $tracker->detail($included->fresh(), 'concert_week_email');
            $this->assertSame('complete', $detail['status']['key']);
            $this->assertSame($delivery->id, $detail['task']->deliveries->sole()->id);
            $this->assertSame($staff->id, $detail['task']->completed_by_user_id);
        }
        $this->assertNotSame('complete', $tracker->detail($later->fresh(), 'concert_week_email')['status']['key']);
        $this->post(route('admin.concert-tracker.tasks.email.send', [$boundary, 'concert_week_email']), ['recipient_email' => 'director@example.test'])->assertSessionHasErrors('email');
        Mail::assertSentCount(1);
        $this->actingAs($staff)->get(route('admin.concert-tracker.tasks.show', [$dr, 'concert_week_email']))->assertOk()->assertSee('20-day booking group');
    }

    public function test_grouping_preserves_already_sent_email_and_invalidates_old_scheduled_duplicates(): void
    {
        [$staff, $event, $second] = $this->fixture();
        $oldTask = ConcertTrackerTask::create(['scope_type' => 'event', 'scope_id' => $second->id, 'task_key' => 'concert_week_email', 'status' => 'complete']);
        $template = ConcertTrackerEmailTemplate::where('task_key', 'concert_week_email')->firstOrFail();
        $sent = ConcertTrackerEmailDelivery::create(['scheduling_event_id' => $second->id, 'concert_tracker_task_id' => $oldTask->id,
            'concert_tracker_email_template_id' => $template->id, 'created_by_user_id' => $staff->id, 'recipient_email' => 'director@example.test',
            'subject' => 'Original subject', 'body' => 'Original sent body', 'status' => 'sent', 'sent_at' => now()->subDay()]);
        $pending = $sent->replicate(['uuid']);
        $pending->fill(['status' => 'scheduled', 'sent_at' => null, 'scheduled_at' => now()->addDay()])->save();
        app(ConsolidateConcertWeekTasks::class)->execute($event);
        foreach ([$event, $second] as $included) {
            $detail = app(ConcertTracker::class)->detail($included->fresh(), 'concert_week_email');
            $this->assertSame('complete', $detail['status']['key']);
            $this->assertSame($event->id, $detail['task']->scope_id);
            $this->assertSame($sent->sent_at->toDateTimeString(), $detail['task']->completed_at->toDateTimeString());
            $this->assertTrue($detail['task']->deliveries->contains('id', $sent->id));
        }
        $this->assertSame('Original sent body', $sent->fresh()->body);
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->invalidated_at);
    }

    public function test_post_concert_email_groups_share_final_deadline_delivery_and_current_instructions(): void
    {
        Queue::fake();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-22 10:00:00'));
        [$staff, $first, $second, $booking] = $this->fixture();
        $booking->update(['wants_concert_photography' => true]);
        $add = function (string $date, string $title) use ($first, $booking): SchedulingEvent {
            $event = SchedulingEvent::create(['studio_id' => $first->studio_id, 'name' => $title, 'event_type' => 'concert', 'event_date' => $date]);
            ConcertBookingItem::create(['concert_booking_id' => $booking->id, 'scheduling_event_id' => $event->id, 'item_type' => 'concert',
                'title' => $title, 'event_date' => $date, 'starts_at' => '18:00', 'finishes_at' => '20:00', 'venue_name' => 'Theatre', 'venue_address' => 'Example street']);

            return $event;
        };
        $last = $add('2026-11-09', 'Final concert in group');
        $next = $add('2026-11-10', 'Separate group');
        $cancelled = $add('2026-11-08', 'Cancelled concert');
        $cancelled->concertBookingItem->update(['cancelled_at' => now()]);
        $tracker = app(ConcertTracker::class);
        foreach ([$first, $second, $last] as $event) {
            $detail = $tracker->detail($event->fresh(), 'post_concert_email');
            $this->assertSame($first->id, $detail['task']->scope_id);
            $this->assertSame('2026-11-10', $detail['status']['dueDate']->toDateString());
            foreach (['Junior Concert', 'Senior Concert', 'Final concert in group', 'Studio Hub', 'Upload studio files', 'Studio messages', 'Access Code', 'My Purchases', 'select multiple videos', 'Review & release concert video'] as $text) {
                $this->assertStringContainsString($text, $detail['renderedTemplate']['body']);
            }
            foreach (['Separate group', 'Cancelled concert', 'principal offer', 'Please send a PDF', "You'll have", '{{'] as $text) {
                $this->assertStringNotContainsString($text, $detail['renderedTemplate']['body']);
            }
        }
        $this->assertSame('2026-11-11', $tracker->detail($next, 'post_concert_email')['status']['dueDate']->toDateString());
        $candidates = app(ConcertTrackerAutomation::class)->candidatesForYear(2026)->where('task_key', 'post_concert_email')->values();
        $this->assertCount(2, $candidates);
        $this->assertSame(['2026-11-10 09:00', '2026-11-11 09:00'], $candidates->map(fn ($candidate) => $candidate['planned_at']->format('Y-m-d H:i'))->all());
        $this->actingAs($staff)->get(route('admin.concert-tracker.tasks.show', [$second, 'post_concert_email']))
            ->assertOk()->assertSee('You are sending before the recommended date')->assertSee('You can still send now')->assertSee('Open Concert events');
        $send = fn ($event, array $extra = []) => $this->post(route('admin.concert-tracker.tasks.email.send', [$event, 'post_concert_email']), ['recipient_email' => 'director@example.test'] + $extra);
        $send($second, ['scheduled_at' => '2026-11-08 09:00'])->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::sole();
        $send($last, ['scheduled_at' => '2026-11-10 10:00'])->assertSessionHasErrors('email');
        (new SendConcertTrackerEmail($delivery->id))->handle();
        Mail::assertNothingSent();
        $this->travelTo(Carbon::parse('2026-11-08 09:00:00'));
        (new SendConcertTrackerEmail($delivery->id))->handle();
        foreach ([$first, $second, $last] as $event) {
            $detail = $tracker->detail($event->fresh(), 'post_concert_email');
            $this->assertSame('complete', $detail['status']['key']);
            $this->assertSame($delivery->id, $detail['task']->deliveries->sole()->id);
        }
        $this->assertNotSame('complete', $tracker->detail($next->fresh(), 'post_concert_email')['status']['key']);
        $send($last)->assertSessionHasErrors('email');
        Mail::assertSent(ConcertTrackerMessage::class, fn ($mail): bool => $mail->accountLabel === 'Open Concert events'
            && $mail->accountUrl === SeatsFamiliesEmailContent::url($first));
        Mail::assertSentCount(1);
    }

    public function test_post_concert_consolidation_preserves_history(): void
    {
        Mail::fake();
        [$staff, $first, $second] = $this->fixture();
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
        $task = ConcertTrackerTask::create(['scope_type' => 'event', 'scope_id' => $second->id, 'task_key' => 'post_concert_email', 'status' => 'complete']);
        $template = ConcertTrackerEmailTemplate::where('task_key', 'post_concert_email')->firstOrFail();
        $sent = ConcertTrackerEmailDelivery::create(['scheduling_event_id' => $second->id, 'concert_tracker_task_id' => $task->id,
            'concert_tracker_email_template_id' => $template->id, 'created_by_user_id' => $staff->id, 'recipient_email' => 'director@example.test',
            'subject' => 'Historical subject', 'body' => 'Historical body', 'status' => 'sent', 'sent_at' => now()->subDay()]);
        $pending = $sent->replicate(['uuid']);
        $pending->fill(['status' => 'scheduled', 'sent_at' => null, 'scheduled_at' => now()->addDay()])->save();
        app(ConsolidateConcertEmailTasks::class)->execute($first, 'post_concert_email');
        $detail = app(ConcertTracker::class)->detail($first->fresh(), 'post_concert_email');
        $this->assertSame('complete', $detail['status']['key']);
        $this->assertSame($detail['task']->id, $sent->fresh()->concert_tracker_task_id);
        $this->assertSame('Historical body', $sent->fresh()->body);
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->invalidated_at);
        Mail::assertNothingSent();
    }

    public function test_post_concert_content_uses_only_booked_services_and_allows_custom_automation_timing(): void
    {
        [, $event, , $booking] = $this->fixture();
        $booking->update(['wants_concert_videography' => false, 'wants_concert_photography' => false, 'wants_portrait_photography' => true]);
        $body = app(ConcertTracker::class)->detail($event->fresh(), 'post_concert_email')['renderedTemplate']['body'];
        $this->assertStringContainsString('Photo gallery released', $body);
        $this->assertStringNotContainsString('## Concert video review', $body);
        $this->assertStringNotContainsString('Upload studio files', $body);
        ConcertTrackerAutomationRule::where('task_key', 'post_concert_email')->update(['offset_days' => -1]);
        $candidate = app(ConcertTrackerAutomation::class)->candidatesForYear(2026)->firstWhere('task_key', 'post_concert_email');
        $this->assertSame('2026-10-20', $candidate['planned_at']->toDateString());
    }

    public function test_post_concert_email_can_be_sent_immediately_before_the_final_event(): void
    {
        Queue::fake();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
        [$staff, $event, $second] = $this->fixture();
        $page = route('admin.concert-tracker.tasks.show', [$event, 'post_concert_email']);
        $this->actingAs($staff)->get($page)->assertOk()->assertSee('You can still send now or choose another send date.');
        $this->post(route('admin.concert-tracker.tasks.email.send', [$event, 'post_concert_email']), ['recipient_email' => 'director@example.test'])->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::sole();
        $this->assertNull($delivery->scheduled_at);
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame('complete', app(ConcertTracker::class)->detail($second->fresh(), 'post_concert_email')['status']['key']);
        $this->post(route('admin.concert-tracker.tasks.email.send', [$second, 'post_concert_email']), ['recipient_email' => 'director@example.test'])->assertSessionHasErrors('email');
        Mail::assertSentCount(1);
        $this->travelTo(Carbon::parse('2026-10-22 10:00:00'));
        $this->get($page)->assertOk()->assertDontSee('You are sending before the recommended date');
    }

    public function test_email_completion_assigns_the_sender_and_shows_the_completion_timestamp(): void
    {
        Queue::fake();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-09-29 18:05:00'));
        [$staff, $event] = $this->fixture();
        $this->actingAs($staff)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'concert_week_email']), [
            'recipient_email' => 'alex@example.test',
        ])->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::latest('id')->firstOrFail();
        $this->assertNull($delivery->task->assignee_user_id);
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $task = $delivery->task->fresh();
        $this->assertSame($staff->id, $task->assignee_user_id);
        $this->assertSame($staff->id, $task->completed_by_user_id);
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'concert_week_email']))
            ->assertOk()->assertSee('Completed on')->assertSee('29 Sep 2026')->assertSee('6:05 pm')
            ->assertDontSee('id="tracker-due"', false);
    }

    public function test_completing_a_manual_task_preserves_its_assignee_and_reopening_restores_due_controls(): void
    {
        [$staff, $event] = $this->fixture();
        $editor = User::factory()->staff()->create();
        $action = app(UpdateConcertTrackerTask::class);
        $task = $action->execute($event, 'concert_edited', $staff, ['status' => 'in_progress', 'assignee_user_id' => $editor->id]);
        $task = $action->execute($event, 'concert_edited', $staff, ['status' => 'complete']);
        $this->assertSame($editor->id, $task->assignee_user_id);
        $this->assertSame($staff->id, $task->completed_by_user_id);
        $page = route('admin.concert-tracker.tasks.show', [$event, 'concert_edited']);
        $this->actingAs($staff)->get($page)->assertOk()->assertSee('Completed on');
        $action->execute($event, 'concert_edited', $staff, ['status' => 'in_progress']);
        $this->get($page)->assertOk()->assertDontSee('Completed on')->assertSee('id="tracker-due"', false);
        $this->assertSame($editor->id, $task->fresh()->assignee_user_id);
    }

    public function test_completed_source_task_uses_its_source_date_and_missing_dates_are_not_invented(): void
    {
        [$staff, $event, , $booking] = $this->fixture();
        $booking->update(['reviewed_at' => '2026-09-28 09:42:00']);
        $bar = app(BookingTrackerBar::class)->page($booking->fresh());
        $html = view('admin.tracking._task-bar', ['bar' => $bar, 'errors' => new ViewErrorBag])->render();
        $this->assertStringContainsString('Completed on', $html);
        $this->assertStringContainsString('28 Sep 2026', $html);
        $this->assertStringContainsString('9:42 am', $html);
        $bar['status']['completedAt'] = null;
        $html = view('admin.tracking._task-bar', ['bar' => $bar, 'errors' => new ViewErrorBag])->render();
        $this->assertStringContainsString('Not recorded', $html);
        $bar['status']['key'] = 'not_required';
        $html = view('admin.tracking._task-bar', ['bar' => $bar, 'errors' => new ViewErrorBag])->render();
        $this->assertStringNotContainsString('Completed on', $html);
    }

    public function test_completed_assignment_backfill_keeps_existing_assignments_and_skips_unknown_completers(): void
    {
        [$staff, $event] = $this->fixture();
        $other = User::factory()->staff()->create();
        $tasks = collect(['concert_edited', 'concert_week_email', 'post_concert_email'])->map(fn ($key) => ConcertTrackerTask::create([
            'scope_type' => 'event', 'scope_id' => $event->id, 'task_key' => $key, 'status' => 'complete', 'completed_at' => now(),
        ]));
        DB::table('concert_tracker_tasks')->whereIn('id', $tasks->take(2)->pluck('id')->all())->update(['completed_by_user_id' => $staff->id]);
        $tasks[1]->update(['assignee_user_id' => $other->id]);
        $migration = require database_path('migrations/2026_09_29_260000_assign_completed_concert_tasks.php');
        $migration->up();
        $this->assertSame($staff->id, $tasks[0]->fresh()->assignee_user_id);
        $this->assertSame($other->id, $tasks[1]->fresh()->assignee_user_id);
        $this->assertNull($tasks[2]->fresh()->assignee_user_id);
        $migration->down();
        $this->assertSame($staff->id, $tasks[0]->fresh()->assignee_user_id);
    }

    private function fixture(string $studioCode = 'AUR'): array
    {
        $staff = User::factory()->staff()->create(['name' => 'Morgan Vale']);
        $studio = Studio::factory()->create(['name' => 'Aurora Dance', 'code' => $studioCode]);
        $booking = ConcertBooking::create([
            'status' => 'approved', 'studio_name' => $studio->name, 'contact_name' => 'Alex Director',
            'contact_email' => 'alex@example.test', 'contact_phone' => '0400000000',
            'wants_concert_videography' => true, 'reviewed_by_user_id' => $staff->id, 'reviewed_at' => now(),
        ]);
        $first = SchedulingEvent::create([
            'studio_id' => $studio->id, 'name' => 'Junior Concert', 'event_type' => 'concert', 'event_date' => '2026-10-20',
        ]);
        $second = SchedulingEvent::create([
            'studio_id' => $studio->id, 'name' => 'Senior Concert', 'event_type' => 'concert', 'event_date' => '2026-10-21',
        ]);
        foreach ([[$first, 'Junior Concert'], [$second, 'Senior Concert']] as [$event, $title]) {
            ConcertBookingItem::create([
                'concert_booking_id' => $booking->id, 'item_type' => 'concert', 'title' => $title,
                'venue_name' => 'Theatre', 'venue_address' => 'Example street', 'starts_at' => '18:00',
                'finishes_at' => '20:00', 'event_date' => $event->event_date, 'scheduling_event_id' => $event->id,
            ]);
        }

        return [$staff, $first, $second, $booking];
    }
}
