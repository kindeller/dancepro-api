<?php

namespace App\Features\Concerts\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ConcertDownloadUrlSigner
{
    public function __construct(
        private readonly ConcertDownloadFilename $filenames,
    ) {}

    public function signedUrl(Concert $concert, MediaAsset $asset): string
    {
        abort_unless(in_array($asset->storage_disk, ['s3_concerts', 's3_concerts_legacy'], true), 503);

        $filename = $this->filenames->for($concert, $asset);
        $disposition = HeaderUtils::makeDisposition('attachment', $filename, str_replace('%', '_', Str::ascii($filename)));

        return Storage::disk($asset->storage_disk)->temporaryUrl(
            $asset->storage_key,
            now()->addMinutes((int) config('concerts.playback.signed_url_ttl_minutes', 15)),
            ['ResponseContentDisposition' => $disposition],
        );
    }
}
