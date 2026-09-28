@once
@push('styles')
<style>
    .booking-quote-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:20px;margin:24px 0; }
    .booking-quote-amount { font-size:36px;font-weight:850;color:#067eaf;margin:8px 0; }
</style>
@endpush
@endonce
        <div class="booking-quote-grid">
            @if($quote['photo_events'])
                <div class="card card-body"><h2>Photography minimum spend</h2><p class="booking-quote-amount">${{ number_format($quote['photo_minimum_cents'] / 100, 2) }}</p><p>{{ $quote['photo_events'] }} photography {{ Str::plural('event', $quote['photo_events']) }} · $800 for the first, $600 for each additional event.</p><p class="muted">This is the required total photography sales across these events, not an upfront photography charge.</p></div>
            @endif
            @if($quote['video_concerts'])
                <div class="card card-body"><h2>Estimated video quote</h2><p class="booking-quote-amount">{{ $quote['video_quote_cents'] !== null ? '$'.number_format($quote['video_quote_cents'] / 100, 2) : 'To be confirmed' }}</p><p>{{ $quote['video_concerts'] }} {{ Str::plural('concert', $quote['video_concerts']) }} · {{ $quote['families'] }} {{ !empty($quote['families_confirmed']) ? 'confirmed' : 'estimated' }} families.</p><p class="muted">$1,500 per concert or $15 per family, whichever is greater. Final family numbers determine the final invoice. Count each family once across all concerts, including performing siblings and parents.</p></div>
            @endif
        </div>
        <p class="muted">{{ $quote['gst_note'] }}</p>
