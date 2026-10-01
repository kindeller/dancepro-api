<?php

namespace App\Features\Auth\Services;

use App\Features\Customers\Support\UserType;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Models\User;
use Illuminate\Support\Collection;

class LocalAccountSwitcher
{
    /**
     * @return Collection<string, Collection<int, array{id: int, name: string, email: string, destination: string}>>
     */
    public function accounts(): Collection
    {
        if (! app()->environment(['local', 'testing'])) {
            return collect();
        }

        $users = User::query()
            ->with('crewProfile')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $studioUserIds = StudioAccountAccess::query()
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();
        $studioEmails = StudioAccountAccess::query()
            ->where('status', 'active')
            ->pluck('email')
            ->filter()
            ->map(fn (string $email): string => mb_strtolower($email))
            ->all();

        return $users
            ->map(function (User $user) use ($studioUserIds, $studioEmails): ?array {
                $role = $this->roleFor($user, $studioUserIds, $studioEmails);

                return $role ? [
                    'role' => $role,
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'destination' => $role === 'studio' ? 'customer.studio-hub' : $user->homeRouteName(),
                ] : null;
            })
            ->filter()
            ->groupBy('role')
            ->pipe(fn (Collection $accounts): Collection => collect(['customer', 'studio', 'staff', 'admin'])
                ->mapWithKeys(fn (string $role): array => [$role => $accounts->get($role, collect())]));
    }

    public function findAllowed(int $userId): ?array
    {
        return $this->accounts()->flatten(1)->firstWhere('id', $userId);
    }

    /** @param array<int, int|string> $studioUserIds @param array<int, string> $studioEmails */
    private function roleFor(User $user, array $studioUserIds, array $studioEmails): ?string
    {
        if ($user->canAccessAdmin()) {
            return 'admin';
        }

        if ($user->type === UserType::Customer->value) {
            return in_array($user->id, $studioUserIds, true)
                || in_array(mb_strtolower($user->email), $studioEmails, true)
                    ? 'studio'
                    : 'customer';
        }

        if ($user->canAccessCrew() || ($user->canAccessCustomer() && in_array($user->type, [UserType::Crew->value, UserType::Staff->value], true))) {
            return 'staff';
        }

        return null;
    }
}
