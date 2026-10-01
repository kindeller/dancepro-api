<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Media\Services\MediaCommerceSettings;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;

class CustomerAccountMediaSelections
{
    public function __construct(
        private readonly CustomerCompetitionCart $competitionCart,
        private readonly CustomerConcertPhotoCart $concertCart,
        private readonly MediaCommerceSettings $settings,
    ) {}

    public function counts(User $user): array
    {
        $competitionSelections = CustomerMediaSelection::where('user_id', $user->id);
        $concertSelections = CustomerConcertPhotoSelection::where('user_id', $user->id);

        return [
            'favourites' => (clone $competitionSelections)->where('is_favourite', true)->count()
                + (clone $concertSelections)->where('is_favourite', true)->count(),
            'cart' => (clone $competitionSelections)->where('in_cart', true)->count()
                + (clone $concertSelections)->where('in_cart', true)->count(),
        ];
    }

    public function forUser(User $user, string $mode, ?string $focusKind = null, ?string $focusEvent = null): array
    {
        $flag = $mode === 'favourites' ? 'is_favourite' : 'in_cart';
        $groups = collect();

        $competitionEventIds = CustomerMediaSelection::query()->where('user_id', $user->id)
            ->where($flag, true)->distinct()->pluck('scheduling_event_id');
        foreach (SchedulingEvent::with('competitionContact')->whereIn('id', $competitionEventIds)->orderByDesc('event_date')->get() as $event) {
            $quote = $this->competitionCart->forEvent($user, $event, includeCards: true);
            $selections = $quote['selections']->where($flag, true)->values();
            if ($selections->isNotEmpty()) {
                $groups->push(['kind' => 'competition', 'event' => $event, 'title' => $event->name,
                    'date' => $event->event_date, 'logo' => $event->crewLogoUrl(), 'selections' => $selections,
                    'details' => $quote['selectionDetails'], 'lineAmounts' => $quote['lineAmounts'],
                    'subtotal' => $quote['subtotal'], 'discount' => $quote['discount'], 'total' => $quote['total'],
                    'bulk_eligible_subtotal' => $quote['bulk_eligible_subtotal'] ?? $quote['subtotal'],
                    'discount_percent' => $quote['discount_percent']]);
            }
        }

        $studioIds = CustomerConcertPhotoSelection::query()->where('user_id', $user->id)
            ->where($flag, true)->distinct()->pluck('studio_id');
        foreach (Studio::whereIn('id', $studioIds)->get() as $studio) {
            $quote = $this->concertCart->forStudio($user, $studio);
            $events = collect();
            foreach ($quote['selections']->where($flag, true)->groupBy('concert_id') as $selections) {
                $concert = $selections->first()->concert->loadMissing('bookingItem.schedulingEvent');
                $cartSelections = $selections->where('in_cart', true);
                $subtotal = $cartSelections->sum(fn ($selection) => $quote['lineAmounts'][$selection->id] ?? 0);
                $eligibleSubtotal = $quote['principalPrice'] ? 0 : $subtotal;
                $percent = $this->settings->discountPercent($eligibleSubtotal);
                $discount = intdiv($eligibleSubtotal * $percent, 100);
                $events->push(['kind' => 'concert', 'event' => $concert, 'studio' => $studio,
                    'title' => $concert->name, 'date' => $concert->event_date, 'logo' => $studio->logoUrl(),
                    'selections' => $selections->values(), 'lineAmounts' => $quote['lineAmounts'],
                    'subtotal' => $subtotal, 'discount' => $discount, 'total' => $subtotal - $discount,
                    'bulk_eligible_subtotal' => $eligibleSubtotal,
                    'discount_percent' => $percent]);
            }
            $events = $events->sort(function (array $left, array $right) use ($focusKind, $focusEvent): int {
                if ($focusKind === 'concert' && ($left['event']->uuid === $focusEvent || $right['event']->uuid === $focusEvent)) {
                    return $left['event']->uuid === $focusEvent ? -1 : 1;
                }

                return ($right['date']?->timestamp ?? 0) <=> ($left['date']?->timestamp ?? 0);
            })->values();
            if ($mode === 'cart' && $events->isNotEmpty()) {
                $groups->push(array_replace($events->first(), [
                    'title' => $studio->name, 'scope' => 'studio', 'events' => $events,
                    'selections' => $events->flatMap(fn (array $event) => $event['selections'])->values(),
                    'subtotal' => $quote['subtotal'], 'discount' => $quote['discount'], 'total' => $quote['total'],
                    'bulk_eligible_subtotal' => $quote['bulk_eligible_subtotal'],
                    'discount_percent' => $quote['percent'], 'principalPrice' => $quote['principalPrice'],
                ]));
            } else {
                $groups = $groups->concat($events);
            }
        }

        $groups = $groups->sort(function (array $left, array $right) use ($focusKind, $focusEvent): int {
            $leftFocused = $left['kind'] === $focusKind && $left['event']->uuid === $focusEvent;
            $rightFocused = $right['kind'] === $focusKind && $right['event']->uuid === $focusEvent;
            if ($leftFocused !== $rightFocused) {
                return $leftFocused ? -1 : 1;
            }

            return ($right['date']?->timestamp ?? 0) <=> ($left['date']?->timestamp ?? 0);
        })->values();

        return ['mode' => $mode, 'groups' => $groups, 'selectionCount' => $groups->sum(fn (array $group) => $group['selections']->count()),
            'subtotal' => $groups->sum('subtotal'), 'discount' => $groups->sum('discount'), 'total' => $groups->sum('total')];
    }
}
