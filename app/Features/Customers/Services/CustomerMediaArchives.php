<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerMediaArchive;
use App\Features\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class CustomerMediaArchives
{
    public function archive(User $user, string $kind, string $eventUuid, string $title, Collection $assets): CustomerMediaArchive
    {
        abort_if($assets->count() < 2, 404);
        $fingerprint = hash('sha256', $assets->sortBy('id')->map(fn (MediaAsset $asset): string => implode('|', [
            $asset->id, $asset->storage_disk, $asset->storage_key, $asset->size_bytes, $asset->updated_at?->getTimestamp(),
        ]))->implode("\n"));
        $scope = $kind === 'competition' ? 'shared' : 'customer';
        $ownerId = $scope === 'customer' ? $user->id : null;
        $existing = CustomerMediaArchive::query()->where([
            'scope' => $scope, 'kind' => $kind, 'event_uuid' => $eventUuid, 'fingerprint' => $fingerprint,
            'user_id' => $ownerId,
        ])->where('expires_at', '>', now())->first();
        if ($existing && Storage::disk($existing->storage_disk)->exists($existing->storage_key)) {
            return $existing;
        }

        $uuid = (string) Str::uuid();
        $filename = Str::slug($title) ?: 'dancepro-media';
        $filename .= '.zip';
        $key = "customer-media-archives/{$uuid}.zip";
        $temporaryDirectory = storage_path("framework/cache/media-archives/{$uuid}");
        $zipPath = $temporaryDirectory.'/archive.zip';
        if (! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
            throw new RuntimeException('Could not prepare the media archive.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create the media archive.');
            }
            $usedNames = [];
            foreach ($assets as $index => $asset) {
                $stream = Storage::disk($asset->storage_disk)->readStream($asset->storage_key);
                if (! is_resource($stream)) {
                    throw new RuntimeException('A purchased media file could not be read.');
                }
                $assetPath = $temporaryDirectory.'/asset-'.$index;
                $output = fopen($assetPath, 'wb');
                stream_copy_to_stream($stream, $output);
                fclose($stream);
                fclose($output);
                $name = $this->uniqueName($asset, $usedNames);
                $zip->addFile($assetPath, $name);
            }
            if (! $zip->close()) {
                throw new RuntimeException('Could not finish the media archive.');
            }
            Storage::disk('local')->put($key, fopen($zipPath, 'rb'));
        } finally {
            foreach (glob($temporaryDirectory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($temporaryDirectory);
        }

        return CustomerMediaArchive::query()->updateOrCreate([
            'scope' => $scope, 'kind' => $kind, 'event_uuid' => $eventUuid, 'fingerprint' => $fingerprint,
            'user_id' => $ownerId,
        ], [
            'uuid' => $uuid, 'storage_disk' => 'local', 'storage_key' => $key, 'filename' => $filename,
            'asset_count' => $assets->count(), 'source_bytes' => (int) $assets->sum('size_bytes'),
            'archive_bytes' => Storage::disk('local')->size($key),
            'expires_at' => now()->addDays($scope === 'shared' ? 30 : 2),
        ]);
    }

    private function uniqueName(MediaAsset $asset, array &$used): string
    {
        $original = basename($asset->original_filename ?: $asset->display_name ?: "media-{$asset->uuid}");
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '', $original) ?: "media-{$asset->uuid}";
        $base = pathinfo($name, PATHINFO_FILENAME);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $candidate = $name;
        $suffix = 2;
        while (isset($used[strtolower($candidate)])) {
            $candidate = $base.'-'.$suffix++.($extension ? '.'.$extension : '');
        }
        $used[strtolower($candidate)] = true;

        return $candidate;
    }
}
