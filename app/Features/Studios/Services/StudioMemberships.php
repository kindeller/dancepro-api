<?php

namespace App\Features\Studios\Services;

use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Models\CustomerStudioRequest;
use App\Features\Studios\Actions\SaveStudio;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudioMemberships
{
    public function search(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        // Search names only: never publish either studio or private gallery codes.
        return Studio::where('status', 'active')->where('name', 'like', '%'.addcslashes($query, '%_\\').'%')
            ->orderBy('name')->limit(15)->get()->map(fn (Studio $studio) => [
                'uuid' => $studio->uuid, 'name' => $studio->name, 'logo' => $studio->logoUrl(),
            ])->all();
    }

    public function add(User $user, string $uuid, string $code): void
    {
        $studio = Studio::where('uuid', $uuid)->where('status', 'active')->lockForUpdate()->first();
        if (! $studio || ! $studio->matchesPhotoAccessCode($code)) {
            throw ValidationException::withMessages(['studio_code' => 'That studio code was not recognised. Ask your studio for its code, or submit it for review below if it does not have one.']);
        }
        $this->attach($user->id, $studio);
    }

    public function submit(User $user, array $input): void
    {
        // Caller locks the account, serialising duplicate submissions from that account.
        $name = preg_replace('/\s+/u', ' ', trim($input['studio_name']));
        $location = trim($input['studio_location'] ?? '');
        $key = hash('sha256', mb_strtolower($name.'|'.$location));
        $existing = CustomerStudioRequest::where('user_id', $user->id)->where('name_key', $key)->first();
        if ($existing && $existing->status !== 'declined') {
            if ($existing->status === 'approved' && $existing->studio?->status->value === 'active') {
                $this->attach($user->id, $existing->studio);
            }

            return;
        }
        CustomerStudioRequest::updateOrCreate(['user_id' => $user->id, 'name_key' => $key], [
            'name' => $name, 'location' => $location ?: null, 'status' => 'pending',
            'studio_id' => null, 'reviewed_by_user_id' => null, 'reviewed_at' => null, 'review_note' => null,
        ]);
    }

    public function reviewPage(CustomerStudioRequest $request): array
    {
        return ['submission' => $request->load('user', 'studio'),
            'studios' => Studio::where('status', 'active')->orderBy('name')->get(),
            'statuses' => StudioStatus::cases(),
            'defaults' => ['name' => $request->name, 'notes' => $request->location ? 'Customer supplied location: '.$request->location : null]];
    }

    public function queue()
    {
        return CustomerStudioRequest::with('user', 'studio')->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()->paginate(30);
    }

    public function review(CustomerStudioRequest $request, User $actor, array $input): void
    {
        DB::transaction(function () use ($request, $actor, $input): void {
            $request = CustomerStudioRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This request has already been reviewed. Refresh to see the outcome.']);
            }
            $studio = null;
            if ($input['decision'] === 'create') {
                $studio = app(SaveStudio::class)->execute(collect($input)->except(['decision', 'review_note', 'studio_uuid'])->all(), actor: $actor);
            } elseif ($input['decision'] === 'match') {
                $studio = Studio::where('uuid', $input['studio_uuid'])->where('status', 'active')->lockForUpdate()->firstOrFail();
            }
            if ($studio) {
                $this->attach($request->user_id, $studio);
            }
            $request->update(['status' => $studio ? 'approved' : 'declined', 'studio_id' => $studio?->id,
                'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now(), 'review_note' => $input['review_note'] ?? null]);
        });
    }

    private function attach(int $userId, Studio $studio): void
    {
        // Approved studio membership includes released concert photos, never Studio Hub roles.
        CustomerStudio::updateOrCreate(['user_id' => $userId, 'studio_id' => $studio->id], ['membership_confirmed_at' => now(), 'access_verified_at' => now()]);
    }
}
