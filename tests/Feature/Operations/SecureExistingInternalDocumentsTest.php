<?php

namespace Tests\Feature\Operations;

use App\Features\Operations\Actions\SecureExistingInternalDocuments;
use App\Features\Operations\Models\OperationalResource;
use App\Features\Operations\Services\OperationsFileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class SecureExistingInternalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_previews_then_moves_tracked_public_documents(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $resource = OperationalResource::query()->create([
            'title' => 'Handbook',
            'resource_type' => 'handbook',
            'file_path' => 'operations/resources/handbook.pdf',
            'is_active' => true,
        ]);
        Storage::disk('public')->put($resource->file_path, 'internal document');

        $this->artisan('operations:secure-documents')->assertSuccessful();
        Storage::disk('public')->assertExists($resource->file_path);
        Storage::disk('local')->assertMissing($resource->file_path);

        $this->artisan('operations:secure-documents --apply')->assertSuccessful();
        Storage::disk('public')->assertMissing($resource->file_path);
        Storage::disk('local')->assertExists($resource->file_path);
    }

    public function test_public_disk_cannot_be_configured_for_operational_documents(): void
    {
        config(['operations.filesystem_disk' => 'public']);

        $this->expectException(LogicException::class);
        app(OperationsFileStorage::class)->diskName();
    }

    public function test_mismatched_existing_private_copy_is_preserved_and_public_copy_is_not_deleted(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $resource = OperationalResource::query()->create([
            'title' => 'Handbook',
            'resource_type' => 'handbook',
            'file_path' => 'operations/resources/handbook.pdf',
            'is_active' => true,
        ]);
        Storage::disk('public')->put($resource->file_path, 'public document');
        Storage::disk('local')->put($resource->file_path, 'different private document');

        try {
            app(SecureExistingInternalDocuments::class)->execute(true);
            $this->fail('Expected mismatched copies to stop the migration.');
        } catch (RuntimeException) {
            Storage::disk('public')->assertExists($resource->file_path);
            Storage::disk('local')->assertExists($resource->file_path);
            $this->assertSame('different private document', Storage::disk('local')->get($resource->file_path));
        }
    }
}
