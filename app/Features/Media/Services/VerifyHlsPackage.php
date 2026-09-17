<?php

namespace App\Features\Media\Services;

use App\Features\Concerts\Support\ConcertMediaPath;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Support\MediaIngestPath;
use Illuminate\Validation\ValidationException;

class VerifyHlsPackage
{
    public function __construct(
        private readonly S3MediaStorage $storage,
        private readonly ConcertMediaPath $paths,
        private readonly MediaIngestPath $ingestPath,
    ) {}

    /** @param list<string> $expectedOutputs */
    public function verify(MediaAsset $asset, array $expectedOutputs): void
    {
        $prefix = $this->paths->assetPrefix($asset).'/stream/';
        $objects = $this->inventory($asset->storage_disk, $prefix);
        $master = $this->playlist($asset->storage_disk, $prefix.'master.m3u8', $objects);
        $variants = $this->variants($master);
        $heights = array_column($variants, 'height');

        foreach (['hls_720p' => 720, 'hls_480p' => 480] as $output => $height) {
            if (in_array($output, $expectedOutputs, true) && ! in_array($height, $heights, true)) {
                $this->invalid("The HLS master is missing the requested {$height}p rendition.");
            }
        }

        foreach ($variants as $variant) {
            $key = $this->reference($prefix.'master.m3u8', $variant['uri'], $prefix);
            if (! str_ends_with($key, '.m3u8') || $key === $prefix.'master.m3u8') {
                $this->invalid('Each HLS variant must reference a child media playlist.');
            }
            $this->verifyMediaPlaylist($this->playlist($asset->storage_disk, $key, $objects), $key, $prefix, $objects);
        }
    }

    /** @return array<string, int> */
    private function inventory(string $disk, string $prefix): array
    {
        $objects = [];
        $cursor = null;
        $limit = max(1, (int) config('media.max_hls_objects', 10000));
        $pages = 0;

        do {
            $page = $this->storage->listObjects($disk, $prefix, 1000, $cursor);
            foreach ($page['objects'] as $object) {
                if (! str_starts_with($object['key'], $prefix)) {
                    $this->invalid('The HLS inventory contains an object outside the asset package.');
                }
                $objects[$object['key']] = $object['size'];
            }
            $cursor = $page['next_token'];
            $pages++;
            if (count($objects) > $limit || ($cursor !== null && $pages >= (int) ceil($limit / 1000))) {
                $this->invalid('The HLS package exceeds the configured object limit.');
            }
        } while ($cursor !== null);

        return $objects;
    }

    /** @return list<string> */
    private function playlist(string $disk, string $key, array $objects): array
    {
        $size = $objects[$key] ?? 0;
        if ($size < 1 || $size > (int) config('media.max_manifest_bytes')) {
            $this->invalid('An HLS playlist is missing, empty or too large.');
        }
        $manifest = $this->storage->read($disk, $key);
        if (strlen($manifest) > (int) config('media.max_manifest_bytes')) {
            $this->invalid('An HLS playlist exceeds the manifest size limit.');
        }
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $manifest) ?: []), fn (string $line): bool => $line !== ''));
        if (($lines[0] ?? null) !== '#EXTM3U') {
            $this->invalid('Every HLS playlist must begin with #EXTM3U.');
        }

        return $lines;
    }

    /** @return list<array{height: int, uri: string}> */
    private function variants(array $lines): array
    {
        $variants = [];
        $height = null;
        foreach (array_slice($lines, 1) as $line) {
            if (str_starts_with($line, '#EXT-X-STREAM-INF:')) {
                if ($height !== null
                    || ! preg_match('/(?:^|,)RESOLUTION=([1-9][0-9]*)x([1-9][0-9]*)(?:,|$)/', substr($line, 18), $resolution)
                    || ! preg_match('/(?:^|,)BANDWIDTH=[1-9][0-9]*(?:,|$)/', substr($line, 18))) {
                    $this->invalid('HLS variants require BANDWIDTH, RESOLUTION and a playlist URI.');
                }
                if (preg_match('/(?:^|,)(AUDIO|VIDEO|SUBTITLES)=/', substr($line, 18))) {
                    $this->invalid('HLS ingest requires self-contained variants with muxed audio.');
                }
                $height = (int) $resolution[2];
            } elseif (! str_starts_with($line, '#')) {
                if ($height === null) {
                    $this->invalid('An HLS master URI has no variant declaration.');
                }
                $variants[] = ['height' => $height, 'uri' => $line];
                $height = null;
            } elseif (str_starts_with($line, '#EXT') && ! preg_match('/^#EXT-X-(VERSION:[1-9][0-9]*|INDEPENDENT-SEGMENTS)$/', $line)) {
                $this->invalid('The HLS master contains an unsupported tag.');
            }
        }
        if ($height !== null || $variants === [] || count($variants) > (int) config('media.max_hls_variants', 4)) {
            $this->invalid('The HLS master must contain a bounded set of complete variants.');
        }

        return $variants;
    }

    private function verifyMediaPlaylist(array $lines, string $key, string $prefix, array $objects): void
    {
        $target = null;
        $duration = null;
        $longest = 0;
        $segments = 0;
        $hasMap = false;
        $ended = false;

        foreach (array_slice($lines, 1) as $line) {
            if ($ended && (str_starts_with($line, '#EXT') || ! str_starts_with($line, '#'))) {
                $this->invalid('An HLS media playlist contains data after ENDLIST.');
            }
            if (preg_match('/^#EXT-X-TARGETDURATION:([1-9][0-9]*)$/', $line, $match)) {
                if ($target !== null) {
                    $this->invalid('An HLS playlist has duplicate target durations.');
                }
                $target = (int) $match[1];
            } elseif (preg_match('/^#EXTINF:([0-9]+(?:\.[0-9]+)?),.*$/', $line, $match)) {
                if ($duration !== null || (float) $match[1] <= 0) {
                    $this->invalid('HLS segments require a positive duration and a URI.');
                }
                $duration = (float) $match[1];
            } elseif (str_starts_with($line, '#EXT-X-MAP:')) {
                if (! preg_match('/^#EXT-X-MAP:URI="([^"]+)"$/', $line, $match)) {
                    $this->invalid('HLS initialization maps must reference a complete in-package object.');
                }
                $map = $this->reference($key, $match[1], $prefix);
                $this->requireObject($map, $objects);
                if (! str_ends_with($map, '.mp4')) {
                    $this->invalid('HLS initialization maps must be MP4 objects.');
                }
                $hasMap = true;
            } elseif ($line === '#EXT-X-ENDLIST') {
                $ended = true;
            } elseif (! str_starts_with($line, '#')) {
                if ($duration === null) {
                    $this->invalid('An HLS segment is missing its EXTINF duration.');
                }
                $segment = $this->reference($key, $line, $prefix);
                if (! preg_match('/\.(ts|m4s|mp4)$/', $segment)
                    || (! str_ends_with($segment, '.ts') && ! $hasMap)) {
                    $this->invalid('HLS segments must be TS or fragmented MP4 with an initialization map.');
                }
                $this->requireObject($segment, $objects);
                $segments++;
                $longest = max($longest, round($duration));
                $duration = null;
            } elseif (str_starts_with($line, '#EXT') && ! preg_match('/^#EXT-X-(VERSION:[1-9][0-9]*|MEDIA-SEQUENCE:[0-9]+|PLAYLIST-TYPE:VOD|INDEPENDENT-SEGMENTS|DISCONTINUITY|DISCONTINUITY-SEQUENCE:[0-9]+)$/', $line)) {
                $this->invalid('The HLS media playlist contains an unsupported tag.');
            }
        }

        if ($segments === 0 || $duration !== null || ! $ended || $target === null || $longest > $target) {
            $this->invalid('HLS media playlists require segments, a valid target duration and ENDLIST.');
        }
    }

    private function reference(string $playlistKey, string $uri, string $prefix): string
    {
        if ($uri === '' || str_starts_with($uri, '/') || str_contains($uri, ':') || str_contains($uri, '?') || str_contains($uri, '#')) {
            $this->invalid('HLS references must be relative in-package paths.');
        }
        $key = dirname($playlistKey).'/'.$uri;
        if (! str_starts_with($key, $prefix)) {
            $this->invalid('An HLS reference escaped the asset package.');
        }
        $relative = 'stream/'.substr($key, strlen($prefix));
        try {
            $this->ingestPath->validate($relative);
        } catch (ValidationException) {
            $this->invalid('An HLS reference is not a safe supported package path.');
        }

        return $key;
    }

    private function requireObject(string $key, array $objects): void
    {
        if (($objects[$key] ?? 0) < 1) {
            $this->invalid('An HLS segment or initialization object is missing or empty.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['expected_outputs' => $message]);
    }
}
