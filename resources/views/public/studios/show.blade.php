@extends('layouts.public', ['title' => $studio->name])

@push('styles')
<style>
    .studio-hero { padding:52px 0; background:linear-gradient(135deg,#101820 0 66%,color-mix(in srgb,var(--accent) 82%,#101820) 66%); }
    .studio-hero-layout { display:grid; grid-template-columns:auto minmax(0,1fr); gap:28px; align-items:center; }
    .studio-hero-layout.has-cover { grid-template-columns:auto minmax(0,1fr) minmax(180px,280px); }
    .studio-hero-logo { display:block; width:180px; height:auto; max-height:180px; border:0; border-radius:8px; background:transparent; object-fit:contain; }
    .studio-hero-copy { min-width:0; max-width:820px; }
    .studio-hero h1 { max-width:none; margin:8px 0 14px; font-size:clamp(40px,5vw,62px); line-height:1.02; text-wrap:balance; }
    .studio-hero .lead { margin-bottom:0; }
    .studio-hero-cover { width:100%; max-height:220px; border-radius:10px; object-fit:contain; }
    @media(max-width:700px) {
        .studio-hero { padding:38px 0; background:#101820; }
        .studio-hero-layout,.studio-hero-layout.has-cover { grid-template-columns:1fr; gap:20px; }
        .studio-hero-logo { width:min(180px,55vw); }
        .studio-hero h1 { font-size:clamp(38px,12vw,54px); }
    }
</style>
@endpush

@section('content')
<section class="hero studio-hero">
    <div class="container studio-hero-layout @if($studio->coverImageForDisplay()) has-cover @endif">
        @if($studio->logo_path)<img class="studio-hero-logo" src="{{ $studio->logoUrl() }}" alt="{{ $studio->name }} logo">@endif
        <div class="studio-hero-copy"><div class="eyebrow">Dance studio</div><h1>{{ $studio->name }}</h1>@if($studio->description)<p class="lead">{{ $studio->description }}</p>@endif</div>
        @if($studio->coverImageForDisplay())<img class="studio-hero-cover" src="{{ $studio->coverImageForDisplay() }}" alt="{{ $studio->name }} image">@endif
    </div>
</section>
<section class="section"><div class="container"><div class="section-head"><h2>Concerts</h2><a href="{{ route('studios.index') }}">All studios</a></div><div class="grid">
@foreach($concerts as $concert)
    <article class="card">
        @if($concert->coverImageForDisplay())<img class="card-media" src="{{ $concert->coverImageForDisplay() }}" alt="">@else<div class="card-media"></div>@endif
        <div class="card-body"><div class="meta">{{ $concert->event_date?->format('j F Y') }} @if($concert->venue_name) · {{ $concert->venue_name }} @endif</div><h3>{{ $concert->name }}</h3><p class="muted">{{ Str::limit($concert->description, 120) }}</p><a class="button" href="{{ route('concerts.show', $concert) }}">View concert</a></div>
    </article>
@endforeach
</div></div></section>
@endsection
