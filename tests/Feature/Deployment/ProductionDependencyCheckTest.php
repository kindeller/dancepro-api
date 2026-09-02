<?php

namespace Tests\Feature\Deployment;

use App\Features\Deployment\Services\ProductionDependencyCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionDependencyCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_dependencies_are_checked_without_leaving_a_storage_probe(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config([
            'operations.filesystem_disk' => 'local',
            'uploads.public_disk' => 'public',
            'cache.default' => 'array',
            'mail.default' => 'log',
            'queue.default' => 'database',
        ]);

        $checks = app(ProductionDependencyCheck::class)->run();

        $this->assertSame([
            'database',
            'cache',
            'private storage',
            'public upload storage',
            'mail transport',
            'queue',
        ], $checks);
        Storage::disk('local')->assertDirectoryEmpty('deployment-health');
        Storage::disk('public')->assertDirectoryEmpty('deployment-health');
    }

    public function test_health_endpoint_has_an_exact_machine_readable_success_response(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertExactJson(['status' => 'up']);
    }
}
