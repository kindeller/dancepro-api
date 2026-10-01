<?php

namespace App\Features\Media\Services;

use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaWatermark;
use App\Features\Programmes\Models\CompetitionGallerySetting;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use GdImage;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaWatermarks
{
    private const MAX_ARTWORK_EDGE = 2000;

    public function create(User $actor, array $input, ?UploadedFile $artwork): MediaWatermark
    {
        return DB::transaction(function () use ($actor, $input, $artwork): MediaWatermark {
            if ($input['is_default']) {
                MediaWatermark::where('gallery_type', $input['gallery_type'])->update(['is_default' => false]);
            }
            $key = $artwork ? $this->storeArtwork($artwork) : null;

            return MediaWatermark::create([
                'name' => $input['name'], 'gallery_type' => $input['gallery_type'], 'mode' => $input['mode'],
                'text' => $input['mode'] === 'text' ? $input['text'] : null,
                'storage_disk' => $key ? 'local' : null, 'storage_key' => $key,
                'is_default' => $input['is_default'], 'is_active' => true, 'created_by_user_id' => $actor->id,
            ]);
        });
    }

    public function storeArtwork(UploadedFile $artwork): string
    {
        $source = @imagecreatefrompng($artwork->getRealPath());
        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['artwork' => 'The watermark PNG could not be processed.']);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, self::MAX_ARTWORK_EDGE / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $normalised = imagecreatetruecolor($width, $height);
        imagealphablending($normalised, false);
        imagesavealpha($normalised, true);
        $transparent = imagecolorallocatealpha($normalised, 0, 0, 0, 127);
        imagefilledrectangle($normalised, 0, 0, $width, $height, $transparent);
        imagecopyresampled($normalised, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        $written = imagepng($normalised, null, 8);
        $bytes = ob_get_clean();
        unset($normalised, $source);
        if (! $written || ! is_string($bytes)) {
            throw ValidationException::withMessages(['artwork' => 'The watermark PNG could not be optimised.']);
        }

        $key = 'media-watermarks/library/'.Str::uuid().'.png';
        if (! Storage::disk('local')->put($key, $bytes)) {
            throw ValidationException::withMessages(['artwork' => 'The watermark PNG could not be stored.']);
        }

        return $key;
    }

    public function update(MediaWatermark $watermark, string $action): void
    {
        DB::transaction(function () use ($watermark, $action): void {
            $watermark = MediaWatermark::whereKey($watermark->id)->lockForUpdate()->firstOrFail();
            if ($action === 'default') {
                abort_unless($watermark->is_active, 409, 'Archived watermarks cannot be defaults.');
                MediaWatermark::where('gallery_type', $watermark->gallery_type)->whereKeyNot($watermark->id)
                    ->update(['is_default' => false]);
                $watermark->update(['is_default' => true]);
            } else {
                if ($watermark->is_default) {
                    throw ValidationException::withMessages([
                        'watermark' => 'Choose another default before archiving this watermark.',
                    ]);
                }
                if ($watermark->assignmentCount() > 0) {
                    throw ValidationException::withMessages([
                        'watermark' => 'Remove this watermark from its assigned galleries before archiving it.',
                    ]);
                }
                $watermark->update(['is_active' => false, 'is_default' => false]);
            }
        });
    }

    public function galleryCover(): array
    {
        $path = resource_path('images/watermarks/gallery-cover.png');
        abort_unless(is_file($path), 503, 'Gallery cover artwork is unavailable.');

        return ['mode' => 'image', 'path' => $path, 'text' => null, 'width_ratio' => 0.82,
            'fingerprint' => 'gallery-cover-v1:'.hash_file('sha256', $path)];
    }

    public function competition(?SchedulingEvent $event): array
    {
        $watermark = $event ? CompetitionGallerySetting::where('scheduling_event_id', $event->id)
            ->with('watermark')->first()?->watermark : null;

        return $this->descriptor($watermark ?: $this->default('competition'), 'competition');
    }

    public function concert(MediaCollection $collection): array
    {
        $collection->loadMissing('watermark');

        return $this->descriptor($collection->watermark ?: $this->default('concert'), 'concert');
    }

    private function default(string $type): ?MediaWatermark
    {
        try {
            if (! Schema::hasTable('media_watermarks')) {
                return null;
            }

            return MediaWatermark::where('gallery_type', $type)->where('is_default', true)->where('is_active', true)->first();
        } catch (QueryException) {
            return null;
        }
    }

    private function descriptor(?MediaWatermark $watermark, string $fallback): array
    {
        if ($watermark?->mode === 'image' && $watermark->storage_disk && $watermark->storage_key
            && Storage::disk($watermark->storage_disk)->exists($watermark->storage_key)) {
            $path = Storage::disk($watermark->storage_disk)->path($watermark->storage_key);

            return ['mode' => 'image', 'path' => $path, 'text' => null,
                'fingerprint' => $watermark->uuid.':'.hash_file('sha256', $path)];
        }
        if ($watermark?->mode === 'text' && $watermark->text) {
            return ['mode' => 'text', 'path' => null, 'text' => $watermark->text,
                'fingerprint' => $watermark->uuid.':'.hash('sha256', $watermark->text)];
        }
        if ($fallback === 'competition') {
            $configured = app(MediaCommerceSettings::class)->get('competition_watermark_path');
            $path = $configured && Storage::disk('local')->exists($configured)
                ? Storage::disk('local')->path($configured) : resource_path('images/watermarks/competition-2026.png');
            abort_unless(is_file($path), 503, 'The competition watermark is unavailable.');

            return ['mode' => 'image', 'path' => $path, 'text' => null,
                'fingerprint' => 'legacy:'.hash_file('sha256', $path)];
        }
        $text = (string) app(MediaCommerceSettings::class)->get('watermark_text');

        return ['mode' => 'text', 'path' => null, 'text' => $text,
            'fingerprint' => 'legacy:'.hash('sha256', $text)];
    }
}
