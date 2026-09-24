<?php

namespace Database\Seeders;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\CompetitionOrderCatalogue;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Programmes\Models\CompetitionGallerySetting;
use App\Features\Programmes\Models\CompetitionRoutineStudio;
use App\Features\Programmes\Models\CompetitionVideoBatch;
use App\Features\Programmes\Models\CompetitionVideoFile;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class CompetitionGalleryDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('CompetitionGalleryDemoSeeder may only run locally.');
        }

        $actor = User::query()->where('email', 'staff@dancepro.test')->firstOrFail();
        $customer = User::query()->where('email', 'orders.customer@dancepro.test')->firstOrFail();
        $studio = Studio::query()->where('slug', 'harbour-light-dance')->firstOrFail();
        $contact = CompetitionContact::withTrashed()->updateOrCreate(
            ['uuid' => '81000000-0000-4000-8000-000000000001'],
            ['name' => 'Sapphire Dance Challenge - DEMO', 'code' => 'SDC', 'organiser_name' => 'Taylor Demo',
                'organiser_email' => 'director@sapphire-dance.test', 'organiser_phone' => '0400 000 810',
                'is_active' => true, 'notes' => 'Fictional local competition organiser.', 'deleted_at' => null],
        );

        $released = $this->event($contact, '82000000-0000-4000-8000-000000000001',
            'Sapphire Dance Challenge - September 2026 DEMO', today()->subDays(18)->toDateString());
        $processing = $this->event($contact, '82000000-0000-4000-8000-000000000002',
            'Sapphire Dance Challenge - Spring Session DEMO', today()->subDays(2)->toDateString());
        $upcoming = $this->event($contact, '82000000-0000-4000-8000-000000000003',
            'Sapphire Dance Challenge - October Preview DEMO', today()->addDays(6)->toDateString());

        [$items, $collections, $videos] = $this->releasedGallery($released, $actor);
        $this->processingGallery($processing, $actor);
        CompetitionGallerySetting::updateOrCreate(['scheduling_event_id' => $upcoming->id], [
            'display_title' => 'Sapphire October Preview - Coming Soon', 'login_code_hash' => Hash::make('SAPPHIRE26'), 'version' => 1,
        ]);

        CustomerCompetitionRegistration::updateOrCreate(
            ['user_id' => $customer->id, 'scheduling_event_id' => $released->id],
            ['access_verified_at' => now()->subDays(12)],
        );
        CustomerDancer::updateOrCreate(['user_id' => $customer->id, 'name' => 'Mia Hart'], [
            'relationship' => 'child', 'programme_names' => ['Mia Hart'],
        ]);
        CustomerStudio::updateOrCreate(['user_id' => $customer->id, 'studio_id' => $studio->id], [
            'access_verified_at' => now()->subMonth(),
        ]);
        foreach ($items as $item) {
            CompetitionRoutineStudio::updateOrCreate(
                ['scheduling_event_id' => $released->id, 'item_uuid' => $item],
                ['studio_id' => $studio->id, 'updated_by_user_id' => $actor->id],
            );
        }

        $this->orders($customer, $released, $collections, $videos);
        $this->command?->info('Fictional competition galleries seeded: released, processing and coming soon.');
        $this->command?->line('Competition gallery login code: SAPPHIRE26');
    }

    private function event(CompetitionContact $contact, string $uuid, string $name, string $date): SchedulingEvent
    {
        return SchedulingEvent::updateOrCreate(['uuid' => $uuid], [
            'competition_contact_id' => $contact->id, 'name' => $name, 'event_type' => 'competition',
            'event_date' => $date, 'organiser_name' => 'Taylor Demo', 'organiser_email' => 'director@sapphire-dance.test',
            'organiser_phone' => '0400 000 810', 'availability_status' => 'closed', 'roster_status' => 'published',
            'roster_published_at' => now()->subMonth(), 'admin_notes' => 'Fictional competition gallery demonstration.',
        ]);
    }

    /** @return array{list<string>, list<MediaCollection>, list<MediaCollection>} */
    private function releasedGallery(SchedulingEvent $event, User $actor): array
    {
        $day = '83000000-0000-4000-8000-000000000001';
        $sections = [
            ['84000000-0000-4000-8000-000000000001', '7', 'Jazz 12 & Under', [
                ['85000000-0000-4000-8000-000000000001', '1', 'Mia Hart'],
                ['85000000-0000-4000-8000-000000000002', '2', 'Zoe Lane'],
                ['85000000-0000-4000-8000-000000000003', '3', 'Lily West'],
            ]],
            ['84000000-0000-4000-8000-000000000002', '8', 'Improvisation 12 & Under', [
                ['85000000-0000-4000-8000-000000000004', '1', 'Mia Hart'],
                ['85000000-0000-4000-8000-000000000005', '2', 'Ava Brooks'],
                ['85000000-0000-4000-8000-000000000006', '3', 'Ruby Quinn'],
            ]],
        ];
        $nodes = [['uuid' => $day, 'kind' => 'day', 'date' => $event->event_date->toDateString(), 'title' => $event->event_date->format('l')]];
        $items = [];
        foreach ($sections as [$sectionUuid, $number, $title, $competitors]) {
            $nodes[] = ['uuid' => $sectionUuid, 'kind' => 'section', 'number' => $number, 'title' => $title, 'parent_uuid' => $day];
            foreach ($competitors as [$uuid, $competitorNumber, $name]) {
                $nodes[] = ['uuid' => $uuid, 'kind' => 'item', 'number' => $competitorNumber, 'title' => $name,
                    'parent_uuid' => $sectionUuid, 'item_kind' => 'dance'];
                $items[] = $uuid;
            }
        }
        [$plan, $gallery] = $this->programme($event, $actor, 'SDC926', $nodes, 1);
        $this->completePhotoRoles($event, $plan, $gallery, $items, $actor);

        $collections = [];
        $videos = [];
        foreach ($items as $index => $itemUuid) {
            $catalogue = app(CompetitionOrderCatalogue::class)->readyRoutine($event, $itemUuid);
            $collections[] = $catalogue['collection'];
            $videos[] = $this->video($event, $itemUuid, $index + 1);
        }
        $this->completedVideoBatch($event, $plan, $videos, $actor);
        $firstPhoto = ProgrammePhotoFile::where('programme_photo_gallery_id', $gallery->id)->orderBy('id')->firstOrFail();
        CompetitionGallerySetting::updateOrCreate(['scheduling_event_id' => $event->id], [
            'display_title' => 'Sapphire Dance Challenge - September 2026 DEMO', 'cover_photo_uuid' => $firstPhoto->uuid,
            'login_code_hash' => Hash::make('SAPPHIRE26'), 'version' => 1,
        ]);

        return [$items, $collections, $videos];
    }

    private function processingGallery(SchedulingEvent $event, User $actor): void
    {
        $day = '83000000-0000-4000-8000-000000000002';
        $section = '84000000-0000-4000-8000-000000000003';
        $item = '85000000-0000-4000-8000-000000000007';
        $nodes = [
            ['uuid' => $day, 'kind' => 'day', 'date' => $event->event_date->toDateString(), 'title' => $event->event_date->format('l')],
            ['uuid' => $section, 'kind' => 'section', 'number' => '1', 'title' => 'Contemporary 10 & Under', 'parent_uuid' => $day],
            ['uuid' => $item, 'kind' => 'item', 'number' => '1', 'title' => 'Noah Vale', 'parent_uuid' => $section, 'item_kind' => 'dance'],
        ];
        [$plan, $gallery] = $this->programme($event, $actor, 'SDC926S', $nodes, 2);
        $role = CrewRole::where('code', 'competition-photographer-p1')->firstOrFail();
        $profile = CrewProfile::whereHas('roles', fn ($query) => $query->whereKey($role->id))->firstOrFail();
        $event->roleRequirements()->updateOrCreate(['crew_role_id' => $role->id], ['quantity' => 1]);
        $contribution = ProgrammePhotoContribution::updateOrCreate([
            'programme_upload_plan_id' => $plan->id, 'item_uuid' => $item, 'crew_profile_id' => $profile->id, 'crew_role_id' => $role->id,
        ], ['registered_by_user_id' => $actor->id, 'assignment_uuids' => [], 'status' => 'receiving', 'completed_at' => null]);
        $this->photo($gallery, $contribution, 1, 'Spring upload in progress', '#a85fa5', false);
        CompetitionGallerySetting::updateOrCreate(['scheduling_event_id' => $event->id], [
            'display_title' => 'Sapphire Spring Session - Photos Processing', 'login_code_hash' => Hash::make('SPRING26'), 'version' => 1,
        ]);
    }

    /** @return array{ProgrammeUploadPlan, ProgrammePhotoGallery} */
    private function programme(SchedulingEvent $event, User $actor, string $eventCode, array $nodes, int $suffix): array
    {
        $programme = Programme::updateOrCreate(['scheduling_event_id' => $event->id], ['draft_version' => 1, 'updated_by_user_id' => $actor->id]);
        $snapshot = ['event_type' => 'competition', 'event_code' => $eventCode, 'nodes' => $nodes];
        $revision = $programme->revisions()->updateOrCreate(['version' => 1], [
            'snapshot' => $snapshot, 'confirmed_by_user_id' => $actor->id, 'confirmed_at' => now()->subDays(25 - $suffix),
        ]);
        $digest = hash('sha256', json_encode($snapshot));
        $plan = ProgrammeUploadPlan::updateOrCreate(['programme_id' => $programme->id, 'digest' => $digest], [
            'programme_revision_id' => $revision->id, 'created_by_user_id' => $actor->id, 'snapshot' => $snapshot,
        ]);
        $gallery = ProgrammePhotoGallery::updateOrCreate(['scheduling_event_id' => $event->id], [
            'programme_upload_plan_id' => $plan->id, 'visibility' => 'private',
        ]);

        return [$plan, $gallery];
    }

    private function completePhotoRoles(SchedulingEvent $event, ProgrammeUploadPlan $plan, ProgrammePhotoGallery $gallery, array $items, User $actor): void
    {
        $event->shifts()->updateOrCreate(['period' => 'morning'], [
            'shift_date' => $event->event_date, 'starts_at' => $event->event_date->copy()->setTime(8, 0),
            'estimated_finish_at' => $event->event_date->copy()->setTime(13, 0),
        ]);
        foreach (['competition-photographer-p1', 'competition-photographer-p2'] as $roleIndex => $roleCode) {
            $role = CrewRole::where('code', $roleCode)->firstOrFail();
            $profile = CrewProfile::whereHas('roles', fn ($query) => $query->whereKey($role->id))->firstOrFail();
            $event->roleRequirements()->updateOrCreate(['crew_role_id' => $role->id], ['quantity' => 1]);
            foreach ($items as $itemIndex => $itemUuid) {
                $contribution = ProgrammePhotoContribution::updateOrCreate([
                    'programme_upload_plan_id' => $plan->id, 'item_uuid' => $itemUuid,
                    'crew_profile_id' => $profile->id, 'crew_role_id' => $role->id,
                ], ['registered_by_user_id' => $actor->id, 'assignment_uuids' => [], 'status' => 'complete',
                    'completed_at' => now()->subDays(15), 'completed_by_user_id' => $actor->id]);
                foreach (range(1, 3) as $photo) {
                    $colors = ['#126b8f', '#7a4f9d', '#c05e75', '#27816c', '#a26a22', '#4457a5'];
                    $this->photo($gallery, $contribution, $photo, 'Routine '.($itemIndex + 1).' · Photo '.($roleIndex * 3 + $photo),
                        $colors[$itemIndex % count($colors)], $photo === 1 && $roleIndex === 0);
                }
            }
        }
    }

    private function photo(ProgrammePhotoGallery $gallery, ProgrammePhotoContribution $contribution, int $number, string $label, string $color, bool $top): ProgrammePhotoFile
    {
        $uuid = Uuid::uuid5($contribution->uuid, 'demo-photo/'.$number)->toString();
        $key = 'local-development/competition-gallery/'.$gallery->uuid.'/'.$contribution->item_uuid.'/'.$contribution->role->code.'-'.$number.'.jpg';
        $bytes = $this->image($label, $color);
        Storage::disk('local')->put($key, $bytes);

        return ProgrammePhotoFile::updateOrCreate(['uuid' => $uuid], [
            'programme_photo_contribution_id' => $contribution->id, 'programme_photo_gallery_id' => $gallery->id,
            'client_photo_uuid' => $uuid, 'file_name' => basename($key), 'byte_count' => strlen($bytes),
            'sha256' => hash('sha256', $bytes), 'is_top_pic' => $top, 'status' => 'verified',
            'storage_disk' => 'local', 'storage_key' => $key, 'verified_at' => now()->subDays(15),
        ]);
    }

    private function video(SchedulingEvent $event, string $itemUuid, int $number): MediaCollection
    {
        $eventCode = 'SDC926';
        $disk = (string) config('media.competition_disk');
        $key = sprintf('%s/%s/%s_%02d_%02d.mp4', $event->event_date->format('Y'), $eventCode, $eventCode,
            intdiv($number - 1, 3) + 7, (($number - 1) % 3) + 1);
        $collectionUuid = Uuid::uuid5($event->uuid, 'customer-video-routine/'.$itemUuid)->toString();
        $collection = MediaCollection::withTrashed()->where('uuid', $collectionUuid)->first()
            ?? MediaCollection::withTrashed()->where('uuid', sprintf('86000000-0000-4000-8000-%012d', $number))->first()
            ?? new MediaCollection;
        $collection->fill([
            'uuid' => $collectionUuid,
            'competition_id' => $event->id, 'name' => sprintf('%02d Routine video', $number), 'media_type' => 'video',
            'catalogue_mode' => 'hybrid', 'status' => 'published', 'visibility' => 'customer', 'storage_disk' => $disk,
            'storage_prefix' => $event->event_date->format('Y').'/'.$eventCode.'/', 'published_at' => now()->subDays(14),
            'metadata' => ['item_uuid' => $itemUuid, 'source' => 'competition_media', 'environment' => 'local'], 'deleted_at' => null,
        ])->save();
        $bytes = file_get_contents(database_path('seeders/assets/dancepro-demo-video.mp4'));
        Storage::disk($disk)->put($key, $bytes);
        $assetUuid = Uuid::uuid5($event->uuid, 'competition-media-video/'.$key)->toString();
        $asset = MediaAsset::withTrashed()->where('uuid', $assetUuid)->first()
            ?? MediaAsset::withTrashed()->where('uuid', sprintf('87000000-0000-4000-8000-%012d', $number))->first()
            ?? new MediaAsset;
        $asset->fill([
            'uuid' => $assetUuid, 'media_collection_id' => $collection->id, 'media_type' => 'video',
            'storage_disk' => $disk, 'storage_key' => $key,
            'original_filename' => basename($key), 'display_name' => sprintf('Routine %02d video', $number), 'status' => 'available',
            'is_visible' => true, 'sort_order' => 1, 'size_bytes' => strlen($bytes), 'duration_seconds' => 3,
            'mime_type' => 'video/mp4', 'extension' => 'mp4', 'verified_at' => now()->subDays(14),
            'metadata' => ['placeholder' => true, 'source' => 'competition_media', 'environment' => 'local'], 'deleted_at' => null,
        ])->save();

        return $collection;
    }

    /** @param list<MediaCollection> $videos */
    private function completedVideoBatch(SchedulingEvent $event, ProgrammeUploadPlan $plan, array $videos, User $actor): void
    {
        $role = CrewRole::where('code', 'competition-videographer')->firstOrFail();
        $batch = CompetitionVideoBatch::updateOrCreate(['uuid' => '89000000-0000-4000-8000-000000000001'], [
            'scheduling_event_id' => $event->id, 'programme_upload_plan_id' => $plan->id,
            'crew_role_id' => $role->id, 'created_by_user_id' => $actor->id,
            'status' => 'complete', 'completed_at' => now()->subDays(14),
        ]);
        foreach ($videos as $index => $collection) {
            $asset = $collection->assets()->firstOrFail();
            CompetitionVideoFile::updateOrCreate(
                ['uuid' => sprintf('8a000000-0000-4000-8000-%012d', $index + 1)],
                ['competition_video_batch_id' => $batch->id, 'scheduling_event_id' => $event->id,
                    'item_uuid' => $collection->metadata['item_uuid'], 'original_filename' => $asset->original_filename,
                    'sha256' => hash('sha256', $asset->original_filename.' demo video'),
                    'byte_count' => $asset->size_bytes, 'storage_disk' => $asset->storage_disk,
                    'storage_key' => $asset->storage_key, 'status' => 'verified',
                    'media_asset_id' => $asset->id, 'verified_at' => now()->subDays(14)],
            );
        }
    }

    private function orders(User $customer, SchedulingEvent $event, array $photos, array $videos): void
    {
        $orders = [
            ['88000000-0000-4000-8000-000000000001', now()->subDays(13), 4500, $photos[0], 'routine_photos'],
            ['88000000-0000-4000-8000-000000000002', now()->subDays(12), 1800, $videos[0], 'routine_video'],
            ['88000000-0000-4000-8000-000000000003', now()->subDays(5), 4500, $photos[3], 'routine_photos'],
        ];
        foreach ($orders as [$uuid, $paidAt, $amount, $collection, $product]) {
            $order = Order::withTrashed()->updateOrCreate(['uuid' => $uuid], [
                'user_id' => $customer->id, 'customer_email' => $customer->email, 'customer_name' => $customer->name,
                'status' => 'fulfilled', 'currency' => 'AUD', 'subtotal_amount' => $amount, 'total_amount' => $amount,
                'tax_amount' => (int) round($amount / 11), 'placed_at' => $paidAt, 'paid_at' => $paidAt,
                'fulfilled_at' => $paidAt->copy()->addDay(), 'metadata' => ['source' => 'customer_cart', 'kind' => 'competition',
                    'source_uuid' => $event->uuid, 'discount_amount' => 0, 'bulk_discount_amount' => 0,
                    'discount_code_amount' => 0, 'account_credit_amount' => 0, 'environment' => 'local'], 'deleted_at' => null,
            ]);
            $asset = $collection->assets()->orderBy('id')->first();
            OrderItem::updateOrCreate(['order_id' => $order->id, 'media_collection_id' => $collection->id], [
                'media_collection_id' => $collection->id, 'media_asset_id' => null, 'snapshot_storage_disk' => $asset?->storage_disk ?? 'local',
                'snapshot_filename' => $asset?->original_filename, 'snapshot_display_name' => $collection->name,
                'item_type' => $collection->media_type->value, 'quantity' => 1, 'unit_price_amount' => $amount,
                'total_price_amount' => $amount, 'tax_rate_basis_points' => 1000, 'tax_amount' => (int) round($amount / 11),
                'metadata' => ['product' => $product, 'item_uuid' => $collection->metadata['item_uuid'],
                    'asset_uuids' => $collection->assets()->pluck('uuid')->all(), 'environment' => 'local'],
            ]);
        }
    }

    private function image(string $label, string $hex): string
    {
        $image = imagecreatetruecolor(1200, 800);
        [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');
        imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, $blue));
        $white = imagecolorallocate($image, 255, 255, 255);
        $dark = imagecolorallocate($image, 15, 28, 40);
        imagefilledellipse($image, 600, 285, 150, 150, $white);
        imagefilledpolygon($image, [600, 350, 430, 650, 770, 650], 3, $dark);
        imagestring($image, 5, 35, 35, 'DANCEPRO DEMO MEDIA', $white);
        imagestring($image, 5, 35, 745, $label, $white);
        ob_start();
        imagejpeg($image, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
