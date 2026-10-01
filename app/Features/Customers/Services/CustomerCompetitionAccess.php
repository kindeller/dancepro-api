<?php

namespace App\Features\Customers\Services;

use App\Features\Families\Services\FamilyGalleryAccess;
use App\Features\Programmes\Services\CompetitionGallerySettings;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;

class CustomerCompetitionAccess
{
    public function allows(User $user, SchedulingEvent $event): bool
    {
        return $user->canAccessCustomer() && $event->event_type->value === 'competition' && $event->cancelled_at === null
            && app(FamilyGalleryAccess::class)->competitions($user)->where('scheduling_event_id', $event->id)->whereNotNull('access_verified_at')->exists();
    }

    public function events(User $user): array
    {
        abort_unless($user->canAccessCustomer(), 403);
        $registrations = app(FamilyGalleryAccess::class)->competitions($user)->whereNotNull('access_verified_at')
            ->whereHas('event', fn ($query) => $query->where('event_type', 'competition')->whereNull('cancelled_at'))
            ->with('event.competitionContact')->orderByDesc('id')->paginate(24)->withQueryString();
        $events = $registrations->getCollection()->pluck('event');
        $presentation = app(CompetitionGallerySettings::class)->forEvents($events);

        $availableEvents = SchedulingEvent::query()->where('event_type', 'competition')->whereNull('cancelled_at')
            ->orderByDesc('event_date')->orderBy('name')->get(['id', 'uuid', 'name', 'event_date']);

        return compact('registrations', 'presentation', 'availableEvents');
    }
}
