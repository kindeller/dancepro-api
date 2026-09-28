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
use App\Features\Scheduling\Models\SchedulingEvent;
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
        Storage::fake('s3_competitions');
        $this->app->instance('env', 'local');

        $this->seed(LocalDevelopmentSeeder::class);

        $counts = $this->domainCounts();

        $realisticConcertAlbums = MediaCollection::query()->whereNotNull('concert_id')
            ->where('metadata->realistic_demo', true)->get();
        if ($realisticConcertAlbums->isNotEmpty()) {
            $juniorConcert = Concert::query()->where('uuid', '75000000-0000-4000-8000-000000000001')->firstOrFail();
            $this->assertSame([$juniorConcert->id], $realisticConcertAlbums->pluck('concert_id')->unique()->values()->all());
        }

        $this->assertGreaterThanOrEqual(5, User::query()->count());
        $this->assertSame(3, CustomerProfile::query()->count());
        $this->assertGreaterThanOrEqual(4, Studio::query()->count());
        $this->assertGreaterThanOrEqual(12, Concert::query()->count());
        $this->assertGreaterThanOrEqual(17, MediaCollection::query()->count());
        $this->assertGreaterThanOrEqual(48, MediaAsset::query()->count());
        $this->assertSame(3, ConcertAccessGrant::query()->count());
        $this->assertSame(4, ConcertAccess::query()->count());
        $this->assertSame(4, DownloadLink::query()->count());
        $this->assertGreaterThanOrEqual(7, Order::query()->count());
        $this->assertGreaterThanOrEqual(7, OrderItem::query()->count());

        $this->assertDatabaseHas('concerts', ['slug' => 'approval-pending', 'requires_approval' => true, 'approved_at' => null]);
        $this->assertDatabaseHas('concerts', ['slug' => 'temporarily-disabled', 'is_enabled' => false]);
        $this->assertDatabaseHas('concert_accesses', ['session_identifier' => 'local-failed-password', 'was_successful' => false]);
        $this->assertDatabaseHas('orders', ['status' => 'fulfilled', 'currency' => 'AUD']);
        $this->assertDatabaseHas('concert_tracker_tasks', ['task_key' => 'director_corrections', 'status' => 'waiting']);
        $this->assertDatabaseHas('concert_tracker_email_deliveries', ['recipient_email' => 'aurora-director@example.test', 'status' => 'sent']);
        $this->assertDatabaseHas('competition_contacts', ['organiser_email' => 'director@sapphire-dance.test']);
        $this->assertDatabaseHas('competition_gallery_settings', ['display_title' => 'Sapphire Dance Challenge - September 2026 DEMO']);
        $this->assertDatabaseHas('competition_gallery_settings', ['display_title' => 'Sapphire October Preview - Coming Soon']);
        $this->assertDatabaseHas('orders', ['uuid' => '88000000-0000-4000-8000-000000000001', 'status' => 'fulfilled']);
        $this->assertDatabaseHas('competition_video_batches', ['uuid' => '89000000-0000-4000-8000-000000000001', 'status' => 'complete']);
        Storage::disk('local')->assertExists('local-development/concerts/moonlit-harbour/videos/opening-number.mp4');
        Storage::disk('s3_competitions')->assertExists('2026/SDC926/SDC926_07_01.mp4');

        $releasedCompetition = SchedulingEvent::query()
            ->where('uuid', '82000000-0000-4000-8000-000000000001')->firstOrFail();
        $customer = User::query()->where('email', 'orders.customer@dancepro.test')->firstOrFail();
        $staff = User::query()->where('email', 'staff@dancepro.test')->firstOrFail();
        $this->assertTrue($staff->canAccessAdmin());
        $guestGallery = $this->get(route('guest-galleries.index'));
        $this->assertSame(200, $guestGallery->getStatusCode(), 'Guest gallery redirected to '.$guestGallery->headers->get('Location'));
        $guestGallery
            ->assertSee('Sapphire Dance Challenge - September 2026 DEMO')
            ->assertSee('Sapphire October Preview - Coming Soon');
        $customerMedia = $this->actingAs($customer)->get(route('customer.media.index'));
        $this->assertSame(200, $customerMedia->getStatusCode(), 'Customer media redirected to '.$customerMedia->headers->get('Location'));
        $customerMedia
            ->assertSee('Sapphire Dance Challenge - September 2026 DEMO')->assertSee('Mia Hart');
        $competitionGallery = $this->get(route('customer.competitions.show', $releasedCompetition));
        $this->assertSame(200, $competitionGallery->getStatusCode(), 'Competition gallery redirected to '.$competitionGallery->headers->get('Location'));
        $competitionGallery
            ->assertSee('07 Jazz 12 &amp; Under', false)->assertSee('08 Improvisation 12 &amp; Under', false);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($staff);
        $this->assertAuthenticatedAs($staff);
        $competitionVideos = $this->get(route('admin.competition-videos.index', $releasedCompetition));
        $this->assertSame(200, $competitionVideos->getStatusCode());
        $competitionVideos->assertSee('SDC926_07_01.mp4')->assertSee('1 verified clip(s)', false);
        $this->get(route('admin.competition.objects.index', ['prefix' => '2026/SDC926']))
            ->assertOk()->assertSee('SDC926_07_01.mp4');
        $concertTracker = $this->get(route('admin.concert-tracker.index'));
        $this->assertSame(200, $concertTracker->getStatusCode(), 'Concert tracker redirected to '.$concertTracker->headers->get('Location'));
        $concertTracker
            ->assertSee('Aurora Matinee')->assertSee('Aurora Spring Gala');
        $mediaSales = $this->get(route('admin.media-sales.index'));
        $this->assertSame(200, $mediaSales->getStatusCode(), 'Media sales redirected to '.$mediaSales->headers->get('Location'));
        $mediaSales->assertSee('$108.00');

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
