<?php

namespace Tests\Feature\Admin;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Concerts\Models\Concert;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminBrandingMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3_concerts');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privateKey));
        config()->set('concerts.playback.cloudfront', [
            'domain' => 'media.dancepro.test',
            'key_pair_id' => 'KTESTKEYPAIR',
            'private_key' => $privateKey,
        ]);
        config(['media.upload_disk' => 's3_concerts', 'media.legacy_disk' => 's3_concerts_legacy',
            'filesystems.disks.s3_concerts.bucket' => 'concert-test',
            'filesystems.disks.s3_concerts_legacy.bucket' => 'concert-legacy']);
    }

    public function test_studio_cover_upload_replace_and_confirmed_clear(): void
    {
        $studio = Studio::factory()->create();
        $this->actingAs(User::factory()->staff()->create());
        $key = "studios/{$studio->uuid}/cover";

        $this->post(route('admin.branding.studios.cover.store', $studio), [
            'file' => $this->image('logo.png'),
        ])->assertRedirect();
        Storage::disk('s3_concerts')->assertExists($key);
        $firstRevision = $studio->refresh()->cover_image_revision;
        $this->assertStringContainsString('/studios/'.$studio->uuid.'/cover', $studio->cover_image_url);

        $this->post(route('admin.branding.studios.cover.store', $studio), [
            'file' => $this->image('new-logo.png'),
        ])->assertSessionHasErrors('replace_confirm');
        $staleDigest = $this->get(route('admin.branding.studios.cover.confirm-delete', $studio))
            ->assertOk()->viewData('digest');
        $this->post(route('admin.branding.studios.cover.store', $studio), [
            'file' => $this->image('new-logo.png'), 'replace_confirm' => '1',
        ])->assertRedirect();
        $this->assertNotSame($firstRevision, $studio->refresh()->cover_image_revision);

        $this->delete(route('admin.branding.studios.cover.destroy', $studio), [
            'confirmation' => 'DELETE', 'digest' => $staleDigest,
        ])->assertSessionHasErrors('confirmation');
        Storage::disk('s3_concerts')->assertExists($key);

        $digest = $this->get(route('admin.branding.studios.cover.confirm-delete', $studio))
            ->assertOk()->assertSee('concert-test')->assertSee($key)->viewData('digest');
        $this->delete(route('admin.branding.studios.cover.destroy', $studio), [
            'confirmation' => 'DELETE', 'digest' => $digest,
        ])->assertRedirect();
        Storage::disk('s3_concerts')->assertMissing($key);
        $this->assertNull($studio->refresh()->cover_image_url);
    }

    public function test_concert_cover_and_program_use_separate_keys_and_program_requires_access(): void
    {
        $concert = Concert::factory()->published()->create(['access_password_hash' => 'secret']);
        $this->actingAs(User::factory()->staff()->create());

        $this->post(route('admin.branding.concerts.cover.store', $concert), [
            'file' => $this->image('poster.png'),
        ])->assertRedirect();
        $this->post(route('admin.branding.concerts.program.store', $concert), [
            'file' => UploadedFile::fake()->createWithContent('program.pdf', "%PDF-1.4\n%%EOF\n"),
        ])->assertRedirect();

        $coverKey = "concerts/{$concert->uuid}/cover";
        $programKey = "concerts/{$concert->uuid}/documents/program.pdf";
        Storage::disk('s3_concerts')->assertExists($coverKey);
        Storage::disk('s3_concerts')->assertExists($programKey);
        $cover = $this->get(route('concerts.cover', $concert))->assertRedirectContains('https://media.dancepro.test/'.$coverKey);
        $program = $this->get(route('concerts.program', $concert))->assertRedirectContains('https://media.dancepro.test/'.$programKey);
        $this->assertStringContainsString('v='.$concert->cover_image_revision, $cover->headers->get('Location'));
        $this->assertStringContainsString('v='.$concert->program_revision, $program->headers->get('Location'));

        auth()->logout();
        $this->get(route('concerts.program', $concert))->assertForbidden();
        $this->get(route('concerts.cover', $concert))->assertRedirectContains('https://media.dancepro.test/'.$coverKey);

        $this->actingAs(User::factory()->staff()->create());
        $digest = $this->get(route('admin.branding.concerts.program.confirm-delete', $concert))
            ->assertOk()->viewData('digest');
        $this->delete(route('admin.branding.concerts.program.destroy', $concert), [
            'confirmation' => 'DELETE', 'digest' => $digest,
        ])->assertRedirect();
        Storage::disk('s3_concerts')->assertMissing($programKey);
        Storage::disk('s3_concerts')->assertExists($coverKey);
        $this->assertNull($concert->refresh()->program_url);

        $digest = $this->get(route('admin.branding.concerts.cover.confirm-delete', $concert))
            ->assertOk()->viewData('digest');
        $this->delete(route('admin.branding.concerts.cover.destroy', $concert), [
            'confirmation' => 'DELETE', 'digest' => $digest,
        ])->assertRedirect();
        Storage::disk('s3_concerts')->assertMissing($coverKey);
        $this->assertNull($concert->refresh()->cover_image_url);
    }

    public function test_booking_event_can_be_linked_and_its_programme_copied_to_concert_storage(): void
    {
        Storage::fake('local');
        $booking = ConcertBooking::query()->create([
            'uuid' => (string) Str::uuid(),
            'studio_name' => 'Example Dance',
            'contact_name' => 'Morgan',
            'contact_email' => 'morgan@example.test',
            'contact_phone' => '0400 000 000',
        ]);
        $item = ConcertBookingItem::query()->create([
            'uuid' => (string) Str::uuid(),
            'concert_booking_id' => $booking->id,
            'item_type' => 'concert',
            'venue_name' => 'Hall',
            'venue_address' => 'Main Street',
            'event_date' => now()->addWeek()->toDateString(),
            'starts_at' => '18:00:00',
            'finishes_at' => '20:00:00',
        ]);
        $source = 'operations/event-programmes/'.$item->uuid.'/programme.pdf';
        Storage::disk('local')->put($source, '%PDF-1.4 booking programme');
        $item->update(['programme_path' => $source, 'programme_mime' => 'application/pdf']);

        $concert = Concert::factory()->create();
        $payload = [
            'studio_id' => $concert->studio_id,
            'concert_booking_item_id' => $item->id,
            'name' => $concert->name,
            'status' => 'draft',
        ];
        $this->actingAs(User::factory()->staff()->create())
            ->put(route('admin.concerts.update', $concert), $payload)->assertRedirect();

        $key = "concerts/{$concert->uuid}/documents/program.pdf";
        Storage::disk('s3_concerts')->assertExists($key);
        $this->assertSame('%PDF-1.4 booking programme', Storage::disk('s3_concerts')->get($key));
        $this->assertSame($item->id, $concert->refresh()->concert_booking_item_id);
        $this->assertSame($source, $concert->booking_programme_path_synced);

        $replacement = 'operations/event-programmes/'.$item->uuid.'/replacement.pdf';
        Storage::disk('local')->put($replacement, '%PDF-1.4 revised programme');
        $item->update(['programme_path' => $replacement]);
        $this->put(route('admin.concerts.update', $concert), $payload)->assertRedirect();
        $this->assertSame('%PDF-1.4 revised programme', Storage::disk('s3_concerts')->get($key));
        $this->assertSame($replacement, $concert->refresh()->booking_programme_path_synced);

        $concert->update(['status' => 'published', 'is_enabled' => true]);
        $this->assertTrue($concert->refresh()->isPubliclyAvailable());
        $item->update(['cancelled_at' => now()]);
        $this->assertFalse($concert->refresh()->isPubliclyAvailable());
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/dNsAAAAASUVORK5CYII='),
        );
    }
}
