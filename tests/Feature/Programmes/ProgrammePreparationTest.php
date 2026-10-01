<?php

namespace Tests\Feature\Programmes;

use App\Features\Bookings\Actions\StoreConcertBookingProgramme;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Programmes\Actions\ConfirmProgramme;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeNode;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesApiTokens;
use Tests\TestCase;

class ProgrammePreparationTest extends TestCase
{
    use AuthenticatesApiTokens, RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->staff()->create();
        $this->actingAs($this->staff);
        config(['operations.filesystem_disk' => 'local', 'operations.malware_scan.enabled' => false]);
        Storage::fake('local');
    }

    public function test_start_is_explicit_idempotent_and_copies_the_source_with_nonconsecutive_days(): void
    {
        $event = $this->event();
        $event->shifts()->create(['shift_date' => '2026-09-04', 'period' => 'morning']);
        $event->shifts()->create(['shift_date' => '2026-09-04', 'period' => 'afternoon']);
        $event->shifts()->create(['shift_date' => '2026-09-11', 'period' => 'morning']);
        Storage::disk('local')->put('original.pdf', '%PDF-1.4 original');
        $event->update(['programme_path' => 'original.pdf']);
        $this->get($this->url($event))->assertOk()->assertSee('Upload and read programme');
        $this->assertDatabaseCount('programmes', 0);
        $programme = $this->start($event);
        $this->post($this->url($event))->assertRedirect();
        $this->assertDatabaseCount('programmes', 1);
        $this->assertDatabaseCount('programme_sources', 1);
        $this->assertSame(['2026-09-04', '2026-09-11'], array_column($programme->draftNodes(), 'date'));
        $this->assertNotSame('original.pdf', $programme->source->path);
        $this->assertSame('%PDF-1.4 original', Storage::disk('local')->get($programme->source->path));
        $this->get($this->url($event))->assertOk()->assertSee('Running order')->assertSee('programme-config');
    }

    public function test_missing_source_does_not_prevent_manual_preparation(): void
    {
        $event = $this->event('concert');
        $event->update(['programme_path' => 'missing.pdf']);
        $programme = $this->start($event);
        $this->assertNull($programme->programme_source_id);
        $this->get($this->url($event))->assertOk()->assertSee('No source PDF retained');
    }

    public function test_image_attachment_is_preserved_without_becoming_a_pdf_source(): void
    {
        $event = $this->event('concert');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aG9sAAAAASUVORK5CYII=');
        Storage::disk('local')->put('programme.png', $image);
        $event->update(['programme_path' => 'programme.png']);
        $programme = $this->start($event);
        $this->assertNull($programme->programme_source_id);
        $this->assertDatabaseCount('programme_sources', 0);
        $this->assertSame($image, Storage::disk('local')->get('programme.png'));
        $this->assertSame('programme.png', $event->fresh()->programme_path);
        $this->get($this->url($event))->assertOk()->assertSee('No source PDF retained');
    }

    public function test_draft_confirmation_and_future_edits_preserve_identity_and_snapshot(): void
    {
        $event = $this->event();
        $programme = $this->start($event);
        $nodes = $this->competitionNodes();
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => $nodes])->assertOk()->assertJsonPath('data.version', 2);
        $this->getJson($this->url($event, '/snapshot'))->assertNotFound();
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertOk();
        $revision = $programme->revisions()->firstOrFail();
        $this->assertSame($this->staff->id, $revision->confirmed_by_user_id);
        $this->assertNotNull($revision->confirmed_at);
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertOk();
        $this->assertDatabaseCount('programme_revisions', 1);
        $nodes[2]['title'] = 'Renamed pair';
        [$nodes[2], $nodes[3]] = [$nodes[3], $nodes[2]];
        $this->putJson($this->url($event, '/draft'), ['version' => 2, 'nodes' => $nodes])->assertOk();
        $this->getJson($this->url($event, '/snapshot'))->assertJsonPath('data.version', 2)->assertJsonPath('data.nodes.2.title', 'Dancer A & Dancer B');
        $this->postJson($this->url($event, '/confirm'), ['version' => 3])->assertOk();
        $this->getJson($this->url($event, '/snapshot'))->assertJsonPath('data.nodes.3.uuid', $nodes[3]['uuid'])->assertJsonPath('data.nodes.3.title', 'Renamed pair');
        $this->getJson($this->url($event, '/snapshot/'.$revision->uuid))->assertJsonPath('data.nodes.2.title', 'Dancer A & Dancer B');
        $this->assertSame(4, $programme->nodes()->where('is_active', true)->count());
    }

    public function test_stale_save_confirmation_and_source_upload_do_not_overwrite_work(): void
    {
        $event = $this->event('concert');
        $programme = $this->start($event);
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => [$this->node('item', null, 'Ballet')]])->assertOk();
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => []])->assertConflict();
        $this->postJson($this->url($event, '/confirm'), ['version' => 1])->assertConflict();
        $this->postJson($this->url($event, '/source'), ['version' => 1, 'programme' => $this->pdf()])->assertConflict();
        $this->assertCount(1, $programme->draftNodes());
        $this->assertDatabaseCount('programme_sources', 0);
    }

    public function test_duplicate_competitors_are_flagged_only_within_their_section_and_gaps_are_preserved(): void
    {
        $event = $this->event();
        $this->start($event);
        $nodes = $this->competitionNodes(); // numbers 1 and 3, intentionally leaving a gap
        $nodes[3]['number'] = '01';
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => $nodes])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertUnprocessable()->assertJsonValidationErrors('nodes.3.number');
        $nodes[3]['number'] = '3';
        $section = $this->node('section', $nodes[0]['uuid'], 'Jazz', '2');
        $nodes[] = $section;
        $nodes[] = $this->node('item', $section['uuid'], 'Competitor', '1');
        $this->putJson($this->url($event, '/draft'), ['version' => 2, 'nodes' => $nodes])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 3])->assertOk();
        $this->getJson($this->url($event, '/snapshot'))->assertJsonPath('data.nodes.3.number', '3');
    }

    public function test_invalid_hierarchy_foreign_identity_and_kind_changes_are_rejected(): void
    {
        $event = $this->event();
        $programme = $this->start($event);
        $day = $programme->draftNodes()[0];
        $other = $this->event();
        $foreignDay = $this->start($other)->draftNodes()[0];
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => [$foreignDay]])->assertUnprocessable();
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => [$this->node('item', $day['uuid'], 'Orphan', '1')]])->assertUnprocessable();
        $day['kind'] = 'group';
        $this->putJson($this->url($other, '/draft'), ['version' => 1, 'nodes' => [$day]])->assertUnprocessable();
        $this->assertSame(1, $programme->fresh()->draft_version);
    }

    public function test_concert_numbering_resets_and_class_only_labels_are_allowed(): void
    {
        $event = $this->event('concert');
        $this->start($event);
        $act1 = $this->node('group', null, 'Act 1');
        $act2 = $this->node('group', null, 'Act 2');
        $nodes = [$act1, $this->node('item', $act1['uuid'], 'Teen Ballet', '1'), $act2, $this->node('item', $act2['uuid'], 'Finale', '1')];
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => $nodes])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertOk();
        $this->getJson($this->url($event, '/snapshot'))->assertJsonPath('data.nodes.1.title', 'Teen Ballet')->assertJsonPath('data.nodes.3.number', '1');
    }

    public function test_incomplete_drafts_can_be_saved_but_not_confirmed(): void
    {
        $event = $this->event();
        $this->start($event);
        $nodes = $this->competitionNodes();
        $nodes[2]['title'] = '';
        $nodes[1]['number'] = null;
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => $nodes])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertUnprocessable()->assertJsonValidationErrors(['nodes.1.number', 'nodes.2.title']);
        $this->assertDatabaseCount('programme_revisions', 0);
    }

    public function test_removed_nodes_keep_their_identity_record_and_confirmed_history(): void
    {
        $event = $this->event('concert');
        $programme = $this->start($event);
        $node = $this->node('item', null, 'Opening');
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => [$node]])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertOk();
        $this->putJson($this->url($event, '/draft'), ['version' => 2, 'nodes' => []])->assertOk();
        $this->assertFalse(ProgrammeNode::where('uuid', $node['uuid'])->firstOrFail()->is_active);
        $this->assertSame([], $programme->draftNodes());
        $this->getJson($this->url($event, '/snapshot'))->assertJsonPath('data.nodes.0.uuid', $node['uuid']);
    }

    public function test_replacing_source_retains_old_pdf_and_does_not_change_confirmed_snapshot_or_event_attachment(): void
    {
        $event = $this->event('concert');
        Storage::disk('local')->put('event.pdf', '%PDF-1.4 original');
        $event->update(['programme_path' => 'event.pdf']);
        $programme = $this->start($event);
        $source = $programme->source;
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => [$this->node('item', null, 'Opening')]])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertOk();
        $this->post($this->url($event, '/source'), ['version' => 2, 'programme' => $this->pdf()])->assertRedirect();
        $this->assertNotSame($source->id, $programme->fresh()->programme_source_id);
        Storage::disk('local')->assertExists($source->path);
        $this->assertSame('event.pdf', $event->fresh()->programme_path);
        $this->getJson($this->url($event, '/snapshot'))->assertJsonPath('data.source_uuid', $source->uuid);
        $this->get($this->url($event, '/sources/'.$source->uuid))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get($this->url($event))->assertOk()->assertSee('Version 2');
    }

    public function test_existing_booking_attachment_replacement_cannot_delete_retained_programme_source(): void
    {
        $event = $this->event('concert');
        $booking = ConcertBooking::create(['studio_name' => 'Example', 'contact_name' => 'Director', 'contact_email' => 'director@example.test', 'contact_phone' => '0000 000 000', 'status' => 'pending']);
        $item = ConcertBookingItem::create(['concert_booking_id' => $booking->id, 'item_type' => 'concert', 'title' => 'Show', 'venue_name' => 'Theatre', 'venue_address' => 'Example street', 'starts_at' => '18:00', 'finishes_at' => '20:00', 'event_date' => '2026-09-04', 'scheduling_event_id' => $event->id]);
        app(StoreConcertBookingProgramme::class)->execute($item, $this->pdf());
        $programme = $this->start($event->fresh());
        $path = $programme->source->path;
        app(StoreConcertBookingProgramme::class)->execute($item->fresh(), $this->pdf());
        Storage::disk('local')->assertExists($path);
        $this->assertNotSame($path, $item->fresh()->programme_path);
    }

    public function test_access_boundaries_and_cross_event_sources_and_revisions(): void
    {
        $event = $this->event('concert');
        $programme = $this->start($event);
        $this->post($this->url($event, '/source'), ['version' => 1, 'programme' => $this->pdf()])->assertRedirect();
        $this->putJson($this->url($event, '/draft'), ['version' => 2, 'nodes' => [$this->node('item', null, 'Opening')]])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 3])->assertOk();
        $source = $programme->fresh()->source;
        $revision = $programme->revisions()->firstOrFail();
        $other = $this->event('concert');
        $this->start($other);
        $this->get($this->url($other, '/sources/'.$source->uuid))->assertNotFound();
        $this->getJson($this->url($other, '/snapshot/'.$revision->uuid))->assertNotFound();
        foreach ([User::factory()->customer()->create(), User::factory()->crew()->create(), User::factory()->staff()->inactive()->create()] as $user) {
            $this->actingAs($user);
            $this->get($this->url($event))->assertForbidden();
            $this->post($this->url($event))->assertForbidden();
            $this->putJson($this->url($event, '/draft'), ['version' => 3, 'nodes' => []])->assertForbidden();
            $this->postJson($this->url($event, '/confirm'), ['version' => 3])->assertForbidden();
            $this->get($this->url($event, '/sources/'.$source->uuid))->assertForbidden();
            $this->getJson($this->url($event, '/snapshot'))->assertForbidden();
        }
    }

    public function test_preparation_api_requires_explicit_ability_and_staff_but_no_crew_role(): void
    {
        $event = $this->event('concert');
        $this->start($event);
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => [$this->node('item', null, 'Opening')]])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertOk();
        $url = '/api/staff/events/'.$event->uuid.'/programme';
        $this->actingAsApiUser($this->staff, ['*']);
        $this->getJson($url)->assertForbidden();
        $this->actingAsApiUser($this->staff, ['programmes:read']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.event_uuid', $event->uuid)->assertJsonMissingPath('data.id')->assertJsonMissingPath('data.notes');
        $this->actingAsApiUser(User::factory()->customer()->create(), ['programmes:read']);
        $this->getJson($url)->assertForbidden();
    }

    public function test_cancelled_events_are_read_only_and_not_available_for_preparation(): void
    {
        $event = $this->event('concert');
        $this->start($event);
        $event->update(['cancelled_at' => now()]);
        $this->get($this->url($event))->assertOk()->assertSee('This event is cancelled');
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => []])->assertForbidden();
        $this->postJson($this->url($event, '/confirm'), ['version' => 1])->assertForbidden();
        $this->getJson($this->url($event, '/snapshot'))->assertConflict();
    }

    public function test_invalid_list_unknown_fields_and_script_content_are_handled_safely(): void
    {
        $event = $this->event('concert');
        $this->start($event);
        $node = $this->node('item', null, '</script><script>alert(1)</script>');
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => ['bad' => $node]])->assertUnprocessable();
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => [$node + ['internal_notes' => 'private']]])->assertUnprocessable();
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => [$node]])->assertOk();
        $this->get($this->url($event))->assertOk()->assertDontSee('</script><script>alert(1)</script>', false);
        $node['kind'] = 'group';
        $this->putJson($this->url($event, '/draft'), ['version' => 2, 'nodes' => [$node]])->assertUnprocessable();
    }

    public function test_preparation_pdf_is_the_confirmed_source_not_a_later_draft_upload(): void
    {
        $event = $this->event('concert');
        $programme = $this->start($event);
        $this->post($this->url($event, '/source'), ['version' => 1, 'programme' => $this->pdf()])->assertRedirect();
        $original = $programme->fresh()->source;
        $this->putJson($this->url($event, '/draft'), ['version' => 2, 'nodes' => [$this->node('item', null, 'Opening')]])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 3])->assertOk();
        $this->post($this->url($event, '/source'), ['version' => 3, 'programme' => UploadedFile::fake()->createWithContent('replacement.pdf', "%PDF-1.4\nreplacement\n%%EOF")])->assertRedirect();
        $revision = $programme->revisions()->firstOrFail();
        $this->actingAsApiUser($this->staff, ['programmes:read']);
        $response = $this->get('/api/staff/events/'.$event->uuid.'/programme/source')->assertOk();
        $this->assertSame(Storage::disk('local')->get($original->path), $response->streamedContent());
        app(ConfirmProgramme::class)->execute($programme, $this->staff, 4);
        $pinned = $this->get('/api/staff/events/'.$event->uuid.'/programme/source/'.$revision->uuid)->assertOk();
        $this->assertSame(Storage::disk('local')->get($original->path), $pinned->streamedContent());
        $event->update(['cancelled_at' => now()]);
        $this->getJson('/api/staff/events/'.$event->uuid.'/programme/source')->assertConflict();
    }

    public function test_concert_folder_numbers_follow_order_preserve_printed_numbers_and_skip_breaks(): void
    {
        $event = $this->event('concert');
        $programme = $this->start($event);
        $first = $this->node('item', null, 'Opening', '1');
        $interval = $this->node('item', null, 'Interval') + ['item_kind' => 'break'];
        $second = $this->node('item', null, 'Second half', '1');
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => [$first, $interval, $second]])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertOk();
        $revision = $programme->revisions()->firstOrFail();
        $this->getJson($this->url($event, '/snapshot'))
            ->assertJsonPath('data.nodes.0.folder_number', '01')
            ->assertJsonPath('data.nodes.1.folder_number', null)
            ->assertJsonPath('data.nodes.2.folder_number', '02')
            ->assertJsonPath('data.nodes.2.number', '1');
        $added = $this->node('item', null, 'Speech') + ['item_kind' => 'presentation'];
        $this->putJson($this->url($event, '/draft'), ['version' => 2, 'nodes' => [$second, $added, $first]])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 3])->assertOk();
        $this->getJson($this->url($event, '/snapshot'))
            ->assertJsonPath('data.nodes.0.uuid', $second['uuid'])
            ->assertJsonPath('data.nodes.0.folder_number', '01')
            ->assertJsonPath('data.nodes.1.folder_number', '02')
            ->assertJsonPath('data.nodes.2.folder_number', '03');
        $this->getJson($this->url($event, '/snapshot/'.$revision->uuid))->assertJsonPath('data.nodes.0.uuid', $first['uuid'])->assertJsonPath('data.nodes.2.folder_number', '02');
        $this->putJson($this->url($event, '/draft'), ['version' => 3, 'nodes' => [$second, $first]])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 4])->assertOk();
        $this->getJson($this->url($event, '/snapshot'))->assertJsonPath('data.nodes.1.folder_number', '02');
    }

    public function test_concert_folder_numbers_cross_99_and_follow_legacy_group_display_order(): void
    {
        $event = $this->event('concert');
        $this->start($event);
        $actA = $this->node('group', null, 'First');
        $actB = $this->node('group', null, 'Second');
        $nodes = [$actB, $actA];
        for ($i = 1; $i <= 100; $i++) {
            $nodes[] = $this->node('item', $i <= 50 ? $actA['uuid'] : $actB['uuid'], 'Item '.$i, (string) $i);
        }
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => $nodes])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertOk();
        $this->getJson($this->url($event, '/snapshot'))
            ->assertJsonPath('data.nodes.52.folder_number', '01')
            ->assertJsonPath('data.nodes.2.folder_number', '51')
            ->assertJsonPath('data.nodes.50.folder_number', '99')
            ->assertJsonPath('data.nodes.51.folder_number', '100');
    }

    public function test_reapproving_a_legacy_concert_creates_a_numbered_revision_without_rewriting_history(): void
    {
        $event = $this->event('concert');
        $programme = $this->start($event);
        $nodes = [$this->node('item', null, 'Opening', '7')];
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => $nodes])->assertOk();
        $legacy = $programme->revisions()->create(['version' => 2, 'snapshot' => ['nodes' => $nodes], 'confirmed_at' => now(), 'confirmed_by_user_id' => $this->staff->id]);
        $originalSnapshot = $legacy->fresh()->snapshot;
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertOk()->assertJsonPath('data.version', 3);
        $this->assertSame($originalSnapshot, $legacy->fresh()->snapshot);
        $this->getJson($this->url($event, '/snapshot'))->assertJsonPath('data.nodes.0.folder_number', '01')->assertJsonPath('data.nodes.0.number', '7');
        $this->postJson($this->url($event, '/confirm'), ['version' => 3])->assertOk();
        $this->assertSame(2, $programme->revisions()->count());
    }

    public function test_folder_titles_are_cleaned_while_display_and_video_titles_keep_punctuation(): void
    {
        $event = $this->event('concert');
        $programme = $this->start($event);
        $title = 'VaVa Voom/That’s What I Like; "Live", Café!';
        $node = $this->node('item', null, $title, '1');
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => [$node]])->assertOk()->assertJsonPath('data.nodes.0.title', $title);
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertOk();
        $this->assertSame($title, $programme->draftNodes()[0]['title']);
        $this->getJson($this->url($event, '/snapshot'))
            ->assertJsonPath('data.nodes.0.title', $title)
            ->assertJsonPath('data.nodes.0.folder_title', 'VaVa Voom Thats What I Like Live Cafe')
            ->assertJsonPath('data.nodes.0.folder_number', '01');
        $competition = $this->event();
        $this->start($competition);
        $nodes = $this->competitionNodes();
        $this->putJson($this->url($competition, '/draft'), ['version' => 1, 'nodes' => $nodes])->assertOk();
        $this->postJson($this->url($competition, '/confirm'), ['version' => 2])->assertOk();
        $snapshot = $this->getJson($this->url($competition, '/snapshot'))->assertOk()->json('data.nodes');
        $this->assertSame('Dancer A & Dancer B', $snapshot[2]['title']);
        $this->assertArrayNotHasKey('folder_title', $snapshot[2]);
    }

    public function test_punctuation_only_concert_title_can_be_drafted_but_cannot_create_an_empty_folder_title(): void
    {
        $event = $this->event('concert');
        $this->start($event);
        $node = $this->node('item', null, '!!! / ?');
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => [$node]])->assertOk();
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertUnprocessable()->assertJsonValidationErrors('nodes.0.title');
        $this->assertDatabaseCount('programme_revisions', 0);
    }

    public function test_reapproving_a_numbered_snapshot_adds_folder_title_without_changing_old_title_cards(): void
    {
        $event = $this->event('concert');
        $programme = $this->start($event);
        $node = $this->node('item', null, 'Rock / Roll!', '1');
        $this->putJson($this->url($event, '/draft'), ['version' => 1, 'nodes' => [$node]])->assertOk();
        $legacy = $programme->revisions()->create(['version' => 2, 'snapshot' => ['nodes' => [$node + ['folder_number' => '01']]], 'confirmed_at' => now(), 'confirmed_by_user_id' => $this->staff->id]);
        $originalSnapshot = $legacy->fresh()->snapshot;
        $this->postJson($this->url($event, '/confirm'), ['version' => 2])->assertOk()->assertJsonPath('data.version', 3);
        $this->assertSame($originalSnapshot, $legacy->fresh()->snapshot);
        $this->getJson($this->url($event, '/snapshot'))->assertJsonPath('data.nodes.0.title', 'Rock / Roll!')->assertJsonPath('data.nodes.0.folder_title', 'Rock Roll');
        $this->postJson($this->url($event, '/confirm'), ['version' => 3])->assertOk();
        $this->assertSame(2, $programme->revisions()->count());
    }

    private function event(string $type = 'competition'): SchedulingEvent
    {
        return SchedulingEvent::create(['name' => 'Example event', 'event_type' => $type, 'event_date' => '2026-09-04']);
    }

    private function start(SchedulingEvent $event): Programme
    {
        $this->post($this->url($event))->assertRedirect();

        return Programme::where('scheduling_event_id', $event->id)->firstOrFail();
    }

    private function node(string $kind, ?string $parent, string $title, ?string $number = null): array
    {
        return ['uuid' => (string) Str::uuid(), 'parent_uuid' => $parent, 'kind' => $kind, 'title' => $title, 'number' => $number];
    }

    private function competitionNodes(): array
    {
        $day = $this->node('day', null, 'Day 1') + ['date' => '2026-09-04'];
        $section = $this->node('section', $day['uuid'], 'Lyrical duos', '1');

        return [$day, $section, $this->node('item', $section['uuid'], 'Dancer A & Dancer B', '1'), $this->node('item', $section['uuid'], 'Competitor', '3')];
    }

    private function url(SchedulingEvent $event, string $suffix = ''): string
    {
        return '/admin/scheduling-events/'.$event->uuid.'/programme'.$suffix;
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('programme.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    }
}
