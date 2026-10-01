@extends('layouts.public', ['title' => (!empty($previousForms) ? 'Previous booking forms' : 'My timeline').' · '.$studio->name, 'footerLabel' => 'Studio Hub'])
@include('customers.studios._styles')
@section('headerParentUrl', route('customer.studio-communications.index', $studio))
@section('content')
@include('customers.studios._header', ['hubHeading' => !empty($previousForms) ? 'Previous booking forms' : 'My timeline', 'hubDescription' => $studio->name.' · Your saved bookings and event plans.'])
<section class="section"><div class="container">
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px"><a class="button secondary" href="{{ route('customer.studio-communications.index', [$studio, 'tab' => 'events']) }}">← Back to My Events</a>@if(!empty($previousForms) && $latestBooking)<a class="button secondary" href="{{ route('customer.studio-bookings.submission', [$studio, $latestBooking]) }}">My booking</a>@endif</div>
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px"><p>{{ !empty($previousForms) ? 'Read your earlier submitted booking forms. Your latest form is available from My booking.' : 'Reopen your timeline, estimates and original submitted form.' }}</p><a class="button" href="{{ route('concert-bookings.create', ['studio' => $studio->uuid]) }}">{{ !empty($bookingDraft) ? 'Continue booking' : 'Make booking' }}</a></div>
    @forelse($bookings as $booking)
        @php($receipt = $booking->submission_snapshot)
        <article class="card card-body" style="margin-bottom:20px">
            <p class="eyebrow">{{ $booking->status->label() }}</p>
            <h2>{{ $receipt['details']['studio_name'] }}</h2>
            <p class="muted">Submitted {{ \Carbon\CarbonImmutable::parse($receipt['submitted_at'])->format('j M Y · g:i a') }}</p>
            <ul>@foreach($receipt['details']['items'] as $item)<li><strong>{{ $item['title'] ?: ($item['item_type'] === 'dress_rehearsal' ? 'DR Portrait' : 'Concert') }}</strong> · {{ \Carbon\CarbonImmutable::parse($item['event_date'])->format('j M Y') }}</li>@endforeach</ul>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:20px"><a class="button" href="{{ route('customer.studio-bookings.show', [$studio, $booking]) }}">View timeline</a><a class="button secondary" href="{{ route('customer.studio-bookings.submission', [$studio, $booking]) }}">View booking form</a></div>
        </article>
    @empty
        <div class="card card-body"><h2>{{ !empty($previousForms) ? 'No previous booking forms yet' : 'No saved timelines yet' }}</h2><p>Bookings made from this Studio Hub will appear here after submission, including requests awaiting approval.</p><p class="muted">Older bookings without a saved submission remain available in the Studio Hub event details.</p></div>
    @endforelse
    @if($bookings->hasPages())<nav aria-label="Booking pages" style="display:flex;justify-content:space-between;gap:16px"><span>@if($bookings->previousPageUrl())<a class="button secondary" href="{{ $bookings->previousPageUrl() }}">Previous</a>@endif</span><span>Page {{ $bookings->currentPage() }} of {{ $bookings->lastPage() }}</span><span>@if($bookings->nextPageUrl())<a class="button secondary" href="{{ $bookings->nextPageUrl() }}">Next</a>@endif</span></nav>@endif
</div></section>
@endsection
