<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Families\Services\FamilyGalleryAccess;
use App\Features\Programmes\Services\CompetitionCustomerPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CustomerMediaReviewExamples
{
    public function __construct(private readonly CustomerCompetitionAccess $access, private readonly CompetitionCustomerPreview $preview) {}

    public function forUser(User $user): array
    {
        $result = ['photos' => [], 'videos' => []];
        if (! app()->environment('local')) {
            return $result;
        }

        foreach ($user->customerProfile?->preferences['media_review_examples'] ?? [] as $example) {
            if (($example['kind'] ?? null) === 'competition') {
                $entry = $this->competition($user, $example);
            } elseif (($example['kind'] ?? null) === 'concert') {
                $entry = $this->concert($user, $example);
            } else {
                continue;
            }
            if ($entry) {
                $result[$entry['kind'] === 'concert' ? 'videos' : 'photos'][] = $entry;
            }
        }

        return $result;
    }

    private function competition(User $user, array $example): ?array
    {
        $event = SchedulingEvent::where('uuid', $example['event_uuid'] ?? '')->first();
        if (! $event || ! $this->access->allows($user, $event)) {
            return null;
        }

        $items = [];
        $title = null;
        foreach ($example['item_uuids'] ?? [] as $itemUuid) {
            try {
                $album = $this->preview->browse($event, $itemUuid, 'customer.competitions.image');
            } catch (HttpExceptionInterface) {
                continue;
            }
            $title = $album['title'];
            foreach ($album['photos']?->items() ?? [] as $photo) {
                $items[$photo->uuid] = ['uuid' => $photo->uuid, 'type' => 'photo',
                    'thumbnail_url' => route('customer.competitions.image', ['schedulingEvent' => $event,
                        'photo' => $photo->uuid, 'size' => 'thumbnail']),
                    'preview_url' => route('customer.competitions.image', ['schedulingEvent' => $event, 'photo' => $photo->uuid])];
            }
        }
        if (! $items) {
            return null;
        }

        return ['event' => $event, 'kind' => 'competition', 'title' => $title ?? $event->name,
            'thumbnail_url' => route('customer.competitions.image', ['schedulingEvent' => $event, 'photo' => array_key_first($items), 'size' => 'cover']), 'can_browse' => true,
            'is_preview' => true, 'items' => array_values($items)];
    }

    private function concert(User $user, array $example): ?array
    {
        $concert = Concert::with('studio')->where('uuid', $example['event_uuid'] ?? '')->first();
        if (! $concert || ! $concert->isPubliclyAvailable()
            || ! app(FamilyGalleryAccess::class)->studios($user)->where('studio_id', $concert->studio_id)->whereNotNull('access_verified_at')->exists()) {
            return null;
        }

        $cover = $concert->coverImageForDisplay() ?: $concert->studio?->logoUrl();

        return ['event' => $concert, 'kind' => 'concert', 'title' => $concert->name,
            'thumbnail_url' => $cover, 'can_browse' => true, 'is_preview' => true,
            'items' => [['uuid' => $concert->uuid, 'type' => 'concert', 'thumbnail_url' => $cover]]];
    }
}
