<?php

namespace Database\Seeders;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Programmes\Models\Programme;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ConcertTrackerDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('ConcertTrackerDemoSeeder may only run locally.');
        }
        $actor = User::query()->where('is_admin', true)->first();
        if (! $actor) {
            throw new RuntimeException('Seed a local administrator before loading Tracker review data.');
        }

        $studio = Studio::withTrashed()->updateOrCreate(['uuid' => '71000000-0000-4000-8000-000000000001'], [
            'name' => 'Aurora Dance Academy - TRACKER DEMO', 'slug' => 'aurora-tracker-demo', 'status' => 'active',
            'code' => 'AUR', 'contact_name' => 'Alex Director', 'contact_email' => 'aurora-director@example.test',
            'description' => 'Fictional local review data for the Concert Tracker.', 'deleted_at' => null,
        ]);
        $booking = ConcertBooking::updateOrCreate(['uuid' => '72000000-0000-4000-8000-000000000001'], [
            'status' => 'approved', 'studio_name' => $studio->name, 'contact_name' => 'Alex Director',
            'contact_email' => 'aurora-director@example.test', 'contact_phone' => '0400 000 777',
            'wants_concert_photography' => true, 'wants_concert_videography' => true,
            'approximate_family_count' => 86, 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now()->subMonths(2),
        ]);

        $past = $this->event($studio, '73000000-0000-4000-8000-000000000001', 'Aurora Junior Showcase - TRACKER DEMO', now()->subDays(10)->toDateString());
        $future = $this->event($studio, '73000000-0000-4000-8000-000000000002', 'Aurora Senior Showcase - TRACKER DEMO', now()->addDays(24)->toDateString());
        $recent = $this->event($studio, '73000000-0000-4000-8000-000000000003', 'Aurora Matinee - TRACKER DEMO', now()->subDays(3)->toDateString());
        $near = $this->event($studio, '73000000-0000-4000-8000-000000000004', 'Aurora Spring Gala - TRACKER DEMO', now()->addDays(4)->toDateString());
        $pastItem = $this->item($booking, $past, '74000000-0000-4000-8000-000000000001', 'Aurora Junior Showcase');
        $futureItem = $this->item($booking, $future, '74000000-0000-4000-8000-000000000002', 'Aurora Senior Showcase');
        $recentItem = $this->item($booking, $recent, '74000000-0000-4000-8000-000000000003', 'Aurora Matinee');
        $nearItem = $this->item($booking, $near, '74000000-0000-4000-8000-000000000004', 'Aurora Spring Gala');
        $pastConcert = $this->concert($studio, $pastItem, $actor, '75000000-0000-4000-8000-000000000001', 'Aurora Junior Showcase', true);
        $this->concert($studio, $futureItem, $actor, '75000000-0000-4000-8000-000000000002', 'Aurora Senior Showcase', false);
        $this->concert($studio, $recentItem, $actor, '75000000-0000-4000-8000-000000000003', 'Aurora Matinee', false);
        $this->concert($studio, $nearItem, $actor, '75000000-0000-4000-8000-000000000004', 'Aurora Spring Gala', false);
        $this->confirmedProgramme($past);
        $this->confirmedProgramme($recent);
        $this->confirmedProgramme($near);
        $future->update(['programme_path' => 'tracker-demo/aurora-senior-programme.pdf']);
        $this->releasedMedia($studio, $pastConcert, $actor);

        $this->manualTasks($booking, $past, $recent, $near, $future, $actor);
        $this->command?->info('Concert Tracker review data loaded for '.now()->year.'.');
    }

    private function event(Studio $studio, string $uuid, string $name, string $date): SchedulingEvent
    {
        return SchedulingEvent::updateOrCreate(['uuid' => $uuid], [
            'studio_id' => $studio->id, 'name' => $name, 'event_type' => 'concert',
            'event_date' => $date, 'roster_status' => 'draft', 'programme_path' => null,
        ]);
    }

    private function item(ConcertBooking $booking, SchedulingEvent $event, string $uuid, string $title): ConcertBookingItem
    {
        return ConcertBookingItem::updateOrCreate(['uuid' => $uuid], [
            'concert_booking_id' => $booking->id, 'item_type' => 'concert', 'title' => $title,
            'venue_name' => 'Demo Arts Theatre', 'venue_address' => '1 Example Street',
            'event_date' => $event->event_date, 'starts_at' => '18:00', 'finishes_at' => '21:00',
            'approval_status' => 'approved', 'scheduling_event_id' => $event->id,
        ]);
    }

    private function concert(Studio $studio, ConcertBookingItem $item, User $actor, string $uuid, string $name, bool $released): Concert
    {
        return Concert::withTrashed()->updateOrCreate(['uuid' => $uuid], [
            'studio_id' => $studio->id, 'concert_booking_item_id' => $item->id, 'name' => $name,
            'slug' => str($name)->slug().'-tracker-demo', 'status' => $released ? 'published' : 'draft',
            'event_date' => $item->event_date, 'venue_name' => $item->venue_name,
            'is_enabled' => true, 'requires_approval' => true,
            'approved_at' => $released ? now()->subDays(2) : null,
            'approved_by_user_id' => $released ? $actor->id : null,
            'published_at' => $released ? now()->subDay() : null,
            'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id,
            'storage_disk' => 'local', 'storage_prefix' => 'tracker-demo/'.$uuid.'/', 'deleted_at' => null,
        ]);
    }

    private function confirmedProgramme(SchedulingEvent $event): void
    {
        $programme = Programme::updateOrCreate(['scheduling_event_id' => $event->id], ['draft_version' => 1]);
        $programme->revisions()->updateOrCreate(['version' => 1], [
            'confirmed_at' => now()->subDays(30),
            'snapshot' => ['event_type' => 'concert', 'nodes' => [
                ['uuid' => $event->uuid.'-title-card-01', 'kind' => 'item', 'folder_number' => '01',
                    'title' => 'Waiting for Tonight', 'class_name' => '12 & Under Jazz', 'is_active' => true],
                ['uuid' => $event->uuid.'-title-card-02', 'kind' => 'item', 'folder_number' => '02',
                    'title' => 'A Million Dreams', 'class_name' => 'Senior Musical Theatre', 'is_active' => true],
                ['uuid' => $event->uuid.'-title-card-03', 'kind' => 'item', 'folder_number' => '03',
                    'title' => 'Finale', 'class_name' => null, 'is_active' => true],
            ]],
        ]);
        $event->update(['programme_path' => 'tracker-demo/'.$event->uuid.'/programme.pdf']);
    }

    private function releasedMedia(Studio $studio, Concert $concert, User $actor): void
    {
        $photoAlbum = MediaCollection::withTrashed()->updateOrCreate(['uuid' => '76000000-0000-4000-8000-000000000001'], [
            'concert_id' => $concert->id, 'name' => '01 Opening Routine', 'media_type' => 'photo',
            'catalogue_mode' => 'managed', 'status' => 'published', 'visibility' => 'customer',
            'storage_disk' => 'local', 'storage_prefix' => 'tracker-demo/photos/', 'sort_order' => 1,
            'published_at' => now()->subDays(3), 'metadata' => ['environment' => 'local', 'tracker_demo' => true],
            'deleted_at' => null,
        ]);
        $photo = null;
        foreach (range(1, 6) as $number) {
            $key = 'tracker-demo/photos/routine-'.$number.'.jpg';
            $bytes = $this->image('Aurora routine photo '.$number, ['#126b8f', '#7a4f9d', '#c05e75'][$number % 3]);
            Storage::disk('local')->put($key, $bytes);
            $asset = MediaAsset::withTrashed()->updateOrCreate(
                ['uuid' => sprintf('77000000-0000-4000-8000-%012d', $number)],
                ['media_collection_id' => $photoAlbum->id, 'media_type' => 'photo', 'storage_disk' => 'local',
                    'storage_key' => $key, 'original_filename' => basename($key),
                    'display_name' => 'Aurora routine photo '.$number, 'status' => 'available', 'is_visible' => true,
                    'sort_order' => $number, 'size_bytes' => strlen($bytes), 'mime_type' => 'image/jpeg', 'extension' => 'jpg',
                    'verified_at' => now()->subDays(5), 'metadata' => ['is_top_pic' => $number === 1, 'tracker_demo' => true],
                    'deleted_at' => null],
            );
            $photo ??= $asset;
        }
        $photoAlbum->update(['cover_media_asset_id' => $photo->id]);
        $videoAlbum = MediaCollection::withTrashed()->updateOrCreate(['uuid' => '76000000-0000-4000-8000-000000000002'], [
            'concert_id' => $concert->id, 'name' => 'Concert videos', 'media_type' => 'video',
            'catalogue_mode' => 'managed', 'status' => 'published', 'visibility' => 'customer',
            'storage_disk' => 'local', 'storage_prefix' => 'tracker-demo/videos/', 'sort_order' => 2,
            'published_at' => now()->subDay(), 'metadata' => ['environment' => 'local', 'tracker_demo' => true],
            'deleted_at' => null,
        ]);
        $videoBytes = file_get_contents(database_path('seeders/assets/dancepro-demo-video.mp4'));
        Storage::disk('local')->put('tracker-demo/videos/concert.mp4', $videoBytes);
        MediaAsset::withTrashed()->updateOrCreate(['uuid' => '77000000-0000-4000-8000-000000000102'], [
            'media_collection_id' => $videoAlbum->id, 'media_type' => 'video', 'storage_disk' => 'local',
            'storage_key' => 'tracker-demo/videos/concert.mp4', 'original_filename' => 'concert.mp4',
            'display_name' => 'Tracker demo concert video', 'status' => 'available', 'is_visible' => true,
            'sort_order' => 1, 'size_bytes' => strlen($videoBytes), 'duration_seconds' => 3,
            'mime_type' => 'video/mp4', 'extension' => 'mp4',
            'verified_at' => now()->subDays(2), 'metadata' => ['tracker_demo' => true], 'deleted_at' => null,
        ]);
        $group = ConcertPhotoReleaseGroup::updateOrCreate(['uuid' => '78000000-0000-4000-8000-000000000001'], [
            'studio_id' => $studio->id, 'name' => 'Aurora 2026 photos - TRACKER DEMO',
            'cover_media_asset_id' => $photo->id, 'created_by_user_id' => $actor->id,
            'released_by_user_id' => $actor->id, 'released_at' => now()->subDays(3),
        ]);
        $group->collections()->syncWithoutDetaching([$photoAlbum->id]);
    }

    private function manualTasks(ConcertBooking $booking, SchedulingEvent $past, SchedulingEvent $recent, SchedulingEvent $near, SchedulingEvent $future, User $actor): void
    {
        foreach ([
            ['booking', $booking->id, 'confirmation_information_email', 'complete'],
            ['booking', $booking->id, 'final_numbers_email', 'complete'],
            ['booking', $booking->id, 'final_numbers_received', 'in_progress'],
            ['booking', $booking->id, 'invoice_sent', 'not_started'],
            ['booking', $booking->id, 'payment_received', 'waiting'],
            ['event', $past->id, 'concert_week_email', 'complete'],
            ['event', $past->id, 'post_concert_email', 'complete'],
            ['event', $past->id, 'slideshow_received', 'waiting'],
            ['event', $past->id, 'concert_edited', 'complete'],
            ['event', $past->id, 'quality_check', 'complete'],
            ['event', $past->id, 'video_holds', 'not_required'],
            ['event', $past->id, 'approval_link_email', 'complete'],
            ['event', $past->id, 'director_corrections', 'waiting'],
            ['event', $future->id, 'concert_week_email', 'not_started'],
            ['event', $future->id, 'concert_edited', 'not_started'],
            ['event', $near->id, 'concert_week_email', 'complete'],
            ['event', $near->id, 'final_numbers_received', 'complete'],
            ['event', $near->id, 'concert_edited', 'not_started'],
            ['event', $recent->id, 'concert_week_email', 'complete'],
            ['event', $recent->id, 'post_concert_email', 'complete'],
            ['event', $recent->id, 'slideshow_received', 'in_progress'],
            ['event', $recent->id, 'concert_edited', 'in_progress'],
            ['event', $recent->id, 'quality_check', 'not_started'],
            ['event', $recent->id, 'approval_link_email', 'not_started'],
        ] as [$scopeType, $scopeId, $taskKey, $status]) {
            $task = ConcertTrackerTask::updateOrCreate(
                ['scope_type' => $scopeType, 'scope_id' => $scopeId, 'task_key' => $taskKey],
                ['status' => $status, 'assignee_user_id' => $actor->id,
                    'completed_by_user_id' => $status === 'complete' ? $actor->id : null,
                    'completed_at' => $status === 'complete' ? now()->subDays(12) : null],
            );
            $template = ConcertTrackerEmailTemplate::where('task_key', $taskKey)->first();
            if ($status === 'complete' && $template) {
                ConcertTrackerEmailDelivery::updateOrCreate(
                    ['uuid' => '79000000-0000-4000-8000-'.str_pad((string) $template->id, 12, '0', STR_PAD_LEFT)],
                    ['concert_tracker_task_id' => $task->id, 'concert_tracker_email_template_id' => $template->id,
                        'created_by_user_id' => $actor->id, 'recipient_name' => 'Alex Director',
                        'recipient_email' => 'aurora-director@example.test', 'subject' => $template->name.' - tracker demo',
                        'body' => 'Fictional local delivery history for tracker review.', 'status' => 'sent',
                        'queued_at' => now()->subDays(12), 'sent_at' => now()->subDays(12)],
                );
            }
        }
        $followUp = ConcertTrackerTask::where('scope_type', 'event')->where('scope_id', $past->id)->where('task_key', 'director_corrections')->firstOrFail();
        $followUp->notes()->updateOrCreate(['body' => 'Director requested a spelling correction on the finale title card.'], [
            'user_id' => $actor->id, 'follow_up_date' => today()->addDays(2),
        ]);
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
        imagestring($image, 5, 35, 35, 'DANCEPRO CONCERT DEMO', $white);
        imagestring($image, 5, 35, 745, $label, $white);
        ob_start();
        imagejpeg($image, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
