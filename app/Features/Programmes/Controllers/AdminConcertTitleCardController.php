<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Models\ConcertTitleCardTemplate;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Requests\SaveConcertTitleCardTemplateRequest;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminConcertTitleCardController extends Controller
{
    public function show(Request $request, SchedulingEvent $schedulingEvent): View
    {
        abort_unless($schedulingEvent->event_type === SchedulingEventType::Concert, 404);
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

        return view('admin.programmes.title-cards', [
            'event' => $schedulingEvent,
            'concertTitle' => $schedulingEvent->concertBookingItem?->title ?: $schedulingEvent->name,
            'template' => $template,
            'items' => $nodes,
            'eventLogoUrl' => $schedulingEvent->logoUrl(),
            'studioLogoUrl' => $schedulingEvent->studio?->logoUrl(),
        ]);
    }

    public function update(SaveConcertTitleCardTemplateRequest $request, SchedulingEvent $schedulingEvent): RedirectResponse
    {
        abort_unless($schedulingEvent->event_type === SchedulingEventType::Concert, 404);
        ConcertTitleCardTemplate::updateOrCreate(
            ['scheduling_event_id' => $schedulingEvent->id],
            $request->validated() + ['font_family' => 'Atlanta', 'updated_by_user_id' => $request->user()->id],
        );

        return back()->with('status', 'Title card template saved.');
    }
}
