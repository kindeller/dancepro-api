<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\Programme;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListProgrammeEvents
{
    public function execute(?string $search, ?string $from, int $perPage, ?User $user = null): LengthAwarePaginator
    {
        return Programme::query()
            ->select('programmes.*')
            ->join('scheduling_events', 'scheduling_events.id', '=', 'programmes.scheduling_event_id')
            ->whereNull('scheduling_events.cancelled_at')
            ->whereHas('revisions')
            ->when($user && ! $user->canAccessAdmin(), fn ($query) => $query->where(fn ($access) => $access
                ->whereHas('event.shifts.assignments', fn ($assignments) => $assignments->where('crew_profile_id', $user->crewProfile?->id)->where('status', 'published'))
                ->orWhereIn('scheduling_events.id', ConcertTrackerTask::where('scope_type', 'event')->where('task_key', 'concert_edited')->where('assignee_user_id', $user->id)->select('scope_id'))))
            ->when($search, fn ($query) => $query->where(fn ($matches) => $matches
                ->where('scheduling_events.name', 'like', '%'.$search.'%')
                ->orWhereHas('event.studio', fn ($studio) => $studio->where('name', 'like', '%'.$search.'%'))
                ->orWhereHas('event.concertBookingItem', fn ($item) => $item->where('title', 'like', '%'.$search.'%'))))
            ->when($from, fn ($query) => $query->whereDate('scheduling_events.event_date', '>=', $from))
            ->with(['event.studio', 'event.competitionContact', 'event.concertBookingItem', 'latestRevision'])
            ->orderBy('scheduling_events.event_date')->orderBy('programmes.id')
            ->paginate($perPage);
    }

    public function data(Programme $programme): array
    {
        $revision = $programme->latestRevision;
        $items = collect($revision->snapshot['nodes'])->where('kind', 'item')
            ->reject(fn ($node) => ($node['item_kind'] ?? 'dance') === 'break');
        $ready = $programme->event->event_type->value !== 'concert' || $items->every(fn ($node) => filled($node['folder_number'] ?? null) && filled($node['folder_title'] ?? null));

        return [
            'uuid' => $programme->event->uuid,
            'name' => $programme->event->name,
            'studio_name' => $programme->event->studio?->name,
            'event_title' => filled($programme->event->concertBookingItem?->title) ? $programme->event->concertBookingItem->title : (($revision->snapshot['capture_mode'] ?? null) === 'dress_rehearsal' ? 'Dress Rehearsal Portraits' : $programme->event->name),
            'capture_mode' => $revision->snapshot['capture_mode'] ?? null,
            'event_type' => $programme->event->event_type->value,
            'event_date' => $programme->event->event_date?->format('Y-m-d'),
            'event_code' => $programme->event->eventCode(),
            'revision_uuid' => $revision->uuid,
            'confirmed_at' => $revision->confirmed_at->toIso8601String(),
            'item_count' => $items->count(),
            'has_pdf' => $revision->programme_source_id !== null,
            'preparation_ready' => $ready,
        ];
    }
}
