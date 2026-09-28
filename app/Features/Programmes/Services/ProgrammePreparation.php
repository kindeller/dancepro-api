<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeRevision;
use App\Features\Programmes\Models\ProgrammeSource;
use App\Features\Scheduling\Models\SchedulingEvent;

class ProgrammePreparation
{
    public function forEvent(SchedulingEvent $event): Programme
    {
        return Programme::where('scheduling_event_id', $event->id)->firstOrFail();
    }

    public function editor(SchedulingEvent $event): array
    {
        $programme = Programme::with('source.createdBy')->where('scheduling_event_id', $event->id)->first();

        return [
            'event' => $event, 'programme' => $programme, 'nodes' => $programme?->draftNodes() ?? [],
            'source' => $programme?->source,
            'extraction' => app(ProgrammeExtractionProgress::class)->forProgramme($programme),
            'revisions' => $programme?->revisions()->with(['confirmedBy', 'source'])->orderByDesc('version')->get() ?? collect(),
        ];
    }

    public function source(SchedulingEvent $event, ProgrammeSource $source): ProgrammeSource
    {
        abort_unless($source->programme_id === $this->forEvent($event)->id, 404);

        return $source;
    }

    public function revision(SchedulingEvent $event, ?ProgrammeRevision $revision = null): ProgrammeRevision
    {
        $programme = $this->forEvent($event);
        if ($revision) {
            abort_unless($revision->programme_id === $programme->id, 404);

            return $revision;
        }

        return $programme->revisions()->orderByDesc('version')->firstOrFail();
    }
}
