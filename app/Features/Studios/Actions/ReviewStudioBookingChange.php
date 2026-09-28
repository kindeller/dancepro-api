<?php

namespace App\Features\Studios\Actions;

use App\Features\Studios\Models\StudioBookingChangeRequest;
use App\Features\Tracking\Models\ConcertTrackerAlert;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewStudioBookingChange
{
    public function execute(StudioBookingChangeRequest $change, User $reviewedBy, string $status): void
    {
        DB::transaction(function () use ($change, $reviewedBy, $status): void {
            $change = StudioBookingChangeRequest::lockForUpdate()->findOrFail($change->id);
            if ($change->status === 'completed' || $status === 'completed') {
                throw ValidationException::withMessages(['status' => 'Complete a request by applying the reviewed changes in the booking editor.']);
            }
            $resolved = in_array($status, ['completed', 'declined'], true);
            $change->update([
                'status' => $status,
                'resolved_by_user_id' => $resolved ? $reviewedBy->id : null,
                'resolved_at' => $resolved ? now() : null,
            ]);

            if ($resolved) {
                ConcertTrackerAlert::query()->where('dedupe_key', 'like', 'studio-booking-change:'.$change->id.':%')
                    ->whereNull('resolved_at')->update(['resolved_at' => now()]);
            }
        });
    }
}
