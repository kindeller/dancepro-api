<?php

namespace App\Features\Families\Services;

use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Families\Models\CustomerFamilyMember;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class FamilyGalleryAccess
{
    public function __construct(private readonly FamilyAccess $families) {}

    /** Read access only: never copy registrations or grant Studio Hub roles. */
    public function ownerIds(User $user): array
    {
        $member = $this->families->membership($user);
        $manager = $member?->family?->manager;
        if (! $user->canAccessCustomer() || ! $user->hasVerifiedEmail()
            || ! $manager?->canAccessCustomer() || ! $manager->hasVerifiedEmail()) {
            return [$user->id];
        }

        return array_values(array_unique([$user->id, $manager->id]));
    }

    public function studios(User $user): Builder
    {
        return CustomerStudio::whereIn('user_id', $this->ownerIds($user))
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhereNotNull('access_verified_at')->orWhereNotNull('membership_confirmed_at'));
    }

    public function competitions(User $user): Builder
    {
        // A member may also have registered independently. Return each event once.
        $ids = CustomerCompetitionRegistration::whereIn('user_id', $this->ownerIds($user))
            ->whereNotNull('access_verified_at')->selectRaw('MIN(id)')->groupBy('scheduling_event_id');

        return CustomerCompetitionRegistration::whereIn('id', $ids);
    }

    public function studioViewers(int $studioId, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();
        $direct = CustomerStudio::where('studio_id', $studioId)->whereNotNull('access_verified_at')
            ->where('access_verified_at', '<=', $at)->select('user_id');
        $members = CustomerFamilyMember::where('status', 'active')->whereNotNull('user_id')->where('accepted_at', '<=', $at)
            ->whereHas('family', fn ($family) => $family->whereIn('manager_user_id', clone $direct)
                ->whereHas('manager', fn ($manager) => $manager->where('is_active', true)->whereNotNull('email_verified_at')
                    ->where('email_verified_at', '<=', $at)->where(fn ($roles) => $roles->whereIn('type', ['customer', 'crew', 'staff', 'admin'])->orWhere('is_admin', true))))
            ->select('user_id');

        return User::where('is_active', true)->whereNotNull('email_verified_at')->where('email_verified_at', '<=', $at)
            ->where('created_at', '<=', $at)
            ->where(fn ($users) => $users->whereIn('id', $direct)->orWhereIn('id', $members));
    }
}
