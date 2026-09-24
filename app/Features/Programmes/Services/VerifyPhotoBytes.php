<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\ProgrammePhotoFile;
use Illuminate\Validation\ValidationException;

class VerifyPhotoBytes
{
    /** The caller owns the returned temporary stream and must close it. */
    public function copyAndVerify(mixed $input, ProgrammePhotoFile $file): mixed
    {
        $temp = tmpfile();
        abort_unless(is_resource($temp) && is_resource($input), 503, 'Unable to create temporary photo storage.');
        try {
            $hash = hash_init('sha256');
            $count = 0;
            while (! feof($input)) {
                $chunk = fread($input, 1048576);
                abort_if($chunk === false, 400, 'Photo transfer was interrupted. Retry this photo.');
                $count += strlen($chunk);
                if ($count > $file->byte_count) {
                    $this->invalid('The received photo is larger than its reservation.');
                }
                abort_unless(fwrite($temp, $chunk) === strlen($chunk), 503, 'Temporary photo storage is full.');
                hash_update($hash, $chunk);
            }
            if ($count !== $file->byte_count || ! hash_equals($file->sha256, hash_final($hash))) {
                $this->invalid('The received photo size or checksum does not match. Retry from the original file.');
            }
            $path = stream_get_meta_data($temp)['uri'];
            $info = @getimagesize($path);
            if (! $info || ($info[2] ?? null) !== IMAGETYPE_JPEG || $info[0] < 1 || $info[1] < 1
                || config('programme-media.max_image_pixels') < $info[0] * $info[1]) {
                $this->invalid('Upload a valid JPEG within the server image-size limit.');
            }
            $limit = ini_parse_quantity(ini_get('memory_limit'));
            if ($limit > 0 && memory_get_usage(true) + $info[0] * $info[1] * 8 + 16777216 > $limit) {
                $this->invalid('This photo exceeds the server image-verification memory limit.');
            }
            fseek($temp, -2, SEEK_END);
            if (fread($temp, 2) !== "\xff\xd9") {
                $this->invalid('The JPEG is incomplete.');
            }
            set_error_handler(static function (int $severity, string $message): never {
                throw new \RuntimeException('JPEG decoding failed.');
            });
            try {
                $image = imagecreatefromjpeg($path);
                if ($image === false) {
                    $this->invalid('The JPEG could not be decoded.');
                }
                unset($image);
            } catch (\RuntimeException $exception) {
                $this->invalid('The JPEG is damaged or incomplete.');
            } finally {
                restore_error_handler();
            }
            rewind($temp);

            return $temp;
        } catch (\Throwable $exception) {
            fclose($temp);
            throw $exception;
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['photo' => $message]);
    }
}
