<?php

namespace Tests\Feature\Programmes;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeRevision;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApiTokens;
use Tests\TestCase;

class ProgrammeEventListTest extends TestCase
{
    use AuthenticatesApiTokens, RefreshDatabase;

    public function test_lists_only_approved_uncancelled_events_with_latest_snapshot_metadata(): void
    {
        $staff = User::factory()->staff()->create();
        $approved = $this->programme('Approved concert');
        $this->revision($approved, $staff, 1, false);
        $latest = $this->revision($approved, $staff, 3, true);
        $this->programme('Draft only');
        $cancelled = $this->programme('Cancelled');
        $this->revision($cancelled, $staff, 1, true);
        $cancelled->event->update(['cancelled_at' => now()]);
        $this->actingAsApiUser($staff, ['programmes:read']);

        $response = $this->getJson('/api/staff/programme-events')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $approved->event->uuid)
            ->assertJsonPath('data.0.revision_uuid', $latest->uuid)
            ->assertJsonPath('data.0.item_count', 1)->assertJsonPath('data.0.preparation_ready', true)
            ->assertJsonPath('data.0.has_pdf', false)->assertJsonPath('meta.last_page', 1);
        $this->assertArrayNotHasKey('id', $response->json('data.0'));
        $this->assertArrayNotHasKey('admin_notes', $response->json('data.0'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_filters_paginates_and_flags_older_concert_snapshots_for_reapproval(): void
    {
        $staff = User::factory()->staff()->create();
        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            $this->revision($this->programme($name), $staff, 1, false);
        }
        $this->actingAsApiUser($staff, ['programmes:read']);
        $this->getJson('/api/staff/programme-events?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/staff/programme-events?search=Beta')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Beta')->assertJsonPath('data.0.preparation_ready', false);
        $this->getJson('/api/staff/programme-events?from=2027-01-01')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/staff/programme-events?per_page=101')->assertUnprocessable();
        $this->getJson('/api/staff/programme-events?from=invalid')->assertUnprocessable();
    }

    public function test_searches_partial_studio_names_and_concert_titles_without_including_drafts(): void
    {
        $staff = User::factory()->staff()->create();
        $studio = Studio::factory()->create(['name' => 'Energy Dance Studio']);
        $booking = ConcertBooking::create(['status' => 'approved', 'studio_id' => $studio->id, 'studio_name' => $studio->name, 'contact_name' => 'Director', 'contact_email' => 'director@example.test', 'contact_phone' => '0400000000']);
        foreach (['Junior concert', 'Senior concert', 'Junior draft'] as $title) {
            $programme = $this->programme('Legacy display name');
            $programme->event->update(['studio_id' => $studio->id]);
            $booking->items()->create(['item_type' => 'concert', 'title' => $title, 'event_date' => '2026-09-21', 'venue_name' => 'Theatre', 'venue_address' => 'Example Road', 'starts_at' => '18:00', 'finishes_at' => '20:00', 'scheduling_event_id' => $programme->scheduling_event_id]);
            if ($title !== 'Junior draft') {
                $this->revision($programme, $staff, 1, true);
            }
        }
        $this->actingAsApiUser($staff, ['programmes:read']);
        $this->getJson('/api/staff/programme-events?search=Ener')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.studio_name', 'Energy Dance Studio');
        $this->getJson('/api/staff/programme-events?search=Jun')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event_title', 'Junior concert');
        $this->getJson('/api/staff/programme-events?search=Senior')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event_title', 'Senior concert');
    }

    public function test_requires_admin_and_explicit_programme_ability(): void
    {
        $this->getJson('/api/staff/programme-events')->assertUnauthorized();
        $staff = User::factory()->staff()->create();
        $this->actingAsApiUser($staff, ['account:read']);
        $this->getJson('/api/staff/programme-events')->assertForbidden();
        $this->actingAsApiUser($staff, ['*']);
        $this->getJson('/api/staff/programme-events')->assertForbidden();
        $this->actingAsApiUser(User::factory()->customer()->create(), ['programmes:read']);
        $this->getJson('/api/staff/programme-events')->assertForbidden();
        $this->actingAsApiUser(User::factory()->crew()->create(), ['programmes:read']);
        $this->getJson('/api/staff/programme-events')->assertForbidden();
        $staff->update(['is_active' => false]);
        $this->actingAsApiUser($staff, ['programmes:read']);
        $this->getJson('/api/staff/programme-events')->assertForbidden();
    }

    private function programme(string $name): Programme
    {
        $event = SchedulingEvent::create(['name' => $name, 'event_type' => 'concert', 'event_date' => '2026-09-21', 'admin_notes' => 'Private']);

        return Programme::create(['scheduling_event_id' => $event->id, 'draft_version' => 1]);
    }

    private function revision(Programme $programme, User $staff, int $version, bool $ready): ProgrammeRevision
    {
        return $programme->revisions()->create([
            'version' => $version, 'confirmed_at' => now(), 'confirmed_by_user_id' => $staff->id,
            'snapshot' => ['nodes' => [
                ['kind' => 'item', 'title' => 'Ballet'] + ($ready ? ['folder_number' => '01', 'folder_title' => 'Ballet'] : []),
                ['kind' => 'item', 'title' => 'Interval', 'item_kind' => 'break'],
            ]],
        ]);
    }
}
