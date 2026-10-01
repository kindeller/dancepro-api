<?php

namespace App\Features\Programmes\Services;

use App\Features\Media\Models\MediaWatermark;
use App\Features\Programmes\Models\CompetitionGallerySetting;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CompetitionGallerySettings
{
    public function presentation(SchedulingEvent $event): array
    {
        $settings = CompetitionGallerySetting::where('scheduling_event_id', $event->id)->first();

        return $this->present($event, $settings);
    }

    public function forEvents(Collection $events): Collection
    {
        $settings = CompetitionGallerySetting::whereIn('scheduling_event_id', $events->pluck('id'))->get()->keyBy('scheduling_event_id');

        return $events->mapWithKeys(fn ($event) => [$event->uuid => $this->present($event, $settings->get($event->id))]);
    }

    private function present(SchedulingEvent $event, ?CompetitionGallerySetting $settings): array
    {
        $cover = $settings?->cover_photo_uuid ? $this->cover($event, $settings->cover_photo_uuid) : null;

        return ['title' => $settings?->display_title ?? $event->name, 'display_title' => $settings?->display_title,
            'login_code_configured' => $settings?->login_code_hash !== null,
            'watermark_uuid' => $settings?->watermark?->uuid,
            'watermark_name' => $settings?->watermark?->name ?? 'Default competition watermark',
            'cover_uuid' => $cover?->uuid, 'version' => $settings?->version ?? 0,
            'cover_url' => $cover ? route('admin.competition-gallery.image', ['schedulingEvent' => $event, 'photo' => $cover->uuid, 'size' => 'cover']) : $event->crewLogoUrl()];
    }

    public function editor(SchedulingEvent $event): array
    {
        abort_unless($event->event_type->value === 'competition', 404);
        $presentation = $this->presentation($event);
        $history = DB::table('competition_gallery_setting_changes as changes')->leftJoin('users', 'users.id', '=', 'changes.actor_user_id')
            ->where('scheduling_event_id', $event->id)->select('changes.*', 'users.name as actor_name')->orderByDesc('changes.id')->limit(10)->get();

        $watermarks = MediaWatermark::where('gallery_type', 'competition')->where('is_active', true)->orderBy('name')->get();

        return compact('event', 'presentation', 'history', 'watermarks');
    }

    public function save(SchedulingEvent $event, User $actor, array $input): void
    {
        DB::transaction(function () use ($event, $actor, $input): void {
            $event = SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless($event->event_type->value === 'competition' && $event->cancelled_at === null, 409);
            $settings = CompetitionGallerySetting::firstOrNew(['scheduling_event_id' => $event->id]);
            abort_unless((int) ($settings->version ?? 0) === (int) $input['version'], 409, 'Gallery settings changed in another window. Reload and try again.');
            $before = $settings->only(['display_title', 'cover_photo_uuid', 'media_watermark_id']);
            if ($input['action'] === 'login_code') {
                $settings->login_code_hash = Hash::make(strtoupper(trim($input['login_code'])));
            } elseif ($input['action'] === 'title') {
                $settings->display_title = $input['display_title'] ?? null;
            } elseif ($input['action'] === 'watermark') {
                $settings->media_watermark_id = empty($input['watermark_uuid']) ? null
                    : MediaWatermark::where('uuid', $input['watermark_uuid'])->where('gallery_type', 'competition')
                        ->where('is_active', true)->firstOrFail()->id;
            } elseif ($input['action'] === 'reset_cover') {
                $settings->cover_photo_uuid = null;
            } else {
                $photo = $this->cover($event, $input['photo_uuid']);
                if (! $photo) {
                    throw ValidationException::withMessages(['photo_uuid' => 'Choose a verified photo from this gallery.']);
                }
                $settings->cover_photo_uuid = $photo->uuid;
            }
            $after = $settings->only(['display_title', 'cover_photo_uuid', 'media_watermark_id']);
            if ($input['action'] === 'login_code') {
                // Audit the action, never the submitted code or its hash.
                $after['login_code_changed'] = true;
            }
            if ($before === $after) {
                return;
            }
            $settings->version = ($settings->version ?? 0) + 1;
            $settings->save();
            DB::table('competition_gallery_setting_changes')->insert(['scheduling_event_id' => $event->id, 'actor_user_id' => $actor->id,
                'before' => json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }

    private function cover(SchedulingEvent $event, string $uuid): ?ProgrammePhotoFile
    {
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();

        return $gallery ? ProgrammePhotoFile::inGallery($gallery)->where('programme_photo_files.uuid', $uuid)->first() : null;
    }
}
