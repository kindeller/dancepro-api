<?php

namespace App\Features\Customers\Actions;

use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\CustomerAccountEmailChange;
use App\Features\Customers\Services\CustomerCommunicationPreferences;
use App\Features\Customers\Support\CustomerDancerNameMatcher;
use App\Features\Families\Services\CustomerFamilies;
use App\Features\Families\Services\FamilyAccess;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Services\StudioMemberships;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveCustomerMediaProfile
{
    public function __construct(private readonly CustomerDancerNameMatcher $nameMatcher, private readonly CustomerAccountEmailChange $emailChange) {}

    public function execute(User $user, array $input): void
    {
        abort_unless($user->canAccessCustomer(), 403);
        DB::transaction(function () use ($user, $input): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->canAccessCustomer(), 403);
            if (str_starts_with($input['action'], 'dancer')) {
                $member = app(FamilyAccess::class)->membership($user);
                abort_if($member && $member->family->manager_user_id !== $user->id, 403);
            }
            match ($input['action']) {
                'details' => $this->details($user, $input),
                'preferences' => $this->preferences($user, $input),
                'dancer', 'dancer_update' => $this->dancer($user, $input),
                'dancer_remove' => app(CustomerFamilies::class)->removeLegacyDancer($user, CustomerDancer::where('user_id', $user->id)->where('uuid', $input['dancer_uuid'])->firstOrFail()),
                'studio' => app(StudioMemberships::class)->add($user, $input['studio_uuid'], $input['studio_code']),
                'studio_request' => app(StudioMemberships::class)->submit($user, $input),
                'studio_gallery' => $this->studio($user, $input['studio_uuid'], $input['studio_gallery_code']),
                'studio_remove' => $this->removeStudio($user, $input['studio_uuid']),
            };
        });
    }

    private function details(User $user, array $input): void
    {
        $user->update(['name' => $input['name'], ...array_intersect_key($input, array_flip(['first_name', 'last_name']))]);
        $profile = $user->customerProfile()->firstOrNew();
        $profile->fill(['preferred_name' => $input['preferred_name'] ?? null, 'phone' => $input['phone'] ?? null]);
        $profile->save();
        $this->emailChange->request($user, $input['email']);
    }

    private function preferences(User $user, array $input): void
    {
        app(CustomerCommunicationPreferences::class)->save($user, $input);
    }

    private function dancer(User $user, array $input): void
    {
        $studioNames = Studio::where('status', 'active')->pluck('name')
            ->map(fn (string $name): string => $this->nameMatcher->canonical($name))->flip();
        $submittedNames = collect([$input['name'], ...($input['programme_names'] ?? [])]);
        if ($submittedNames->contains(fn (string $name): bool => $studioNames->has($this->nameMatcher->canonical($name)))) {
            throw ValidationException::withMessages([
                'name' => 'Enter an individual dancer name here. Add a dance studio under My studio instead.',
            ]);
        }

        $dancer = $input['action'] === 'dancer_update'
            ? CustomerDancer::where('user_id', $user->id)->where('uuid', $input['dancer_uuid'])->firstOrFail()
            : CustomerDancer::firstOrNew(['user_id' => $user->id, 'name' => $input['name']]);
        if (CustomerDancer::where('user_id', $user->id)->where('name', $input['name'])->when($dancer->exists, fn ($query) => $query->where('id', '!=', $dancer->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'This dancer is already in your profile.']);
        }
        $dancer->fill(['name' => $input['name'], 'relationship' => $input['relationship'] ?? $dancer->relationship ?? 'unspecified',
            'programme_names' => array_key_exists('programme_names', $input) ? ($input['programme_names'] ?? []) : ($dancer->programme_names ?? [])]);
        $dancer->save();
        app(CustomerFamilies::class)->syncLegacyDancer($user, $dancer);
    }

    private function studio(User $user, string $uuid, string $code): void
    {
        $studio = Studio::where('uuid', $uuid)->where('status', 'active')->first();
        if (! $studio) {
            throw ValidationException::withMessages(['studio_uuid' => 'Choose an active studio from the list.']);
        }
        $studio = Studio::whereKey($studio->id)->lockForUpdate()->firstOrFail();
        if (! $studio->matchesPhotoAccessCode($code)) {
            throw ValidationException::withMessages(['studio_gallery_code' => 'That studio gallery code was not recognised.']);
        }
        CustomerStudio::updateOrCreate(['user_id' => $user->id, 'studio_id' => $studio->id], ['access_verified_at' => now(), 'membership_confirmed_at' => now()]);
    }

    private function removeStudio(User $user, string $uuid): void
    {
        CustomerStudio::where('user_id', $user->id)->whereHas('studio', fn ($query) => $query->where('uuid', $uuid))->firstOrFail()->delete();
    }
}
