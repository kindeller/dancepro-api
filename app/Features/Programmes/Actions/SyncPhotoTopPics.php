<?php

namespace App\Features\Programmes\Actions;

use App\Features\Media\Services\ConcertPhotoGallerySetup;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Policies\ProgrammePolicy;
use App\Features\Programmes\Services\UploadAudit;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SyncPhotoTopPics
{
    public function execute(SchedulingEvent $event, User $user, array $changes): array
    {
        return DB::transaction(function () use ($event, $user, $changes): array {
            $event = SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(ProgrammePolicy::class)->upload($user, $event), 403);
            $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->firstOrFail();
            $receipts = [];
            foreach ($changes as $change) {
                $file = ProgrammePhotoFile::where('uuid', strtolower($change['file_uuid']))
                    ->where('programme_photo_gallery_id', $gallery->id)
                    ->whereHas('contribution', fn ($query) => $query->where('programme_upload_plan_id', $gallery->programme_upload_plan_id))
                    ->where('status', 'verified')->whereNotNull('verified_at')->whereNotNull('storage_key')
                    ->lockForUpdate()->firstOrFail();
                // Replacement laptops can reference a deduplicated receipt. Update
                // its canonical gallery photo too, without copying or moving bytes.
                $target = $file->duplicate_of_file_id === null ? $file : ProgrammePhotoFile::whereKey($file->duplicate_of_file_id)
                    ->where('programme_photo_gallery_id', $gallery->id)
                    ->where('programme_photo_contribution_id', $file->programme_photo_contribution_id)
                    ->where('status', 'verified')->whereNull('duplicate_of_file_id')->lockForUpdate()->firstOrFail();
                $selected = (bool) $change['is_top_pic'];
                if ($target->is_top_pic !== $selected) {
                    $target->update(['is_top_pic' => $selected]);
                    app(UploadAudit::class)->record($event, $target->contribution->role->uuid, $user, 'top_pic_changed', $target->uuid,
                        ['is_top_pic' => $selected, 'source_file_uuid' => $file->uuid, 'change_uuid' => $change['change_uuid']]);
                }
                if ($target->id !== $file->id && $file->is_top_pic !== $selected) {
                    $file->update(['is_top_pic' => $selected]);
                }
                app(ConcertPhotoGallerySetup::class)->syncVerifiedPhoto($event, $target);
                $receipts[] = ['file_uuid' => $file->uuid, 'change_uuid' => strtolower($change['change_uuid']), 'is_top_pic' => $selected];
            }

            return ['event_uuid' => $event->uuid, 'changes' => $receipts];
        });
    }
}
