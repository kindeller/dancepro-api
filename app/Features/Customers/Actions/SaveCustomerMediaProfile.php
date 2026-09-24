<?php

namespace App\Features\Customers\Actions;

use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SaveCustomerMediaProfile
{
    public function execute(User $user, array $input): void
    {
        abort_unless($user->canAccessCustomer(), 403);
        DB::transaction(function () use ($user, $input): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->canAccessCustomer(), 403);
            match ($input['action']) {
                'details' => $this->details($user, $input),
                'preferences' => $this->preferences($user, $input),
                'dancer', 'dancer_update' => $this->dancer($user, $input),
                'dancer_remove' => CustomerDancer::where('user_id', $user->id)->where('uuid', $input['dancer_uuid'])->firstOrFail()->delete(),
                'studio' => $this->studio($user, $input['studio_uuid'], $input['studio_gallery_code']),
                'studio_remove' => $this->removeStudio($user, $input['studio_uuid']),
            };
        });
    }

    private function details(User $user, array $input): void
    {
        $user->update(['name' => $input['name']]);
        $profile = $user->customerProfile()->firstOrNew();
        $profile->fill(['preferred_name' => $input['preferred_name'] ?? null, 'phone' => $input['phone'] ?? null]);
        $profile->save();
    }

    private function preferences(User $user, array $input): void
    {
        $profile = $user->customerProfile()->firstOrNew();
        $profile->preferences = array_merge($profile->preferences ?? [], [
            'media_ready_email' => (bool) $input['media_ready_email'],
            'expiry_reminders_email' => (bool) $input['expiry_reminders_email'],
        ]);
        $profile->marketing_consent_at = $input['marketing_email'] ? ($profile->marketing_consent_at ?? now()) : null;
        $profile->save();
    }

    private function dancer(User $user, array $input): void
    {
        $dancer = $input['action'] === 'dancer_update'
            ? CustomerDancer::where('user_id', $user->id)->where('uuid', $input['dancer_uuid'])->firstOrFail()
            : CustomerDancer::firstOrNew(['user_id' => $user->id, 'name' => $input['name']]);
        if (CustomerDancer::where('user_id', $user->id)->where('name', $input['name'])->when($dancer->exists, fn ($query) => $query->where('id', '!=', $dancer->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'This dancer is already in your profile.']);
        }
        $dancer->fill(['name' => $input['name'], 'relationship' => $input['relationship'] ?? $dancer->relationship ?? 'unspecified',
            'programme_names' => array_key_exists('programme_names', $input) ? ($input['programme_names'] ?? []) : ($dancer->programme_names ?? [])]);
        $dancer->save();
    }

    private function studio(User $user, string $uuid, string $code): void
    {
        $studio = Studio::where('uuid', $uuid)->where('status', 'active')->first();
        if (! $studio) {
            throw ValidationException::withMessages(['studio_uuid' => 'Choose an active studio from the list.']);
        }
        $studio = Studio::whereKey($studio->id)->lockForUpdate()->firstOrFail();
        if (! $studio->gallery_code_hash || ! Hash::check(strtoupper(trim($code)), $studio->gallery_code_hash)) {
            throw ValidationException::withMessages(['studio_gallery_code' => 'That studio gallery code was not recognised.']);
        }
        CustomerStudio::updateOrCreate(['user_id' => $user->id, 'studio_id' => $studio->id], ['access_verified_at' => now()]);
    }

    private function removeStudio(User $user, string $uuid): void
    {
        CustomerStudio::where('user_id', $user->id)->whereHas('studio', fn ($query) => $query->where('uuid', $uuid))->firstOrFail()->delete();
    }
}
