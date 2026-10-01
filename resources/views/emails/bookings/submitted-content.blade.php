<h1>Booking received</h1>
<p>Hi {{ $firstName }},</p>
<p>Thank you for submitting your booking for <strong>{{ $studioName }}</strong>. Your booking is <strong>pending approval</strong>; it is not yet confirmed.</p>
<p>We will review the dates, venues and requested services, and notify you by email when your booking is approved. We will contact you if we need any more information.</p>
<ul>@foreach($items as $item)<li>{{ $item['title'] ?: ($item['item_type'] === 'dress_rehearsal' ? 'DR Portrait' : 'Concert') }} · {{ \Carbon\CarbonImmutable::parse($item['event_date'])->format('j M Y') }}</li>@endforeach</ul>
@if($hasStudio)
<p>Your requested events are listed as pending in <strong>Studio Hub → Concert events</strong>. <strong>My booking</strong> opens your submitted form, and <strong>My timeline</strong> shows your estimates and next steps. A copy of this update is available in <strong>Studio messages</strong>.</p>
@else
<p>Keep your submitted booking confirmation for your records. Once your booking is approved and linked to a studio, you can manage its events through Studio Hub.</p>
@endif
<p>If any details are incorrect, please contact DancePro before submitting another booking.</p>
