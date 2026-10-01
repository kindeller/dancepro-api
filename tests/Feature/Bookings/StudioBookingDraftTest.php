<?php

namespace Tests\Feature\Bookings;

use App\Features\Bookings\Mail\BookingSubmitted;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\StudioBookingDraft;
use App\Features\Bookings\Services\StudioBookingDrafts;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StudioBookingDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_draft_resumes_without_events_or_mail_and_submission_clears_it(): void
    {
        Mail::fake();
        [$user, $studio] = $this->account();
        $form = route('concert-bookings.create', ['studio' => $studio->uuid]);
        $hub = route('customer.studio-communications.index', $studio);
        $this->actingAs($user)->get($hub)->assertSee('Make booking');
        $data = ['studio_name' => $studio->name, 'contact_email' => 'unfinished@', 'wants_concert_photography' => false,
            'wants_concert_videography' => false, 'items' => [['item_type' => 'dress_rehearsal', 'title' => 'Autumn show', 'venue_search' => 'Theatre part']]];
        $this->post(route('customer.studio-bookings.draft', $studio), $data)->assertRedirect($form)->assertSessionHasNoErrors();
        $draft = StudioBookingDraft::firstOrFail();
        $this->assertSame(0, ConcertBooking::count());
        $this->assertSame(0, StudioConversationMessage::count());
        Mail::assertNothingSent();
        $this->flushSession();
        $this->get($hub)->assertSee('Continue booking')->assertDontSee('>My booking</a>', false);
        $this->get($form)->assertSee('Autumn show')->assertSee('unfinished@')->assertSee('Theatre part')->assertSee('Save draft');
        $data += ['draft_uuid' => $draft->uuid, 'draft_version' => $draft->version];
        $data['contact_name'] = 'Draft Director';
        $data['contact_email'] = 'director@example.test';
        $data['contact_phone'] = '0400000000';
        $data['studio'] = $studio->uuid;
        $data['accepted_requirements'] = ['accurate', 'portrait_space', 'no_personal_backdrop_photos'];
        $data['items'][0] += ['venue_uuid' => 'other', 'venue_name' => 'New Theatre', 'venue_address' => '10 Stage Road',
            'event_date' => today()->addMonth()->toDateString(), 'starts_at' => '16:00', 'finishes_at' => '18:00'];
        $this->post(route('concert-bookings.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, StudioBookingDraft::count());
        $this->assertSame(1, ConcertBooking::count());
        Mail::assertSent(BookingSubmitted::class, 1);
        $this->get($hub)->assertSee('Make booking')->assertDontSee('Continue booking')->assertSee('Pending approval');
    }

    public function test_drafts_are_private_and_stale_saves_cannot_overwrite_newer_changes(): void
    {
        [$user, $studio] = $this->account();
        $url = route('customer.studio-bookings.draft', $studio);
        $this->actingAs($user)->post($url, ['contact_name' => 'First draft'])->assertSessionHasNoErrors();
        $draft = StudioBookingDraft::firstOrFail();
        $this->post($url, ['draft_uuid' => $draft->uuid, 'draft_version' => $draft->version, 'contact_name' => 'Newer draft'])->assertSessionHasNoErrors();
        $this->post($url, ['draft_uuid' => $draft->uuid, 'draft_version' => $draft->version, 'contact_name' => 'Stale draft'])->assertSessionHasErrors('draft');
        $this->assertSame('Newer draft', $draft->fresh()->data['contact_name']);
        app(StudioBookingDrafts::class)->submitted($studio, ['draft_uuid' => $draft->uuid, 'draft_version' => $draft->version]);
        $this->assertNotNull($draft->fresh());
        $this->actingAs(User::factory()->customer()->create())->post($url, [])->assertForbidden();
        $this->get(route('concert-bookings.create', ['studio' => $studio->uuid]))->assertForbidden();
    }

    private function account(): array
    {
        $user = User::factory()->customer()->create();
        $studio = Studio::factory()->create();
        StudioAccountAccess::create(['studio_id' => $studio->id, 'user_id' => $user->id, 'email' => $user->email, 'status' => 'active', 'role' => 'director']);

        return [$user, $studio];
    }
}
