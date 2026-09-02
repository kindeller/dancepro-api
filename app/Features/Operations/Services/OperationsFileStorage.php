<?php

namespace App\Features\Operations\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;

class OperationsFileStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        $disk = (string) config('operations.filesystem_disk', 'local');
        if ($disk === '' || $disk === 'public') {
            throw new LogicException('Operational documents must use a configured private filesystem disk.');
        }

        return $disk;
    }

    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, $this->diskName());
    }

    public function deleteReplaced(?string $previousPath, string $currentPath): void
    {
        if (filled($previousPath) && $previousPath !== $currentPath) {
            $this->disk()->delete($previousPath);
        }
    }
}
