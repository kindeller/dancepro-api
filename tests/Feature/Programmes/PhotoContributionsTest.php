<?php

namespace Tests\Feature\Programmes;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Concerts\Models\Concert;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Media\Models\MediaCollection;
use App\Features\Programmes\Actions\SyncProgramme;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Policies\ProgrammePolicy;
use App\Features\Programmes\Services\CompetitionProgrammeNodes;
use App\Features\Programmes\Services\VerifyPhotoBytes;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Services\ConcertTracker;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AuthenticatesApiTokens;
use Tests\TestCase;

class PhotoContributionsTest extends TestCase
{
    use AuthenticatesApiTokens, RefreshDatabase;

    private function fixture(bool $competition = false): array
    {
        $user = User::factory()->staff()->create();
        $event = SchedulingEvent::create(['name' => 'Contribution test', 'event_type' => $competition ? 'competition' : 'concert', 'event_date' => '2026-09-04']);
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $role = CrewRole::where('code', $competition ? 'competition-photographer-p1' : 'concert-photographer-p1')->firstOrFail();
        $crew = CrewProfile::factory()->create();
        $shift = $event->shifts()->create(['shift_date' => '2026-09-04', 'period' => $competition ? 'morning' : null]);
        $assignment = $shift->assignments()->create(['crew_role_id' => $role->id, 'crew_profile_id' => $crew->id, 'status' => 'published']);
        $day = (string) Str::uuid();
        $section = (string) Str::uuid();
        $nodes = array_map(fn ($i) => [
            'uuid' => (string) Str::uuid(), 'kind' => 'item', 'title' => "Dance $i", 'number' => (string) $i,
            'parent_uuid' => $competition ? $section : null,
        ] + ($competition ? [] : ['folder_number' => "0$i", 'folder_title' => "Dance $i"]), [1, 2]);
        $items = array_column($nodes, 'uuid');
        if ($competition) {
            $nodes = array_merge([
                ['uuid' => $day, 'kind' => 'day', 'title' => 'Day', 'date' => '2026-09-04'],
                ['uuid' => $section, 'kind' => 'section', 'title' => 'Section', 'number' => '1', 'parent_uuid' => $day],
            ], $nodes);
        }
        $revision = $programme->revisions()->create(['version' => 1, 'confirmed_at' => now(), 'snapshot' => [
            'schema_version' => 1, 'event_uuid' => $event->uuid, 'event_type' => $event->event_type->value,
            'programme_uuid' => $programme->uuid, 'event_name' => $event->name, 'version' => 1, 'nodes' => $nodes,
        ]]);
        $plan = app(SyncProgramme::class)->execute($event, $user, $revision->uuid, $nodes);
        $body = ['plan_uuid' => $plan->uuid, 'crew_uuid' => $crew->uuid, 'role_uuid' => $role->uuid, 'item_uuids' => $items];
        $this->actingAsApiUser($user, ['concert-media:upload']);

        return compact('event', 'programme', 'revision', 'plan', 'body', 'assignment', 'crew', 'role', 'user', 'shift');
    }

    private function url(SchedulingEvent $event): string
    {
        return "/api/staff/events/{$event->uuid}/programme/photo-contributions";
    }

    private function jpegBytes(int $red = 40): string
    {
        $image = imagecreatetruecolor(24, 20);
        imagefill($image, 0, 0, imagecolorallocate($image, $red, 70, 100));
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        unset($image);

        return $bytes;
    }

    private function reserveBytes(array $f, string $bytes, ?string $clientUuid = null): array
    {
        $body = $this->fileBody($f);
        $body['photos'][0]['byte_count'] = strlen($bytes);
        $body['photos'][0]['sha256'] = hash('sha256', $bytes);
        if ($clientUuid !== null) {
            $body['photos'][0]['photo_uuid'] = $clientUuid;
        }
        $url = str_replace('photo-contributions', 'photo-files', $this->url($f['event']));

        return $this->postJson($url, $body)->assertOk()->json('data.files.0');
    }

    private function sendBytes(SchedulingEvent $event, string $uuid, string $bytes): TestResponse
    {
        return $this->call('PUT', "/api/staff/events/{$event->uuid}/programme/photo-files/$uuid/content", [], [], [],
            $this->transformHeadersToServerVars(['Content-Type' => 'image/jpeg', 'Accept' => 'application/json']), $bytes);
    }

    public function test_concert_upload_automatically_builds_private_gallery_without_manual_sync(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $studio = Studio::factory()->create();
        $booking = ConcertBooking::create([
            'studio_name' => $studio->name, 'contact_name' => 'Director', 'contact_email' => 'director@example.test',
            'contact_phone' => '0400000000', 'status' => 'approved', 'studio_id' => $studio->id,
        ]);
        $item = ConcertBookingItem::create([
            'concert_booking_id' => $booking->id, 'scheduling_event_id' => $f['event']->id,
            'item_type' => 'concert', 'title' => 'Concert', 'event_date' => '2026-09-04',
            'venue_name' => 'Theatre', 'venue_address' => 'Example street', 'starts_at' => '18:00', 'finishes_at' => '20:00',
        ]);
        $concert = Concert::factory()->published()->create([
            'studio_id' => $studio->id, 'concert_booking_item_id' => $item->id,
        ]);
        $bytes = $this->jpegBytes();
        $reservation = $this->reserveBytes($f, $bytes);
        $this->assertDatabaseCount('media_assets', 0);
        $this->sendBytes($f['event'], $reservation['uuid'], $bytes)->assertOk();
        $album = MediaCollection::where('concert_id', $concert->id)->firstOrFail();
        $this->assertSame('private', $album->visibility->value);
        $this->assertSame('draft', $album->status->value);
        $this->assertNull($album->published_at);
        $asset = $album->assets()->sole();
        $photo = ProgrammePhotoFile::where('uuid', $reservation['uuid'])->firstOrFail();
        $this->assertSame($photo->storage_key, $asset->storage_key);
        Storage::disk('local')->assertExists($asset->thumbnail_storage_key);
        $this->sendBytes($f['event'], $reservation['uuid'], $bytes)->assertOk();
        $duplicate = $this->reserveBytes($f, $bytes);
        $this->sendBytes($f['event'], $duplicate['uuid'], $bytes)->assertOk();
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('media_collections', 1);

        $secondBytes = $this->jpegBytes(90);
        $second = $this->reserveBytes($f, $secondBytes);
        $this->sendBytes($f['event'], $second['uuid'], $secondBytes)->assertOk();
        $this->assertSame(2, $album->assets()->count());
        $this->assertSame($asset->id, $album->fresh()->cover_media_asset_id);
        $this->assertSame('private', $album->fresh()->visibility->value);

        $url = str_replace('photo-contributions', 'top-pics', $this->url($f['event']));
        $this->postJson($url, ['changes' => [[
            'file_uuid' => $photo->uuid, 'change_uuid' => (string) Str::uuid(), 'is_top_pic' => true,
        ]]])->assertOk();
        $this->assertTrue($asset->fresh()->metadata['is_top_pic']);
    }

    public function test_top_pic_changes_after_upload_update_both_event_types_without_reuploading(): void
    {
        Storage::fake('local');
        foreach ([false, true] as $competition) {
            $f = $this->fixture($competition);
            $bytes = $this->jpegBytes();
            $receipt = $this->reserveBytes($f, $bytes);
            $this->sendBytes($f['event'], $receipt['uuid'], $bytes)->assertOk();
            $file = ProgrammePhotoFile::where('uuid', $receipt['uuid'])->firstOrFail();
            $original = $file->only(['storage_key', 'verified_at', 'sha256', 'uploaded_by_user_id']);
            $this->postJson($this->finishUrl($f), $f['body'])->assertOk();
            $url = "/api/staff/events/{$f['event']->uuid}/programme/top-pics";
            foreach ([false, true, false] as $selected) {
                $change = ['file_uuid' => $file->uuid, 'change_uuid' => (string) Str::uuid(), 'is_top_pic' => $selected];
                $this->postJson($url, ['changes' => [$change]])->assertOk()
                    ->assertJsonPath('data.event_uuid', $f['event']->uuid)->assertJsonPath('data.changes.0', $change);
                $auditCount = DB::table('programme_upload_audits')->where('action', 'top_pic_changed')->count();
                $this->postJson($url, ['changes' => [$change]])->assertOk();
                $this->assertSame($auditCount, DB::table('programme_upload_audits')->where('action', 'top_pic_changed')->count());
                $this->assertSame($selected, $file->refresh()->is_top_pic);
            }
            $this->assertEquals($original, $file->only(array_keys($original)));
            $this->assertSame($bytes, Storage::disk('local')->get($file->storage_key));
            $this->assertSame('complete', $file->contribution->status);
        }
    }

    public function test_top_pic_sync_rejects_wrong_events_unverified_files_and_unauthorised_accounts(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $bytes = $this->jpegBytes();
        $receipt = $this->reserveBytes($f, $bytes);
        $this->sendBytes($f['event'], $receipt['uuid'], $bytes)->assertOk();
        $url = "/api/staff/events/{$f['event']->uuid}/programme/top-pics";
        $change = ['file_uuid' => $receipt['uuid'], 'change_uuid' => (string) Str::uuid(), 'is_top_pic' => true];
        $other = $this->fixture();
        $this->postJson("/api/staff/events/{$other['event']->uuid}/programme/top-pics", ['changes' => [$change]])->assertNotFound();
        $unverified = $this->reserveBytes($f, $this->jpegBytes(80));
        $this->postJson($url, ['changes' => [$change, array_replace($change, ['file_uuid' => $unverified['uuid'], 'change_uuid' => (string) Str::uuid()])]])->assertNotFound();
        $this->assertFalse(ProgrammePhotoFile::where('uuid', $receipt['uuid'])->firstOrFail()->is_top_pic);
        $this->postJson($url, ['changes' => [array_replace($change, ['is_top_pic' => 'invalid'])]])->assertUnprocessable();
        $this->actingAsApiUser(User::factory()->create(['type' => 'customer', 'is_admin' => false]), ['concert-media:upload']);
        $this->postJson($url, ['changes' => [$change]])->assertForbidden();
        $this->actingAsApiUser($f['user'], ['programmes:read']);
        $this->postJson($url, ['changes' => [$change]])->assertForbidden();
        $other['crew']->user->update(['type' => 'crew', 'is_admin' => false]);
        $f['crew']->user->update(['type' => 'crew', 'is_admin' => false]);
        $this->actingAsApiUser($other['crew']->user, ['concert-media:upload']);
        $this->postJson($url, ['changes' => [$change]])->assertForbidden();
        $this->actingAsApiUser($f['crew']->user, ['concert-media:upload']);
        $this->postJson($url, ['changes' => [$change]])->assertOk();
        $f['assignment']->update(['status' => 'draft']);
        $this->postJson($url, ['changes' => [$change]])->assertForbidden();
    }

    public function test_top_pic_sync_updates_canonical_photo_for_replacement_laptop_receipt(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $bytes = $this->jpegBytes();
        $first = $this->reserveBytes($f, $bytes);
        $this->sendBytes($f['event'], $first['uuid'], $bytes)->assertOk();
        $copy = $this->reserveBytes($f, $bytes);
        $this->sendBytes($f['event'], $copy['uuid'], $bytes)->assertOk();
        $original = ProgrammePhotoFile::where('uuid', $first['uuid'])->firstOrFail();
        $duplicate = ProgrammePhotoFile::where('uuid', $copy['uuid'])->firstOrFail();
        $this->assertSame($original->id, $duplicate->duplicate_of_file_id);
        $original->update(['is_top_pic' => true]);
        $duplicate->update(['is_top_pic' => true]);
        $this->postJson("/api/staff/events/{$f['event']->uuid}/programme/top-pics", ['changes' => [[
            'file_uuid' => $copy['uuid'], 'change_uuid' => (string) Str::uuid(), 'is_top_pic' => false,
        ]]])->assertOk();
        $this->assertFalse($original->refresh()->is_top_pic);
        $this->assertFalse($duplicate->refresh()->is_top_pic);
        $this->assertSame($original->storage_key, $duplicate->storage_key);
        $this->assertDatabaseCount('programme_photo_files', 2);
    }

    public function test_crew_uses_own_account_to_upload_for_another_event_role(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $actor = $f['crew']->user;
        $actor->update(['type' => 'crew', 'is_admin' => false]);
        $p2 = CrewRole::where('code', 'concert-photographer-p2')->firstOrFail();
        $f['event']->roleRequirements()->create(['crew_role_id' => $p2->id, 'quantity' => 1]);
        $this->actingAsApiUser($actor, ['programmes:read', 'concert-media:upload']);
        $this->getJson("/api/staff/events/{$f['event']->uuid}/programme/upload-roles")->assertOk()
            ->assertJsonPath('data.default_role_uuid', $f['role']->uuid)->assertJsonCount(2, 'data.roles');
        $f['body']['role_uuid'] = $p2->uuid;
        unset($f['body']['crew_uuid']);
        $bytes = $this->jpegBytes();
        $file = $this->reserveBytes($f, $bytes);
        $this->sendBytes($f['event'], $file['uuid'], $bytes)->assertOk();
        $this->assertDatabaseHas('programme_photo_files', ['uuid' => $file['uuid'], 'uploaded_by_user_id' => $actor->id]);
        $this->assertDatabaseHas('programme_upload_audits', ['file_uuid' => $file['uuid'], 'actor_user_id' => $actor->id, 'role_uuid' => $p2->uuid]);
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk();
        $this->assertDatabaseHas('programme_photo_contributions', ['crew_role_id' => $p2->id, 'crew_profile_id' => null, 'status' => 'complete', 'completed_by_user_id' => $actor->id]);
        $this->assertFalse(app(ProgrammePolicy::class)->update($actor, $f['event']));
        $other = $this->fixture();
        $this->actingAsApiUser($actor, ['programmes:read', 'concert-media:upload']);
        $this->getJson("/api/staff/events/{$other['event']->uuid}/programme/upload-roles")->assertForbidden();
        $this->getJson('/api/staff/programme-events')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_role_contributions_survive_crew_handover_and_video_is_selectable_only(): void
    {
        $f = $this->fixture(true);
        $video = CrewRole::where('code', 'competition-videographer')->firstOrFail();
        $f['event']->roleRequirements()->create(['crew_role_id' => $video->id, 'quantity' => 1]);
        $this->actingAsApiUser($f['user'], ['programmes:read', 'concert-media:upload']);
        $this->getJson("/api/staff/events/{$f['event']->uuid}/programme/upload-roles")->assertOk()->assertJsonPath('data.default_role_uuid', null)
            ->assertJsonFragment(['role_uuid' => $video->uuid, 'media_type' => 'video']);
        $first = $this->postJson($this->url($f['event']), $f['body'])->assertOk()->json('data.contributions.0.uuid');
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk();
        $replacement = CrewProfile::factory()->create();
        $f['assignment']->update(['crew_profile_id' => $replacement->id]);
        $f['body']['crew_uuid'] = $replacement->uuid;
        $this->postJson($this->url($f['event']), $f['body'])->assertOk()->assertJsonPath('data.contributions.0.uuid', $first)
            ->assertJsonPath('data.contributions.0.status', 'complete');
        $this->assertDatabaseCount('programme_photo_contributions', 2);
        $this->postJson($this->url($f['event']), array_replace($f['body'], ['role_uuid' => $video->uuid]))->assertConflict();
    }

    public function test_replacement_laptop_copy_reuses_stored_bytes_and_keeps_upload_actors(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $bytes = $this->jpegBytes();
        $first = $this->reserveBytes($f, $bytes);
        $this->sendBytes($f['event'], $first['uuid'], $bytes)->assertOk();
        $secondActor = User::factory()->staff()->create();
        $this->actingAsApiUser($secondActor, ['concert-media:upload', 'programmes:read']);
        $copy = $this->reserveBytes($f, $bytes);
        $this->sendBytes($f['event'], $copy['uuid'], $bytes)->assertOk();
        $original = ProgrammePhotoFile::where('uuid', $first['uuid'])->firstOrFail();
        $duplicate = ProgrammePhotoFile::where('uuid', $copy['uuid'])->firstOrFail();
        $this->assertSame($original->id, $duplicate->duplicate_of_file_id);
        $this->assertSame($original->storage_key, $duplicate->storage_key);
        $this->assertSame($f['user']->id, $original->uploaded_by_user_id);
        $this->assertSame($secondActor->id, $duplicate->uploaded_by_user_id);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonPath('data.photographers.0.verified_file_count', 1)->assertJsonCount(2, 'data.photographers.0.uploaders');
        $this->sendBytes($f['event'], $copy['uuid'], $bytes)->assertOk();
        $this->assertDatabaseCount('programme_upload_audits', 2);
    }

    public function test_migrated_identical_client_receipts_can_be_replayed_without_changing_historical_paths(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $bytes = $this->jpegBytes();
        $first = $this->reserveBytes($f, $bytes);
        $this->sendBytes($f['event'], $first['uuid'], $bytes)->assertOk();
        $original = ProgrammePhotoFile::where('uuid', $first['uuid'])->firstOrFail();
        $legacy = $original->replicate();
        $legacy->uuid = (string) Str::uuid();
        $legacy->reservation_key = null;
        $legacy->duplicate_of_file_id = $original->id;
        $legacy->storage_key = 'historical/keep.jpg';
        $legacy->save();
        Storage::disk('local')->put($legacy->storage_key, $bytes);
        $this->sendBytes($f['event'], $original->uuid, $bytes)->assertOk();
        $this->sendBytes($f['event'], $legacy->uuid, $bytes)->assertOk();
        $this->assertSame('historical/keep.jpg', $legacy->fresh()->storage_key);
        $this->assertCount(2, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('programme_upload_audits', 1);
    }

    private function exceptionUrl(array $f): string
    {
        return route('admin.photo-uploads.update', $f['event'], false);
    }

    private function exceptionBody(array $f, string $action = 'waive', ?string $scope = null): array
    {
        return ['plan_uuid' => $f['plan']->uuid, 'role_uuid' => $f['role']->uuid,
            'scope_uuid' => $scope, 'action' => $action, 'reason' => 'Camera failure prevented capture'];
    }

    public function test_admin_can_waive_missing_items_and_reinstate_with_an_audit_trail(): void
    {
        $f = $this->fixture(true);
        $this->actingAs($f['user'], 'web');
        $this->get($this->exceptionUrl($f))->assertOk()->assertSee('Photo uploads')->assertSee('No exceptions recorded.');
        $section = collect($f['plan']->snapshot['nodes'])->firstWhere('kind', 'section')['uuid'];
        $this->actingAs($f['user'], 'web');
        $this->post($this->exceptionUrl($f), $this->exceptionBody($f, 'waive', $section))->assertRedirect();
        $this->assertSame(2, ProgrammePhotoContribution::where('status', 'waived')->count());
        $this->assertDatabaseHas('programme_photo_contributions', ['waived_by_user_id' => $f['user']->id, 'waiver_reason' => 'Camera failure prevented capture', 'completed_at' => null]);
        $this->assertDatabaseHas('programme_upload_audits', ['action' => 'contribution_waive', 'actor_user_id' => $f['user']->id]);
        $this->get($this->exceptionUrl($f))->assertOk()->assertSee('Camera failure prevented capture');
        $this->actingAsApiUser($f['user'], ['programmes:read', 'concert-media:upload']);
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonPath('data.photographers.0.status', 'waived')
            ->assertJsonPath('data.photographers.0.complete_item_count', 0)->assertJsonPath('data.photographers.0.waived_item_count', 2);
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk()->assertJsonPath('data.waived_item_count', 2);
        $this->assertSame(2, ProgrammePhotoContribution::where('status', 'waived')->count());
        $this->actingAs($f['user'], 'web');
        $this->actingAs($f['user'], 'web');
        $this->post($this->exceptionUrl($f), $this->exceptionBody($f, 'reinstate'))->assertRedirect();
        $this->assertSame(2, ProgrammePhotoContribution::where('status', 'awaiting_files')->whereNull('waived_at')->count());
        $this->assertDatabaseHas('programme_upload_audits', ['action' => 'contribution_reinstate']);
        $this->assertDatabaseCount('programme_photo_galleries', 0);
    }

    public function test_waiver_is_scoped_and_does_not_relabel_completed_work(): void
    {
        $f = $this->fixture();
        $this->postJson($this->url($f['event']), $f['body'])->assertOk();
        $complete = ProgrammePhotoContribution::where('item_uuid', $f['body']['item_uuids'][0])->firstOrFail();
        $complete->update(['status' => 'complete', 'completed_at' => now(), 'completed_by_user_id' => $f['user']->id]);
        $this->actingAs($f['user'], 'web');
        $this->actingAs($f['user'], 'web');
        $this->post($this->exceptionUrl($f), $this->exceptionBody($f))->assertRedirect();
        $this->assertSame('complete', $complete->fresh()->status);
        $this->assertSame(1, ProgrammePhotoContribution::where('status', 'waived')->count());
        $this->actingAsApiUser($f['user'], ['programmes:read']);
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonPath('data.photographers.0.status', 'complete_with_exceptions')
            ->assertJsonPath('data.photographers.0.complete_item_count', 1)->assertJsonPath('data.photographers.0.waived_item_count', 1);
    }

    public function test_item_waiver_does_not_affect_other_items_or_roles_and_is_idempotent(): void
    {
        $f = $this->fixture(true);
        $otherRole = CrewRole::where('code', 'competition-photographer-p2')->firstOrFail();
        $f['shift']->assignments()->create(['crew_role_id' => $otherRole->id, 'crew_profile_id' => CrewProfile::factory()->create()->id, 'status' => 'published']);
        $this->postJson($this->url($f['event']), array_replace($f['body'], ['role_uuid' => $otherRole->uuid]))->assertOk();
        $this->actingAs($f['user'], 'web');
        $body = $this->exceptionBody($f, 'waive', $f['body']['item_uuids'][0]);
        $this->post($this->exceptionUrl($f), $body)->assertRedirect();
        $this->post($this->exceptionUrl($f), $body)->assertRedirect();
        $this->assertSame(1, ProgrammePhotoContribution::where('status', 'waived')->count());
        $this->assertSame(2, ProgrammePhotoContribution::where('crew_role_id', $otherRole->id)->where('status', 'awaiting_files')->count());
        $this->assertDatabaseMissing('programme_photo_contributions', ['crew_role_id' => $f['role']->id, 'item_uuid' => $f['body']['item_uuids'][1]]);
        $this->assertSame(1, DB::table('programme_upload_audits')->where('action', 'contribution_waive')->count());
    }

    public function test_new_reservations_and_receiving_old_reservations_reopen_waivers(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $bytes = $this->jpegBytes();
        $old = $this->reserveBytes($f, $bytes);
        $this->actingAs($f['user'], 'web');
        $this->actingAs($f['user'], 'web');
        $this->post($this->exceptionUrl($f), $this->exceptionBody($f))->assertRedirect();
        $this->sendBytes($f['event'], $old['uuid'], $bytes)->assertOk();
        $this->assertDatabaseHas('programme_photo_contributions', ['item_uuid' => $f['body']['item_uuids'][0], 'status' => 'receiving', 'waived_at' => null]);
        $this->actingAs($f['user'], 'web');
        $this->post($this->exceptionUrl($f), $this->exceptionBody($f))->assertRedirect();
        $this->sendBytes($f['event'], $old['uuid'], $bytes)->assertOk();
        $this->assertDatabaseHas('programme_photo_contributions', ['item_uuid' => $f['body']['item_uuids'][0], 'status' => 'waived']);
        $this->reserveBytes($f, $this->jpegBytes(90));
        $this->assertDatabaseHas('programme_photo_contributions', ['item_uuid' => $f['body']['item_uuids'][0], 'status' => 'receiving', 'waiver_reason' => null]);
        $this->assertSame(2, DB::table('programme_upload_audits')->where('action', 'waiver_reopened')->count());
        $this->assertSame('private', ProgrammePhotoGallery::first()->visibility);
    }

    public function test_exceptions_require_admin_reason_current_programme_and_valid_scope(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['user'], 'web');
        $this->postJson($this->exceptionUrl($f), array_replace($this->exceptionBody($f), ['reason' => '  ']))->assertUnprocessable();
        $this->postJson($this->exceptionUrl($f), $this->exceptionBody($f, 'waive', (string) Str::uuid()))->assertUnprocessable();
        $other = $this->fixture();
        $this->actingAs($f['user'], 'web');
        $this->postJson($this->exceptionUrl($f), array_replace($this->exceptionBody($f), ['plan_uuid' => $other['plan']->uuid]))->assertNotFound();
        $f['programme']->revisions()->create(['version' => 2, 'confirmed_at' => now(), 'snapshot' => $f['revision']->snapshot]);
        $this->postJson($this->exceptionUrl($f), $this->exceptionBody($f))->assertConflict();
        $crew = User::factory()->crew()->create();
        CrewProfile::factory()->create(['user_id' => $crew->id]);
        $this->actingAs($crew, 'web');
        $this->postJson($this->exceptionUrl($f), $this->exceptionBody($f))->assertForbidden();
        $this->assertDatabaseCount('programme_photo_contributions', 0);
    }

    private function competitionExtra(array $f): array
    {
        $day = collect($f['plan']->snapshot['nodes'])->firstWhere('kind', 'day');
        $section = CompetitionProgrammeNodes::identity($f['programme']->uuid, 'section', '07');
        $item = CompetitionProgrammeNodes::identity($f['programme']->uuid, 'item', '03', $section);

        return [
            ['uuid' => $section, 'parent_uuid' => $day['uuid'], 'kind' => 'section', 'number' => '07', 'title' => 'Jazz 12 & Under'],
            ['uuid' => $item, 'parent_uuid' => $section, 'kind' => 'item', 'number' => '03', 'title' => 'Jessica Smith', 'item_kind' => 'dance'],
        ];
    }

    public function test_competition_additions_keep_the_same_plan_and_existing_uploads_and_completion(): void
    {
        Storage::fake('local');
        $f = $this->fixture(true);
        $original = $f['plan']->fresh()->snapshot;
        $bytes = $this->jpegBytes();
        $file = $this->reserveBytes($f, $bytes);
        $this->sendBytes($f['event'], $file['uuid'], $bytes)->assertOk();
        $stored = ProgrammePhotoFile::where('uuid', $file['uuid'])->firstOrFail();
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk();
        $extra = $this->competitionExtra($f);
        $sync = ['revision_uuid' => $f['revision']->uuid, 'nodes' => array_merge($original['nodes'], $extra)];
        $url = "/api/staff/events/{$f['event']->uuid}/programme/sync";
        $this->postJson($url, $sync)->assertOk()->assertJsonPath('data.uuid', $f['plan']->uuid);
        $this->postJson($url, $sync)->assertOk();
        // An older station's unchanged sync must not remove somebody else's additions.
        $this->postJson($url, ['revision_uuid' => $f['revision']->uuid, 'nodes' => $original['nodes']])->assertOk()->assertJsonPath('data.uuid', $f['plan']->uuid);
        $this->assertSame($original, $f['plan']->fresh()->snapshot);
        $this->assertSame(2, ProgrammePhotoContribution::where('status', 'complete')->count());
        $this->assertDatabaseCount('competition_programme_additions', 2);
        $this->assertSame(1, $f['event']->messages()->count());
        $this->actingAsApiUser($f['user'], ['programmes:read', 'concert-media:upload']);
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonPath('data.photographers.0.expected_item_count', 3)
            ->assertJsonPath('data.photographers.0.complete_item_count', 2)->assertJsonPath('data.photographers.0.status', 'receiving');
        $f['body']['item_uuids'] = [$extra[1]['uuid']];
        $added = $this->reserveBytes($f, $this->jpegBytes(130));
        $this->sendBytes($f['event'], $added['uuid'], $this->jpegBytes(130))->assertOk();
        $this->assertSame($bytes, Storage::disk('local')->get($stored->storage_key));
        $this->assertSame($stored->storage_key, $stored->fresh()->storage_key);
        $this->assertSame($f['plan']->id, ProgrammePhotoGallery::first()->programme_upload_plan_id);
    }

    public function test_competition_inventories_compare_p1_p2_and_video_without_blocking_uploads(): void
    {
        $f = $this->fixture(true);
        $p2 = CrewRole::where('code', 'competition-photographer-p2')->firstOrFail();
        $video = CrewRole::where('code', 'competition-videographer')->firstOrFail();
        foreach ([$p2, $video] as $role) {
            $f['shift']->assignments()->create(['crew_role_id' => $role->id, 'crew_profile_id' => CrewProfile::factory()->create()->id, 'status' => 'published']);
        }
        $extra = $this->competitionExtra($f);
        $base = $f['plan']->snapshot['nodes'];
        $nodes = array_merge($base, $extra);
        $folders = array_map(fn ($node) => ['node_uuid' => $node['uuid'], 'title' => $node['title']], array_values(array_filter($nodes, fn ($node) => $node['kind'] !== 'day')));
        $body = ['revision_uuid' => $f['revision']->uuid, 'role_uuid' => $f['role']->uuid,
            'device_uuid' => (string) Str::uuid(), 'nodes' => $nodes, 'folders' => $folders];
        $url = "/api/staff/events/{$f['event']->uuid}/programme/competition-folders";
        $this->postJson($url, $body)->assertOk();
        $this->postJson($url, $body)->assertOk();
        $this->assertSame(1, $f['event']->messages()->count());
        $p2Body = array_replace($body, ['role_uuid' => $p2->uuid, 'folders' => array_slice($folders, 0, 3)]);
        $rows = $this->postJson($url, $p2Body)->assertOk()->json('data.items');
        $row = collect($rows)->firstWhere('uuid', $extra[1]['uuid']);
        $this->assertSame('not_seen', collect($row['roles'])->firstWhere('role_uuid', $p2->uuid)['status']);
        $this->assertSame('not_checked', collect($row['roles'])->firstWhere('role_uuid', $video->uuid)['status']);
        $p2Body['folders'] = $folders;
        $p2Body['folders'][4]['title'] = 'Jess Smith';
        $rows = $this->postJson($url, $p2Body)->assertOk()->json('data.items');
        $row = collect($rows)->firstWhere('uuid', $extra[1]['uuid']);
        $this->assertSame('name_difference', collect($row['roles'])->firstWhere('role_uuid', $p2->uuid)['status']);
        $this->postJson($url, array_replace($body, ['role_uuid' => $video->uuid]))->assertOk();
        $this->assertDatabaseCount('programme_photo_contributions', 0);
        $this->postJson($this->url($f['event']), array_replace($f['body'], ['item_uuids' => [$extra[1]['uuid']]]))->assertOk();
        $this->assertDatabaseCount('programme_photo_contributions', 1);
        $this->actingAsApiUser($f['user'], ['programmes:read']);
        $this->getJson($url.'?revision_uuid='.$f['revision']->uuid)->assertOk();
        $this->postJson($url, $body)->assertForbidden();
        $this->actingAsApiUser(User::factory()->customer()->create(), ['programmes:read']);
        $this->getJson($url.'?revision_uuid='.$f['revision']->uuid)->assertForbidden();
    }

    public function test_competition_inventory_rejects_invalid_identity_and_rolls_back_unknown_role(): void
    {
        $f = $this->fixture(true);
        $extra = $this->competitionExtra($f);
        $body = ['revision_uuid' => $f['revision']->uuid, 'role_uuid' => (string) Str::uuid(),
            'device_uuid' => (string) Str::uuid(), 'nodes' => array_merge($f['plan']->snapshot['nodes'], $extra), 'folders' => []];
        $url = "/api/staff/events/{$f['event']->uuid}/programme/competition-folders";
        $this->postJson($url, $body)->assertUnprocessable();
        $body['role_uuid'] = $f['role']->uuid;
        $body['nodes'][count($body['nodes']) - 1]['uuid'] = (string) Str::uuid();
        $this->postJson($url, $body)->assertUnprocessable();
        $body['nodes'] = array_merge($f['plan']->snapshot['nodes'], $extra);
        $body['folders'] = [['node_uuid' => (string) Str::uuid(), 'title' => 'Unknown']];
        $this->postJson($url, $body)->assertUnprocessable();
        $this->assertDatabaseCount('competition_programme_additions', 0);
        $this->assertDatabaseCount('competition_folder_inventories', 0);
        $this->assertSame(0, $f['event']->messages()->count());
    }

    private function progressUrl(array $f): string
    {
        return str_replace('photo-contributions', 'photo-progress', $this->url($f['event'])).'?plan_uuid='.$f['plan']->uuid;
    }

    public function test_progress_is_read_only_and_tracks_each_photographer_independently(): void
    {
        Storage::fake('local');
        $f = $this->fixture(true);
        $this->actingAsApiUser($f['user'], ['programmes:read', 'concert-media:upload']);
        $other = CrewProfile::factory()->create();
        $role = CrewRole::where('code', 'competition-photographer-p2')->firstOrFail();
        $f['shift']->assignments()->create(['crew_role_id' => $role->id, 'crew_profile_id' => $other->id, 'status' => 'published']);
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonCount(2, 'data.photographers')
            ->assertJsonPath('data.photographers.0.status', 'missing')->assertJsonPath('data.photographers.0.expected_item_count', 2);
        $this->assertDatabaseCount('programme_photo_contributions', 0);
        $bytes = $this->jpegBytes();
        $file = $this->reserveBytes($f, $bytes);
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonPath('data.photographers.0.pending_file_count', 1);
        $this->sendBytes($f['event'], $file['uuid'], $bytes)->assertOk();
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonPath('data.photographers.0.verified_file_count', 1)
            ->assertJsonPath('data.photographers.0.status', 'receiving');
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk();
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonPath('data.photographers.0.status', 'complete')
            ->assertJsonPath('data.photographers.1.status', 'missing')->assertJsonPath('data.visibility', 'private');
        $this->reserveBytes($f, $bytes);
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonPath('data.photographers.0.status', 'receiving')
            ->assertJsonPath('data.photographers.0.complete_item_count', 1);
    }

    public function test_progress_reports_unfilled_roles_and_changed_programme(): void
    {
        $f = $this->fixture(true);
        $this->actingAsApiUser($f['user'], ['programmes:read', 'concert-media:upload']);
        $role = CrewRole::where('code', 'competition-photographer-p2')->firstOrFail();
        $f['event']->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk();
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonCount(1, 'data.unfilled')
            ->assertJsonPath('data.unfilled.0.missing_count', 1);
        $f['programme']->revisions()->create(['version' => 2, 'confirmed_at' => now(), 'snapshot' => $f['revision']->snapshot]);
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonPath('data.plan_current', false)
            ->assertJsonPath('data.photographers.0.status', 'needs_attention')->assertJsonPath('data.photographers.0.complete_item_count', 0);
    }

    public function test_changed_assignments_preserve_role_completion_and_day_scope_is_respected(): void
    {
        $f = $this->fixture(true);
        $this->actingAsApiUser($f['user'], ['programmes:read', 'concert-media:upload']);
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk();
        $shift = $f['event']->shifts()->create(['shift_date' => '2026-09-04', 'period' => 'afternoon']);
        $shift->assignments()->create(['crew_role_id' => $f['role']->id, 'crew_profile_id' => $f['crew']->id, 'status' => 'published']);
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonPath('data.photographers.0.complete_item_count', 2);
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk();
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonPath('data.photographers.0.status', 'complete');
        $f['shift']->update(['shift_date' => '2026-09-05']);
        $shift->update(['shift_date' => '2026-09-05']);
        $this->getJson($this->progressUrl($f))->assertOk()->assertJsonPath('data.photographers.0.status', 'no_items')
            ->assertJsonPath('data.photographers.0.expected_item_count', 0);
    }

    public function test_progress_rejects_foreign_plan_invalid_scope_and_missing_ability(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $this->getJson($this->progressUrl($f))->assertForbidden();
        $this->actingAsApiUser($f['user'], ['programmes:read']);
        $url = str_replace($f['plan']->uuid, $other['plan']->uuid, $this->progressUrl($f));
        $this->getJson($url)->assertNotFound();
        $this->getJson(str_replace($f['plan']->uuid, 'bad', $this->progressUrl($f)))->assertUnprocessable();
        $this->actingAsApiUser(User::factory()->customer()->create(), ['programmes:read']);
        $this->getJson($this->progressUrl($f))->assertForbidden();
        $this->assertDatabaseCount('programme_photo_contributions', 0);
    }

    public function test_admin_upload_page_shows_registered_counts_and_tracker_bar_before_completion(): void
    {
        Storage::fake('local');
        config(['programme-media.uploads_enabled' => true]);
        $f = $this->fixture();
        $firstBytes = $this->jpegBytes(40);
        $secondBytes = $this->jpegBytes(80);
        $first = $this->reserveBytes($f, $firstBytes);
        $second = $this->reserveBytes($f, $secondBytes);
        $this->sendBytes($f['event'], $first['uuid'], $firstBytes)->assertOk();
        $url = route('admin.photo-uploads.show', $f['event']);
        $this->actingAs($f['user'], 'web')->get($url)->assertOk()
            ->assertSee('3.1 Photo uploads')->assertSee('50% of registered photos verified')
            ->assertSee('Awaiting files')->assertSee('Back to tracker')
            ->assertViewHas('uploadSummary', fn ($summary) => $summary === [
                'photographers' => 1, 'roles' => 1, 'verified' => 1, 'pending' => 1, 'registered' => 2, 'percent' => 50,
            ]);
        $this->get(route('admin.concert-tracker.tasks.show', [$f['event'], 'photos_uploaded']))->assertRedirect($url);
        $barUrl = route('admin.concert-tracker.source-bar.update', [$f['event'], 'photos_uploaded']);
        $this->patchJson($barUrl, ['panel' => 'due', 'due_date_override' => '2026-10-01'])->assertOk();
        $this->patchJson($barUrl, ['panel' => 'status', 'status_override' => 'complete'])->assertUnprocessable();
        $this->sendBytes($f['event'], $second['uuid'], $secondBytes)->assertOk();
        $this->actingAs($f['user'], 'web')->get($url)->assertOk()->assertSee('100% of registered photos verified')->assertSee('Awaiting completion');
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk();
        $this->actingAs($f['user'], 'web')->get($url)->assertOk()->assertSee('Upload complete');
        $this->actingAs(User::factory()->customer()->create(), 'web')->get($url)->assertForbidden();
    }

    public function test_upload_page_shows_roster_without_a_plan_and_keeps_competitions_separate(): void
    {
        $user = User::factory()->staff()->create();
        $event = SchedulingEvent::create(['name' => 'No uploads yet', 'event_type' => 'concert', 'event_date' => '2026-10-01']);
        $this->actingAs($user)->get(route('admin.photo-uploads.show', $event))->assertOk()
            ->assertSee('3.1 Photo uploads')->assertSee('No working programme')->assertDontSee('0% of registered');
        $event->update(['event_type' => 'competition']);
        $this->get(route('admin.photo-uploads.show', $event))->assertOk()->assertDontSee('3.1 Photo uploads');
    }

    public function test_tracker_photo_roles_wait_upload_and_complete_independently_without_yellow(): void
    {
        Storage::fake('local');
        config(['programme-media.uploads_enabled' => true]);
        $f = $this->fixture();
        $p2 = CrewRole::where('code', 'concert-photographer-p2')->firstOrFail();
        $f['shift']->assignments()->create(['crew_role_id' => $p2->id, 'crew_profile_id' => CrewProfile::factory()->create()->id, 'status' => 'published']);
        $tracker = app(ConcertTracker::class);
        $detail = $tracker->detail($f['event'], 'photos_uploaded');
        $detail['task']->update(['due_date_override' => today()->addDay()]);
        $status = $tracker->detail($f['event'], 'photos_uploaded')['status'];
        $this->assertFalse($status['nearDue']);
        $this->assertSame(['P1 Waiting', 'P2 Waiting'], array_column($status['photoRoles'], 'label'));
        $this->assertSame(['ready', 'ready'], array_column($status['photoRoles'], 'tone'));
        $detail['task']->update(['due_date_override' => today()->subDay()]);
        $status = $tracker->detail($f['event'], 'photos_uploaded')['status'];
        $this->assertSame(['overdue', 'overdue'], array_column($status['photoRoles'], 'tone'));
        $bytes = $this->jpegBytes();
        $file = $this->reserveBytes($f, $bytes);
        $status = $tracker->detail($f['event'], 'photos_uploaded')['status'];
        $this->assertSame(['P1 Uploading', 'P2 Waiting'], array_column($status['photoRoles'], 'label'));
        $this->assertSame(['ready', 'overdue'], array_column($status['photoRoles'], 'tone'));
        $this->sendBytes($f['event'], $file['uuid'], $bytes)->assertOk();
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk();
        $status = $tracker->detail($f['event'], 'photos_uploaded')['status'];
        $this->assertNotSame('complete', $status['key']);
        $this->assertSame(['P1 Complete', 'P2 Waiting'], array_column($status['photoRoles'], 'label'));
        $finishedAt = $this->postJson($this->finishUrl($f), array_replace($f['body'], ['role_uuid' => $p2->uuid]))->assertOk()->json('data.completed_at');
        $status = $tracker->detail($f['event'], 'photos_uploaded')['status'];
        $this->assertSame('complete', $status['key']);
        $this->assertSame(['complete', 'complete'], array_column($status['photoRoles'], 'tone'));
        $this->assertSame($finishedAt, $status['completedAt']->toIso8601String());
        $this->reserveBytes($f, $this->jpegBytes(90));
        $status = $tracker->detail($f['event'], 'photos_uploaded')['status'];
        $this->assertSame('in_progress', $status['key']);
        $this->assertSame(['P1 Uploading', 'P2 Complete'], array_column($status['photoRoles'], 'label'));
        $this->assertNull($status['completedAt']);
        $this->actingAs($f['user'], 'web')->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => 'editing']))
            ->assertOk()->assertSee('P1 Uploading')->assertSee('P2 Complete');
    }

    private function finishUrl(array $f): string
    {
        return str_replace('photo-contributions', 'photo-completion', $this->url($f['event']));
    }

    public function test_finish_confirms_verified_and_empty_items_without_publishing(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $bytes = $this->jpegBytes();
        $file = $this->reserveBytes($f, $bytes);
        $this->postJson($this->finishUrl($f), $f['body'])->assertConflict();
        $this->assertDatabaseMissing('programme_photo_contributions', ['status' => 'complete']);
        $this->sendBytes($f['event'], $file['uuid'], $bytes)->assertOk();
        $first = $this->postJson($this->finishUrl($f), $f['body'])->assertOk()
            ->assertJsonPath('data.status', 'complete')->assertJsonPath('data.contribution_count', 2)
            ->assertJsonPath('data.verified_file_count', 1)->assertJsonPath('data.visibility', 'private')->json('data');
        $this->travel(1)->minutes();
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk()->assertJsonPath('data.completed_at', $first['completed_at']);
        $this->assertSame(2, ProgrammePhotoContribution::where('status', 'complete')->whereNotNull('completed_at')->count());
        $this->assertSame($f['user']->id, ProgrammePhotoContribution::first()->completed_by_user_id);
        $this->assertSame('private', ProgrammePhotoGallery::first()->visibility);
        $this->assertDatabaseCount('media_assets', 0);
        $this->reserveBytes($f, $bytes, $file['photo_uuid']);
        $this->assertSame(2, ProgrammePhotoContribution::where('status', 'complete')->count());
        $next = $this->reserveBytes($f, $bytes);
        $this->assertSame(1, ProgrammePhotoContribution::where('status', 'complete')->count());
        $this->assertDatabaseHas('programme_photo_contributions', ['status' => 'receiving', 'completed_at' => null, 'completed_by_user_id' => null]);
        $this->postJson($this->finishUrl($f), $f['body'])->assertConflict();
        $this->sendBytes($f['event'], $next['uuid'], $bytes)->assertOk();
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk()->assertJsonPath('data.verified_file_count', 1);
    }

    public function test_empty_event_can_be_finished_once_without_creating_gallery(): void
    {
        $f = $this->fixture();
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk()->assertJsonPath('data.verified_file_count', 0);
        $this->assertDatabaseCount('programme_photo_galleries', 0);
        $this->assertSame(2, ProgrammePhotoContribution::where('status', 'complete')->count());
    }

    public function test_completion_is_scoped_to_the_selected_photographer(): void
    {
        $f = $this->fixture(true);
        $other = CrewProfile::factory()->create();
        $role = CrewRole::where('code', 'competition-photographer-p2')->firstOrFail();
        $f['shift']->assignments()->create(['crew_role_id' => $role->id, 'crew_profile_id' => $other->id, 'status' => 'published']);
        $second = array_replace($f['body'], ['crew_uuid' => $other->uuid, 'role_uuid' => $role->uuid]);
        $this->postJson($this->url($f['event']), $second)->assertOk();
        $this->postJson($this->finishUrl($f), $f['body'])->assertOk();
        $this->assertSame(2, ProgrammePhotoContribution::where('crew_role_id', $role->id)->where('status', 'awaiting_files')->count());
        $this->assertDatabaseCount('programme_photo_galleries', 0);
    }

    public function test_finish_rejects_stale_roster_revision_foreign_plan_and_missing_ability(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $this->postJson($this->finishUrl($f), array_replace($f['body'], ['plan_uuid' => $other['plan']->uuid]))->assertNotFound();
        $f['role']->update(['is_active' => false]);
        $this->postJson($this->finishUrl($f), $f['body'])->assertConflict();
        $f['role']->update(['is_active' => true]);
        $f['programme']->revisions()->create(['version' => 2, 'confirmed_at' => now(), 'snapshot' => $f['revision']->snapshot]);
        $this->postJson($this->finishUrl($f), $f['body'])->assertConflict();
        $this->actingAsApiUser($f['user'], ['profile:read']);
        $this->postJson($this->finishUrl($f), $f['body'])->assertForbidden();
        $this->assertDatabaseCount('programme_photo_contributions', 0);
    }

    public function test_received_jpeg_is_verified_private_and_idempotent(): void
    {
        Storage::fake('local');
        config(['programme-media.uploads_enabled' => true]);
        $f = $this->fixture();
        $bytes = $this->jpegBytes();
        $file = $this->reserveBytes($f, $bytes);
        $response = $this->sendBytes($f['event'], $file['uuid'], $bytes)->assertOk()
            ->assertJsonPath('data.status', 'verified')->assertJsonPath('data.visibility', 'private')
            ->assertJsonPath('data.sha256', hash('sha256', $bytes));
        $gallery = $response->json('data.gallery_uuid');
        $this->sendBytes($f['event'], $file['uuid'], $bytes)->assertOk()->assertJsonPath('data.gallery_uuid', $gallery);
        $saved = ProgrammePhotoFile::where('uuid', $file['uuid'])->firstOrFail();
        $this->assertSame($bytes, Storage::disk('local')->get($saved->storage_key));
        $this->assertNotNull($saved->verified_at);
        $this->assertDatabaseCount('programme_photo_galleries', 1);
        $this->assertDatabaseCount('programme_photo_files', 1);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseHas('programme_photo_contributions', ['status' => 'receiving']);
    }

    public function test_bad_bytes_and_fake_jpegs_never_create_a_verified_gallery(): void
    {
        Storage::fake('local');
        config(['programme-media.uploads_enabled' => true]);
        $f = $this->fixture();
        $bytes = $this->jpegBytes();
        $file = $this->reserveBytes($f, $bytes);
        $this->sendBytes($f['event'], $file['uuid'], substr($bytes, 0, -2))->assertUnprocessable();
        $this->sendBytes($f['event'], $file['uuid'], $bytes.'extra')->assertUnprocessable();
        $invalid = 'not a jpeg at all';
        $bad = $this->reserveBytes($f, $invalid);
        $this->sendBytes($f['event'], $bad['uuid'], $invalid)->assertUnprocessable();
        $this->assertDatabaseCount('programme_photo_galleries', 0);
        $this->assertSame(0, ProgrammePhotoFile::where('status', 'verified')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_conflicting_working_programme_cannot_reassign_an_existing_gallery(): void
    {
        Storage::fake('local');
        config(['programme-media.uploads_enabled' => true]);
        $f = $this->fixture();
        $bytes = $this->jpegBytes();
        $file = $this->reserveBytes($f, $bytes);
        $this->sendBytes($f['event'], $file['uuid'], $bytes)->assertOk();
        $nodes = $f['plan']->snapshot['nodes'];
        $nodes[0]['title'] = 'Different';
        $nodes[0]['folder_title'] = 'Different';
        $this->postJson("/api/staff/events/{$f['event']->uuid}/programme/sync", [
            'revision_uuid' => $f['revision']->uuid, 'nodes' => $nodes, 'base_plan_uuid' => $f['plan']->uuid,
        ])->assertConflict();
        $this->assertSame($f['plan']->id, ProgrammePhotoGallery::first()->programme_upload_plan_id);
        $this->assertSame(1, ProgrammePhotoFile::where('status', 'verified')->count());
    }

    public function test_changed_photo_version_cannot_overwrite_verified_bytes(): void
    {
        Storage::fake('local');
        config(['programme-media.uploads_enabled' => true]);
        $f = $this->fixture();
        $firstBytes = $this->jpegBytes(20);
        $first = $this->reserveBytes($f, $firstBytes);
        $this->sendBytes($f['event'], $first['uuid'], $firstBytes)->assertOk();
        $secondBytes = $this->jpegBytes(200);
        $second = $this->reserveBytes($f, $secondBytes, $first['photo_uuid']);
        $this->sendBytes($f['event'], $second['uuid'], $secondBytes)->assertConflict();
        $file = ProgrammePhotoFile::where('uuid', $first['uuid'])->firstOrFail();
        $this->assertSame($firstBytes, Storage::disk('local')->get($file->storage_key));
    }

    public function test_transfer_rechecks_event_assignment_and_server_enablement(): void
    {
        Storage::fake('local');
        config(['programme-media.uploads_enabled' => true]);
        $f = $this->fixture();
        $bytes = $this->jpegBytes();
        $file = $this->reserveBytes($f, $bytes);
        $other = $this->fixture();
        $this->sendBytes($other['event'], $file['uuid'], $bytes)->assertNotFound();
        $f['role']->update(['is_active' => false]);
        $this->sendBytes($f['event'], $file['uuid'], $bytes)->assertConflict();
        $f['role']->update(['is_active' => true]);
        config(['programme-media.uploads_enabled' => false]);
        $this->sendBytes($f['event'], $file['uuid'], $bytes)->assertStatus(503);
        $this->assertDatabaseCount('programme_photo_galleries', 0);
    }

    public function test_role_disabled_during_receipt_prevents_storage_and_verification(): void
    {
        Storage::fake('local');
        config(['programme-media.uploads_enabled' => true]);
        $f = $this->fixture();
        $bytes = $this->jpegBytes();
        $file = $this->reserveBytes($f, $bytes);
        $this->mock(VerifyPhotoBytes::class, function ($mock) use ($f, $bytes): void {
            $mock->shouldReceive('copyAndVerify')->once()->andReturnUsing(function () use ($f, $bytes) {
                $f['role']->update(['is_active' => false]);
                $temp = tmpfile();
                fwrite($temp, $bytes);
                rewind($temp);

                return $temp;
            });
        });
        $this->sendBytes($f['event'], $file['uuid'], $bytes)->assertConflict();
        $this->assertDatabaseCount('programme_photo_galleries', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failed_storage_does_not_mark_reserved_photo_verified(): void
    {
        config(['programme-media.uploads_enabled' => true]);
        $f = $this->fixture();
        $bytes = $this->jpegBytes();
        $file = $this->reserveBytes($f, $bytes);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->sendBytes($f['event'], $file['uuid'], $bytes)->assertStatus(503);
        $this->assertDatabaseHas('programme_photo_files', ['uuid' => $file['uuid'], 'status' => 'reserved', 'verified_at' => null]);
        $this->assertDatabaseCount('programme_photo_galleries', 0);
    }

    private function fileBody(array $fixture): array
    {
        $body = $fixture['body'];
        unset($body['item_uuids']);
        $body['photos'] = [[
            'photo_uuid' => (string) Str::uuid(), 'item_uuid' => $fixture['body']['item_uuids'][0],
            'file_name' => 'IMG_0001.JPG', 'byte_count' => 1234, 'sha256' => str_repeat('a', 64), 'is_top_pic' => false,
        ]];

        return $body;
    }

    public function test_file_reservations_retry_without_duplicates_and_top_pic_changes_are_metadata(): void
    {
        $f = $this->fixture();
        $body = $this->fileBody($f);
        $url = str_replace('photo-contributions', 'photo-files', $this->url($f['event']));
        $id = $this->postJson($url, $body)->assertOk()->assertJsonPath('data.files.0.status', 'reserved')->json('data.files.0.uuid');
        $body['photos'][0]['is_top_pic'] = true;
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.files.0.uuid', $id);
        $this->assertDatabaseCount('programme_photo_files', 1);
        $this->assertDatabaseHas('programme_photo_files', ['uuid' => $id, 'is_top_pic' => true, 'status' => 'reserved']);
        $this->assertDatabaseHas('programme_photo_contributions', ['status' => 'awaiting_files']);
        $this->assertDatabaseCount('media_assets', 0);
        $body['photos'][0]['sha256'] = str_repeat('b', 64);
        $this->postJson($url, $body)->assertOk();
        $this->assertDatabaseCount('programme_photo_files', 2);
    }

    public function test_file_reservation_size_conflict_rolls_back_entire_batch(): void
    {
        $f = $this->fixture();
        $body = $this->fileBody($f);
        $url = str_replace('photo-contributions', 'photo-files', $this->url($f['event']));
        $this->postJson($url, $body)->assertOk();
        $original = $body['photos'][0];
        $body['photos'][0]['photo_uuid'] = (string) Str::uuid();
        $body['photos'][] = array_replace($original, ['byte_count' => 9999]);
        $this->postJson($url, $body)->assertConflict();
        $this->assertDatabaseCount('programme_photo_files', 1);
    }

    public function test_file_reservations_reject_paths_bad_hashes_duplicates_and_changed_assignments(): void
    {
        $f = $this->fixture();
        $body = $this->fileBody($f);
        $url = str_replace('photo-contributions', 'photo-files', $this->url($f['event']));
        foreach (['../escape.jpg', 'folder\\photo.jpg', 'file.mov', "bad\nname.jpg"] as $name) {
            $invalid = $body;
            $invalid['photos'][0]['file_name'] = $name;
            $this->postJson($url, $invalid)->assertUnprocessable();
        }
        foreach (['sha256' => 'bad', 'byte_count' => 536870913, 'item_uuid' => (string) Str::uuid()] as $key => $value) {
            $invalid = $body;
            $invalid['photos'][0][$key] = $value;
            $this->postJson($url, $invalid)->assertUnprocessable();
        }
        $invalid = $body;
        $invalid['photos'][] = $body['photos'][0];
        $this->postJson($url, $invalid)->assertUnprocessable();
        $f['role']->update(['is_active' => false]);
        $this->postJson($url, $body)->assertConflict();
        $this->assertDatabaseCount('programme_photo_files', 0);
        $this->assertDatabaseCount('programme_photo_contributions', 0);
    }

    public function test_registration_is_idempotent_and_does_not_mark_files_received_or_complete(): void
    {
        $f = $this->fixture();
        $body = $f['body'] + ['status' => 'complete'];
        $first = $this->postJson($this->url($f['event']), $body)->assertOk()
            ->assertJsonCount(2, 'data.contributions')->assertJsonPath('data.contributions.0.status', 'awaiting_files')->json('data');
        $this->postJson($this->url($f['event']), $body)->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Queued items connected to the photographer. Files have not been uploaded.', 'data' => $first,
        ]);
        $this->assertDatabaseCount('programme_photo_contributions', 2);
        $this->assertSame([$f['shift']->uuid], ProgrammePhotoContribution::first()->assignment_uuids);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame(1, $f['programme']->revisions()->count());
    }

    public function test_rejects_another_event_plan_and_foreign_item_without_partial_creation(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $body = $f['body'];
        $body['plan_uuid'] = $other['plan']->uuid;
        $this->postJson($this->url($f['event']), $body)->assertNotFound();
        $body = $f['body'];
        $body['item_uuids'][] = $other['body']['item_uuids'][0];
        $this->postJson($this->url($f['event']), $body)->assertUnprocessable();
        $this->assertDatabaseCount('programme_photo_contributions', 0);
    }

    public function test_retries_recheck_assignment_and_revision_instead_of_trusting_existing_records(): void
    {
        $f = $this->fixture();
        $this->postJson($this->url($f['event']), $f['body'])->assertOk();
        $f['role']->update(['is_active' => false]);
        $this->postJson($this->url($f['event']), $f['body'])->assertConflict();
        $f['role']->update(['is_active' => true]);
        $f['programme']->revisions()->create(['version' => 2, 'snapshot' => $f['revision']->snapshot, 'confirmed_at' => now()]);
        $this->postJson($this->url($f['event']), $f['body'])->assertConflict();
        $this->assertDatabaseCount('programme_photo_contributions', 2);
    }

    public function test_competition_contribution_requires_assignment_on_the_programme_day(): void
    {
        $f = $this->fixture(true);
        $this->postJson($this->url($f['event']), $f['body'])->assertOk();
        $f['shift']->update(['shift_date' => '2026-09-05']);
        $this->postJson($this->url($f['event']), $f['body'])->assertConflict();
    }

    public function test_superseded_shared_plan_cannot_register_contributions(): void
    {
        $f = $this->fixture();
        $nodes = $f['plan']->snapshot['nodes'];
        $nodes[0]['title'] = $nodes[0]['folder_title'] = 'Updated title';
        $next = app(SyncProgramme::class)->execute($f['event'], $f['user'], $f['revision']->uuid, $nodes, $f['plan']->uuid);
        $this->postJson($this->url($f['event']), $f['body'])->assertConflict();
        $this->postJson($this->url($f['event']), array_replace($f['body'], ['plan_uuid' => $next->uuid]))->assertOk();
        $this->assertSame(2, $f['event']->messages()->where('message_type', 'programme_update')->count());
    }

    public function test_programme_chat_notifications_are_event_scoped_and_do_not_repeat(): void
    {
        $f = $this->fixture();
        app(SyncProgramme::class)->execute($f['event'], $f['user'], $f['revision']->uuid, $f['plan']->snapshot['nodes']);
        $this->assertSame(1, $f['event']->messages()->where('message_type', 'programme_update')->count());
        $this->assertDatabaseHas('crew_notifications', ['user_id' => $f['crew']->user_id, 'destination_id' => $f['event']->uuid, 'type' => 'event_message']);
        $this->actingAs($f['crew']->user, 'web');
        $url = route('crew.chat.programme-updates', $f['event'], false);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.author', $f['user']->name);
        $this->get(route('crew.chat.event', $f['event'], false))->assertOk()->assertSee('Programme update (automatic)')->assertSee('checkProgrammeUpdates');
        $outsider = CrewProfile::factory()->create();
        $this->actingAs($outsider->user, 'web');
        $this->getJson($url)->assertNotFound();
    }

    public function test_retired_and_nonperformance_items_are_rejected(): void
    {
        $f = $this->fixture();
        $nodes = $f['plan']->snapshot['nodes'];
        $nodes[0]['merged_into_uuid'] = $nodes[1]['uuid'];
        $nodes[0]['folder_number'] = null;
        $nodes[0]['folder_title'] = null;
        $nodes[1]['folder_number'] = '01';
        $plan = app(SyncProgramme::class)->execute($f['event'], $f['user'], $f['revision']->uuid, $nodes, $f['plan']->uuid);
        $body = $f['body'];
        $body['plan_uuid'] = $plan->uuid;
        $this->postJson($this->url($f['event']), $body)->assertUnprocessable();
        $body['item_uuids'] = [$nodes[1]['uuid']];
        $this->postJson($this->url($f['event']), $body)->assertOk();
        $this->assertDatabaseCount('programme_photo_contributions', 1);
    }

    public function test_p1_and_p2_have_distinct_contributions_for_the_same_item(): void
    {
        $f = $this->fixture();
        $first = $this->postJson($this->url($f['event']), $f['body'])->assertOk()->json('data.contributions.0.uuid');
        $p2 = CrewRole::where('code', 'concert-photographer-p2')->firstOrFail();
        $crew = CrewProfile::factory()->create();
        $f['shift']->assignments()->create(['crew_profile_id' => $crew->id, 'crew_role_id' => $p2->id, 'status' => 'published']);
        $body = array_replace($f['body'], ['crew_uuid' => $crew->uuid, 'role_uuid' => $p2->uuid]);
        $second = $this->postJson($this->url($f['event']), $body)->assertOk()->json('data.contributions.0.uuid');
        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('programme_photo_contributions', 4);
    }

    public function test_unconfigured_role_and_insufficient_permission_are_rejected(): void
    {
        $f = $this->fixture();
        $body = array_replace($f['body'], ['role_uuid' => CrewRole::where('code', 'concert-photographer-p2')->firstOrFail()->uuid]);
        $this->postJson($this->url($f['event']), $body)->assertConflict();
        $this->actingAsApiUser($f['user'], ['programmes:read']);
        $this->postJson($this->url($f['event']), $f['body'])->assertForbidden();
        $this->actingAsApiUser(User::factory()->customer()->create(), ['concert-media:upload']);
        $this->postJson($this->url($f['event']), $f['body'])->assertForbidden();
        $this->assertDatabaseCount('programme_photo_contributions', 0);
    }
}
