<?php

namespace Tests\Feature\Storage;

use Illuminate\Support\Facades\Route;
use League\Flysystem\UnableToWriteFile;
use Tests\TestCase;

class StorageFailureHandlingTest extends TestCase
{
    public function test_all_application_disks_throw_and_report_failures(): void
    {
        foreach (config('filesystems.disks') as $name => $disk) {
            $this->assertTrue($disk['throw'] ?? false, "Filesystem disk [{$name}] must throw failures.");
            $this->assertTrue($disk['report'] ?? false, "Filesystem disk [{$name}] must report failures.");
        }
    }

    public function test_browser_storage_failure_returns_a_user_visible_retry_message(): void
    {
        Route::post('/test/storage-failure', fn () => throw UnableToWriteFile::atLocation('test.pdf'))
            ->middleware('web');

        $this->from('/previous-page')
            ->post('/test/storage-failure', ['title' => 'Retain this'])
            ->assertRedirect('/previous-page')
            ->assertSessionHasErrors(['file' => 'The file could not be saved. Please try again.'])
            ->assertSessionHasInput('title', 'Retain this');
    }

    public function test_api_storage_failure_returns_consistent_service_unavailable_response(): void
    {
        Route::post('/api/test/storage-failure', fn () => throw UnableToWriteFile::atLocation('test.pdf'));

        $this->postJson('/api/test/storage-failure')
            ->assertStatus(503)
            ->assertExactJson([
                'success' => false,
                'message' => 'File storage is temporarily unavailable. Please try again.',
                'errors' => [],
            ]);
    }
}
