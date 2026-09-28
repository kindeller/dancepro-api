<?php

namespace App\Features\Tracking\Services;

use App\Features\Programmes\Models\ConcertVideoBatch;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConcertVideoReadiness
{
    public function page(SchedulingEvent $event): array
    {
        $batch = ConcertVideoBatch::with(['files.asset', 'completedBy'])->where('scheduling_event_id', $event->id)->latest('id')->first();
        $files = $batch?->files ?? collect();
        $studioFiles = $event->studioFiles()->where('kind', 'studio_video')->where('is_current', true)->get();
        $tasks = ConcertTrackerTask::where('scope_type', 'event')->where('scope_id', $event->id)->whereIn('task_key', ['concert_edited', 'quality_check'])->get()->keyBy('task_key');
        $editing = $tasks->get('concert_edited');
        $quality = app(ConcertQualityChecklist::class)->summary($tasks->get('quality_check'));
        $verified = $files->whereNotNull('verified_at')->count();
        $inclusions = $batch?->studio_inclusions ?? [];
        $missing = $studioFiles->filter(fn ($studioFile) => $studioFile->status !== 'approved'
            || ! $files->contains(fn ($file) => $file->uuid === ($inclusions[$studioFile->uuid]['file_uuid'] ?? null) && $file->verified_at));
        $blockers = [];
        if (! $batch) {
            $blockers[] = 'No concert video queue has been registered by the laptop uploader.';
        } elseif ($files->isEmpty()) {
            $blockers[] = 'The concert video queue is empty. Add the final video files in the laptop uploader.';
        } elseif ($verified < $files->count()) {
            $blockers[] = ($files->count() - $verified).' queued videos still need upload verification.';
        }
        if ($batch && ! $batch->completed_at) {
            $blockers[] = 'The uploader has not confirmed Finished Uploading.';
        }
        if (($editing?->status_override ?? $editing?->status) !== 'complete') {
            $blockers[] = 'Concert editing (3.2) is not complete.';
        }
        if (! $quality['complete']) {
            $blockers[] = 'Quality check (3.3) has '.$quality['checked'].' of 20 checks confirmed.';
        }
        if ($missing->isNotEmpty()) {
            $blockers[] = $missing->count().' studio videos/slideshows need approval and confirmation of their inclusion in the final exports.';
        }
        if (! $event->concertBookingItem?->concert) {
            $blockers[] = 'No concert video record is linked to this event.';
        }
        if ($batch?->completed_at && $files->contains(fn ($file) => ! $file->asset || $file->asset->status->value !== 'available')) {
            $blockers[] = 'One or more uploaded videos are no longer available.';
        }

        return compact('batch', 'files', 'studioFiles', 'inclusions', 'verified', 'blockers') + [
            'queued' => $files->count(), 'percent' => $files->isEmpty() ? 0 : (int) floor(100 * $verified / $files->count()),
            'ready' => $batch && $files->isNotEmpty() && ! $blockers,
        ];
    }

    public function status(SchedulingEvent $event): array
    {
        $data = $this->page($event);

        return ['key' => $data['ready'] ? 'complete' : ($data['batch'] ? 'in_progress' : 'waiting'),
            'label' => $data['ready'] ? 'Ready for studio' : ($data['queued'] ? $data['verified'].' / '.$data['queued'].' verified' : 'Waiting for uploads'),
            'detail' => $data['ready'] ? 'All queued videos verified, Finished Uploading recorded, editing and quality checks complete, studio media accounted for.' : implode(' ', $data['blockers']),
            'sourceUrl' => route('admin.concert-tracker.tasks.show', [$event, 'video_uploaded']),
            'completedAt' => $data['ready'] ? $data['batch']->completed_at : null,
            'activity' => $data['batch']?->completed_at ? 'Finished uploading '.$data['batch']->completed_at->format('j M Y, g:i a').' · '.($data['batch']->completedBy?->name ?? 'Former staff member') : null];
    }

    public function saveInclusions(SchedulingEvent $event, User $actor, string $batchUuid, array $input): void
    {
        DB::transaction(function () use ($event, $actor, $batchUuid, $input): void {
            SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $batch = ConcertVideoBatch::where('scheduling_event_id', $event->id)->latest('id')->lockForUpdate()->firstOrFail();
            abort_unless($batch->uuid === $batchUuid, 409, 'The concert upload queue changed. Reload before confirming studio media.');
            $allowed = $batch->files()->whereNotNull('verified_at')->pluck('uuid')->all();
            $result = [];
            foreach ($event->studioFiles()->where('kind', 'studio_video')->where('is_current', true)->get() as $file) {
                $target = $input[$file->uuid] ?? null;
                if (! $target) {
                    continue;
                }
                abort_unless($file->status === 'approved' && in_array($target, $allowed, true), 422, 'Choose a verified export for each approved studio file.');
                $result[$file->uuid] = ['file_uuid' => $target, 'user_id' => $actor->id, 'confirmed_at' => now()->toIso8601String()];
            }
            $batch->update(['studio_inclusions' => $result]);
        });
    }
}
