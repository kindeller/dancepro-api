<?php

namespace App\Features\Programmes\Actions;

use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Services\PhotoUploadContext;
use App\Features\Programmes\Services\UploadAudit;
use App\Features\Programmes\Services\VerifyPhotoBytes;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReceiveProgrammePhoto
{
    public function __construct(private readonly PhotoUploadContext $context, private readonly VerifyPhotoBytes $bytes) {}

    public function execute(SchedulingEvent $event, ProgrammePhotoFile $file, User $user, mixed $input): array
    {
        $this->context->validate($event, $file, $user);
        $temp = $this->bytes->copyAndVerify($input, $file);
        try {
            return DB::transaction(function () use ($event, $file, $user, $temp): array {
                // Recheck after potentially slow network receipt; retain the event
                // and programme locks until the verified file record is committed.
                $context = $this->context->validate($event, $file, $user);
                $file = ProgrammePhotoFile::whereKey($file->id)->lockForUpdate()->firstOrFail();
                $gallery = ProgrammePhotoGallery::firstOrCreate(['scheduling_event_id' => $event->id], [
                    'programme_upload_plan_id' => $context['plan_id'], 'visibility' => 'private',
                ]);
                abort_unless($gallery->programme_upload_plan_id === $context['plan_id'], 409,
                    'This event already has uploads for a different working programme. Use the same working programme before uploading from another Mac. Existing photos have been kept.');
                // A stable client photo may not change bytes or item after receipt.
                $existing = ProgrammePhotoFile::where('programme_photo_gallery_id', $gallery->id)
                    ->where('client_photo_uuid', $file->client_photo_uuid)->where('status', 'verified')->where('id', '!=', $file->id)
                    ->where(fn ($query) => $query->where('programme_photo_contribution_id', '!=', $file->programme_photo_contribution_id)
                        ->orWhere('sha256', '!=', $file->sha256)->orWhere('byte_count', '!=', $file->byte_count))->exists();
                abort_if($existing, 409, 'This photo was already uploaded with different bytes or a different item. Administrative correction is required.');
                $duplicate = ProgrammePhotoFile::where('programme_photo_contribution_id', $file->programme_photo_contribution_id)
                    ->where('sha256', $file->sha256)->where('byte_count', $file->byte_count)
                    ->where('status', 'verified')->whereNull('duplicate_of_file_id')->where('id', '!=', $file->id)->first();
                $key = $file->storage_key ?? $duplicate?->storage_key ?? 'programme-photos/'.$event->uuid.'/'.$gallery->uuid.'/'.$file->uuid.'.jpg';
                $disk = Storage::disk('local');
                rewind($temp);
                if (! $duplicate) {
                    abort_unless($disk->put($key, $temp, ['visibility' => 'private']), 503, 'Unable to save the photo. Retry it.');
                }
                $stored = $disk->readStream($key);
                abort_unless(is_resource($stored), 503, 'Unable to verify the saved photo.');
                try {
                    $hash = hash_init('sha256');
                    $count = hash_update_stream($hash, $stored);
                    abort_unless($count === $file->byte_count && hash_equals($file->sha256, hash_final($hash)), 503, 'Saved photo verification failed. Retry it.');
                } finally {
                    fclose($stored);
                }
                $firstReceipt = $file->status !== 'verified';
                $file->update([
                    'programme_photo_gallery_id' => $gallery->id, 'storage_disk' => 'local', 'storage_key' => $key,
                    'status' => 'verified', 'verified_at' => $file->verified_at ?? now(),
                    'uploaded_by_user_id' => $firstReceipt ? $user->id : $file->uploaded_by_user_id,
                    'duplicate_of_file_id' => $duplicate?->id ?? $file->duplicate_of_file_id,
                ]);

                if ($firstReceipt) {
                    app(UploadAudit::class)->record($event, $context['role_uuid'], $user, 'photo_verified', $file->uuid, ['duplicate_of' => $duplicate?->uuid]);
                }

                $contribution = ProgrammePhotoContribution::findOrFail($file->programme_photo_contribution_id);
                if ($firstReceipt && $contribution->status === 'waived') {
                    app(UploadAudit::class)->record($event, $context['role_uuid'], $user, 'waiver_reopened', $file->uuid, ['reason' => 'Previously reserved photo received', 'item_uuid' => $context['item_uuid']]);
                    $contribution->update(['status' => 'receiving', 'waiver_reason' => null, 'waived_at' => null, 'waived_by_user_id' => null]);
                } elseif ($contribution->status === 'awaiting_files') {
                    $contribution->update(['status' => 'receiving']);
                }

                return [
                    'uuid' => $file->uuid, 'photo_uuid' => $file->client_photo_uuid,
                    'event_uuid' => $event->uuid, 'plan_uuid' => $context['plan_uuid'],
                    'gallery_uuid' => $gallery->uuid, 'item_uuid' => $context['item_uuid'],
                    'contribution_uuid' => $context['contribution_uuid'],
                    'sha256' => $file->sha256, 'byte_count' => $file->byte_count,
                    'status' => 'verified', 'visibility' => 'private',
                ];
            });
        } finally {
            fclose($temp);
        }
    }
}
