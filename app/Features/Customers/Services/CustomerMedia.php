<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Payments\Services\CustomerCredits;
use App\Models\User;

class CustomerMedia
{
    public function __construct(private readonly CustomerCompetitionAccess $competitions, private readonly CustomerMediaProfile $profile, private readonly CustomerRecommendedMedia $recommendations, private readonly CustomerPurchasedMedia $purchases, private readonly CustomerMediaReviewExamples $reviewExamples, private readonly CustomerEvents $events, private readonly CustomerCredits $credits) {}

    public function forUser(User $user): array
    {
        abort_unless($user->canAccessCustomer(), 403);
        $data = $this->competitions->events($user);
        $profile = $this->profile->forUser($user);
        $purchases = $this->purchases->forUser($user);
        $examples = $this->reviewExamples->forUser($user);
        $mediaLibrary = [];
        foreach (['photos', 'videos'] as $type) {
            $mediaLibrary[$type] = collect([...$purchases[$type], ...$examples[$type]])
                ->unique(fn (array $entry) => $entry['kind'].':'.$entry['event']->id)->values()->all();
        }
        $videoYears = Concert::whereIn('studio_id', $profile['studios']->pluck('id'))
            ->where('status', 'published')->where('is_enabled', true)->whereNotNull('event_date')->get(['event_date'])
            ->map(fn ($concert) => $concert->event_date->year)->unique()->sortDesc()->values();

        return $profile + ['availableEvents' => $data['availableEvents'], 'videoYears' => $videoYears,
            'recommendations' => $this->recommendations->forUser($user), 'purchases' => $purchases,
            'mediaLibrary' => $mediaLibrary,
            'creditBalance' => $this->credits->balance($user),
            'events' => $this->events->forUser($user, $profile['studios'], $purchases)];
    }
}
