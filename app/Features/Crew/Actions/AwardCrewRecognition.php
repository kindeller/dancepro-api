<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewRecognition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AwardCrewRecognition
{
    public function execute(array $data, int $awardedByUserId): Collection
    {
        return DB::transaction(function () use ($data, $awardedByUserId): Collection {
            return collect($data['crew_profile_ids'])->map(fn (int|string $crewProfileId): CrewRecognition => CrewRecognition::query()->create([
                'recognition_type_id' => $data['recognition_type_id'] ?? null,
                'crew_profile_id' => (int) $crewProfileId,
                'scheduling_event_id' => $data['scheduling_event_id'] ?? null,
                'awarded_by_user_id' => $awardedByUserId,
                'title' => $data['title'],
                'message' => $data['message'],
                'icon' => $data['icon'],
                'design' => $data['design'],
                'awarded_on' => $data['awarded_on'],
                'show_on_profile' => $data['show_on_profile'],
            ]));
        });
    }
}
