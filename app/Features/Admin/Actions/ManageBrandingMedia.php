<?php

namespace App\Features\Admin\Actions;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Support\MediaUploadDestination;
use App\Features\Studios\Models\Studio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ManageBrandingMedia
{
    public function __construct(private readonly MediaUploadDestination $destination) {}

    public function upload(Studio|Concert $owner, string $kind, UploadedFile $file): void
    {
        $disk = $this->disk();
        $key = $this->key($owner, $kind);
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            if (! Storage::disk($disk)->put($key, $stream, ['ContentType' => $file->getMimeType()])) {
                throw new RuntimeException('The file could not be uploaded to concert storage.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $revision = (string) Str::uuid();
        $url = $this->url($owner, $kind).'?v='.$revision;
        [$urlField, $keyField, $revisionField, $mimeField] = $this->fields($kind);
        $owner->update([$urlField => $url, $keyField => $key, $revisionField => $revision, $mimeField => $file->getMimeType()]);
    }

    public function digest(Studio|Concert $owner, string $kind): string
    {
        [$urlField, $keyField, $revisionField] = $this->fields($kind);
        return hash_hmac('sha256', json_encode([
            $owner->uuid, $kind, $owner->{$urlField}, $owner->{$keyField}, $owner->{$revisionField}, $this->disk(),
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function clear(Studio|Concert $owner, string $kind, string $digest): void
    {
        if (! hash_equals($this->digest($owner, $kind), $digest)) {
            throw ValidationException::withMessages(['confirmation' => 'This file changed. Review the confirmation again.']);
        }
        [$urlField, $keyField, $revisionField, $mimeField] = $this->fields($kind);
        $key = $owner->{$keyField};
        if ($key !== null) {
            if ($key !== $this->key($owner, $kind)) {
                throw ValidationException::withMessages(['file' => 'The stored key is outside this managed media slot.']);
            }
            $disk = Storage::disk($this->disk());
            if ($disk->exists($key) && ! $disk->delete($key)) {
                throw new RuntimeException('The S3 object could not be deleted; the database reference was retained.');
            }
        }
        $owner->update([$urlField => null, $keyField => null, $revisionField => null, $mimeField => null]);
    }

    public function key(Studio|Concert $owner, string $kind): string
    {
        if ($owner instanceof Studio && $kind === 'cover') {
            return "studios/{$owner->uuid}/cover";
        }
        if ($owner instanceof Concert && $kind === 'cover') {
            return "concerts/{$owner->uuid}/cover";
        }
        if ($owner instanceof Concert && $kind === 'program') {
            return "concerts/{$owner->uuid}/documents/program.pdf";
        }

        throw new RuntimeException('Unsupported branding media slot.');
    }

    private function url(Studio|Concert $owner, string $kind): string
    {
        if ($owner instanceof Studio) {
            return route('studios.cover', $owner);
        }

        return route($kind === 'program' ? 'concerts.program' : 'concerts.cover', $owner);
    }

    /** @return array{string, string, string, string} */
    public function fields(string $kind): array
    {
        return $kind === 'program'
            ? ['program_url', 'program_storage_key', 'program_revision', 'program_mime_type']
            : ['cover_image_url', 'cover_image_storage_key', 'cover_image_revision', 'cover_image_mime_type'];
    }

    public function disk(): string
    {
        $disk = (string) config('media.upload_disk');
        $this->destination->ensureAllowed($disk);

        return $disk;
    }
}
