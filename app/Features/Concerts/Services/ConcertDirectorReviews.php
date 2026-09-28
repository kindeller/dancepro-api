<?php

namespace App\Features\Concerts\Services;

use App\Features\Concerts\Actions\ManageConcertCodeGroup;
use App\Features\Concerts\Models\ConcertDirectorReview;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Services\ConcertVideoReadiness;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConcertDirectorReviews
{
    public function canReview(User $user, SchedulingEvent $event): bool
    {
        if (! $user->is_active || ! $user->canAccessCustomer() || ! $user->hasVerifiedEmail() || ! $event->studio || $event->cancelled_at || $event->concertBookingItem?->cancelled_at) {
            return false;
        }
        $access = StudioAccountAccess::where('studio_id', $event->studio_id)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereRaw('lower(email) = ?', [mb_strtolower($user->email)]))->first();
        if ($access) {
            return $access->status !== 'revoked' && in_array($access->role, ['owner', 'director'], true)
                && app(StudioCommunicationHistory::class)->canView($user, $event->studio);
        }

        return strcasecmp($event->studio->contact_email ?? '', $user->email) === 0
            && app(StudioCommunicationHistory::class)->canView($user, $event->studio);
    }

    public function page(SchedulingEvent $event): array
    {
        $review = ConcertDirectorReview::firstOrNew(['scheduling_event_id' => $event->id], ['revision' => 0]);
        $uploads = app(ConcertVideoReadiness::class)->page($event);
        $checksReady = $review->corrections_completed_at && $review->corrections_uploaded_at
            && $review->corrected_batch_id === $uploads['batch']?->id;
        $currentMail = ConcertTrackerEmailDelivery::where('scheduling_event_id', $event->id)
            ->whereHas('task', fn ($q) => $q->where('task_key', 'director_corrections'))
            ->where('status', 'sent')->where('workflow_context->revision', $review->revision)
            ->where('workflow_context->batch_id', $uploads['batch']?->id)->latest('id')->first();

        return compact('review', 'uploads', 'checksReady', 'currentMail') + [
            'canRelease' => $uploads['ready'] && (! $review->revision || ($checksReady && $currentMail)),
            'released' => $review->released_at && $review->released_batch_id === $uploads['batch']?->id
                && $event->concertBookingItem?->concert?->isPubliclyAvailable(),
        ];
    }

    public function feedback(SchedulingEvent $event, User $actor, array $input): void
    {
        DB::transaction(function () use ($event, $actor, $input): void {
            $this->lockEvent($event);
            abort_unless($this->canReview($actor, $event), 403);
            $data = $this->page($event);
            $this->current($data, $input);
            abort_unless($data['uploads']['ready'] && ! $data['released'], 422, 'This concert is not currently open for review.');
            $review = $data['review'];
            $feedback = $review->feedback ?? [];
            $feedback[] = ['body' => trim($input['feedback']), 'user_id' => $actor->id, 'name' => $actor->name,
                'created_at' => now()->toIso8601String(), 'revision' => $review->revision + 1];
            $review->fill(['feedback' => $feedback, 'revision' => $review->revision + 1,
                'corrections_completed_at' => null, 'corrections_uploaded_at' => null,
                'completed_by_user_id' => null, 'uploaded_by_user_id' => null, 'corrected_batch_id' => null])->save();
            ConcertTrackerEmailDelivery::where('scheduling_event_id', $event->id)->whereNotNull('workflow_context')
                ->whereIn('status', ['queued', 'scheduled'])->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'New director feedback received.']);
            ConcertTrackerTask::where('scope_type', 'event')->where('scope_id', $event->id)->where('task_key', 'director_corrections')
                ->update(['status' => 'in_progress', 'status_override' => null, 'completed_at' => null, 'completed_by_user_id' => null]);
        });
    }

    public function checks(SchedulingEvent $event, User $actor, array $input): bool
    {
        return DB::transaction(function () use ($event, $actor, $input): bool {
            $this->lockEvent($event);
            $data = $this->page($event);
            $this->current($data, $input);
            $review = $data['review'];
            abort_unless($review->revision > 0 && ! $data['released'], 422, 'No open director corrections.');
            $complete = (bool) ($input['corrections_complete'] ?? false);
            $uploaded = (bool) ($input['corrections_uploaded'] ?? false);
            if ($uploaded && ! $data['uploads']['ready']) {
                throw ValidationException::withMessages(['corrections_uploaded' => 'Finish and verify the current video upload and quality checks first.']);
            }
            $review->fill([
                'corrections_completed_at' => $complete ? ($review->corrections_completed_at ?? now()) : null,
                'completed_by_user_id' => $complete ? ($review->completed_by_user_id ?? $actor->id) : null,
                'corrections_uploaded_at' => $uploaded ? ($review->corrections_uploaded_at ?? now()) : null,
                'uploaded_by_user_id' => $uploaded ? ($review->uploaded_by_user_id ?? $actor->id) : null,
                'corrected_batch_id' => $uploaded ? $data['uploads']['batch']->id : null,
            ])->save();

            return $complete && $uploaded;
        });
    }

    public function emailContext(SchedulingEvent $event, string $key): array
    {
        $data = $this->page($event);
        $blockers = $this->emailBlockers($data, $key);
        if ($blockers) {
            throw ValidationException::withMessages(['email' => $blockers]);
        }

        return ['revision' => (int) $data['review']->revision, 'batch_id' => $data['uploads']['batch']->id];
    }

    public function emailBlockers(array $data, string $key): array
    {
        if ($data['released']) {
            return ['The studio has already released this concert video.'];
        }
        $blockers = $data['uploads']['blockers'];
        if (! $data['uploads']['ready'] && ! $blockers) {
            $blockers[] = 'The current concert video must be ready for studio review.';
        }
        if ($key === 'director_corrections' && (! $data['review']->revision || ! $data['checksReady'])) {
            $blockers[] = 'Confirm corrections complete and corrections uploaded before sending.';
        }

        return $blockers;
    }

    public function deliveryCurrent(ConcertTrackerEmailDelivery $delivery): bool
    {
        try {
            $current = $this->emailContext($delivery->event, $delivery->task->task_key);

            return ($delivery->workflow_context['revision'] ?? null) === $current['revision']
                && ($delivery->workflow_context['batch_id'] ?? null) === $current['batch_id'];
        } catch (HttpException|ValidationException $exception) {
            return false;
        }
    }

    public function release(SchedulingEvent $event, User $actor, array $input): void
    {
        DB::transaction(function () use ($event, $actor, $input): void {
            $this->lockEvent($event);
            abort_unless($this->canReview($actor, $event), 403);
            $data = $this->page($event);
            $this->current($data, $input);
            if ($data['released']) {
                return;
            }
            abort_unless($data['canRelease'], 422, 'The current concert is not ready for release.');
            $concert = $event->concertBookingItem->concert;
            if (! $concert->requiresPassword()) {
                app(ManageConcertCodeGroup::class)->create($concert->studio, $actor, [
                    'year' => $concert->event_date->year, 'name' => $concert->name.' '.$concert->uuid,
                    'code' => Str::upper(Str::random(8)), 'concert_uuids' => [$concert->uuid],
                ]);
                $concert->refresh();
            }
            $assetIds = $data['uploads']['files']->pluck('media_asset_id');
            $concert->mediaCollections()->where('media_type', 'video')->update(['status' => 'draft', 'visibility' => 'private', 'published_at' => null]);
            foreach ($data['uploads']['files'] as $file) {
                $file->asset->collection->assets()->whereNotIn('id', $assetIds)->update(['is_visible' => false]);
                $file->asset->update(['is_visible' => true]);
                $file->asset->collection->update(['status' => 'published', 'visibility' => 'customer', 'published_at' => now()]);
            }
            $concert->update(['status' => 'published', 'is_enabled' => true, 'requires_approval' => true,
                'approved_at' => now(), 'approved_by_user_id' => $actor->id, 'published_at' => now(), 'available_from' => null]);
            if ($concert->codeGroup && ! $concert->codeGroup->released_at) {
                app(ManageConcertCodeGroup::class)->release($concert->codeGroup, $actor, ['version' => $concert->codeGroup->version]);
            }
            abort_unless($concert->fresh()->isPubliclyAvailable(), 422, 'Concert availability must be updated before release.');
            $data['review']->fill(['released_at' => now(), 'released_by_user_id' => $actor->id,
                'released_batch_id' => $data['uploads']['batch']->id])->save();
        });
    }

    public function video(SchedulingEvent $event, string $uuid)
    {
        $data = $this->page($event);
        abort_unless($data['uploads']['ready'] || $data['released'], 404);
        $file = $data['uploads']['files']->firstWhere('uuid', $uuid);
        abort_unless($file && $file->verified_at && $file->asset, 404);
        $disk = Storage::disk($file->storage_disk);
        if (config('filesystems.disks.'.$file->storage_disk.'.driver') === 'local') {
            abort_unless($disk->exists($file->storage_key), 404);

            return response()->file($disk->path($file->storage_key), ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store']);
        }

        return redirect()->away($disk->temporaryUrl($file->storage_key, now()->addMinutes(15), ['ResponseContentType' => 'video/mp4']))
            ->header('Cache-Control', 'private, no-store');
    }

    private function current(array $data, array $input): void
    {
        abort_unless((int) ($input['revision'] ?? -1) === (int) $data['review']->revision
            && ($input['batch_uuid'] ?? null) === $data['uploads']['batch']?->uuid, 409,
            'The review or video upload changed. Reload before continuing.');
    }

    private function lockEvent(SchedulingEvent $event): void
    {
        SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
        abort_if($event->fresh()->cancelled_at, 422, 'This event was cancelled.');
    }
}
