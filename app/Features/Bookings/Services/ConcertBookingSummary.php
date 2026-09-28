<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Support\ConcertBookingRequirements;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use Carbon\CarbonImmutable;

class ConcertBookingSummary
{
    public function __construct(
        private readonly ConcertBookingQuote $quotes,
        private readonly ConcertTrackerTaskCatalog $tasks,
    ) {}

    public function submitted(?string $uuid): ?array
    {
        return $uuid ? ConcertBooking::query()->where('uuid', $uuid)->first()?->submission_snapshot : null;
    }

    public function snapshot(ConcertBooking $booking, array $details): array
    {
        $details['items'] = $booking->items->map(fn ($item): array => [
            'item_type' => $item->item_type->value,
            'title' => $item->title,
            'venue_name' => $item->venue_name,
            'venue_address' => $item->venue_address,
            'event_date' => $item->event_date->toDateString(),
            'starts_at' => substr($item->starts_at, 0, 5),
            'finishes_at' => substr($item->finishes_at, 0, 5),
        ])->all();

        return [
            'submitted_at' => $booking->created_at->toIso8601String(),
            'details' => $details,
            'quote' => $quote = $this->quotes->calculate($details),
            'requirements' => array_values(array_intersect_key(ConcertBookingRequirements::all(), array_flip($details['accepted_requirements']))),
            'timeline' => $this->timeline($details, $quote),
        ];
    }

    private function timeline(array $details, array $quote): array
    {
        $entries = [];
        if ($quote['video_concerts'] > 0 || (! empty($details['wants_concert_photography']) && collect($details['items'])->contains('item_type', 'concert'))) {
            $entries[] = $this->entry('seat', 'Confirm seats and technical arrangements', null,
                ['Confirm reserved seats and access with the venue. DancePro will confirm the deadline for these details.']);
        }
        if ($quote['video_concerts'] > 0) {
            $entries[] = $this->entry('programme', 'Confirm final performing family numbers', null,
                ['Count each family once across the concerts, including siblings and parents. Final numbers determine the video invoice.',
                    'DancePro will confirm when final numbers are required.']);
        }
        $lastFinish = null;
        foreach ($details['items'] as $index => $item) {
            $start = CarbonImmutable::parse($item['event_date'].' '.$item['starts_at']);
            $finish = CarbonImmutable::parse($item['event_date'].' '.$item['finishes_at']);
            if ($finish->lessThan($start)) {
                $finish = $finish->addDay();
            }
            $lastFinish = $lastFinish === null || $finish->greaterThan($lastFinish) ? $finish : $lastFinish;
            $portrait = $item['item_type'] === 'dress_rehearsal';
            $name = $item['title'] ?: ($portrait ? 'DR Portrait' : 'Concert').' '.($index + 1);
            $photo = ! $portrait && $details['wants_concert_photography'];
            $video = ! $portrait && $details['wants_concert_videography'];

            if ($photo || $video) {
                $entries[] = $this->entry('programme', 'Programme · '.$name,
                    $start->startOfDay()->addDays($this->offset('programme_prepared')),
                    ['Supply the current programme and confirm the performance order.']);
            }
            $requirements = [
                $item['venue_name'].($item['venue_address'] ? ' · '.$item['venue_address'] : ''),
                'Event: '.$start->format('g:i a').'–'.$finish->format('g:i a').'.',
                $video ? 'Allow 1.5–2 hours for crew arrival and setup before the concert.' : 'Allow one hour for crew arrival and setup before the event.',
            ];
            if ($portrait) {
                $requirements = [...$requirements, ...array_values(ConcertBookingRequirements::PORTRAIT)];
            }
            if ($photo) {
                $requirements[] = ConcertBookingRequirements::PHOTO['photographer_seat'];
                $requirements[] = ConcertBookingRequirements::PHOTO['no_audience_recording'];
            }
            if ($video) {
                $requirements[] = 'Theatre access required from '.$start->subHours(2)->format('g:i a').'–'.$start->subMinutes(90)->format('g:i a').' (1.5–2 hours before the concert).';
                $requirements[] = ConcertBookingRequirements::VIDEO['video_space_audio'];
                $entries[] = $this->entry('video', 'Studio video / slideshow · '.$name,
                    $start->startOfDay()->addDays($this->offset('slideshow_received')),
                    ['Supply any studio video or slideshow to include in the finished concert, if applicable.']);
            }
            $setupStart = $start->subMinutes($video ? 120 : 60);
            $entries[] = $this->entry('calendar', 'Crew arrival & setup · '.$name, $setupStart,
                [$video
                    ? 'Arrival/setup window: '.$setupStart->format('g:i a').'–'.$start->subMinutes(90)->format('g:i a').'. Allow 1.5–2 hours before the concert.'
                    : 'Crew arrival and setup starts one hour before the event.',
                    'Have the venue open and the required photography space, seats and audio access ready for the booked services.']);
            $entries[] = $this->entry($portrait ? 'camera' : 'calendar', $name, $start, $requirements);
        }

        if ($quote['photo_events'] > 0) {
            $release = $lastFinish->addHours(48);
            $entries[] = $this->entry('camera', 'Estimated photo gallery release', $release,
                ['48 hours after the final event. This is an estimate, subject to production checks.']);
            $entries[] = $this->entry('invoice', 'Photography minimum-spend review', $release->addDays(30),
                ['The review takes place 30 days after the actual gallery release. This date assumes the estimated release above.',
                    'If the minimum spend has not been met, DancePro will contact the studio to explore ways to resolve the shortfall. An invoice for the remaining difference is issued only after those options are exhausted.']);
        }
        if ($quote['video_concerts'] > 0) {
            $entries[] = $this->entry('invoice', 'Video invoice prepared & sent', $lastFinish->startOfDay()->addDays($this->offset('invoice_sent')),
                ['One video invoice covers this booking, using confirmed final family numbers. It is sent seven days before the final event. Payment is due 14 days after the invoice email is sent; your invoice shows the actual deadline.']);
            $entries[] = $this->entry('video', 'Estimated video ready for approval', $lastFinish->addWeeks(2),
                ['Two weeks after the final event. Programme and supplied studio media must be finalised. Earlier concerts may be reviewed before payment; payment is required for final-concert approval access.',
                    'This is the estimated approval stage, not the final video release date.']);
        }

        usort($entries, fn (array $left, array $right): int => strcmp($left['due_at'] ?? '', $right['due_at'] ?? ''));

        return $entries;
    }

    private function offset(string $key): int
    {
        return (int) $this->tasks->all()->firstWhere('key', $key)['dueOffsetDays'];
    }

    private function entry(string $icon, string $title, ?CarbonImmutable $date, array $details): array
    {
        return ['icon' => $icon, 'title' => $title, 'due_at' => $date?->toIso8601String(), 'details' => $details];
    }
}
