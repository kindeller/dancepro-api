@extends('layouts.public', ['title' => $studio->name.' concert photos', 'footerLabel' => 'DancePro Media'])
@section('content')
<section class="hero"><div class="container">@include('customers._heading', ['headingImageUrl' => $studio->logoUrl(), 'eyebrow' => 'Concert photos', 'heading' => $studio->name, 'description' => 'Choose an event to browse its photo albums.'])</div></section>
<section class="section"><div class="container">
    <x-gallery-breadcrumbs :items="[['label' => 'My Media', 'url' => route('customer.media.index')], ['label' => $studio->name]]" />
    @if($concerts->isEmpty())<p>Your studio’s concert galleries are not available yet. Please check back soon.</p>@else
    <div class="gallery-navigation-grid">
        @foreach($concerts as $concert)
            @php($cover = $covers[$concert->uuid] ?? null)
            @php($external = !($nativeAlbums[$concert->uuid] ?? false) && $concert->galleryForDisplay())
            <x-gallery-navigation-card :title="$concert->name"
                :image="$cover ? route('customer.concerts.photos.image', ['concert' => $concert, 'asset' => $cover->uuid, 'size' => 'cover']) : $studio->logoUrl()"
                :logo="!$cover" :subtitle="$concert->event_date?->format('j M Y') ?? 'Date to be confirmed'"
                :href="($nativeAlbums[$concert->uuid] ?? false) ? route('customer.concerts.photos.show', $concert) : ($concert->galleryForDisplay() ?: null)"
                :action="($nativeAlbums[$concert->uuid] ?? false) || $external ? 'View gallery' : 'Photos are not available yet'"
                :target="$external ? '_blank' : null" :rel="$external ? 'noopener' : null" />
        @endforeach
    </div>
    @endif
</div></section>
@endsection
