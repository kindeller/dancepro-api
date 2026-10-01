@extends('layouts.public')

@section('content')
<section class="hero"><div class="container">@include('customers._heading', ['eyebrow' => 'DancePro Media', 'heading' => 'Find your dance studio.', 'description' => 'Browse released concerts, then securely watch and download your performance media.'])</div></section>
<section class="section"><div class="container">
    <div class="section-head"><div><div class="eyebrow">Studios</div><h2>Available now</h2></div><div class="muted">Listed alphabetically</div></div>
    @if($studios->isEmpty())
        <div class="empty"><h3>No concerts are available yet</h3><p class="muted">Please check back after your studio's media has been released.</p></div>
    @else
        <div class="gallery-navigation-grid">
            @foreach($studios as $studio)
                <x-gallery-navigation-card :title="$studio->name" :image="$studio->logoUrl() ?? $studio->coverImageForDisplay()" :logo="(bool)$studio->logoUrl()" :subtitle="$studio->available_concerts_count.' '.Str::plural('concert', $studio->available_concerts_count)" :href="route('studios.show', $studio)" action="View events" />
            @endforeach
        </div>
    @endif
</div></section>
@endsection
