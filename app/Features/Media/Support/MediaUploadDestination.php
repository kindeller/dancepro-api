<?php

namespace App\Features\Media\Support;

use App\Features\Media\Models\MediaCollection;
use Illuminate\Validation\ValidationException;

class MediaUploadDestination
{
    public function ensureAllowed(string $disk): void
    {
        $uploadDisk = (string) config('media.upload_disk');
        $legacyDisk = (string) config('media.legacy_disk');
        $bucket = config("filesystems.disks.{$uploadDisk}.bucket");
        $legacyBucket = config("filesystems.disks.{$legacyDisk}.bucket");

        if ($disk !== $uploadDisk || $uploadDisk === '' || $uploadDisk === $legacyDisk
            || (filled($bucket) && $bucket === $legacyBucket)) {
            throw ValidationException::withMessages(['storage_disk' => 'Uploads are permitted only on the dedicated media upload disk, never legacy storage.']);
        }
    }

    public function ensureCollectionAllowed(MediaCollection $collection): void
    {
        $this->ensureAllowed($collection->storage_disk);

        if ($collection->catalogue_mode !== MediaCatalogueMode::Managed || $collection->media_type !== MediaType::Video) {
            throw ValidationException::withMessages(['collection' => 'Uploads require a managed video collection.']);
        }
    }
}
