<?php

namespace Tests\Feature\Tracking;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Crew\Models\CrewRole;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeSource;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\ConcertEventRoleSeats;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Studios\Models\StudioContact;
use App\Features\Studios\Models\StudioEventFile;
use App\Features\Studios\Notifications\StudioAccountInvitation;
use App\Features\Tracking\Actions\CreateConcertTrackerAlerts;
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
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Tracking\Services\ConcertTrackerAutomation;
use App\Features\Tracking\Services\EmailContentFormatter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConcertTrackerTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSee('Final numbers received')
            ->assertSee('rowspan="2"', false)
            ->assertSeeInOrder([$first->name, $second->name]);

        foreach (['preparation' => 'Photo uploads', 'editing' => 'Director corrections', 'completion' => 'Payment received'] as $phase => $task) {
            $this->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => $phase]))
                ->assertOk()->assertSee($task);
        }

        $this->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => 'all']))
            ->assertOk()
            ->assertSee('Complete automatically from the unified booking record.')
            ->assertSee('Record and resolve anything preventing delivery');
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

    public function test_programme_cell_opens_upload_directly_and_replaces_due_date_with_upload_audit(): void
    {
        [$staff, $event] = $this->fixture();
        $programme = Programme::create(['scheduling_event_id' => $event->id, 'updated_by_user_id' => $staff->id]);

        $empty = app(ConcertTracker::class)->page('date', 2026, 'all')['rows']->firstWhere(fn (array $row): bool => $row['event']->is($event))['tasks']['programme_prepared'];
        $this->assertSame(route('admin.programmes.show', $event), $empty['detailUrl']);
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
        $this->assertStringContainsString($event->name, $delivery->subject);
        $this->assertSame('queued', $delivery->status);
        Queue::assertPushed(SendConcertTrackerEmail::class, fn ($job) => $job->deliveryId === $delivery->id);
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
        $this->actingAs($customer)->get(route('customer.studio-communications.index', $studio))
            ->assertOk()->assertSee($delivery->subject)->assertSee('The studio details are confirmed.')
            ->assertSee('From DancePro')->assertSee('Sent to DancePro');
        $this->post(route('customer.studio-communications.store', $studio), [
            'subject' => 'Updated arrival details', 'body' => 'Please arrive through the stage door.',
            'attachment' => UploadedFile::fake()->create('stage-door-map.pdf', 120, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('studio_conversation_messages', [
            'studio_id' => $studio->id, 'direction' => 'to_dancepro', 'subject' => 'Updated arrival details',
        ]);

        $this->actingAs($staff)->get(route('admin.concert-tracker.studio-communications.index'))
            ->assertOk()->assertSee($studio->name)->assertSee('Updated arrival details');
        $this->get(route('admin.concert-tracker.studio-communications.show', $studio))
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

        $this->delete(route('admin.studios.account-access.revoke', [$studio, $access]))
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

        $this->actingAs($customer)->get(route('customer.studio-communications.index', $event->studio))
            ->assertOk()->assertSee('Event details')->assertSee('Photographer P1')->assertSee('Videographer');
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
            'roles' => ['concert-videographer' => [['row' => 'J', 'seat' => '20']]],
        ])->assertSessionHasErrors('roles.concert-videographer');
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
            ->assertRedirect()->assertSessionHas('status', 'Email queued again.');
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
            ->assertSee('Final numbers &amp; technical information', false)
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
        $this->get(route('admin.concert-tracker.alerts.index'))->assertOk()->assertSee('Final numbers received');
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
            ->assertOk()->assertSee('Fri 30 Oct 2026');
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
        $this->assertStringContainsString('- **Reserved DancePro seats:** Photographer P1: Row H, Seat 12 | Videographer V: Row J, Seat 20; Row J, Seat 21', $video['body']);
        $this->assertSame(2, substr_count($video['body'], '- **Reserved DancePro seats:**'));
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

    private function fixture(): array
    {
        $staff = User::factory()->staff()->create(['name' => 'Morgan Vale']);
        $studio = Studio::factory()->create(['name' => 'Aurora Dance', 'code' => 'AUR']);
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
