<section class="card card-pad" style="max-width:1000px;margin-top:20px">
<h2>Create invoice from booking</h2>
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
@if($booking->status->value !== 'approved' || !$booking->studio_id)
<p>Approve the booking and link its studio before preparing the invoice.</p><a class="button secondary" href="{{ route('admin.concert-bookings.show', $booking) }}">Review booking</a>
@elseif($booking->wants_concert_videography && !$booking->family_count_confirmed_at)
<p>Pending final family numbers. The invoice has no deadline until they are confirmed.</p><a class="button secondary" href="{{ route('admin.concert-bookings.show', [$booking, 'tab' => 'seats-families']) }}">Confirm final family numbers</a>
@else
<p>Review the quote, add any adjustments or discounts, then send the invoice. The studio can view, download and pay it through their account.</p>
<form method="post" action="{{ route('admin.studio-invoices.store', $booking) }}">@csrf
<label>Invoice type<select name="kind">@if($booking->wants_concert_videography)<option value="video">Confirmed video quote</option>@endif<option value="custom">Custom invoice</option></select></label>
<label style="display:flex;align-items:center;gap:8px;margin:18px 0"><input type="checkbox" name="is_test" value="1" @checked(app(\App\Features\Tracking\Services\ConcertEmailRehearsal::class)->simulated())> Test invoice (no live payment)</label>
<button>Create draft invoice</button></form>
@endif
</section>
