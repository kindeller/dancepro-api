<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\DB;

class PhotoUploadProgress
{
    public function __construct(private readonly ProgrammeUploadRoles $photographers, private readonly PhotoContributionScope $scope, private readonly SharedConcertProgramme $shared) {}

    public function forEvent(SchedulingEvent $event, string $planUuid): array
    {
        return DB::transaction(function () use ($event, $planUuid): array {
            $event = SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $programme = Programme::where('scheduling_event_id', $event->id)->lockForUpdate()->firstOrFail();
            $plan = ProgrammeUploadPlan::where('programme_id', $programme->id)->where('uuid', $planUuid)->firstOrFail();
            $roster = $this->photographers->forEvent($event);
            $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
            $current = $programme->revisions()->orderByDesc('version')->value('id') === $plan->programme_revision_id
                && (! $gallery || $gallery->programme_upload_plan_id === $plan->id)
                && ($plan->snapshot['event_type'] !== 'concert' || $this->shared->current($programme)?->id === $plan->id);
            $contributions = ProgrammePhotoContribution::with('role')->where('programme_upload_plan_id', $plan->id)->get();
            $files = ProgrammePhotoFile::whereIn('programme_photo_contribution_id', $contributions->modelKeys())
                ->whereNull('duplicate_of_file_id')->select('programme_photo_contribution_id')->selectRaw('COUNT(*) AS total')
                ->selectRaw("SUM(CASE WHEN status = 'verified' AND verified_at IS NOT NULL AND storage_key IS NOT NULL THEN 1 ELSE 0 END) AS received")
                ->groupBy('programme_photo_contribution_id')->get()->keyBy('programme_photo_contribution_id');
            $people = [];
            foreach ($roster['roles'] as $person) {
                if ($person['media_type'] !== 'photo') {
                    continue;
                }
                $expected = $this->scope->items($plan, $person);
                $own = $contributions->filter(fn ($c) => $c->role->uuid === $person['role_uuid'])->keyBy('item_uuid');
                $complete = $verified = $pending = $waived = 0;
                foreach ($expected as $item => $assignments) {
                    $contribution = $own->get($item);
                    $itemFiles = $files->get($contribution?->id);
                    $received = (int) ($itemFiles?->received ?? 0);
                    $remaining = (int) ($itemFiles?->total ?? 0) - $received;
                    $verified += $received;
                    $pending += $remaining;
                    if ($current && $contribution?->status === 'waived' && $contribution->waived_at !== null) {
                        $waived++;
                    }
                    if ($current && $contribution?->status === 'complete' && $contribution->completed_at !== null
                        && $remaining === 0) {
                        $complete++;
                    }
                }
                $count = count($expected);
                $status = match (true) {
                    ! $current => 'needs_attention', $count === 0 => 'no_items', $complete === $count => 'complete',
                    $waived === $count => 'waived', $complete + $waived === $count => 'complete_with_exceptions',
                    $verified + $pending + $complete + $waived > 0 => 'receiving', default => 'missing',
                };
                $uploaders = DB::table('programme_photo_files as files')->leftJoin('users', 'users.id', '=', 'files.uploaded_by_user_id')
                    ->whereIn('files.programme_photo_contribution_id', $own->whereIn('item_uuid', array_keys($expected))->pluck('id'))
                    ->where('files.status', 'verified')->whereNotNull('files.verified_at')
                    ->select('users.name')->selectRaw('COUNT(*) AS submissions')
                    ->selectRaw('SUM(CASE WHEN files.duplicate_of_file_id IS NOT NULL THEN 1 ELSE 0 END) AS duplicates')
                    ->groupBy('files.uploaded_by_user_id', 'users.name')->get()
                    ->map(fn ($row) => ['name' => $row->name ?? 'Earlier uploads (account not recorded)',
                        'submission_count' => (int) $row->submissions, 'duplicate_count' => (int) $row->duplicates])->all();
                $people[] = [
                    'id' => $person['id'], 'role_uuid' => $person['role_uuid'],
                    'name' => $person['name'], 'role_name' => $person['role_name'], 'status' => $status,
                    'expected_item_count' => $count, 'complete_item_count' => $complete, 'waived_item_count' => $waived,
                    'verified_file_count' => $verified, 'pending_file_count' => $pending, 'uploaders' => $uploaders,
                ];
            }

            return [
                'event_uuid' => $event->uuid, 'plan_uuid' => $plan->uuid, 'checked_at' => now()->toIso8601String(),
                'plan_current' => $current, 'visibility' => 'private', 'photographers' => $people,
                'unfilled' => $roster['unfilled'],
            ];
        });
    }
}
