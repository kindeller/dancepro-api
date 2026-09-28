<?php

namespace App\Features\Publicity\Services;

use App\Features\Media\Services\AdminTopPics;
use App\Features\Programmes\Services\ProgrammePhotoPreview;
use App\Features\Publicity\Models\PublicitySlide;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\Storage;

class SlideshowPlaylist
{
    public function __construct(private readonly AdminTopPics $pics, private readonly SlideshowManagement $management, private readonly ProgrammePhotoPreview $preview, private readonly SlideshowArtwork $artwork, private readonly CompetitionSlideTemplates $templates) {}

    public function manifest(SchedulingEvent $event, bool $adminPreview = false): array
    {
        $settings = $this->management->settings($event);
        $slides = [];
        $enabled = $event->event_type->value === 'competition' && ! $event->cancelled_at && ($settings->enabled || $adminPreview);
        $promotions = PublicitySlide::where('enabled', true)->forEvent($event)
            ->orderBy('sort_order')->orderBy('id')->get()->map(fn ($slide) => [
                'id' => 'slide-'.$slide->uuid, 'kind' => $slide->video_key ? 'video' : ($slide->image_key ? 'image' : 'text'), 'title' => $slide->title, 'body' => $slide->body,
                'version' => hash('sha256', json_encode([$slide->title, $slide->body, $slide->image_hash, $slide->video_hash, $slide->video_seconds, $slide->play_sound, $slide->duration_seconds])),
                'asset_version' => $slide->video_hash ?? $slide->image_hash,
                'scope' => $slide->scheduling_event_id === null ? 'standard' : 'event',
                'seconds' => $slide->video_seconds ?? $slide->duration_seconds,
                'play_sound' => $slide->play_sound, 'sha256' => $slide->video_hash, 'bytes' => $slide->video_bytes,
            ])->all();
        $standardSlides = array_values(array_filter($promotions, fn ($slide) => $slide['scope'] === 'standard'));
        if ($enabled) {
            $videos = array_values(array_filter($promotions, fn ($slide) => $slide['kind'] === 'video'));
            $promotions = array_values(array_filter($promotions, fn ($slide) => $slide['kind'] !== 'video'));
            $promotions = array_merge($promotions, $this->templates->promotions($event) ?: [$this->artwork->promotion($event)]);
            $photos = $this->pics->files()->where('galleries.scheduling_event_id', $event->id)->select('programme_photo_files.*')->orderBy('programme_photo_files.id')->get();
            $videosByPhoto = $this->videosByPhoto($videos, $photos->count(), $settings->promotion_every);
            $promotionIndex = 0;
            foreach ($photos as $index => $photo) {
                if ($index % $settings->promotion_every === 0 && $promotions) {
                    $slides[] = $promotions[$promotionIndex++ % count($promotions)];
                }
                $slides[] = ['id' => 'photo-'.$photo->uuid, 'kind' => 'image', 'title' => '', 'body' => null,
                    'version' => hash('sha256', 'unwatermarked-v1:'.$photo->sha256), 'seconds' => $settings->photo_seconds];
                foreach ($videosByPhoto[$index + 1] ?? [] as $video) {
                    $slides[] = $video;
                }
            }
            // Include every information/advertising slide, even when there are few or no photos.
            while ($promotionIndex < count($promotions)) {
                $slides[] = $promotions[$promotionIndex++];
            }
            if ($photos->isEmpty()) {
                $slides = array_merge($slides, $videos);
            }
        }
        $overlays = $this->artwork->overlays($event);
        $revision = hash('sha256', json_encode([$event->uuid, $event->name, $settings->enabled, $slides, $standardSlides, $overlays]));

        return ['image_variant' => 'unwatermarked-v1', 'event' => ['uuid' => $event->uuid, 'name' => $event->name], 'enabled' => $enabled,
            'overlays' => $overlays, 'revision' => $revision, 'poll_seconds' => 60, 'offline_until' => now()->addDay()->toIso8601String(), 'slides' => $slides, 'standard_slides' => $standardSlides];
    }

    /** Place videos at the middle of equally sized photo groups in the repeating loop. */
    private function videosByPhoto(array $videos, int $photoCount, int $promotionEvery): array
    {
        $positions = [];
        if ($photoCount === 0) {
            return $positions;
        }
        foreach ($videos as $index => $video) {
            $afterPhoto = min($photoCount, (int) floor(($index + 0.5) * $photoCount / count($videos)) + 1);
            // Leave a photo between a video and the next regular image/QR advert.
            if ($afterPhoto > 1 && $afterPhoto % $promotionEvery === 0) {
                $afterPhoto--;
            }
            $positions[$afterPhoto][] = $video;
        }

        return $positions;
    }

    public function video(SchedulingEvent $event, string $key): string
    {
        abort_unless(str_starts_with($key, 'slide-'), 404);
        $slide = PublicitySlide::where('uuid', substr($key, 6))->where('enabled', true)->forEvent($event)->firstOrFail();
        abort_unless($slide->video_key && Storage::disk('local')->exists($slide->video_key), 404);

        return $slide->video_key;
    }

    public function image(SchedulingEvent $event, string $key): string
    {
        if (str_starts_with($key, 'template-')) {
            return $this->templates->image($event, $key);
        }
        if ($key === 'auto-'.$event->uuid) {
            return $this->artwork->image($event);
        }
        if (str_starts_with($key, 'photo-')) {
            $file = $this->pics->files()->where('galleries.scheduling_event_id', $event->id)
                ->where('programme_photo_files.uuid', substr($key, 6))->select('programme_photo_files.*')->firstOrFail();

            return $this->preview->publicityKey($file);
        }
        abort_unless(str_starts_with($key, 'slide-'), 404);
        $slide = PublicitySlide::where('uuid', substr($key, 6))->where('enabled', true)
            ->forEvent($event)->firstOrFail();
        abort_unless($slide->image_key && Storage::disk('local')->exists($slide->image_key), 404);

        return $slide->image_key;
    }
}
