<?php

namespace App\Features\Tracking\Support;

use App\Features\Scheduling\Models\SchedulingEvent;

class SeatsFamiliesEmailContent
{
    public const SUBJECT = 'Please confirm your concert seats and family numbers';

    public const BODY = <<<'TEXT'
Hi {{ contact_name }},

We're looking forward to your concerts. Please confirm your event details, reserved seats and final family numbers in **Studio Hub → Concert events within 10 days of this email**.

Use the **Open Concert events** button below and sign in to the DancePro account linked to your studio, if prompted. Open a concert card, then look under **Your actions**.

## 1. Confirm final family numbers
Open **Confirm final family numbers**. Enter the total number of unique performing families across all concerts in this booking, tick the confirmation box, then select **Confirm final numbers**.

Count each family once, including performing siblings and parents. The pre-filled number is your booking estimate; it is not confirmed until you submit it. You only need to confirm this total once per booking. You can update it later using **Update confirmed numbers**.
{{#if video}}
This confirmed total is used to update your concert video quote.
{{/if}}

## 2. Enter reserved seats for each concert
Open **Enter reserved seats** in each concert card. Enter the row and seat numbers for every DancePro role listed, then select **Save seating details**. Use a simple format such as **J16-20** for seats J16 through J20, or **J16, 18** for separate seats. Use a new line for another row.

{{#if photo}}Please reserve **1–2 seats for each photography role**.{{/if}}
{{#if video}}Please reserve **5–6 adjacent seats for videography**.{{/if}}
Seats are recorded separately for each concert, including junior and senior concerts at the same venue. Dress rehearsal portraits do not need auditorium seat allocations.

## 3. Confirm the details for every event
Open **Confirm event details** under **Your actions** in each event card, including dress rehearsals. Please check that arrival/setup times, start and finish times, date and venue are correct. Tick the confirmation box and select **Save confirmation**. This is due on the same date as your seats and family numbers.

Click **Request changes** in the event's Booking details as soon as possible if there are any errors. Only confirm once the details shown are correct.

{{ event_details }}

You can also upload your programme, videos and slideshows from the relevant event's **Your actions** section. Follow the due dates shown there; these uploads have their own deadlines.

If a date, time, venue or booked service is incorrect, select **Request changes** in the event's Booking details. If you need help, contact us through **Studio messages** in your Studio Hub.
TEXT;

    public static function url(SchedulingEvent $event): string
    {
        return $event->studio
            ? route('customer.studio-communications.index', [$event->studio, 'tab' => 'events', 'actions' => 1]).'#events'
            : route('customer.studio-hub');
    }
}
