@if(!$booking->cancelled_at && $booking->items->whereNull('cancelled_at')->contains(fn ($item) => $item->item_type->value === 'concert'))
<section id="family-numbers" @if(!($familyNumbersEmbedded ?? false)) class="card card-body card-pad" style="margin:24px 0" @endif>
    <h2>{{ $familyNumbersHeading ?? 'Final family numbers' }}</h2>
    <p>Count each family once across all concerts in this booking, including performing siblings and parents. This count determines the video quote when videography is booked.</p>
    @if($booking->family_count_confirmed_at)<p><strong>{{ $booking->final_family_count }} families confirmed</strong> · {{ $booking->family_count_confirmed_at->format('j M Y, g:i a') }}</p>@endif
    @if(!$booking->family_count_confirmed_at)<p class="muted">The value below starts with the booking estimate. It is not confirmed until you check the box and select Confirm final numbers. You can confirm earlier than the due date when the final count is known.</p>@endif
    <form method="post" action="{{ $familyNumbersUrl }}">
        @csrf
        <label>Unique performing families<input type="number" name="final_family_count" min="1" max="10000" required value="{{ old('final_family_count', $booking->final_family_count ?? $booking->approximate_family_count) }}"></label>
        <label class="choice" style="display:flex;align-items:flex-start;gap:10px;margin:16px 0"><input type="checkbox" name="confirm_unique_families" value="1" required style="width:18px;height:18px;flex:0 0 18px"><span>I confirm each family has been counted only once across these concerts.</span></label>
        <button class="button" type="submit">{{ $booking->family_count_confirmed_at ? 'Update confirmed numbers' : 'Confirm final numbers' }}</button>
    </form>
</section>
@endif
