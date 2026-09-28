@extends('layouts.public', ['title' => 'Event timeline'])
@section('headerParentUrl', $parentUrl ?? '')
@push('styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
    .booking-plan { max-width:1000px; }
    .booking-quote-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr)); gap:20px; margin:24px 0; }
    .booking-quote-amount { font-size:36px; font-weight:850; color:#067eaf; margin:8px 0; }
    .booking-timeline { list-style:none; margin:32px 0; padding:0; }
    .booking-timeline>li { position:relative; padding:0 0 28px 68px; }
    .booking-timeline>li:not(:last-child)::before { content:''; position:absolute; top:44px; bottom:0; left:23px; width:2px; background:#bedce8; }
    .booking-milestone-icon { position:absolute; top:0; left:0; display:grid; place-items:center; width:48px; height:48px; border-radius:50%; background:#e1f4fb; color:#067eaf; border:1px solid #bedce8; }
    .booking-milestone-icon svg { width:25px; height:25px; }
    .booking-milestone-time { color:#067eaf; font-size:14px; font-weight:800; }
    .booking-timeline h3 { margin:8px 0; }
    .booking-timeline ul { padding-left:20px; }
    .booking-timeline li li { margin:6px 0; }
    @media(max-width:600px) { .booking-timeline>li { padding-left:50px; } .booking-milestone-icon { width:36px; height:36px; } .booking-timeline>li:not(:last-child)::before { left:17px; top:36px; } .booking-timeline .card-body { padding:16px; } }
</style>
@endpush
@section('content')
<section class="hero"><div class="container"><div class="eyebrow">{{ !empty($receipt['live']) ? 'Event timeline · '.ucfirst($receipt['status']) : 'Booking request received' }}</div><h1>{{ $receipt ? $receipt['details']['studio_name'] : 'Thank you.' }}</h1><p class="lead">{{ $receipt ? 'Your events. Your next steps.' : 'Your booking request has been received.' }}</p></div></section>
<section class="section"><div class="container booking-plan">
    @if(!empty($receipt['live']))
        <p>This timeline uses your current booking details and production progress. Your original submitted form is kept separately.</p>
        <p class="muted">Booking status: <strong>{{ ucfirst($receipt['status']) }}</strong> · Booking updated {{ $receipt['updated_at']->format('j M Y, g:i a') }}</p>
        @if($receipt['cancelled_events']->isNotEmpty())<p>Cancelled events: {{ $receipt['cancelled_events']->join(', ') }}. These are excluded from the current estimates.</p>@endif
    @else
    <p>DancePro will review your request before confirming the booking. The plan below is based on the details you submitted; dates and estimates may change if those details change.</p>
    @endif
    @if($receipt)
        @php($quote = $receipt['quote'])
        <div class="booking-quote-grid">
            @if($quote['photo_events'])
                <div class="card card-body"><h2>Photography minimum spend</h2><p class="booking-quote-amount">${{ number_format($quote['photo_minimum_cents'] / 100, 2) }}</p><p>{{ $quote['photo_events'] }} photography {{ Str::plural('event', $quote['photo_events']) }} · $800 for the first, $600 for each additional event.</p><p class="muted">This is the required total photography sales across these events, not an upfront photography charge.</p></div>
            @endif
            @if($quote['video_concerts'])
                <div class="card card-body"><h2>Estimated video quote</h2><p class="booking-quote-amount">{{ $quote['video_quote_cents'] !== null ? '$'.number_format($quote['video_quote_cents'] / 100, 2) : 'To be confirmed' }}</p><p>{{ $quote['video_concerts'] }} {{ Str::plural('concert', $quote['video_concerts']) }} · {{ $quote['families'] }} {{ !empty($quote['families_confirmed']) ? 'confirmed' : 'estimated' }} families.</p><p class="muted">$1,500 per concert or $15 per family, whichever is greater. Final family numbers determine the final invoice. Count each family once across all concerts, including performing siblings and parents.</p></div>
            @endif
        </div>
        <p class="muted">{{ $quote['gst_note'] }}</p>
        @if(!empty($receipt['live']) && isset($booking))
            @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
            @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
            <p><a class="button secondary" href="{{ route('customer.studio-invoices.index', $studio) }}">Studio invoices</a></p>
            @include('public.concert-bookings._family-numbers', ['familyNumbersUrl' => route('customer.studio-bookings.families', [$studio, $booking])])
        @endif
        @if(!empty($receipt['live']))
            <section aria-label="Current event progress">
                <h2>Current progress</h2>
                @foreach($receipt['progress'] as $eventProgress)
                    <div class="card card-body" style="margin-bottom:16px"><h3>{{ $eventProgress['title'] }}</h3>
                        @forelse($eventProgress['tasks'] as $task)
                            <div style="padding:10px 0;border-bottom:1px solid #dce8ee;display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between">
                                <a href="{{ $task['url'] }}">{{ $task['title'] }}</a>
                                <span @if($task['overdue']) style="color:#b42318" @endif>{{ $task['status'] }}@if($task['completed']) · {{ $task['completed']->format('j M Y') }}@elseif($task['due']) · {{ $task['overdue'] ? 'Overdue' : 'Due' }} {{ $task['due']->format('j M Y') }}@endif</span>
                            </div>
                        @empty<p>Awaiting booking confirmation.</p>@endforelse
                    </div>
                @endforeach
            </section>
        @endif
        <h2>Event timeline</h2>
        <p class="muted">All times are {{ config('app.timezone') }}. Programme, supplied-media and invoice dates use the current tracker deadlines.</p>
        <ol class="booking-timeline">
            <li><span class="booking-milestone-icon">@include('public.concert-bookings._timeline-icon', ['icon' => 'check'])</span><div class="card card-body"><time class="booking-milestone-time" datetime="{{ $receipt['submitted_at'] }}">{{ \Carbon\CarbonImmutable::parse($receipt['submitted_at'])->format('j M Y · g:i a') }}</time><h3>Booking submitted</h3><p>We have received your booking request.</p><a href="{{ $submissionUrl ?? route('concert-bookings.submission', $bookingUuid) }}">View your submitted booking form →</a></div></li>
            @foreach($receipt['timeline'] as $entry)
                <li><span class="booking-milestone-icon">@include('public.concert-bookings._timeline-icon', ['icon' => $entry['icon']])</span><div class="card card-body">
                    @if($entry['due_at'])<time class="booking-milestone-time" datetime="{{ $entry['due_at'] }}">{{ \Carbon\CarbonImmutable::parse($entry['due_at'])->format('j M Y') }}@if(in_array($entry['icon'], ['calendar', 'camera'])) · {{ \Carbon\CarbonImmutable::parse($entry['due_at'])->format('g:i a') }}@endif</time>@else<span class="booking-milestone-time">Due date to be confirmed</span>@endif
                    <h3>{{ $entry['title'] }}</h3><ul>@foreach($entry['details'] as $detail)<li>{{ $detail }}</li>@endforeach</ul>
                    @if($entry['due_at'] && \Carbon\CarbonImmutable::parse($entry['due_at'])->lt(\Carbon\CarbonImmutable::parse($receipt['submitted_at'])->startOfDay()))<p class="muted">This date was already past when you booked. Please arrange the next step with DancePro.</p>@endif
                </div></li>
            @endforeach
        </ol>
        @if($quote['photo_events'])<div class="card card-body"><h3>How the minimum-spend review works</h3><p>If the minimum spend has not been met 30 days after the gallery is released, DancePro will contact you to explore ways to resolve the shortfall. Only after those options are exhausted will an invoice be issued for the difference between actual spend and the minimum spend.</p></div>@endif
    @endif
</div></section>
@endsection
