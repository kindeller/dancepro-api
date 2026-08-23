@extends('layouts.public')

@section('content')
<section class="hero"><div class="container"><div class="eyebrow">Dance studio</div><h1>{{ $studio->name }}</h1><p class="lead">{{ $studio->description }}</p></div></section>
<section class="section"><div class="container"><div class="section-head"><h2>Concerts</h2><a href="{{ route('studios.index') }}">All studios</a></div><div class="grid">
@foreach($concerts as $concert)
    <article class="card">
        @if($concert->cover_image_url)<img class="card-media" src="{{ $concert->cover_image_url }}" alt="">@else<div class="card-media"></div>@endif
        <div class="card-body"><div class="meta">{{ $concert->event_date?->format('j F Y') }} @if($concert->venue_name) · {{ $concert->venue_name }} @endif</div><h3>{{ $concert->name }}</h3><p class="muted">{{ Str::limit($concert->description, 120) }}</p><a class="button" href="{{ route('concerts.show', $concert) }}">View concert</a></div>
    </article>
@endforeach
</div></div></section>
@endsection
