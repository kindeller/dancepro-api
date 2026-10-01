<?php

namespace App\Features\Media\Services;

use App\Features\Media\Models\MediaAsset;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Programmes\Services\CompetitionProgrammeNodes;
use App\Features\Programmes\Services\UploadAudit;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTopPics
{
    public function __construct(private readonly CompetitionProgrammeNodes $nodes, private readonly UploadAudit $audit) {}

    // Restrict every view and download to verified, non-duplicate files in the current gallery plan.
    public function files(): Builder
    {
        return ProgrammePhotoFile::query()
            ->join('programme_photo_galleries as galleries', 'galleries.id', '=', 'programme_photo_files.programme_photo_gallery_id')
            ->join('programme_photo_contributions as contributions', 'contributions.id', '=', 'programme_photo_files.programme_photo_contribution_id')
            ->whereColumn('contributions.programme_upload_plan_id', 'galleries.programme_upload_plan_id')
            ->where('programme_photo_files.is_top_pic', true)
            ->where('programme_photo_files.status', 'verified')
            ->whereNotNull('programme_photo_files.verified_at')
            ->whereNotNull('programme_photo_files.storage_key')
            ->whereNull('programme_photo_files.duplicate_of_file_id');
    }

    public function counts(): Builder
    {
        return $this->files()->selectRaw('galleries.scheduling_event_id, COUNT(*) as top_pic_count,
            COALESCE(MAX(CASE WHEN programme_photo_files.id = galleries.top_pic_cover_photo_id
                THEN programme_photo_files.uuid END), MIN(programme_photo_files.uuid)) as cover_uuid')
            ->groupBy('galleries.scheduling_event_id');
    }

    public function chooseCover(SchedulingEvent $event, ?string $photoUuid, User $actor): void
    {
        abort_unless($event->event_type->value === 'concert', 404);
        DB::transaction(function () use ($event, $photoUuid, $actor): void {
            $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->lockForUpdate()->firstOrFail();
            $photo = $photoUuid ? $this->files()->where('galleries.id', $gallery->id)
                ->where('programme_photo_files.uuid', $photoUuid)->select('programme_photo_files.*')->firstOrFail() : null;
            $gallery->update(['top_pic_cover_photo_id' => $photo?->id]);
            if ($photo) {
                $this->audit->record($event, $photo->contribution->role->uuid, $actor, 'admin_top_pic_cover', $photo->uuid);
            }
        });
    }

    public function events(string $type): array
    {
        $counts = $this->counts();
        $events = SchedulingEvent::query()->joinSub($counts, 'top_pics', 'top_pics.scheduling_event_id', '=', 'scheduling_events.id')
            ->where('event_type', $type)->with('studio')
            ->select('scheduling_events.*', 'top_pics.top_pic_count', 'top_pics.cover_uuid')
            ->orderByDesc('event_date')->orderByDesc('scheduling_events.id')->paginate(24)->withQueryString();

        return compact('events', 'type');
    }

    public function album(SchedulingEvent $event): array
    {
        abort_unless(in_array($event->event_type->value, ['competition', 'concert'], true), 404);
        $event->load('studio', 'concertBookingItem.concert');
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
        $plan = $gallery ? ProgrammeUploadPlan::find($gallery->programme_upload_plan_id) : null;
        $nodes = collect($plan ? $this->nodes->nodes($plan) : [])->keyBy('uuid');
        $photos = $this->files()->where('galleries.scheduling_event_id', $event->id)
            ->select('programme_photo_files.*', 'contributions.item_uuid')
            ->orderBy('contributions.item_uuid')->orderBy('programme_photo_files.id')->paginate(48)->withQueryString();
        $concert = $event->concertBookingItem?->concert;
        $assets = $concert ? MediaAsset::whereHas('collection', fn (Builder $query) => $query->where('concert_id', $concert->id))
            ->where('status', 'available')->where('is_visible', true)->with('collection')
            ->whereIn('metadata->programme_photo_file_uuid', $photos->pluck('uuid'))->get()
            ->keyBy(fn (MediaAsset $asset) => $asset->metadata['programme_photo_file_uuid']) : collect();
        $photos->through(function (ProgrammePhotoFile $photo) use ($event, $nodes, $assets): array {
            $item = $nodes->get($photo->item_uuid, []);
            $section = $nodes->get($item['parent_uuid'] ?? '', []);
            $asset = $assets->get($photo->uuid);
            $competition = $event->event_type->value === 'competition';

            return [
                'file' => $photo,
                'item' => trim(($item['folder_number'] ?? $item['number'] ?? '').' '.($item['title'] ?? $photo->file_name)),
                'section' => $competition ? trim(($section['number'] ?? '').' '.($section['title'] ?? '')) : $event->name,
                'gallery_url' => $this->galleryUrl($event, $competition ? $photo->item_uuid : null).($asset ? '#album-'.$asset->collection->uuid : ''),
                'grant_kind' => $competition ? 'competition_photo' : 'concert_photo',
                'grant_uuid' => $competition ? $photo->uuid : $asset?->uuid,
                'grant_event_uuid' => $competition ? $event->uuid : $event->concertBookingItem?->concert?->uuid,
            ];
        });

        return ['event' => $event, 'photos' => $photos, 'type' => $event->event_type->value,
            'coverPhotoId' => $gallery?->top_pic_cover_photo_id];
    }

    public function galleryUrl(SchedulingEvent $event, ?string $folder = null): string
    {
        return route($event->event_type->value === 'competition' ? 'admin.competition-gallery.show' : 'admin.concert-photo-gallery.show',
            ['schedulingEvent' => $event, 'folder' => $folder]);
    }

    public function download(SchedulingEvent $event, string $uuid, User $actor): StreamedResponse
    {
        abort_unless(in_array($event->event_type->value, ['competition', 'concert'], true), 404);
        $file = $this->files()->where('galleries.scheduling_event_id', $event->id)
            ->where('programme_photo_files.uuid', $uuid)->select('programme_photo_files.*')->firstOrFail();
        abort_unless($file->storage_disk === 'local' && Storage::disk('local')->exists($file->storage_key), 404);
        $this->audit->record($event, $file->contribution->role->uuid, $actor, 'admin_top_pic_download', $file->uuid);

        return Storage::disk('local')->download($file->storage_key, $file->file_name, ['Cache-Control' => 'private, no-store']);
    }
}
