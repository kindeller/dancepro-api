<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Services\CrewMobileAssignments;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrewMobileAssignmentFileController extends Controller
{
    public function __invoke(
        Request $request,
        SchedulingShiftAssignment $assignment,
        string $kind,
        CrewMobileAssignments $assignments,
        OperationsFileStorage $files,
    ): StreamedResponse {
        $assignment = $assignments->findOwned($request->user()->crewProfile, $assignment);
        $event = $assignment->shift->schedulingEvent;
        $venue = $event->venue;

        [$disk, $path, $name] = match ($kind) {
            'programme' => [$files->disk(), $event->programme_path, $event->name.' programme'],
            'venue-map' => [$files->disk(), $venue?->map_path, ($venue?->name ?? 'Venue').' map'],
            'venue-reference' => [Storage::disk(config('uploads.public_disk')), $venue?->reference_image_path, ($venue?->name ?? 'Venue').' reference'],
            default => abort(404),
        };

        abort_unless(filled($path) && $disk->exists($path), 404);

        return $disk->response($path, $name, ['Cache-Control' => 'private, no-store'], 'inline');
    }
}
