<?php

namespace App\Features\Media\Support;

use Illuminate\Validation\ValidationException;

class MediaIngestPath
{
    public function validate(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '..') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            throw ValidationException::withMessages(['relative_path' => 'The media path is not safe.']);
        }

        if (! preg_match('#^(original/video\.mp4|stream/fallback\.mp4|stream/master\.m3u8|stream/[A-Za-z0-9][A-Za-z0-9._/-]*\.(m3u8|m4s|mp4|ts)|thumbnail/poster\.(png|jpg|jpeg|webp))$#', $path)) {
            throw ValidationException::withMessages(['relative_path' => 'The media path is not part of the supported package.']);
        }

        return $path;
    }

    public function contentTypeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp4' => 'video/mp4',
            'm3u8' => 'application/vnd.apple.mpegurl',
            'm4s' => 'video/iso.segment',
            'ts' => 'video/mp2t',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
