@extends('layouts.public', ['title' => $studio->name.' concert photos', 'footerLabel' => 'DancePro Media'])
@section('content')
<section class="hero"><div class="container"><h1>{{ $studio->name }}</h1><p class="lead">Concert photos</p></div></section>
<section class="section"><div class="container">
    <p><a class="button secondary" href="{{ route('customer.media.index') }}">← My Media</a></p>
    @if($concerts->isEmpty())<p>Your studio’s concert galleries are not available yet. Please check back soon.</p>@else
    <div class="grid">
        @foreach($concerts as $concert)
            <article class="card card-body"><h2>{{ $concert->name }}</h2><p>{{ $concert->event_date?->format('j M Y') }}</p>
                @if($nativeAlbums[$concert->uuid] ?? false)<a class="button" href="{{ route('customer.concerts.photos.show', $concert) }}">View photos</a>
                @elseif($concert->galleryForDisplay())<a class="button" href="{{ $concert->galleryForDisplay() }}" target="_blank" rel="noopener">View/order photos</a>
                @else<p>Photos are not available yet. Please check back soon.</p>@endif
            </article>
        @endforeach
    </div>
    @endif
</div></section>
@endsection
