<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\ProgrammeSource;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class ProgrammePdfExtractor
{
    public function extract(ProgrammeSource $source, string $type, ?int $year = null, ?callable $progress = null): array
    {
        $directory = storage_path('app/private/programme-extraction');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create extraction workspace.');
        }
        $path = tempnam($directory, 'pdf-');
        if ($path === false) {
            throw new RuntimeException('Cannot create extraction workspace.');
        }
        $input = null;
        $output = null;
        try {
            $disk = Storage::disk($source->disk);
            if ($disk->size($source->path) > 100 * 1024 * 1024) {
                throw new RuntimeException('Source exceeds extraction size limit.');
            }
            $input = $disk->readStream($source->path);
            $output = fopen($path, 'wb');
            if (! is_resource($input) || ! is_resource($output)) {
                throw new RuntimeException('Cannot read programme source.');
            }
            stream_copy_to_stream($input, $output, 100 * 1024 * 1024 + 1);
            fclose($output);
            $output = null;
            $process = new Process(['python3', app_path('Features/Programmes/Extraction/extract.py'), $path, $type, (string) $year]);
            $process->setTimeout(150);
            $buffer = '';
            $process->mustRun(function (string $channel, string $chunk) use ($progress, &$buffer): void {
                if ($channel !== Process::ERR || ! $progress) {
                    return;
                }
                $buffer .= $chunk;
                while (($end = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $end);
                    $buffer = substr($buffer, $end + 1);
                    if (str_starts_with($line, 'PROGRESS ')) {
                        $data = json_decode(substr($line, 9), true);
                        if (isset($data['page'], $data['total']) && is_int($data['page']) && is_int($data['total'])) {
                            $progress($data['page'], $data['total'], (bool) ($data['ocr'] ?? false));
                        }
                    }
                }
            });
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            if (! isset($result['nodes'], $result['issues']) || count($result['nodes']) > 10000) {
                throw new RuntimeException('Invalid extraction result.');
            }

            return $result;
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            // Only this private temporary local copy is removed, never the retained source.
            @unlink($path);
        }
    }
}
