<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\StudioBookingDraft;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudioBookingDrafts
{
    public function __construct(private readonly StudioCommunicationHistory $access) {}

    public function find(User $user, Studio $studio): ?StudioBookingDraft
    {
        abort_unless($this->access->canView($user, $studio), 403);

        return StudioBookingDraft::where('studio_id', $studio->id)->first();
    }

    public function save(User $user, Studio $studio, array $data): void
    {
        abort_unless($this->access->canView($user, $studio), 403);
        DB::transaction(function () use ($user, $studio, $data): void {
            Studio::whereKey($studio->id)->lockForUpdate()->firstOrFail();
            $draft = StudioBookingDraft::where('studio_id', $studio->id)->first();
            if (($draft?->version ?? 0) !== (int) ($data['draft_version'] ?? 0) || ($draft && $draft->uuid !== ($data['draft_uuid'] ?? null))) {
                throw ValidationException::withMessages(['draft' => 'This draft was changed in another window. Reopen Continue booking to see the latest saved version before saving again.']);
            }
            StudioBookingDraft::updateOrCreate(['studio_id' => $studio->id], [
                'saved_by_user_id' => $user->id, 'version' => ($draft?->version ?? 0) + 1,
                'data' => collect($data)->except(['draft_uuid', 'draft_version'])->all(),
            ]);
        });
    }

    public function submitted(Studio $studio, array $data): void
    {
        // Preserve a newer draft saved from another window while this form was open.
        StudioBookingDraft::where('studio_id', $studio->id)->where('uuid', $data['draft_uuid'] ?? null)
            ->where('version', $data['draft_version'] ?? 0)->delete();
    }
}
