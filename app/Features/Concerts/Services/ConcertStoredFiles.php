<?php

namespace App\Features\Concerts\Services;

use App\Features\Media\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ConcertStoredFiles
{
    public function playback(MediaAsset $asset, string $localUrl): array
    {
        $this->ensureAllowed($asset->storage_disk);
        abort_unless(Storage::disk($asset->storage_disk)->exists($asset->storage_key), 404);

        return ['format' => 'progressive', 'url' => $this->isLocal($asset->storage_disk) ? $localUrl
            : $this->temporaryUrl($asset->storage_disk, $asset->storage_key), 'fallback_url' => null];
    }

    public function response(MediaAsset $asset, ?string $filename = null, ?\DateTimeInterface $expiresAt = null): RedirectResponse|BinaryFileResponse
    {
        $this->ensureAllowed($asset->storage_disk);
        abort_unless(Storage::disk($asset->storage_disk)->exists($asset->storage_key), 404);
        if ($this->isLocal($asset->storage_disk)) {
            $path = Storage::disk($asset->storage_disk)->path($asset->storage_key);

            return $filename === null ? response()->file($path, ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store'])
                : response()->download($path, $filename, ['Cache-Control' => 'private, no-store']);
        }

        return redirect()->away($this->temporaryUrl($asset->storage_disk, $asset->storage_key, $filename, $expiresAt))->header('Cache-Control', 'private, no-store');
    }

    public function ensureAllowed(string $disk): void
    {
        abort_unless(in_array($disk, ['s3_concerts', 's3_concerts_legacy', 'local'], true), 503, 'Concert storage is not configured for delivery.');
        abort_if($disk === 'local' && ! app()->environment(['local', 'testing']), 503);
    }

    public function isLocal(string $disk): bool
    {
        $this->ensureAllowed($disk);

        return config('filesystems.disks.'.$disk.'.driver') === 'local';
    }

    public function temporaryUrl(string $disk, string $key, ?string $filename = null, ?\DateTimeInterface $expiresAt = null): string
    {
        $this->ensureAllowed($disk);
        $options = $filename === null ? ['ResponseContentType' => 'video/mp4'] : [
            'ResponseContentDisposition' => HeaderUtils::makeDisposition('attachment', $filename, 'dancepro-download.mp4'),
        ];

        return Storage::disk($disk)->temporaryUrl($key, $expiresAt ?? now()->addMinutes(15), $options);
    }
}
