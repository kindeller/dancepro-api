Hi {!! $firstName !!},

Thank you for submitting your booking for {!! $studioName !!}. Your booking is pending approval; it is not yet confirmed.

We will review the dates, venues and requested services, and notify you by email when your booking is approved. We will contact you if we need any more information.

@foreach($items as $item)
{!! $item['title'] ?: ($item['item_type'] === 'dress_rehearsal' ? 'DR Portrait' : 'Concert') !!} — {{ \Carbon\CarbonImmutable::parse($item['event_date'])->format('j M Y') }}
@endforeach

@if($hasStudio)
Your requested events are listed as pending in Studio Hub → Concert events. My booking opens your submitted form, and My timeline shows your estimates and next steps. A copy of this update is available in Studio messages.
@else
Keep your submitted booking confirmation for your records. Once your booking is approved and linked to a studio, you can manage its events through Studio Hub.
@endif

If any details are incorrect, please contact DancePro before submitting another booking.

Open your DancePro account: {!! $accountUrl !!}
