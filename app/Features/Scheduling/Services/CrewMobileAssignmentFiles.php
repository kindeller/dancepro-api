<?php

namespace App\Features\Scheduling\Services;

use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrewMobileAssignmentFiles
{
    public function __construct(private readonly OperationsFileStorage $operationsFiles) {}

    public function issue(SchedulingShiftAssignment $assignment, string $kind): array
    {
        $file = $this->file($assignment, $kind);
        $expiresAt = now()->addMinutes(10);

        return [
            'url' => URL::temporarySignedRoute('api.v1.assignment-files.content', $expiresAt, [
                'assignment' => $assignment,
                'kind' => $kind,
            ]),
            'expires_at' => $expiresAt->toIso8601String(),
            'mime_type' => $file['disk']->mimeType($file['path']) ?: 'application/octet-stream',
            'filename' => $file['filename'],
        ];
    }

    public function response(SchedulingShiftAssignment $assignment, string $kind): StreamedResponse
    {
        $file = $this->file($assignment, $kind);

        return $file['disk']->response(
            $file['path'],
            $file['filename'],
            ['Cache-Control' => 'private, no-store'],
            'inline',
        );
    }

    /** @return array{disk: Filesystem, path: string, filename: string} */
    private function file(SchedulingShiftAssignment $assignment, string $kind): array
    {
        $assignment->loadMissing('shift.schedulingEvent.venue');
        $event = $assignment->shift->schedulingEvent;
        $venue = $event->venue;
        [$disk, $path, $baseName] = match ($kind) {
            'programme' => [$this->operationsFiles->disk(), $event->programme_path, $event->name.' programme'],
            'venue-map' => [$this->operationsFiles->disk(), $venue?->map_path, ($venue?->name ?? 'Venue').' map'],
            'venue-reference' => [Storage::disk(config('uploads.public_disk')), $venue?->reference_image_path, ($venue?->name ?? 'Venue').' reference'],
            default => abort(404),
        };
        abort_unless(filled($path) && $disk->exists($path), 404);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $safeBaseName = preg_replace('/[^A-Za-z0-9 _.-]/', '', $baseName) ?: 'assignment file';

        return [
            'disk' => $disk,
            'path' => $path,
            'filename' => trim($safeBaseName).($extension !== '' ? '.'.$extension : ''),
        ];
    }
}
