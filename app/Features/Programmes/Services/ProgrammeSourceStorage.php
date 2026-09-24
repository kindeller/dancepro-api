<?php

namespace App\Features\Programmes\Services;

use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeSource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgrammeSourceStorage
{
    public function __construct(private readonly OperationsFileStorage $files) {}

    public function capture(Programme $programme, User $user, ?UploadedFile $upload = null): ?ProgrammeSource
    {
        $original = $programme->event->programme_path;
        if (! $upload && ! $original) {
            return null;
        }
        $directory = 'programme-sources/'.$programme->uuid;
        if ($upload) {
            $path = $this->files->store($upload, $directory);
            $name = basename($upload->getClientOriginalName());
        } else {
            if (! $this->files->disk()->exists($original)) {
                return null;
            }
            if ($this->files->disk()->mimeType($original) !== 'application/pdf') {
                return null;
            }
            $path = $directory.'/'.Str::uuid().'.pdf';
            if (! $this->files->disk()->copy($original, $path)) {
                throw new RuntimeException('The programme source could not be retained.');
            }
            $name = 'Event programme.pdf';
        }
        if (! $path) {
            throw new RuntimeException('The programme source could not be stored.');
        }

        // Retained originals are never removed by the event attachment replacement flow.
        return ProgrammeSource::create([
            'programme_id' => $programme->id, 'disk' => $this->files->diskName(),
            'path' => $path, 'name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'Programme.pdf', 0, 255),
            'created_by_user_id' => $user->id,
        ]);
    }

    public function respond(ProgrammeSource $source): StreamedResponse
    {
        $disk = Storage::disk($source->disk);
        abort_unless($disk->exists($source->path), 404);

        return $disk->response($source->path, $source->name, [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
