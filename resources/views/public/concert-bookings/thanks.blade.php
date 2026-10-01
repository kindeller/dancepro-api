@extends('layouts.public', ['title' => 'My timeline'])
@include('customers.studios._styles')
@section('headerParentUrl', $parentUrl ?? '')
@push('styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
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
@if(isset($studio))
    @include('customers.studios._header', ['hubHeading' => 'My timeline', 'hubDescription' => $studio->name.' · Your events. Your next steps.'])
@else
    <section class="hero"><div class="container">@include('customers._heading', ['showMediaLogo' => false, 'eyebrow' => 'Booking request received', 'heading' => 'My timeline', 'description' => $receipt ? $receipt['details']['studio_name'].' · Your events. Your next steps.' : 'Your booking request has been received.'])</div></section>
@endif
<section class="section"><div class="container booking-plan">
    <p><a class="button secondary" href="{{ isset($studio) ? route('customer.studio-communications.index', [$studio, 'tab' => 'events']) : route('customer.media.index') }}">← Back to My Events</a></p>
    @if(!empty($receipt['live']))
        <p>This timeline uses your current booking details and production progress. Your original submitted form is kept separately.</p>
        <p class="muted">Booking status: <strong>{{ ucfirst($receipt['status']) }}</strong> · Booking updated {{ $receipt['updated_at']->format('j M Y, g:i a') }}</p>
        @if($receipt['cancelled_events']->isNotEmpty())<p>Cancelled events: {{ $receipt['cancelled_events']->join(', ') }}. These are excluded from the current estimates.</p>@endif
    @else
    <p>DancePro will review your request before confirming the booking. The plan below is based on the details you submitted; dates and estimates may change if those details change.</p>
    @endif
    @if($receipt)
        @php($quote = $receipt['quote'])
        @include('public.concert-bookings._quote', ['quote' => $quote])
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
        <h2>My timeline</h2>
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
