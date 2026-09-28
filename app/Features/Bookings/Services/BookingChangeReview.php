<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Studios\Models\StudioBookingChangeRequest;
use Illuminate\Support\Arr;

class BookingChangeReview
{
    public function page(ConcertBooking $booking, ?string $requestUuid): array
    {
        $requests = StudioBookingChangeRequest::whereIn('scheduling_event_id', $booking->items->pluck('scheduling_event_id')->filter())
            ->with('requestedBy')->latest()->get();
        $selected = $requestUuid ? $requests->firstWhere('uuid', $requestUuid) : null;
        abort_if($requestUuid && ! $selected, 404);

        $details = $booking->only(['wants_portrait_photography', 'wants_concert_photography', 'wants_concert_videography', 'approximate_family_count', 'final_family_count']);
        $details['items'] = $booking->items->whereNull('cancelled_at')->map(fn ($item) => ['item_type' => $item->item_type->value])->all();

        $revisions = $booking->revisions()->with('user')->paginate(10, ['*'], 'revisions_page');
        $revisionRows = $revisions->getCollection()->mapWithKeys(fn ($revision) => [$revision->id => $this->differences($revision->before, $revision->after)]);

        return ['revisionRows' => $revisionRows, 'currentQuote' => app(ConcertBookingQuote::class)->calculate($details), 'changeRequests' => $requests, 'changeRequest' => $selected, 'revisions' => $revisions];
    }

    private function differences(array $before, array $after): array
    {
        $rows = [];
        $labels = ['wants_portrait_photography' => 'DR portrait photography', 'wants_concert_photography' => 'Concert photography', 'wants_concert_videography' => 'Concert videography', 'posted_arrival_at' => 'Crew arrival', 'starts_at' => 'Start', 'finishes_at' => 'Finish', 'estimated_finish_at' => 'Crew finish', 'previous_video_feedback' => 'Feedback and requests'];
        foreach (Arr::dot($after) as $path => $value) {
            $previous = Arr::get($before, $path);
            $parts = explode('.', $path);
            $field = end($parts);
            if ($previous === $value || in_array($field, ['id', 'uuid', 'venue_id'], true)) {
                continue;
            }
            $label = $labels[$field] ?? str($field)->replace('_', ' ')->title()->toString();
            if ($parts[0] === 'items') {
                $item = $after['items'][$parts[1]];
                $prefix = $item['title'] ?: 'Event '.((int) $parts[1] + 1);
                if (($parts[2] ?? null) === 'shifts') {
                    $prefix .= ' · Shift '.((int) $parts[3] + 1);
                }
                $label = $prefix.' · '.$label;
            }
            $rows[] = ['label' => $label, 'before' => $this->display($previous), 'after' => $this->display($value)];
        }

        return $rows;
    }

    private function display(mixed $value): string
    {
        return is_bool($value) ? ($value ? 'Yes' : 'No') : (is_array($value) ? implode(', ', $value) : (string) ($value ?? 'Not set'));
    }
}
