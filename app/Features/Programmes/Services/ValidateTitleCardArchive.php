<?php

namespace App\Features\Programmes\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ValidateTitleCardArchive
{
    public function validate(UploadedFile $upload, int $expected): void
    {
        $zip = new ZipArchive;
        if ($zip->open($upload->getRealPath()) !== true) {
            throw ValidationException::withMessages(['package' => 'The title package is not a readable ZIP.']);
        }
        try {
            $valid = $expected > 0 && $expected <= 500 && $zip->numFiles === $expected;
            $names = [];
            $total = 0;
            for ($i = 0; $valid && $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $total += $entry['size'];
                $name = $entry['name'];
                $valid = (bool) preg_match('/\A[a-zA-Z0-9 -]+\.jpg\z/', $name) && strlen($name) <= 240
                    && ! isset($names[strtolower($name)]) && $entry['size'] > 0 && $entry['size'] <= 8 * 1024 * 1024 && $total <= 200 * 1024 * 1024;
                if (! $valid) {
                    break;
                }
                $names[strtolower($name)] = true;
                $bytes = $zip->getFromIndex($i);
                $image = $bytes === false ? false : @getimagesizefromstring($bytes);
                $valid = $image && $image[0] === 3840 && $image[1] === 2160 && $image[2] === IMAGETYPE_JPEG;
            }
            if (! $valid) {
                throw ValidationException::withMessages(['package' => 'The package must contain one unique 4K JPEG per programme item, up to 500 images and 200 MB.']);
            }
        } finally {
            $zip->close();
        }
    }
}
