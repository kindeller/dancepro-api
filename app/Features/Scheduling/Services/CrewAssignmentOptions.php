<?php

namespace App\Features\Scheduling\Services;

use App\Features\Crew\Models\CrewRole;
use Illuminate\Support\Collection;

class CrewAssignmentOptions
{
    public function forRole(Collection $crew, CrewRole $role, Collection $responses): array
    {
        $options = $crew->map(function ($profile) use ($role, $responses): array {
            $trained = $profile->roles->contains(fn ($qualification) => $qualification->id === $role->id && $qualification->pivot->status->value === 'approved');
            $available = $responses->get($profile->id)?->status->value === 'available';

            return ['profile' => $profile, 'name' => $profile->preferred_name ?: $profile->user->name, 'recommended' => $trained && $available];
        })->sortBy(fn ($option) => mb_strtolower($option['name']))->values();

        return [
            'recommended' => $options->where('recommended', true)->values(),
            'other' => $options->where('recommended', false)->values(),
        ];
    }
}
