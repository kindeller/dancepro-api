<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompetitionCustomerPreview
{
    public function __construct(
        private readonly CompetitionGallery $gallery,
        private readonly CompetitionSectionReadiness $readiness,
        private readonly CompetitionProgrammeNodes $nodes,
        private readonly CompetitionFolderCovers $folderCovers,
    ) {}

    public function browse(SchedulingEvent $event, ?string $folder, string $imageRoute = 'admin.competition-gallery.customer-preview.image'): array
    {
        $data = $this->gallery->browse($event, $folder, false);
        $ready = array_keys(array_filter($data['sectionReadiness'], fn ($section) => $section['ready']));
        $allowed = array_fill_keys($ready, true);
        foreach ($data['nodes'] as $node) {
            if ($node['kind'] === 'section' && isset($allowed[$node['uuid']])) {
                $allowed[$node['parent_uuid']] = true;
            }
            if ($node['kind'] === 'item' && in_array($node['parent_uuid'], $ready, true)) {
                $allowed[$node['uuid']] = true;
            }
        }
        abort_if($data['selected'] && ! isset($allowed[$data['selected']['uuid']]), 404);
        $children = array_values(array_filter($data['children'], fn ($node) => isset($allowed[$node['uuid']])));
        $children = $this->folderCovers->apply($children, $data['nodes'], $event, $allowed);
        $coverUrl = $event->crewLogoUrl();
        if ($data['selected'] && $data['presentation']['cover_uuid']) {
            $photoGallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
            $item = $photoGallery ? ProgrammePhotoFile::inGallery($photoGallery)
                ->where('programme_photo_files.uuid', $data['presentation']['cover_uuid'])->addSelect('contributions.item_uuid')->first()?->item_uuid : null;
            if ($item && isset($allowed[$item])) {
                $coverUrl = route($imageRoute, ['schedulingEvent' => $event,
                    'photo' => $data['presentation']['cover_uuid'], 'size' => 'cover']);
            }
        }

        // Explicit customer-view fields only; never pass contribution/audit records to this template.
        return ['event' => $event, 'title' => $data['presentation']['title'], 'eventCode' => $data['eventCode'],
            'selected' => $data['selected'], 'breadcrumbs' => $data['breadcrumbs'], 'children' => $children,
            'photos' => $data['photos'], 'coverUrl' => $coverUrl];
    }

    public function image(SchedulingEvent $event, string $uuid, string $size): StreamedResponse
    {
        abort_unless($event->event_type->value === 'competition' && $event->cancelled_at === null, 404);
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->firstOrFail();
        $plan = ProgrammeUploadPlan::findOrFail($gallery->programme_upload_plan_id);
        $file = ProgrammePhotoFile::inGallery($gallery)->where('programme_photo_files.uuid', $uuid)
            ->addSelect('contributions.item_uuid')->firstOrFail();
        $item = collect($this->nodes->nodes($plan))->firstWhere('uuid', $file->item_uuid);
        abort_unless($item && $item['kind'] === 'item' && ($item['item_kind'] ?? 'dance') !== 'break' && empty($item['merged_into_uuid']), 404);
        $sections = $this->readiness->forPlan($event, $plan);
        abort_unless($sections[$item['parent_uuid']]['ready'] ?? false, 404);

        return $this->gallery->image($event, $uuid, $size);
    }
}
