<?php

namespace App\Features\Publicity\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class AdvertisingVideo
{
    /** Validate a self-contained TV-compatible MP4 before publishing it to players. */
    public function store(UploadedFile $file): array
    {
        $message = 'Use an MP4 with H.264 video and optional AAC audio, up to 1080p, 60 fps, 2 minutes and 100 MB.';
        try {
            $probe = new Process(['ffprobe', '-v', 'error', '-protocol_whitelist', 'file', '-show_format', '-show_streams', '-of', 'json', $file->getRealPath()]);
            $probe->setTimeout(20)->mustRun();
            $metadata = json_decode($probe->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $streams = collect($metadata['streams'] ?? []);
            $videos = $streams->where('codec_type', 'video');
            $video = $videos->first();
            $seconds = (float) ($metadata['format']['duration'] ?? 0);
            [$numerator, $denominator] = array_pad(explode('/', $video['avg_frame_rate'] ?? '0/1'), 2, '1');
            $fps = (float) $numerator / max(1, (float) $denominator);
            $width = (int) ($video['width'] ?? 0);
            $height = (int) ($video['height'] ?? 0);
            $valid = $videos->count() === 1 && ($video['codec_name'] ?? '') === 'h264'
                && ($video['pix_fmt'] ?? '') === 'yuv420p' && ($video['level'] ?? 100) <= 42
                && $width > 0 && $height > 0 && max($width, $height) <= 1920 && min($width, $height) <= 1080
                && $fps > 0 && $fps <= 60 && $seconds > 0 && $seconds <= 120 && $file->getSize() <= 100 * 1024 * 1024
                && str_contains($metadata['format']['format_name'] ?? '', 'mp4')
                && $streams->every(fn ($stream) => ($stream['codec_type'] ?? '') === 'video' || (($stream['codec_type'] ?? '') === 'audio' && ($stream['codec_name'] ?? '') === 'aac' && ($stream['channels'] ?? 99) <= 2));
            if (! $valid) {
                throw ValidationException::withMessages(['video' => $message]);
            }
            // Reject truncated/corrupt files rather than caching clips that fail midway.
            (new Process(['ffmpeg', '-v', 'error', '-xerror', '-protocol_whitelist', 'file', '-i', $file->getRealPath(), '-map', '0:v:0', '-map', '0:a?', '-f', 'null', '-']))->setTimeout(90)->mustRun();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['video' => 'This video could not be verified. '.$message]);
        }
        $key = 'publicity/slides/'.Str::uuid().'.mp4';
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            if (! Storage::disk('local')->put($key, $stream)) {
                throw ValidationException::withMessages(['video' => 'The video could not be saved. Please try again.']);
            }
        } finally {
            fclose($stream);
        }

        return ['video_key' => $key, 'video_hash' => hash_file('sha256', $file->getRealPath()), 'video_bytes' => $file->getSize(), 'video_seconds' => max(1, (int) ceil($seconds)), 'image_key' => null, 'image_hash' => null];
    }
}
