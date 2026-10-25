@extends('layouts.public')

@section('content')
<section class="hero"><div class="container"><div class="eyebrow">Your performance, beautifully preserved</div><h1>Find your dance studio.</h1><p class="lead">Browse released concerts, then securely watch and download your performance media.</p></div></section>
<section class="section"><div class="container">
    <div class="section-head"><div><div class="eyebrow">Studios</div><h2>Available now</h2></div><div class="muted">Listed alphabetically</div></div>
    @if($studios->isEmpty())
        <div class="empty"><h3>No concerts are available yet</h3><p class="muted">Please check back after your studio's media has been released.</p></div>
    @else
        <div class="grid">
            @foreach($studios as $studio)
                <a class="card card-link" href="{{ route('studios.show', $studio) }}" aria-labelledby="studio-{{ $studio->uuid }}" style="--accent: {{ $studio->brand_color ?? '#0aa0db' }}">
                    @if($studio->cover_image_url)<img class="card-media" src="{{ $studio->cover_image_url }}" alt="">@else<div class="card-media"></div>@endif
                    <div class="card-body"><div class="meta">{{ $studio->available_concerts_count }} {{ Str::plural('concert', $studio->available_concerts_count) }}</div><h3 id="studio-{{ $studio->uuid }}">{{ $studio->name }}</h3><p class="muted">{{ Str::limit($studio->description, 120) }}</p></div>
                </a>
            @endforeach
        </div>
    @endif
</div></section>
@endsection
