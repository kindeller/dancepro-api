<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\ConcertTitleCardTemplate;
use App\Features\Programmes\Models\Programme;
use App\Features\Scheduling\Models\SchedulingEvent;

class ConcertTitleCards
{
    public function page(SchedulingEvent $schedulingEvent): array
    {
        $schedulingEvent->load(['studio', 'concertBookingItem']);
        $programme = Programme::query()->where('scheduling_event_id', $schedulingEvent->id)->with('latestRevision')->first();
        $nodes = collect($programme?->latestRevision?->snapshot['nodes'] ?? $programme?->draftNodes() ?? [])
            ->filter(fn (array $node): bool => ($node['kind'] ?? null) === 'item' && ($node['merged_into_uuid'] ?? null) === null)
            ->values();
        $template = ConcertTitleCardTemplate::firstOrNew(['scheduling_event_id' => $schedulingEvent->id], [
            'logo_source' => $schedulingEvent->logo_path ? 'event' : 'studio',
            'background_color' => '#000000',
            'logo_band_color' => $schedulingEvent->studio?->brand_color ?: '#0B438C',
            'text_color' => '#FFFFFF',
            'separator_start_color' => '#000000',
            'separator_end_color' => '#FFFFFF',
            'font_family' => 'Atlanta',
        ]);

        return [
            'event' => $schedulingEvent,
            'concertTitle' => $schedulingEvent->concertBookingItem?->title ?: $schedulingEvent->name,
            'template' => $template,
            'items' => $nodes,
            'titleRevision' => $programme?->latestRevision,
            'titleFingerprint' => $programme?->latestRevision ? app(ConcertTitlePackages::class)->fingerprint($schedulingEvent, $programme->latestRevision) : null,
            'eventLogoUrl' => $schedulingEvent->logoUrl(),
            'studioLogoUrl' => $schedulingEvent->studio?->logoUrl(),
        ];
    }
}
