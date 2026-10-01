<?php

namespace App\Features\Media\Services;

use App\Features\Customers\Services\ConcertPhotoPreview;
use App\Features\Media\Models\MediaAsset;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminConcertPhotoPreview
{
    public function response(SchedulingEvent $event, MediaAsset $asset, string $size): StreamedResponse
    {
        $event->loadMissing('concertBookingItem.concert');
        $asset->loadMissing('collection');

        abort_unless(
            $event->event_type->value === 'concert'
            && $event->concertBookingItem?->concert?->id === $asset->collection?->concert_id
            && $asset->media_type->value === 'photo'
            && $asset->is_visible
            && $asset->status->value === 'available',
            404,
        );

        if ($size === 'cover') {
            $key = app(ConcertPhotoPreview::class)->key($asset, 'cover');

            return Storage::disk('local')->response($key, $asset->uuid.'-cover.jpg',
                ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store'], 'inline');
        }

        $useThumbnail = $size === 'thumbnail'
            && $asset->thumbnail_storage_disk
            && $asset->thumbnail_storage_key
            && Storage::disk($asset->thumbnail_storage_disk)->exists($asset->thumbnail_storage_key);
        $disk = $useThumbnail ? $asset->thumbnail_storage_disk : $asset->storage_disk;
        $key = $useThumbnail ? $asset->thumbnail_storage_key : $asset->storage_key;

        abort_unless($disk && $key && Storage::disk($disk)->exists($key), 404);

        return Storage::disk($disk)->response(
            $key,
            $asset->display_name ?: $asset->original_filename,
            ['Content-Type' => $useThumbnail ? 'image/jpeg' : ($asset->mime_type ?: 'image/jpeg'), 'Cache-Control' => 'private, no-store'],
            'inline',
        );
    }
}
