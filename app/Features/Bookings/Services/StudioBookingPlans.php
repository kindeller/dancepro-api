<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudioBookingPlans
{
    public function __construct(private readonly StudioCommunicationHistory $access) {}

    public function forStudio(User $user, Studio $studio): LengthAwarePaginator
    {
        abort_unless($this->access->canView($user, $studio), 403);

        return $this->query($studio)->latest('created_at')->latest('id')->paginate(12);
    }

    public function latestForStudio(User $user, Studio $studio): ?ConcertBooking
    {
        abort_unless($this->access->canView($user, $studio), 403);

        return $this->query($studio)->latest('created_at')->latest('id')->first();
    }

    public function previousForStudio(User $user, Studio $studio): LengthAwarePaginator
    {
        $latest = $this->latestForStudio($user, $studio);

        return $this->query($studio)->when($latest, fn (Builder $query) => $query->whereKeyNot($latest->id))
            ->latest('created_at')->latest('id')->paginate(12);
    }

    public function estimates(User $user, Studio $studio): Collection
    {
        abort_unless($this->access->canView($user, $studio), 403);

        return $this->query($studio)->where('status', '!=', 'cancelled')->with('items')->latest('created_at')->latest('id')->get()
            ->map(fn (ConcertBooking $booking): array => [
                'booking' => $booking,
                'receipt' => app(CurrentBookingTimeline::class)->summaryForBooking($booking),
            ])->filter(fn (array $estimate): bool => $estimate['receipt']['quote']['photo_events'] > 0 || $estimate['receipt']['quote']['video_concerts'] > 0)->values();
    }

    public function receipt(User $user, Studio $studio, ConcertBooking $booking): array
    {
        abort_unless($this->access->canView($user, $studio), 403);
        abort_unless($this->query($studio)->whereKey($booking->id)->exists(), 404);

        return $booking->submission_snapshot;
    }

    public function timeline(User $user, Studio $studio, ConcertBooking $booking): array
    {
        $this->receipt($user, $studio, $booking);

        return app(CurrentBookingTimeline::class)->forBooking($booking, $studio);
    }

    private function query(Studio $studio): Builder
    {
        return ConcertBooking::query()->whereNotNull('submission_snapshot')
            ->where(function (Builder $query) use ($studio): void {
                $query->where('studio_id', $studio->id)
                    ->orWhere(function (Builder $legacy) use ($studio): void {
                        $legacy->whereNull('studio_id')
                            ->whereHas('items.schedulingEvent', fn (Builder $events) => $events->where('studio_id', $studio->id))
                            ->whereDoesntHave('items.schedulingEvent', fn (Builder $events) => $events->whereNotNull('studio_id')->where('studio_id', '!=', $studio->id));
                    });
            });
    }
}
