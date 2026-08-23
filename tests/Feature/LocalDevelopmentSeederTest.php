<?php

namespace Tests\Feature;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccess;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Customers\Models\CustomerProfile;
use App\Features\Downloads\Models\DownloadLink;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Database\Seeders\LocalDevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class LocalDevelopmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_refuses_to_run_outside_local_environment(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV=local');

        $this->seed(LocalDevelopmentSeeder::class);
    }

    public function test_seeder_creates_repeatable_linked_local_data(): void
    {
        Storage::fake('local');
        $this->app->instance('env', 'local');

        $this->seed(LocalDevelopmentSeeder::class);

        $counts = $this->domainCounts();

        $this->assertGreaterThanOrEqual(5, User::query()->count());
        $this->assertSame(3, CustomerProfile::query()->count());
        $this->assertSame(3, Studio::query()->count());
        $this->assertSame(8, Concert::query()->count());
        $this->assertSame(3, MediaCollection::query()->count());
        $this->assertSame(4, MediaAsset::query()->count());
        $this->assertSame(3, ConcertAccessGrant::query()->count());
        $this->assertSame(4, ConcertAccess::query()->count());
        $this->assertSame(4, DownloadLink::query()->count());
        $this->assertSame(4, Order::query()->count());
        $this->assertSame(4, OrderItem::query()->count());

        $this->assertDatabaseHas('concerts', ['slug' => 'approval-pending', 'requires_approval' => true, 'approved_at' => null]);
        $this->assertDatabaseHas('concerts', ['slug' => 'temporarily-disabled', 'is_enabled' => false]);
        $this->assertDatabaseHas('concert_accesses', ['session_identifier' => 'local-failed-password', 'was_successful' => false]);
        $this->assertDatabaseHas('orders', ['status' => 'fulfilled', 'currency' => 'AUD']);
        Storage::disk('local')->assertExists('local-development/concerts/moonlit-harbour/videos/opening-number.mp4');

        $this->seed(LocalDevelopmentSeeder::class);

        $this->assertSame($counts, $this->domainCounts());
    }

    /** @return array<string, int> */
    private function domainCounts(): array
    {
        return [
            'users' => User::query()->count(),
            'profiles' => CustomerProfile::query()->count(),
            'studios' => Studio::query()->count(),
            'concerts' => Concert::query()->count(),
            'collections' => MediaCollection::query()->count(),
            'assets' => MediaAsset::query()->count(),
            'grants' => ConcertAccessGrant::query()->count(),
            'accesses' => ConcertAccess::query()->count(),
            'downloads' => DownloadLink::query()->count(),
            'orders' => Order::query()->count(),
            'items' => OrderItem::query()->count(),
        ];
    }
}
