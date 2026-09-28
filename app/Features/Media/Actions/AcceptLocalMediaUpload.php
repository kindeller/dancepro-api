<?php

namespace App\Features\Media\Actions;

use App\Features\Media\Models\MediaUpload;
use App\Features\Media\Services\LocalMediaStorage;
use App\Features\Media\Support\MediaUploadKind;
use App\Features\Media\Support\MediaUploadStatus;
use App\Features\Programmes\Models\ConcertVideoBatch;
use App\Features\Programmes\Models\ConcertVideoFile;

class AcceptLocalMediaUpload
{
    public function __construct(private readonly LocalMediaStorage $storage) {}

    public function object(string $key, string $checksum, mixed $body): void
    {
        $file = MediaUpload::query()
            ->where('kind', MediaUploadKind::SingleBatch->value)
            ->where('status', MediaUploadStatus::Pending->value)
            ->where('storage_disk', 's3_concerts')
            ->where('expires_at', '>', now())
            ->get()
            ->flatMap(fn (MediaUpload $upload) => $upload->files ?? [])
            ->first(fn (array $file): bool => ($file['storage_key'] ?? null) === $key && ($file['checksum_sha256'] ?? null) === $checksum);
        if ($file === null) {
            $concertFile = ConcertVideoFile::with('batch')->where('storage_disk', 's3_concerts')->where('storage_key', $key)->whereNull('verified_at')->first();
            abort_unless($concertFile && ! $concertFile->batch->completed_at
                && hash_equals(base64_encode(hex2bin($concertFile->sha256)), $checksum)
                && ConcertVideoBatch::where('scheduling_event_id', $concertFile->batch->scheduling_event_id)->latest('id')->value('id') === $concertFile->concert_video_batch_id, 404);
            $file = ['size_bytes' => $concertFile->byte_count];
        }
        abort_unless($file !== null, 404);

        $this->storage->uploadObject($key, $checksum, (int) $file['size_bytes'], $body);
    }

    public function part(string $key, string $uploadId, int $partNumber, string $checksum, mixed $body): string
    {
        $upload = MediaUpload::query()
            ->where('provider_upload_id', $uploadId)
            ->where('storage_key', $key)
            ->where('storage_disk', 's3_concerts')
            ->where('kind', MediaUploadKind::Multipart->value)
            ->where('status', MediaUploadStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->first();
        abort_unless($upload !== null, 404);

        return $this->storage->uploadPart($key, $uploadId, $partNumber, $checksum, $body);
    }
}
