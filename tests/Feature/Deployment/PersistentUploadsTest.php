<?php

namespace Tests\Feature\Deployment;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Deployment\Actions\MigratePublicUploads;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Venues\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersistentUploadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracked_public_uploads_can_be_copied_to_durable_storage_without_deleting_sources(): void
    {
        Storage::fake('public');
        Storage::fake('s3_public_uploads');
        config(['uploads.public_disk' => 's3_public_uploads']);

        $paths = [
            'logos/studios/studio.jpg',
            'logos/competition-contacts/contact.jpg',
            'logos/competitions/event.jpg',
            'operations/venues/reference.jpg',
        ];
        foreach ($paths as $path) {
            Storage::disk('public')->put($path, 'image');
        }

        Studio::query()->create(['name' => 'Studio', 'logo_path' => $paths[0]]);
        CompetitionContact::query()->create([
            'name' => 'Competition',
            'organiser_name' => 'Organiser',
            'organiser_email' => 'organiser@example.test',
            'organiser_phone' => '0400 000 000',
            'logo_path' => $paths[1],
        ]);
        SchedulingEvent::query()->create([
            'name' => 'Event',
            'event_type' => 'competition',
            'event_date' => now()->addMonth(),
            'logo_path' => $paths[2],
        ]);
        Venue::query()->create(['name' => 'Venue', 'reference_image_path' => $paths[3]]);

        $dryRun = app(MigratePublicUploads::class)->execute();
        $this->assertSame(4, $dryRun['copied']);
        Storage::disk('s3_public_uploads')->assertDirectoryEmpty('/');

        $result = app(MigratePublicUploads::class)->execute(apply: true);
        $this->assertSame(4, $result['copied']);
        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
            Storage::disk('s3_public_uploads')->assertExists($path);
        }
    }
}
