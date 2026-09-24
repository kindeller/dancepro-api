<?php

namespace App\Features\Programmes\Actions;

use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Services\UploadAudit;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReservePhotoFiles
{
    public function __construct(private readonly RegisterPhotoContributions $register) {}

    public function execute(SchedulingEvent $event, User $user, array $input): array
    {
        return DB::transaction(function () use ($event, $user, $input): array {
            // Reuse the live event/revision/assignment checks and programme lock.
            $registered = $this->register->execute($event, $user, $input + [
                'item_uuids' => array_values(array_unique(array_map(fn ($photo) => strtolower($photo['item_uuid']), $input['photos']))),
            ]);
            $links = collect($registered['contributions'])->keyBy('item_uuid');
            $contributions = ProgrammePhotoContribution::whereIn('uuid', $links->pluck('uuid'))->get()->keyBy('uuid');
            $files = [];
            foreach ($input['photos'] as $photo) {
                $item = strtolower($photo['item_uuid']);
                $contribution = $contributions[$links[$item]['uuid']];
                $file = ProgrammePhotoFile::firstOrCreate([
                    'programme_photo_contribution_id' => $contribution->id,
                    'client_photo_uuid' => strtolower($photo['photo_uuid']), 'sha256' => $photo['sha256'],
                ], [
                    'reservation_key' => hash('sha256', $contribution->id.':'.strtolower($photo['photo_uuid']).':'.$photo['sha256']),
                    'file_name' => $photo['file_name'], 'byte_count' => $photo['byte_count'],
                    'is_top_pic' => $photo['is_top_pic'], 'status' => 'reserved',
                ]);
                abort_unless($file->byte_count === (int) $photo['byte_count'], 409,
                    'A photo reservation has a different size for the same checksum. Recheck the local photo.');
                if ($file->status !== 'verified' && ($contribution->status === 'complete' || ($file->wasRecentlyCreated && $contribution->status === 'waived'))) {
                    if ($contribution->status === 'waived') {
                        app(UploadAudit::class)->record($event, $input['role_uuid'], $user, 'waiver_reopened', $file->uuid, ['reason' => 'New photo reserved', 'item_uuid' => $item]);
                    }
                    $contribution->update(['status' => 'receiving', 'completed_at' => null, 'completed_by_user_id' => null,
                        'waiver_reason' => null, 'waived_at' => null, 'waived_by_user_id' => null]);
                }
                // A changed favourite is metadata, not a different file version.
                if ($file->status === 'reserved' && $file->is_top_pic !== (bool) $photo['is_top_pic']) {
                    $file->update(['is_top_pic' => $photo['is_top_pic']]);
                }
                $files[] = [
                    'uuid' => $file->uuid, 'photo_uuid' => $file->client_photo_uuid,
                    'item_uuid' => $item, 'contribution_uuid' => $contribution->uuid,
                    'sha256' => $file->sha256, 'byte_count' => $file->byte_count, 'status' => $file->status,
                ];
            }
            unset($registered['contributions']);

            return $registered + ['files' => $files];
        });
    }
}
