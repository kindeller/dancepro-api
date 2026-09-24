<?php

namespace App\Features\Customers\Services;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Models\User;

class CustomerMobileCatalogue
{
    public function __construct(private readonly CustomerMedia $media) {}

    public function forUser(User $user): array
    {
        $data = $this->media->forUser($user);
        $studioIds = StudioAccountAccess::query()->where('user_id', $user->id)->where('status', 'active')->pluck('studio_id');
        $studioEvents = SchedulingEvent::query()->whereIn('studio_id', $studioIds)->where('event_type', 'concert')
            ->with(['studio', 'concertBookingItem', 'studioFiles' => fn ($files) => $files->where('is_current', true), 'roleRequirements.crewRole', 'roleSeats'])
            ->orderByDesc('event_date')->get();

        return [
            'dancers' => $data['dancers']->map(fn ($dancer) => ['uuid' => $dancer->uuid, 'name' => $dancer->name,
                'programme_names' => $dancer->programme_names ?? []])->values()->all(),
            'studios' => $data['studios']->map(fn ($studio) => ['uuid' => $studio->uuid, 'name' => $studio->name])->values()->all(),
            'available_competitions' => $data['availableEvents']->map(fn ($event) => $this->event($event, 'competition'))->all(),
            'concert_video_years' => $data['videoYears']->all(),
            'recommendations' => collect($data['recommendations'])->map(fn ($entries) => collect($entries)->map(function ($entry) {
                $event = $entry['event'];
                $routine = $entry['routine'];

                return ['event' => $this->event($event, 'competition'), 'item_uuid' => $routine['uuid'],
                    'title' => $routine['label'], 'section' => $routine['section'], 'day' => $routine['day'],
                    'registered' => $entry['registered'],
                    'thumbnail_url' => $entry['photo'] ? route('api.v1.customer.recommendations.thumbnail', [
                        'schedulingEvent' => $event, 'item' => $routine['uuid'], 'photo' => $entry['photo'],
                    ]) : $event->crewLogoUrl()];
            })->all())->all(),
            'purchases' => collect($data['purchases'])->map(fn ($entries) => collect($entries)->map(fn ($entry) => [
                'event' => $this->event($entry['event'], $entry['kind']), 'can_browse' => $entry['can_browse'],
            ])->all())->all(),
            'events' => collect($data['events'])->map(fn ($entry) => [
                'event' => $this->event($entry['event'], $entry['kind']), 'title' => $entry['title'],
                'gallery' => $entry['gallery'], 'photos' => $entry['photos'], 'video' => $entry['video'],
                'purchased' => $entry['purchased'],
            ])->all(),
            'studio_event_submissions' => $studioEvents->map(fn ($event): array => [
                'studio_uuid' => $event->studio?->uuid,
                'event_uuid' => $event->uuid,
                'name' => $event->concertBookingItem?->title ?: $event->name,
                'date' => $event->event_date?->toDateString(),
                'files' => $event->studioFiles->map(fn ($file): array => [
                    'uuid' => $file->uuid, 'kind' => $file->kind, 'name' => $file->original_name,
                    'size_bytes' => $file->size_bytes, 'status' => $file->status,
                ])->values()->all(),
                'roles' => $event->roleRequirements->filter(fn ($requirement): bool => str_contains($requirement->crewRole->code, 'concert-photographer') || str_contains($requirement->crewRole->code, 'videographer'))
                    ->map(fn ($requirement): array => [
                        'code' => $requirement->crewRole->code, 'name' => $requirement->crewRole->name,
                        'seats' => $event->roleSeats->firstWhere('crew_role_id', $requirement->crew_role_id)?->seats ?? [],
                    ])->values()->all(),
            ])->values()->all(),
        ];
    }

    private function event($event, string $kind): array
    {
        return ['uuid' => $event->uuid, 'name' => $event->name, 'kind' => $kind,
            'date' => $event->event_date?->toDateString(),
            'cover_url' => $kind === 'concert' ? ($event->coverImageForDisplay() ?: $event->studio?->logoUrl()) : $event->crewLogoUrl()];
    }
}
