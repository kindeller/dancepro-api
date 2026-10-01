@extends('layouts.public', ['title' => 'My booking'])
@section('headerParentUrl', $parentUrl ?? route('concert-bookings.thanks'))
@include('customers.studios._styles')
@section('content')
@if(isset($studio))
    @include('customers.studios._header', ['hubHeading' => 'My booking', 'hubDescription' => $studio->name.' · Your submitted booking form.'])
@else
    <section class="hero"><div class="container">@include('customers._heading', ['showMediaLogo' => false, 'eyebrow' => 'Your DancePro booking', 'heading' => 'My booking', 'description' => 'Your submitted booking form.'])</div></section>
@endif
<section class="section"><div class="container">
    @php($details = $receipt['details'])
    @if(isset($studio))<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px"><a class="button secondary" href="{{ route('customer.studio-communications.index', [$studio, 'tab' => 'events']) }}">← Back to My Events</a><a class="button secondary" href="{{ $parentUrl }}">My timeline</a><a class="button secondary" href="{{ route('customer.studio-bookings.previous', $studio) }}">See previous booking forms</a></div>@endif
    <p class="muted">Submitted {{ \Carbon\CarbonImmutable::parse($receipt['submitted_at'])->format('j M Y · g:i a') }} ({{ config('app.timezone') }}). This is a read-only copy of your original submission.</p>
    <div class="card card-body"><h2>Studio details</h2><dl>
        @foreach(['studio_name' => 'Studio/company name', 'contact_name' => 'Owner/director name', 'contact_email' => 'Email', 'contact_phone' => 'Phone number', 'postal_address' => 'Postal address'] as $key => $label)
            <dt><strong>{{ $label }}</strong></dt><dd style="margin:0 0 16px;white-space:pre-line;overflow-wrap:anywhere">{{ $details[$key] ?? 'Not supplied' }}</dd>
        @endforeach
    </dl><h3>Services</h3><ul>@if($details['wants_portrait_photography'])<li>Dress rehearsal portrait photography</li>@endif @if($details['wants_concert_photography'])<li>Concert photography</li>@endif @if($details['wants_concert_videography'])<li>Concert videography</li>@endif</ul></div>
    @foreach($details['items'] as $item)
        <div class="card card-body" style="margin-top:20px"><h2>{{ $item['title'] ?: ($item['item_type'] === 'dress_rehearsal' ? 'DR Portrait' : 'Concert') }}</h2><p>{{ $item['item_type'] === 'dress_rehearsal' ? 'DR Portrait' : 'Concert' }} · {{ \Carbon\CarbonImmutable::parse($item['event_date'])->format('j M Y') }} · {{ $item['starts_at'] }}–{{ $item['finishes_at'] }}</p><p>{{ $item['venue_name'] }}<br>{{ $item['venue_address'] }}</p></div>
    @endforeach
    <div class="card card-body" style="margin-top:20px"><h2>Booking requirements</h2>@if($details['wants_concert_videography'])<p>Estimated performing families: {{ $details['approximate_family_count'] }}</p>@endif<h3>Feedback and requests</h3><p style="white-space:pre-line">{{ $details['previous_video_feedback'] ?? 'Not supplied' }}</p><h3>Requirements accepted at submission</h3><ul>@foreach($receipt['requirements'] as $requirement)<li>{{ $requirement }}</li>@endforeach</ul></div>
</div></section>
@endsection
