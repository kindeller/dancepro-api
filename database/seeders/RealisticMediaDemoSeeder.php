<?php

namespace Database\Seeders;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Concerts\Models\Concert;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\CompetitionOrderCatalogue;
use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Programmes\Actions\TidyDemoTopPics;
use App\Features\Programmes\Models\CompetitionGallerySetting;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class RealisticMediaDemoSeeder extends Seeder
{
    private string $structures;

    private string $photos;

    private array $sourcePhotos = [];

    private int $photoCursor = 0;

    public function run(): void
    {
        $this->structures = database_path('seeders/assets/realistic-media/structures');
        $this->photos = database_path('seeders/assets/realistic-media/photos');

        if (! app()->environment('local')) {
            throw new RuntimeException('RealisticMediaDemoSeeder may only run locally.');
        }
        if (! is_dir($this->structures) || ! is_dir($this->photos)) {
            $this->command?->warn('Realistic media assets are unavailable; skipping optional local gallery import.');

            return;
        }

        $this->sourcePhotos = collect(glob($this->photos.'/*.{JPG,jpg,JPEG,jpeg}', GLOB_BRACE))
            ->sortBy(fn (string $path) => filesize($path))->take(80)->values()->all();
        if ($this->sourcePhotos === []) {
            throw new RuntimeException('No source JPEG photos were found.');
        }

        $actor = User::where('email', 'staff@dancepro.test')->firstOrFail();
        $customer = User::where('email', 'customer-review-5c70c3@example.test')->first()
            ?? User::where('email', 'orders.customer@dancepro.test')->firstOrFail();

        $this->competition('PDC826', 'Perth Dance Challenge - REALISTIC DEMO', 'PDC',
            'logos/competition-contacts/e8319b64-bc6b-4d0d-8958-0717fdc31193/logo.jpg', today()->subDays(34), $actor, $customer);
        $this->competition('EDC926', 'Express Dance Challenge - REALISTIC DEMO', 'EDC',
            'logos/competition-contacts/826c429d-fe6a-47e2-85bb-ffb8687c8218/logo.jpg', today()->subDays(21), $actor, $customer);
        $this->concert($actor, $customer);
        app(TidyDemoTopPics::class)->execute();

        $this->command?->info('Realistic PDC826, EDC926 and concert galleries populated with copied source photos.');
        $this->command?->line('Competition gallery login codes: PDC-REVIEW and EDC-REVIEW');
    }

    private function competition(string $folder, string $name, string $code, string $logo, $date, User $actor, User $customer): void
    {
        $contactUuid = Uuid::uuid5(Uuid::NAMESPACE_URL, 'dancepro-demo/contact/'.$folder)->toString();
        $contact = CompetitionContact::withTrashed()->updateOrCreate(['code' => $code], [
            'uuid' => $contactUuid,
            'name' => $name, 'code' => $code, 'organiser_name' => 'Demo Organiser',
            'organiser_email' => strtolower($code).'@example.test', 'organiser_phone' => '0400 000 000',
            'logo_path' => $logo, 'is_active' => true, 'notes' => 'Local realistic media review data.', 'deleted_at' => null,
        ]);
        $eventUuid = Uuid::uuid5(Uuid::NAMESPACE_URL, 'dancepro-demo/event/'.$folder)->toString();
        $event = SchedulingEvent::updateOrCreate(['uuid' => $eventUuid], [
            'competition_contact_id' => $contact->id, 'name' => $name, 'event_type' => 'competition',
            'event_date' => $date->toDateString(), 'organiser_name' => 'Demo Organiser',
            'organiser_email' => strtolower($code).'@example.test', 'organiser_phone' => '0400 000 000',
            'availability_status' => 'closed', 'roster_status' => 'published', 'roster_published_at' => now()->subMonth(),
            'admin_notes' => 'Realistic local gallery populated from supplied folder structure.',
        ]);

        $root = $this->structures.'/'.$folder;
        $dayCount = $folder === 'PDC826' ? 3 : 1;
        $sectionPaths = collect(glob($root.'/*', GLOB_ONLYDIR))->sort(SORT_NATURAL)->filter(function (string $path) {
            return count(glob($path.'/*', GLOB_ONLYDIR)) >= 3;
        })->take($dayCount * 3)->values();
        $days = collect(range(0, $dayCount - 1))->map(function (int $offset) use ($eventUuid, $date): array {
            $dayNumber = str_pad((string) ($offset + 1), 2, '0', STR_PAD_LEFT);
            $dayDate = $date->copy()->addDays($offset);

            return ['uuid' => Uuid::uuid5($eventUuid, 'day/'.$dayNumber)->toString(), 'kind' => 'day',
                'number' => $dayNumber, 'date' => $dayDate->toDateString(), 'title' => $dayNumber.' '.$dayDate->format('l')];
        });
        $nodes = $days->all();
        $items = [];
        foreach ($sectionPaths as $sectionIndex => $sectionPath) {
            [$sectionNumber, $sectionTitle] = $this->folderParts(basename($sectionPath));
            $sectionUuid = Uuid::uuid5($eventUuid, 'section/'.basename($sectionPath))->toString();
            $dayIndex = min(intdiv($sectionIndex, 3), $days->count() - 1);
            $nodes[] = ['uuid' => $sectionUuid, 'kind' => 'section', 'number' => $sectionNumber, 'title' => $sectionTitle,
                'parent_uuid' => $days[$dayIndex]['uuid']];
            foreach (collect(glob($sectionPath.'/*', GLOB_ONLYDIR))->sort(SORT_NATURAL)->take(4) as $itemPath) {
                [$number, $title] = $this->folderParts(basename($itemPath));
                $itemUuid = Uuid::uuid5($sectionUuid, basename($itemPath))->toString();
                $nodes[] = ['uuid' => $itemUuid, 'kind' => 'item', 'number' => $number, 'title' => $title,
                    'parent_uuid' => $sectionUuid, 'item_kind' => 'dance'];
                $items[] = ['uuid' => $itemUuid, 'name' => $title];
            }
        }

        $programme = Programme::updateOrCreate(['scheduling_event_id' => $event->id], ['draft_version' => 1, 'updated_by_user_id' => $actor->id]);
        $snapshot = ['event_type' => 'competition', 'event_code' => $folder, 'nodes' => $nodes];
        $revision = $programme->revisions()->updateOrCreate(['version' => 1], [
            'snapshot' => $snapshot, 'confirmed_by_user_id' => $actor->id, 'confirmed_at' => now()->subDays(18),
        ]);
        $plan = ProgrammeUploadPlan::updateOrCreate(['programme_id' => $programme->id, 'digest' => hash('sha256', json_encode($snapshot))], [
            'programme_revision_id' => $revision->id, 'created_by_user_id' => $actor->id, 'snapshot' => $snapshot,
        ]);
        $gallery = ProgrammePhotoGallery::updateOrCreate(['scheduling_event_id' => $event->id], [
            'programme_upload_plan_id' => $plan->id, 'visibility' => 'private',
        ]);
        foreach ($days as $day) {
            $shiftDate = $event->event_date->copy()->addDays(((int) $day['number']) - 1);
            $event->shifts()->updateOrCreate(['period' => 'morning', 'shift_date' => $day['date']], [
                'starts_at' => $shiftDate->copy()->setTime(8, 0),
                'estimated_finish_at' => $shiftDate->copy()->setTime(17, 0),
            ]);
        }
        $role = CrewRole::where('code', 'competition-photographer-p1')->firstOrFail();
        $profile = CrewProfile::whereHas('roles', fn ($query) => $query->whereKey($role->id))->firstOrFail();
        $event->roleRequirements()->updateOrCreate(['crew_role_id' => $role->id], ['quantity' => 1]);
        $secondRole = CrewRole::where('code', 'competition-photographer-p2')->firstOrFail();
        $secondProfile = CrewProfile::whereHas('roles', fn ($query) => $query->whereKey($secondRole->id))->firstOrFail();
        $firstPhoto = null;
        foreach ($items as $itemIndex => $item) {
            ProgrammePhotoContribution::updateOrCreate([
                'programme_upload_plan_id' => $plan->id, 'item_uuid' => $item['uuid'],
                'crew_profile_id' => $secondProfile->id, 'crew_role_id' => $secondRole->id,
            ], ['registered_by_user_id' => $actor->id, 'assignment_uuids' => [], 'status' => 'waived',
                'waived_at' => now()->subDays(17), 'waived_by_user_id' => $actor->id,
                'waiver_reason' => 'Single-photographer realistic demo gallery.']);
            $contribution = ProgrammePhotoContribution::updateOrCreate([
                'programme_upload_plan_id' => $plan->id, 'item_uuid' => $item['uuid'], 'crew_profile_id' => $profile->id, 'crew_role_id' => $role->id,
            ], ['registered_by_user_id' => $actor->id, 'assignment_uuids' => [], 'status' => 'complete',
                'completed_at' => now()->subDays(17), 'completed_by_user_id' => $actor->id]);
            foreach (range(1, 4) as $number) {
                $source = $this->nextPhoto();
                $uuid = Uuid::uuid5($contribution->uuid, 'real-photo/'.$number)->toString();
                $key = 'local-development/realistic-media/'.$folder.'/'.$item['uuid'].'/'.basename($source);
                $this->copyPhoto($source, $key);
                $file = ProgrammePhotoFile::orderBy('id')->updateOrCreate(['storage_disk' => 'local', 'storage_key' => $key], [
                    'programme_photo_contribution_id' => $contribution->id, 'programme_photo_gallery_id' => $gallery->id,
                    'client_photo_uuid' => $uuid, 'file_name' => basename($source), 'byte_count' => filesize($source),
                    'sha256' => hash_file('sha256', $source), 'is_top_pic' => $number === 1, 'status' => 'verified',
                    'verified_at' => now()->subDays(17),
                ]);
                $firstPhoto ??= $file;
            }
        }
        foreach ($items as $item) {
            app(CompetitionOrderCatalogue::class)->readyRoutine($event, $item['uuid']);
        }
        CompetitionGallerySetting::updateOrCreate(['scheduling_event_id' => $event->id], [
            'display_title' => $name, 'cover_photo_uuid' => $firstPhoto?->uuid,
            'login_code_hash' => Hash::make($code.'-REVIEW'), 'version' => 1,
        ]);
        CustomerCompetitionRegistration::updateOrCreate(['user_id' => $customer->id, 'scheduling_event_id' => $event->id], ['access_verified_at' => now()->subDays(15)]);
        if ($folder === 'EDC926') {
            foreach (['Ella-Rose Briggs', 'Sophie Trewin'] as $dancerName) {
                CustomerDancer::updateOrCreate(['user_id' => $customer->id, 'name' => $dancerName], [
                    'relationship' => 'child', 'programme_names' => [],
                ]);
            }
        }
    }

    private function concert(User $actor, User $customer): void
    {
        $concert = Concert::query()->where('uuid', '75000000-0000-4000-8000-000000000001')->firstOrFail();
        $studio = $concert->studio;
        CustomerStudio::updateOrCreate(['user_id' => $customer->id, 'studio_id' => $studio->id], ['access_verified_at' => now()->subMonth()]);
        $folders = collect(glob($this->structures.'/TDC2026/2026 Teen Company/*', GLOB_ONLYDIR))->sort(SORT_NATURAL)->take(8);
        $collectionIds = [];
        $cover = null;
        foreach ($folders as $index => $folder) {
            [, $title] = $this->folderParts(basename($folder));
            $uuid = Uuid::uuid5($concert->uuid, 'realistic-album/'.basename($folder))->toString();
            $collection = MediaCollection::withTrashed()->updateOrCreate(['uuid' => $uuid], [
                'concert_id' => $concert->id, 'name' => basename($folder), 'media_type' => 'photo', 'catalogue_mode' => 'managed',
                'status' => 'published', 'visibility' => 'customer', 'storage_disk' => 'local',
                'storage_prefix' => 'local-development/realistic-media/TDC2026/', 'sort_order' => $index + 1,
                'published_at' => now()->subDays(3), 'metadata' => ['environment' => 'local', 'realistic_demo' => true, 'class_name' => 'Teen Company'],
                'deleted_at' => null,
            ]);
            $albumCover = null;
            foreach (range(1, 5) as $number) {
                $source = $this->nextPhoto();
                $key = 'local-development/realistic-media/TDC2026/'.$collection->uuid.'/'.basename($source);
                $this->copyPhoto($source, $key);
                $asset = MediaAsset::withTrashed()->updateOrCreate([
                    'uuid' => Uuid::uuid5($collection->uuid, 'photo/'.$number)->toString(),
                ], ['media_collection_id' => $collection->id, 'media_type' => 'photo', 'storage_disk' => 'local', 'storage_key' => $key,
                    'original_filename' => basename($source), 'display_name' => $title.' photo '.$number, 'status' => 'available',
                    'is_visible' => true, 'sort_order' => $number, 'size_bytes' => filesize($source), 'mime_type' => 'image/jpeg',
                    'extension' => 'jpg', 'thumbnail_storage_disk' => 'local', 'thumbnail_storage_key' => $key,
                    'verified_at' => now()->subDays(4), 'metadata' => ['is_top_pic' => $number === 1, 'realistic_demo' => true], 'deleted_at' => null]);
                $albumCover ??= $asset;
                $cover ??= $asset;
            }
            $collection->update(['cover_media_asset_id' => $albumCover->id]);
            $collectionIds[] = $collection->id;
        }
        $group = ConcertPhotoReleaseGroup::updateOrCreate([
            'uuid' => Uuid::uuid5($concert->uuid, 'realistic-release')->toString(),
        ], ['studio_id' => $studio->id, 'name' => 'Aurora Teen Company realistic gallery', 'cover_media_asset_id' => $cover?->id,
            'created_by_user_id' => $actor->id, 'released_by_user_id' => $actor->id, 'released_at' => now()->subDays(3)]);
        $group->collections()->syncWithoutDetaching($collectionIds);
    }

    private function folderParts(string $folder): array
    {
        return preg_match('/^(\d+)[. ]+(.+)$/u', $folder, $matches)
            ? [str_pad($matches[1], 2, '0', STR_PAD_LEFT), trim($matches[2])]
            : ['01', $folder];
    }

    private function nextPhoto(): string
    {
        return $this->sourcePhotos[$this->photoCursor++ % count($this->sourcePhotos)];
    }

    private function copyPhoto(string $source, string $key): void
    {
        $stream = fopen($source, 'rb');
        Storage::disk('local')->put($key, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }
}
