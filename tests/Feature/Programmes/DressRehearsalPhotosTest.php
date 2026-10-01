<?php

namespace Tests\Feature\Programmes;

use App\Features\Bookings\Actions\ApproveConcertBooking;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Crew\Models\CrewRole;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\GuestGalleryCatalogue;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\ConcertPhotoReleases;
use App\Features\Programmes\Actions\PrepareDressRehearsalPhotos;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Services\ProgrammeUploadRoles;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Services\ConcertPhotoUploadStatus;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesApiTokens;
use Tests\TestCase;

class DressRehearsalPhotosTest extends TestCase
{
    use AuthenticatesApiTokens, RefreshDatabase;

    private function approve(): array
    {
        $actor = User::factory()->staff()->create();
        $studio = Studio::factory()->create();
        $venue = Venue::create(['name' => 'Portrait venue', 'address_line_1' => 'Test road']);
        $booking = ConcertBooking::create(['status' => 'pending', 'studio_id' => $studio->id,
            'studio_name' => $studio->name, 'contact_name' => 'Director', 'contact_email' => 'director@example.test',
            'contact_phone' => '0400000000', 'wants_portrait_photography' => true]);
        $item = $booking->items()->create(['item_type' => 'dress_rehearsal', 'approval_status' => 'pending', 'event_date' => today()->addDays(10),
            'title' => 'Portrait session', 'venue_id' => $venue->id, 'venue_name' => $venue->name,
            'venue_address' => 'Test road', 'starts_at' => '10:00', 'finishes_at' => '12:00']);
        $event = app(ApproveConcertBooking::class)->approveItem($item, $actor);

        return [$actor, $studio, $event, $item->fresh()->concert];
    }

    public function test_approval_prepares_one_folder_without_pdf_and_retries_preserve_identities(): void
    {
        [$actor, $studio, $event] = $this->approve();
        $programme = Programme::where('scheduling_event_id', $event->id)->firstOrFail();
        $revision = $programme->latestRevision;
        $this->assertNull($revision->programme_source_id);
        $this->assertCount(1, $revision->snapshot['nodes']);
        $this->assertSame('Dress Rehearsal Portraits', $revision->snapshot['nodes'][0]['title']);
        $this->assertSame('dress_rehearsal', $revision->snapshot['capture_mode']);
        app(PrepareDressRehearsalPhotos::class)->execute($event, $actor);
        $this->assertSame($revision->uuid, $programme->fresh()->latestRevision->uuid);
        $this->assertSame(1, $programme->revisions()->count());
        $roles = app(ProgrammeUploadRoles::class)->forEvent($event);
        $this->assertCount(1, $roles['roles']); // The portrait assistant does not owe a separate upload.
        $this->assertSame('photo', $roles['roles'][0]['media_type']);
        $this->actingAsApiUser($actor, ['programmes:read']);
        $this->getJson('/api/staff/programme-events')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $event->uuid)->assertJsonPath('data.0.capture_mode', 'dress_rehearsal')
            ->assertJsonPath('data.0.has_pdf', false)->assertJsonPath('data.0.preparation_ready', true);
        $event->update(['cancelled_at' => now()]);
        $this->getJson('/api/staff/programme-events')->assertOk()->assertJsonCount(0, 'data');
        $this->assertNull(app(PrepareDressRehearsalPhotos::class)->execute($event));
    }

    public function test_portraits_upload_complete_and_release_directly_to_images(): void
    {
        Storage::fake('local');
        [$actor, $studio, $event, $concert] = $this->approve();
        $this->actingAsApiUser($actor, ['programmes:read', 'concert-media:upload']);
        $base = '/api/staff/events/'.$event->uuid.'/programme';
        $snapshot = $this->getJson($base)->assertOk()->json('data');
        $plan = $this->postJson($base.'/sync', ['revision_uuid' => $snapshot['revision_uuid'], 'nodes' => $snapshot['nodes']])
            ->assertOk()->json('data.uuid');
        $role = CrewRole::where('code', 'photographer-p')->firstOrFail();
        $identity = ['plan_uuid' => $plan, 'role_uuid' => $role->uuid];
        $item = $snapshot['nodes'][0]['uuid'];
        $image = imagecreatetruecolor(30, 24);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 80, 120));
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        unset($image);
        $file = $this->postJson($base.'/photo-files', $identity + ['photos' => [[
            'photo_uuid' => (string) Str::uuid(), 'item_uuid' => $item, 'file_name' => 'Portrait.jpg',
            'byte_count' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'is_top_pic' => false,
        ]]])->assertOk()->json('data.files.0.uuid');
        $this->postJson($base.'/photo-completion', $identity)->assertConflict();
        $this->call('PUT', $base.'/photo-files/'.$file.'/content', [], [], [],
            $this->transformHeadersToServerVars(['Content-Type' => 'image/jpeg', 'Accept' => 'application/json']), $bytes)->assertOk();
        $this->postJson($base.'/photo-completion', $identity)->assertOk();
        $this->assertSame('complete', app(ConcertPhotoUploadStatus::class)->forEvent($event)['key']);
        $album = MediaCollection::where('concert_id', $concert->id)->sole();
        $this->assertSame('Dress Rehearsal Portraits', $album->name);
        $this->assertSame('private', $album->visibility->value);
        $this->assertSame(1, $album->assets()->count());
        $customer = User::factory()->customer()->create();
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);
        $this->withHeaders(['Authorization' => ''])->actingAs($customer, 'web');
        $url = route('customer.concerts.photos.show', $concert);
        $this->get($url)->assertForbidden();
        $release = app(ConcertPhotoReleases::class);
        $group = $release->create($actor, ['name' => 'Do not nest this group', 'collection_uuids' => [$album->uuid]]);
        $release->release($group, $actor);
        $this->get($url)->assertOk()->assertSee('Dress Rehearsal Portraits')->assertSee('Watermarked preview')
            ->assertDontSee('Do not nest this group')->assertDontSee('All albums')
            ->assertViewHas('selected', fn ($selected) => $selected->id === $album->id)
            ->assertViewHas('photos', fn ($photos) => $photos->total() === 1);
        $cards = app(GuestGalleryCatalogue::class)->concerts($studio);
        $this->assertCount(1, $cards);
        $this->assertSame($concert->name, $cards->first()['title']);
        $outsider = User::factory()->customer()->create();
        $this->actingAs($outsider, 'web')->get($url)->assertForbidden();
    }
}
