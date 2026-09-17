<?php

namespace Tests\Feature\Admin;

use App\Features\Concerts\Models\Concert;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBrandingMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3_concerts');
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
        $this->get(route('concerts.cover', $concert))->assertOk();
        $this->get(route('concerts.program', $concert))->assertOk();

        auth()->logout();
        $this->get(route('concerts.program', $concert))->assertForbidden();
        $this->get(route('concerts.cover', $concert))->assertOk();

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

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/dNsAAAAASUVORK5CYII='),
        );
    }
}
