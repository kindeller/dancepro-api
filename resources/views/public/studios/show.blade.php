@extends('layouts.public')

@section('content')
<section class="hero"><div class="container" style="display:flex;gap:30px;align-items:center;flex-wrap:wrap"><div style="flex:1;min-width:240px"><div class="eyebrow">Dance studio</div><h1>{{ $studio->name }}</h1><p class="lead">{{ $studio->description }}</p></div>@if($studio->cover_image_url)<img src="{{ $studio->cover_image_url }}" alt="{{ $studio->name }} image" style="width:min(100%,280px);max-height:300px;object-fit:contain;border-radius:10px">@endif</div></section>
<section class="section"><div class="container"><div class="section-head"><h2>Concerts</h2><a href="{{ route('studios.index') }}">All studios</a></div><div class="grid">
@foreach($concerts as $concert)
    <a class="card card-link" href="{{ route('concerts.show', $concert) }}" aria-labelledby="concert-{{ $concert->uuid }}">
        @if($concert->cover_image_url)<img class="card-media" src="{{ $concert->cover_image_url }}" alt="">@else<div class="card-media"></div>@endif
        <div class="card-body"><div class="meta">{{ $concert->event_date?->format('j F Y') }} @if($concert->venue_name) · {{ $concert->venue_name }} @endif</div><h3 id="concert-{{ $concert->uuid }}">{{ $concert->name }}</h3><p class="muted">{{ Str::limit($concert->description, 120) }}</p><span class="button">View concert</span></div>
    </a>
@endforeach
</div></div></section>
@endsection
