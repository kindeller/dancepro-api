<?php

namespace Tests\Feature\Venues;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Venues\Actions\ImportVenueCatalog;
use App\Features\Venues\Models\Venue;
use App\Features\Venues\Support\VenueCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportVenueCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_real_venues_maps_notes_and_replaces_placeholders(): void
    {
        Storage::fake('public');
        $source = storage_path('framework/testing/venue-catalog');
        File::deleteDirectory($source);
        File::ensureDirectoryExists($source);

        foreach (VenueCatalog::venues() as $definition) {
            foreach ([$definition['map'], $definition['reference']] as $filename) {
                if ($filename) {
                    File::put($source.DIRECTORY_SEPARATOR.$filename, 'image');
                }
            }
        }

        $placeholder = Venue::query()->create(['name' => 'Fictional Regal Theatre']);
        $event = SchedulingEvent::query()->create([
            'name' => 'Demo event',
            'event_type' => 'concert',
            'event_date' => now()->addMonth(),
            'venue_id' => $placeholder->id,
        ]);
        Venue::query()->create(['name' => 'Fictional Harbour Arts Centre']);

        $summary = app(ImportVenueCatalog::class)->execute($source);

        $this->assertSame(41, $summary['venues']);
        $this->assertSame(35, $summary['maps']);
        $this->assertSame(1, $summary['references']);
        $this->assertSame(1, $summary['removed']);
        $this->assertDatabaseMissing('venues', ['name' => 'Fictional Regal Theatre']);
        $this->assertDatabaseMissing('venues', ['name' => 'Fictional Harbour Arts Centre']);

        $regal = Venue::query()->where('name', 'Regal Theatre')->firstOrFail();
        $this->assertSame($regal->id, $event->refresh()->venue_id);

        $kingsway = Venue::query()->where('name', 'Kingsway Christian College Auditorium')->firstOrFail();
        Storage::disk('public')->assertExists($kingsway->map_path);
        Storage::disk('public')->assertExists($kingsway->reference_image_path);

        $nexus = Venue::query()->where('name', 'Nexus Theatre - Murdoch University')->firstOrFail();
        $this->assertStringContainsString('XLR sound feed', $nexus->operational_notes);
    }
}
