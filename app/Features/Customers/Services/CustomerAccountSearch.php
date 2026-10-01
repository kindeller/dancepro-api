<?php

namespace App\Features\Customers\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class CustomerAccountSearch
{
    /** @return Collection<int, array{id: int, uuid: string, name: string, email: string, phone: ?string}> */
    public function search(string $term, int $limit = 8): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        return User::query()
            ->where('type', 'customer')
            ->where('is_active', true)
            ->whereNull('merged_into_user_id')
            ->where(function ($query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')->orWhere('uuid', 'like', $term.'%');
            })
            ->orderByRaw('CASE WHEN email = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [$term, $term.'%'])
            ->orderBy('name')
            ->limit($limit)
            ->with('customerProfile')
            ->get(['id', 'uuid', 'name', 'email'])
            ->map(fn (User $customer): array => [
                'id' => $customer->id,
                'uuid' => $customer->uuid,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->customerProfile?->phone,
            ]);
    }
}
