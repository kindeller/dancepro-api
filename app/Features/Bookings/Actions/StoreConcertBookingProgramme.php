<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Operations\Services\OperationsFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class StoreConcertBookingProgramme
{
    public function __construct(private readonly OperationsFileStorage $files) {}

    public function execute(ConcertBookingItem $item, UploadedFile $programme): void
    {
        $previousPath = $item->programme_path;
        $path = $this->files->store($programme, 'operations/event-programmes/'.$item->uuid);

        try {
            DB::transaction(function () use ($item, $path, $programme): void {
                $item->update([
                    'programme_path' => $path,
                    'programme_name' => $this->safeOriginalName($programme),
                    'programme_mime' => $programme->getMimeType(),
                    'programme_size' => $programme->getSize(),
                ]);
                $item->schedulingEvent?->update(['programme_path' => $path]);
            });
        } catch (Throwable $exception) {
            $this->files->disk()->delete($path);

            throw $exception;
        }

        $this->files->deleteReplaced($previousPath, $path);
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename($file->getClientOriginalName())) ?: 'programme.pdf';

        return mb_substr($name, 0, 255);
    }
}
