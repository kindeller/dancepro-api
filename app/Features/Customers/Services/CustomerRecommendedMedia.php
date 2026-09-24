<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;

class CustomerRecommendedMedia
{
    public function __construct(private readonly CustomerDancerRoutines $dancers, private readonly CustomerStudioRoutines $studios) {}

    public function forUser(User $user): array
    {
        $galleries = ProgrammePhotoGallery::all()->keyBy('scheduling_event_id');
        $events = SchedulingEvent::with('competitionContact')->whereIn('id', $galleries->keys())
            ->where('event_type', 'competition')->whereNull('cancelled_at')->orderByDesc('event_date')->get();
        $registered = CustomerCompetitionRegistration::where('user_id', $user->id)->whereNotNull('access_verified_at')
            ->whereIn('scheduling_event_id', $events->pluck('id'))->pluck('scheduling_event_id')->flip();
        $result = ['dancers' => [], 'studios' => []];

        foreach ($events as $event) {
            $gallery = $galleries->get($event->id);
            $groups = [
                'dancers' => $this->dancers->forEvent($user, $event)['routines'],
                'studios' => $this->studios->forEvent($user, $event)['routines'],
            ];
            $itemUuids = collect($groups)->flatten(1)->pluck('uuid')->unique();
            if ($itemUuids->isEmpty()) {
                continue;
            }
            $photos = ProgrammePhotoFile::inGallery($gallery)->whereIn('contributions.item_uuid', $itemUuids)
                ->addSelect('contributions.item_uuid')->orderBy('programme_photo_files.id')->get()->keyBy('item_uuid');

            foreach ($groups as $group => $routines) {
                foreach ($routines as $routine) {
                    $photo = $photos->get($routine['uuid']);
                    $result[$group][] = [
                        'event' => $event, 'routine' => $routine, 'photo' => $photo?->uuid,
                        'registered' => $registered->has($event->id),
                    ];
                }
            }
        }

        return $result;
    }

    public function allowsThumbnail(User $user, SchedulingEvent $event, string $itemUuid, string $photoUuid): bool
    {
        if (! $user->canAccessCustomer() || $event->event_type->value !== 'competition' || $event->cancelled_at !== null) {
            return false;
        }
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
        if (! $gallery || ! ProgrammePhotoFile::inGallery($gallery)->where('contributions.item_uuid', $itemUuid)
            ->where('programme_photo_files.uuid', $photoUuid)->exists()) {
            return false;
        }

        return collect($this->dancers->forEvent($user, $event)['routines'])->contains('uuid', $itemUuid)
            || collect($this->studios->forEvent($user, $event)['routines'])->contains('uuid', $itemUuid);
    }
}
