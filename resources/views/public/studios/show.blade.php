@extends('layouts.public', ['title' => $studio->name])

@section('content')
<section class="hero"><div class="container">@include('customers._heading', ['headingImageUrl' => $studio->logoUrl(), 'eyebrow' => $studio->is_school ? 'School' : 'Dance studio', 'heading' => $studio->name, 'description' => $studio->description ?: 'Browse your studio’s released concert media.'])</div></section>
<section class="section"><div class="container"><div class="section-head"><h2>Concerts</h2><a href="{{ route('studios.index') }}">All studios</a></div><div class="gallery-navigation-grid">
@foreach($concerts as $concert)
    <x-gallery-navigation-card :title="$concert->name" :image="$concert->coverImageForDisplay()" :subtitle="$concert->event_date?->format('j F Y').($concert->venue_name ? ' · '.$concert->venue_name : '')" :href="route('concerts.show', $concert)" action="View concert" />
@endforeach
</div></div></section>
@endsection
