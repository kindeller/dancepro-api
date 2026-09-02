<?php

namespace Tests\Feature\Contacts;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Contacts\Actions\ExportContactDirectory;
use App\Features\Contacts\Actions\ImportContactDirectory;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ContactDirectoryTransferTest extends TestCase
{
    use RefreshDatabase;

    private string $archive;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->archive = storage_path('framework/testing/contact-directory.zip');
        File::delete($this->archive);
    }

    public function test_it_exports_validates_and_repeatably_imports_the_directory_with_logos(): void
    {
        $studio = Studio::query()->create([
            'uuid' => '10000000-0000-4000-8000-000000000111',
            'name' => 'Example Dance Studio',
            'code' => 'EDS',
            'slug' => 'example-dance-studio',
            'status' => StudioStatus::Inactive,
            'contact_name' => 'Taylor Example',
            'contact_email' => 'taylor@example.test',
            'contact_phone' => '0400 000 001',
            'logo_path' => 'logos/source/studio.png',
        ]);
        $studio->contacts()->create([
            'name' => 'Taylor Example',
            'role' => 'Owner',
            'emails' => ['taylor@example.test', 'office@example.test'],
            'phone' => '0400 000 001',
            'position' => 0,
        ]);

        $competition = CompetitionContact::query()->create([
            'uuid' => '20000000-0000-4000-8000-000000000222',
            'name' => 'Example Dance Challenge',
            'code' => 'EDC',
            'organiser_name' => 'Morgan Example',
            'organiser_email' => 'morgan@example.test',
            'organiser_phone' => '0400 000 002',
            'is_active' => true,
            'logo_path' => 'logos/source/competition.png',
        ]);
        $competition->staff()->create([
            'name' => 'Morgan Example',
            'role' => 'Director',
            'emails' => ['morgan@example.test'],
            'phone' => '0400 000 002',
            'position' => 0,
        ]);

        Storage::disk('public')->put('logos/source/studio.png', $this->png());
        Storage::disk('public')->put('logos/source/competition.png', $this->png());

        $export = app(ExportContactDirectory::class)->execute($this->archive);
        $this->assertSame(1, $export['studios']);
        $this->assertSame(1, $export['competitions']);
        $this->assertSame(2, $export['logos']);
        $this->assertFileExists($this->archive);

        $studio->forceDelete();
        $competition->forceDelete();

        $dryRun = app(ImportContactDirectory::class)->execute($this->archive);
        $this->assertFalse($dryRun['applied']);
        $this->assertSame(1, $dryRun['new_studios']);
        $this->assertSame(1, $dryRun['new_competitions']);
        $this->assertDatabaseCount('studios', 0);
        $this->assertDatabaseCount('competition_contacts', 0);

        $applied = app(ImportContactDirectory::class)->execute($this->archive, true);
        $this->assertTrue($applied['applied']);
        $this->assertDatabaseHas('studios', ['uuid' => $studio->uuid, 'code' => 'EDS', 'status' => 'inactive']);
        $this->assertDatabaseHas('studio_contacts', ['name' => 'Taylor Example', 'role' => 'Owner']);
        $this->assertDatabaseHas('competition_contacts', ['uuid' => $competition->uuid, 'code' => 'EDC', 'is_active' => true]);
        $this->assertDatabaseHas('competition_contact_staff', ['name' => 'Morgan Example', 'role' => 'Director']);

        $importedStudio = Studio::query()->where('uuid', $studio->uuid)->firstOrFail();
        $importedCompetition = CompetitionContact::query()->where('uuid', $competition->uuid)->firstOrFail();
        Storage::disk('public')->assertExists($importedStudio->logo_path);
        Storage::disk('public')->assertExists($importedCompetition->logo_path);

        $secondImport = app(ImportContactDirectory::class)->execute($this->archive, true);
        $this->assertSame(1, $secondImport['updated_studios']);
        $this->assertSame(1, $secondImport['updated_competitions']);
        $this->assertDatabaseCount('studios', 1);
        $this->assertDatabaseCount('studio_contacts', 1);
        $this->assertDatabaseCount('competition_contacts', 1);
        $this->assertDatabaseCount('competition_contact_staff', 1);
    }

    public function test_it_rejects_a_tampered_logo_before_writing_any_records(): void
    {
        $studio = Studio::query()->create([
            'uuid' => '30000000-0000-4000-8000-000000000333',
            'name' => 'Integrity Studio',
            'code' => 'INT',
            'status' => StudioStatus::Active,
            'logo_path' => 'logos/source/integrity.png',
        ]);
        Storage::disk('public')->put($studio->logo_path, $this->png());
        app(ExportContactDirectory::class)->execute($this->archive);
        $studio->forceDelete();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->archive) === true);
        $zip->addFromString("logos/studios/{$studio->uuid}.png", $this->png().'tampered');
        $zip->close();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('integrity check');

        try {
            app(ImportContactDirectory::class)->execute($this->archive, true);
        } finally {
            $this->assertDatabaseCount('studios', 0);
        }
    }

    public function test_it_rejects_a_name_or_code_collision_with_another_uuid(): void
    {
        $source = Studio::query()->create([
            'uuid' => '40000000-0000-4000-8000-000000000444',
            'name' => 'Collision Studio',
            'code' => 'COL',
            'status' => StudioStatus::Active,
        ]);
        app(ExportContactDirectory::class)->execute($this->archive);
        $source->forceDelete();
        Studio::query()->create([
            'uuid' => '50000000-0000-4000-8000-000000000555',
            'name' => 'Collision Studio',
            'code' => 'OTHER',
            'status' => StudioStatus::Active,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Studio identity conflict');

        app(ImportContactDirectory::class)->execute($this->archive);
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    }
}
