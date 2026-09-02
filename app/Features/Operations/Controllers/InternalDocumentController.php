<?php

namespace App\Features\Operations\Controllers;

use App\Features\Operations\Models\EventMessage;
use App\Features\Operations\Models\OperationalResource;
use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Venues\Models\Venue;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InternalDocumentController extends Controller
{
    public function __construct(private readonly OperationsFileStorage $files) {}

    public function resource(Request $request, OperationalResource $resource): StreamedResponse
    {
        abort_unless($request->user()->canAccessAdmin() || ($request->user()->canAccessCrew() && $resource->is_active), 403);

        return $this->respond($resource->file_path, $resource->title);
    }

    public function venueMap(Request $request, Venue $venue): StreamedResponse
    {
        abort_unless($request->user()->canAccessAdmin() || $this->crewHasVenue($request->user(), $venue), 403);

        return $this->respond($venue->map_path, $venue->name.' map');
    }

    public function programme(Request $request, SchedulingEvent $schedulingEvent): StreamedResponse
    {
        abort_unless($request->user()->canAccessAdmin() || $this->crewHasEvent($request->user(), $schedulingEvent), 403);

        return $this->respond($schedulingEvent->programme_path, $schedulingEvent->name.' programme');
    }

    public function messageAttachment(Request $request, EventMessage $message): StreamedResponse
    {
        $message->loadMissing('schedulingEvent');
        abort_unless($request->user()->canAccessAdmin() || $this->crewHasEvent($request->user(), $message->schedulingEvent), 403);

        return $this->respond($message->attachment_path, $message->attachment_name);
    }

    private function crewHasEvent(User $user, SchedulingEvent $event): bool
    {
        if (! $user->canAccessCrew()) {
            return false;
        }

        return $event->shifts()->whereHas('assignments', fn ($query) => $query
            ->where('crew_profile_id', $user->crewProfile->id)
            ->where('status', 'published'))->exists();
    }

    private function crewHasVenue(User $user, Venue $venue): bool
    {
        if (! $user->canAccessCrew()) {
            return false;
        }

        return $venue->schedulingEvents()->whereHas('shifts.assignments', fn ($query) => $query
            ->where('crew_profile_id', $user->crewProfile->id)
            ->where('status', 'published'))->exists();
    }

    private function respond(?string $path, ?string $name): StreamedResponse
    {
        abort_if(blank($path) || ! $this->files->disk()->exists($path), 404);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $filename = trim((string) $name) ?: basename($path);
        if ($extension !== '' && ! str_ends_with(strtolower($filename), '.'.strtolower($extension))) {
            $filename .= '.'.$extension;
        }

        return $this->files->disk()->response($path, $filename, [], 'inline');
    }
}
