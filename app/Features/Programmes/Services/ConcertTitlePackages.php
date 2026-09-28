<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\ConcertTitleCardPackage;
use App\Features\Programmes\Models\ConcertTitleCardTemplate;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeRevision;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Models\ConcertTrackerTask;

class ConcertTitlePackages
{
    public const SETTINGS = ['logo_source', 'background_color', 'logo_band_color', 'text_color', 'separator_start_color', 'separator_end_color'];

    public function fingerprint(SchedulingEvent $event, ProgrammeRevision $revision): string
    {
        return hash('sha256', json_encode([$revision->uuid, $revision->snapshot, $event->concertBookingItem?->title ?: $event->name, $event->logo_path, $event->studio?->logo_path], JSON_THROW_ON_ERROR));
    }

    public function matches(SchedulingEvent $event, ?ConcertTitleCardPackage $package, ?Programme $programme, ?ConcertTitleCardTemplate $template): bool
    {
        return ! $event->cancelled_at && $package && $programme?->latestRevision && $template
            && $package->programme_revision_id === $programme->latestRevision->id
            && $package->settings == $template->only(self::SETTINGS)
            && hash_equals($package->content_fingerprint, $this->fingerprint($event, $programme->latestRevision));
    }

    public function downloadable(SchedulingEvent $event): ?ConcertTitleCardPackage
    {
        $ready = ConcertTrackerTask::where('scope_type', 'event')->where('scope_id', $event->id)->where('task_key', 'video_titles')->where('status', 'complete')->exists();

        return $ready ? $this->current($event) : null;
    }

    public function current(SchedulingEvent $event): ?ConcertTitleCardPackage
    {
        $package = ConcertTitleCardPackage::where('scheduling_event_id', $event->id)->latest('id')->first();
        $programme = Programme::with('latestRevision')->where('scheduling_event_id', $event->id)->first();
        $template = ConcertTitleCardTemplate::where('scheduling_event_id', $event->id)->first();

        return $this->matches($event, $package, $programme, $template) ? $package : null;
    }
}
