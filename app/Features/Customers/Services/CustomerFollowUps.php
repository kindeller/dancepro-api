<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerAccountNote;
use Illuminate\Database\Eloquent\Builder;

class CustomerFollowUps
{
    public function build(array $filters): array
    {
        $scope = $filters['scope'] ?? 'due';

        return ['scope' => $scope, 'notes' => $this->query($scope)->with('user', 'createdBy')->paginate(30)->withQueryString()];
    }

    public function dueCount(): int
    {
        return $this->query('due')->count();
    }

    private function query(string $scope): Builder
    {
        $query = CustomerAccountNote::query()->whereNotNull('follow_up_at');
        if ($scope === 'completed') {
            $query->whereNotNull('completed_at')->latest('completed_at');
        } else {
            $query->whereNull('completed_at')->where('follow_up_at', $scope === 'upcoming' ? '>' : '<=', now())->orderBy('follow_up_at');
        }

        return $query;
    }
}
