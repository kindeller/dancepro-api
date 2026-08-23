<?php

namespace Tests\Feature\Concerts;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaType;
use App\Features\Studios\Models\Studio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicConcertExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_studios_with_available_concerts_are_listed_alphabetically(): void
    {
        $zulu = Studio::factory()->create(['name' => 'Zulu Dance']);
        $alpha = Studio::factory()->create(['name' => 'Alpha Dance']);
        $hidden = Studio::factory()->create(['name' => 'Hidden Dance']);
        Concert::factory()->published()->for($zulu)->create();
        Concert::factory()->published()->for($alpha)->create();
        Concert::factory()->for($hidden)->create();

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Alpha Dance', 'Zulu Dance'])
            ->assertDontSee('Hidden Dance');
    }

    public function test_password_and_student_name_unlock_a_concert_for_the_session(): void
    {
        $concert = Concert::factory()->published()->create(['access_password_hash' => 'dance123']);

        $this->get(route('concerts.show', $concert))->assertOk()->assertSee('Unlock concert');

        $this->post(route('concerts.unlock', $concert), [
            'student_name' => 'Taylor Student',
            'password' => 'wrong',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseHas('concert_accesses', [
            'concert_id' => $concert->id,
            'student_name' => 'Taylor Student',
            'was_successful' => false,
        ]);

        $this->post(route('concerts.unlock', $concert), [
            'student_name' => 'Taylor Student',
            'password' => 'dance123',
        ])->assertRedirect(route('concerts.show', $concert));

        $this->get(route('concerts.show', $concert))
            ->assertOk()
            ->assertDontSee('Unlock concert')
            ->assertSee('Media is not available yet');
    }

    public function test_unavailable_concert_is_not_public(): void
    {
        $concert = Concert::factory()->published()->create(['available_from' => now()->addDay()]);

        $this->get(route('concerts.show', $concert))->assertNotFound();
        $this->getJson('/api/concerts/'.$concert->uuid)->assertNotFound();
    }

    public function test_signed_download_bypasses_password_without_exposing_an_internal_id(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('concerts/performance.mp4', 'video-content');
        $concert = Concert::factory()->published()->create(['access_password_hash' => 'secret']);
        $collection = MediaCollection::factory()->for($concert)->create([
            'status' => MediaCollectionStatus::Published,
            'media_type' => MediaType::Video,
            'storage_disk' => 'local',
        ]);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create([
            'storage_disk' => 'local',
            'storage_key' => 'concerts/performance.mp4',
            'original_filename' => 'performance.mp4',
            'media_type' => MediaType::Video,
        ]);
        $url = URL::temporarySignedRoute('concerts.media.download', now()->addMinute(), [
            'concert' => $concert,
            'asset' => $asset,
            'access' => 1,
        ]);

        $this->get($url)->assertOk()->assertDownload('performance.mp4');
        $this->assertStringContainsString($concert->uuid, $url);
        $this->assertStringContainsString($asset->uuid, $url);
    }
}
