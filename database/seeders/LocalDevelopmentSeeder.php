<?php

namespace Database\Seeders;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccess;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Concerts\Support\ConcertAccessGrantSource;
use App\Features\Concerts\Support\ConcertAccessGrantStatus;
use App\Features\Concerts\Support\ConcertAccessMethod;
use App\Features\Concerts\Support\ConcertStatus;
use App\Features\Customers\Models\CustomerProfile;
use App\Features\Customers\Support\UserType;
use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Support\DownloadLinkStatus;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaAssetLocation;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Support\MediaAssetLocationStatus;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaCatalogueMode;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaCollectionVisibility;
use App\Features\Media\Support\MediaType;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LocalDevelopmentSeeder extends Seeder
{
    private const PASSWORD = 'local-demo-password';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('LocalDevelopmentSeeder may only run when APP_ENV=local.');
        }

        [$staff, $customers] = $this->seedUsers();
        [$openConcert, $protectedConcert, $emptyConcert] = $this->seedStudiosAndConcerts($staff);
        [$videoCollection, $videoAssets, $photoCollection, $photoAsset] = $this->seedMedia($openConcert, $protectedConcert);
        $this->seedAccess($staff, $customers, $protectedConcert, $openConcert);
        $orders = $this->seedOrders($customers, $photoCollection, $photoAsset);
        $this->seedDownloadLinks($staff, $openConcert, $videoCollection, $videoAssets, $orders);
        $this->writeLocalPlaceholderFiles([...$videoAssets, $photoAsset]);
        $this->call([
            ConcertBookingDemoSeeder::class,
            CrewSchedulingDemoSeeder::class,
            EventOperationsSeeder::class,
            PaymentPlaceholderSeeder::class,
            TimesheetInvoiceDemoSeeder::class,
        ]);

        $this->command?->info('Local DancePro development data seeded.');
        $this->command?->warn('Fictional local logins use password: '.self::PASSWORD);
        $this->command?->line('Public examples include open, password-protected, empty-media, draft, awaiting-approval, disabled and expired concerts.');
        $this->command?->line('Empty-media concert: '.$emptyConcert->name);
    }

    /** @return array{0: User, 1: array<string, User>} */
    private function seedUsers(): array
    {
        $staff = $this->user('staff@dancepro.test', 'Morgan Vale', UserType::Staff, true);
        $this->user('inactive.staff@dancepro.test', 'Casey North', UserType::Staff, false);

        $customers = [
            'granted' => $this->user('granted.customer@dancepro.test', 'Avery Rowan', UserType::Customer, true),
            'ungranted' => $this->user('ungranted.customer@dancepro.test', 'Jordan Wren', UserType::Customer, true),
            'order' => $this->user('orders.customer@dancepro.test', 'Riley Cove', UserType::Customer, true),
        ];

        foreach ($customers as $key => $customer) {
            CustomerProfile::withTrashed()->updateOrCreate(
                ['user_id' => $customer->id],
                [
                    'preferred_name' => $customer->name,
                    'phone' => $key === 'ungranted' ? null : '0400 000 00'.($key === 'granted' ? '1' : '2'),
                    'registration_source' => 'local_development',
                    'terms_accepted_at' => now()->subMonths(2),
                    'privacy_accepted_at' => now()->subMonths(2),
                    'marketing_consent_at' => $key === 'order' ? now()->subMonth() : null,
                    'preferences' => ['email_updates' => $key === 'order'],
                    'deleted_at' => null,
                ],
            );
        }

        return [$staff, $customers];
    }

    private function user(string $email, string $name, UserType $type, bool $active): User
    {
        return User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'type' => $type->value,
                'is_active' => $active,
                'email_verified_at' => now()->subMonths(3),
                'password' => self::PASSWORD,
                'deleted_at' => null,
            ],
        );
    }

    /** @return array{Concert, Concert, Concert} */
    private function seedStudiosAndConcerts(User $staff): array
    {
        $harbour = $this->studio('10000000-0000-4000-8000-000000000001', 'harbour-light-dance', 'Harbour Light Dance', StudioStatus::Active, '#0A84A6');
        $paperbird = $this->studio('10000000-0000-4000-8000-000000000002', 'paperbird-performing-arts', 'Paperbird Performing Arts', StudioStatus::Active, '#8A5DA8');
        $quiet = $this->studio('10000000-0000-4000-8000-000000000003', 'quiet-stage-academy', 'Quiet Stage Academy', StudioStatus::Inactive, '#A66732');

        $open = $this->concert('20000000-0000-4000-8000-000000000001', $harbour, $staff, [
            'name' => 'Moonlit Harbour', 'slug' => 'moonlit-harbour', 'status' => ConcertStatus::Published,
            'event_date' => now()->subWeeks(3)->toDateString(), 'published_at' => now()->subWeeks(2),
            'description' => 'An open local-development concert with a program and managed video examples.',
            'venue_name' => 'Fictional Bay Theatre', 'program_url' => 'https://example.test/programs/moonlit-harbour',
            'external_gallery_url' => 'https://example.test/galleries/moonlit-harbour',
        ]);
        $protected = $this->concert('20000000-0000-4000-8000-000000000002', $paperbird, $staff, [
            'name' => 'Paper Wings', 'slug' => 'paper-wings', 'status' => ConcertStatus::Published,
            'event_date' => now()->subMonth()->toDateString(), 'published_at' => now()->subWeeks(3),
            'description' => 'A password-protected released concert without a program.',
            'venue_name' => 'Fictional Lantern Hall', 'access_password_hash' => 'paper-wings-demo',
        ]);
        $empty = $this->concert('20000000-0000-4000-8000-000000000003', $harbour, $staff, [
            'name' => 'Waiting in the Wings', 'slug' => 'waiting-in-the-wings', 'status' => ConcertStatus::Published,
            'event_date' => now()->subWeek()->toDateString(), 'published_at' => now()->subDays(4),
            'description' => 'A released concert with no available media or program.',
        ]);
        $this->concert('20000000-0000-4000-8000-000000000004', $harbour, $staff, [
            'name' => 'First Rehearsal', 'slug' => 'first-rehearsal', 'status' => ConcertStatus::Draft,
            'event_date' => now()->addMonths(2)->toDateString(), 'description' => 'A draft concert.',
        ]);
        $this->concert('20000000-0000-4000-8000-000000000005', $paperbird, $staff, [
            'name' => 'Approval Pending', 'slug' => 'approval-pending', 'status' => ConcertStatus::Published,
            'event_date' => now()->subDays(5)->toDateString(), 'published_at' => now()->subDays(2),
            'requires_approval' => true, 'approved_at' => null, 'approved_by_user_id' => null,
        ]);
        $this->concert('20000000-0000-4000-8000-000000000006', $paperbird, $staff, [
            'name' => 'Past Availability', 'slug' => 'past-availability', 'status' => ConcertStatus::Published,
            'event_date' => now()->subYear()->toDateString(), 'published_at' => now()->subYear(),
            'available_from' => now()->subYear(), 'available_until' => now()->subMonth(),
        ]);
        $this->concert('20000000-0000-4000-8000-000000000007', $harbour, $staff, [
            'name' => 'Temporarily Disabled', 'slug' => 'temporarily-disabled', 'status' => ConcertStatus::Published,
            'event_date' => now()->subDays(10)->toDateString(), 'published_at' => now()->subWeek(), 'is_enabled' => false,
        ]);
        $this->concert('20000000-0000-4000-8000-000000000008', $quiet, $staff, [
            'name' => 'Hidden Studio Concert', 'slug' => 'hidden-studio-concert', 'status' => ConcertStatus::Published,
            'event_date' => now()->subDays(8)->toDateString(), 'published_at' => now()->subWeek(),
        ]);

        return [$open, $protected, $empty];
    }

    private function studio(string $uuid, string $slug, string $name, StudioStatus $status, string $brandColor): Studio
    {
        $studio = Studio::withTrashed()->updateOrCreate(['uuid' => $uuid], [
            'name' => $name, 'slug' => $slug, 'status' => $status,
            'description' => 'A fictional studio created only for local DancePro development.',
            'brand_color' => $brandColor, 'contact_name' => 'Local Studio Contact',
            'contact_email' => $slug.'@example.test', 'notes' => 'Local development data.', 'deleted_at' => null,
        ]);

        $studio->contacts()->updateOrCreate(['position' => 0], [
            'name' => 'Local Studio Contact',
            'role' => 'Placeholder contact',
            'emails' => [$slug.'@example.test'],
        ]);

        return $studio;
    }

    private function concert(string $uuid, Studio $studio, User $staff, array $attributes): Concert
    {
        return Concert::withTrashed()->updateOrCreate(['uuid' => $uuid], [
            'studio_id' => $studio->id, 'is_enabled' => true, 'requires_approval' => false,
            'storage_disk' => 'local', 'storage_prefix' => "local-development/concerts/{$uuid}/",
            'created_by_user_id' => $staff->id, 'updated_by_user_id' => $staff->id,
            'brand_color' => $studio->brand_color, 'deleted_at' => null, ...$attributes,
        ]);
    }

    /** @return array{MediaCollection, list<MediaAsset>, MediaCollection, MediaAsset} */
    private function seedMedia(Concert $open, Concert $protected): array
    {
        $openVideos = $this->collection('30000000-0000-4000-8000-000000000001', $open, 'Performance Videos', MediaType::Video, MediaCatalogueMode::Managed, MediaCollectionVisibility::Public, 1);
        $protectedVideos = $this->collection('30000000-0000-4000-8000-000000000002', $protected, 'Protected Performance Videos', MediaType::Video, MediaCatalogueMode::Managed, MediaCollectionVisibility::Password, 1);
        $photos = $this->collection('30000000-0000-4000-8000-000000000003', $open, 'Performance Photos', MediaType::Photo, MediaCatalogueMode::Hybrid, MediaCollectionVisibility::Public, 2);

        $assets = [
            $this->asset('40000000-0000-4000-8000-000000000001', $openVideos, 'local-development/concerts/moonlit-harbour/videos/opening-number.mp4', 'Opening Number', MediaType::Video, 1),
            $this->asset('40000000-0000-4000-8000-000000000002', $openVideos, 'local-development/concerts/moonlit-harbour/videos/finale.mp4', 'Finale', MediaType::Video, 2),
            $this->asset('40000000-0000-4000-8000-000000000003', $protectedVideos, 'local-development/concerts/paper-wings/videos/feature-dance.mp4', 'Feature Dance', MediaType::Video, 1),
        ];
        $photo = $this->asset('40000000-0000-4000-8000-000000000004', $photos, 'local-development/concerts/moonlit-harbour/photos/IMG_0001.jpg', 'Curtain Call Photo', MediaType::Photo, 1);

        MediaAssetLocation::updateOrCreate(
            ['storage_disk' => 'local', 'storage_key' => 'local-development/archive/opening-number-original.mp4'],
            ['media_asset_id' => $assets[0]->id, 'status' => MediaAssetLocationStatus::Retired, 'became_active_at' => now()->subYear(), 'retired_at' => now()->subMonths(2), 'metadata' => ['environment' => 'local']],
        );

        return [$openVideos, $assets, $photos, $photo];
    }

    private function collection(string $uuid, Concert $concert, string $name, MediaType $type, MediaCatalogueMode $mode, MediaCollectionVisibility $visibility, int $sort): MediaCollection
    {
        return MediaCollection::withTrashed()->updateOrCreate(['uuid' => $uuid], [
            'concert_id' => $concert->id, 'competition_id' => null, 'name' => $name,
            'media_type' => $type, 'catalogue_mode' => $mode, 'status' => MediaCollectionStatus::Published,
            'visibility' => $visibility, 'storage_disk' => 'local',
            'storage_prefix' => $concert->storage_prefix.strtolower($type->value).'s/',
            'sort_order' => $sort, 'published_at' => now()->subWeek(),
            'metadata' => ['environment' => 'local'], 'deleted_at' => null,
        ]);
    }

    private function asset(string $uuid, MediaCollection $collection, string $key, string $name, MediaType $type, int $sort): MediaAsset
    {
        $extension = pathinfo($key, PATHINFO_EXTENSION);

        return MediaAsset::withTrashed()->updateOrCreate(['uuid' => $uuid], [
            'media_collection_id' => $collection->id, 'media_type' => $type,
            'storage_disk' => 'local', 'storage_key' => $key, 'original_filename' => basename($key),
            'display_name' => $name, 'status' => MediaAssetStatus::Available, 'is_visible' => true,
            'sort_order' => $sort, 'size_bytes' => 2048, 'duration_seconds' => $type === MediaType::Video ? 180 : null,
            'mime_type' => $type === MediaType::Video ? 'video/mp4' : 'image/jpeg', 'extension' => $extension,
            'verified_at' => now(), 'metadata' => ['placeholder' => true, 'environment' => 'local'], 'deleted_at' => null,
        ]);
    }

    private function seedAccess(User $staff, array $customers, Concert $protected, Concert $open): void
    {
        $claimed = ConcertAccessGrant::withTrashed()->updateOrCreate(
            ['uuid' => '50000000-0000-4000-8000-000000000001'],
            ['concert_id' => $protected->id, 'user_id' => $customers['granted']->id, 'email' => $customers['granted']->email, 'source' => ConcertAccessGrantSource::Invitation, 'status' => ConcertAccessGrantStatus::Claimed, 'granted_by_user_id' => $staff->id, 'claimed_at' => now()->subWeek(), 'first_accessed_at' => now()->subWeek(), 'last_accessed_at' => now()->subDay(), 'metadata' => ['environment' => 'local'], 'deleted_at' => null],
        );
        ConcertAccessGrant::withTrashed()->updateOrCreate(
            ['uuid' => '50000000-0000-4000-8000-000000000002'],
            ['concert_id' => $protected->id, 'user_id' => null, 'email' => 'invited.viewer@dancepro.test', 'source' => ConcertAccessGrantSource::Invitation, 'status' => ConcertAccessGrantStatus::Active, 'granted_by_user_id' => $staff->id, 'expires_at' => now()->addMonth(), 'metadata' => ['environment' => 'local'], 'deleted_at' => null],
        );
        ConcertAccessGrant::withTrashed()->updateOrCreate(
            ['uuid' => '50000000-0000-4000-8000-000000000003'],
            ['concert_id' => $protected->id, 'user_id' => null, 'email' => 'expired.viewer@dancepro.test', 'source' => ConcertAccessGrantSource::Invitation, 'status' => ConcertAccessGrantStatus::Expired, 'granted_by_user_id' => $staff->id, 'expires_at' => now()->subWeek(), 'metadata' => ['environment' => 'local'], 'deleted_at' => null],
        );

        $this->access('local-successful-password', $protected, null, null, ConcertAccessMethod::Password, true, 'Fictional Student');
        $this->access('local-failed-password', $protected, null, null, ConcertAccessMethod::Password, false, 'Fictional Student', 'invalid_password');
        $this->access('local-claimed-customer', $protected, $customers['granted'], $claimed, ConcertAccessMethod::SavedAccess, true, 'Avery Student');
        $this->access('local-open-concert', $open, $customers['ungranted'], null, ConcertAccessMethod::Account, true, null);
    }

    private function access(string $session, Concert $concert, ?User $user, ?ConcertAccessGrant $grant, ConcertAccessMethod $method, bool $successful, ?string $student, ?string $failure = null): void
    {
        ConcertAccess::updateOrCreate(
            ['concert_id' => $concert->id, 'session_identifier' => $session],
            ['user_id' => $user?->id, 'concert_access_grant_id' => $grant?->id, 'access_method' => $method, 'accessed_at' => now()->subDays(2), 'last_seen_at' => $successful ? now()->subDay() : null, 'student_name' => $student, 'ip_address' => '192.0.2.10', 'user_agent' => 'DancePro local development browser', 'referrer' => 'http://localhost', 'was_successful' => $successful, 'failure_reason' => $failure],
        );
    }

    /** @return array<string, array{order: Order, item: OrderItem}> */
    private function seedOrders(array $customers, MediaCollection $collection, MediaAsset $asset): array
    {
        $definitions = [
            'draft' => ['60000000-0000-4000-8000-000000000001', OrderStatus::Draft, null, null, null, null],
            'paid' => ['60000000-0000-4000-8000-000000000002', OrderStatus::Paid, now()->subDays(3), now()->subDays(3), null, null],
            'fulfilled' => ['60000000-0000-4000-8000-000000000003', OrderStatus::Fulfilled, now()->subWeek(), now()->subWeek(), now()->subDays(5), null],
            'cancelled' => ['60000000-0000-4000-8000-000000000004', OrderStatus::Cancelled, now()->subDays(4), null, null, now()->subDays(3)],
        ];
        $orders = [];

        foreach ($definitions as $key => [$uuid, $status, $placed, $paid, $fulfilled, $cancelled]) {
            $order = Order::withTrashed()->updateOrCreate(['uuid' => $uuid], [
                'user_id' => $customers['order']->id, 'customer_email' => $customers['order']->email,
                'customer_name' => $customers['order']->name, 'status' => $status, 'currency' => 'AUD',
                'subtotal_amount' => 1500, 'total_amount' => 1500, 'placed_at' => $placed,
                'paid_at' => $paid, 'fulfilled_at' => $fulfilled, 'cancelled_at' => $cancelled,
                'metadata' => ['environment' => 'local', 'reference' => "local-{$key}"], 'deleted_at' => null,
            ]);
            $item = OrderItem::updateOrCreate(
                ['order_id' => $order->id, 'snapshot_storage_key' => $asset->storage_key],
                ['media_collection_id' => $collection->id, 'media_asset_id' => $asset->id, 'snapshot_storage_disk' => 'local', 'snapshot_filename' => $asset->original_filename, 'snapshot_display_name' => $asset->display_name, 'item_type' => 'media', 'quantity' => 1, 'unit_price_amount' => 1500, 'total_price_amount' => 1500, 'metadata' => ['environment' => 'local']],
            );
            $orders[$key] = compact('order', 'item');
        }

        return $orders;
    }

    private function seedDownloadLinks(User $staff, Concert $concert, MediaCollection $collection, array $assets, array $orders): void
    {
        $definitions = [
            ['70000000-0000-4000-8000-000000000001', 'local-active-download-token', DownloadLinkStatus::ACTIVE, now()->addMonth(), $assets[0], null, null],
            ['70000000-0000-4000-8000-000000000002', 'local-expired-download-token', DownloadLinkStatus::EXPIRED, now()->subDay(), $assets[1], null, null],
            ['70000000-0000-4000-8000-000000000003', 'local-revoked-download-token', DownloadLinkStatus::REVOKED, now()->addMonth(), $assets[0], now()->subDay(), $staff],
            ['70000000-0000-4000-8000-000000000004', 'local-order-download-token', DownloadLinkStatus::ACTIVE, now()->addDays(14), $assets[0], null, null],
        ];

        foreach ($definitions as $index => [$uuid, $token, $status, $expires, $asset, $revokedAt, $revokedBy]) {
            DownloadLink::withTrashed()->updateOrCreate(['uuid' => $uuid], [
                'generated_by_user_id' => $staff->id, 'concert_id' => $concert->id,
                'media_collection_id' => $collection->id, 'media_asset_id' => $asset->id,
                'order_item_id' => $index === 3 ? $orders['fulfilled']['item']->id : null,
                'storage_disk' => 'local', 'storage_key' => $asset->storage_key,
                'original_filename' => $asset->original_filename, 'purpose' => $index === 3 ? 'Fulfilled local order' : 'Local concert download',
                'token_hash' => hash('sha256', $token), 'status' => $status, 'expires_at' => $expires,
                'download_count' => $index === 1 ? 2 : 0, 'revoked_at' => $revokedAt,
                'revoked_by_user_id' => $revokedBy?->id, 'revoke_reason' => $revokedAt ? 'Local demonstration revocation.' : null,
                'notes' => 'Fictional local development record.', 'deleted_at' => null,
            ]);
        }
    }

    /** @param list<MediaAsset> $assets */
    private function writeLocalPlaceholderFiles(array $assets): void
    {
        foreach ($assets as $asset) {
            if (! Storage::disk('local')->exists($asset->storage_key)) {
                Storage::disk('local')->put($asset->storage_key, "DancePro fictional local placeholder for {$asset->display_name}.\n");
            }
        }
    }
}
