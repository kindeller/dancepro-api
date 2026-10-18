<?php

namespace App\Features\Media\Actions;

use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Services\S3MediaStorage;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaUploadKind;
use App\Features\Media\Support\MediaUploadStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelMediaAssetUpload
{
    public function __construct(
        private readonly S3MediaStorage $storage,
        private readonly RecordMediaIngestEvent $recordEvent,
        private readonly MediaUploadDestination $destination,
    ) {}

    public function handle(MediaAsset $asset, User $user): MediaAsset
    {
        $this->destination->ensureCollectionAllowed($asset->collection);
        $this->destination->ensureAllowed($asset->storage_disk);

        if ($asset->storage_disk !== $asset->collection->storage_disk) {
            throw ValidationException::withMessages([
                'asset' => 'This video uses a different storage disk and cannot be cancelled here.',
            ]);
        }

        if ($asset->status === MediaAssetStatus::Cancelled) {
            return $asset;
        }

        if ($asset->status !== MediaAssetStatus::Processing || $asset->verified_at !== null) {
            throw ValidationException::withMessages([
                'asset' => 'Only an unfinished processing upload can be cancelled.',
            ]);
        }

        $pendingUploads = $asset->uploads()
            ->where('status', MediaUploadStatus::Pending->value)
            ->orderBy('id')
            ->get();

        foreach ($pendingUploads as $upload) {
            $this->destination->ensureAllowed($upload->storage_disk);
            if ($upload->storage_disk !== $asset->storage_disk) {
                throw ValidationException::withMessages([
                    'upload' => 'This upload uses a different storage disk and cannot be cancelled here.',
                ]);
            }

            if ($upload->kind === MediaUploadKind::Multipart) {
                $this->storage->abortMultipart(
                    $upload->storage_disk,
                    (string) $upload->storage_key,
                    (string) $upload->provider_upload_id,
                );
            }

            $upload->update([
                'status' => MediaUploadStatus::Aborted,
                'aborted_at' => now(),
            ]);
        }

        DB::transaction(function () use ($asset, $user, $pendingUploads): void {
            $asset->update([
                'status' => MediaAssetStatus::Cancelled,
                'is_visible' => false,
            ]);
            $this->recordEvent->handle($user, $asset, 'media_asset.upload_cancelled', [
                'upload_count' => $pendingUploads->count(),
            ]);
        });

        return $asset->refresh();
    }
}
