@extends('layouts.public', ['title' => $title.' · My Media', 'footerLabel' => 'DancePro Media'])

@push('styles')
<style>
    .library-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,230px)); gap:14px; align-items:start; }
    .library-item { overflow:hidden; }
    .library-image { display:grid; place-items:center; aspect-ratio:4/3; background:#14212a; color:#fff; }
    .library-image img { width:100%; height:100%; object-fit:contain; }
    .library-item-title { padding:12px; margin:0; font-size:14px; overflow-wrap:anywhere; }
    .library-note { margin-bottom:24px; }
    @media (max-width:520px) { .library-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; } }
</style>
@endpush

@section('content')
<div class="container" style="padding-top:18px"><a href="{{ route('customer.media.index') }}#purchased-heading">← My Media</a></div>
<section class="hero" style="padding:38px 0"><div class="container">
    <div class="eyebrow">{{ ucfirst($kind) }} media</div><h1 style="font-size:clamp(32px,5vw,54px)">{{ $title }}</h1>
    @if($kind === 'concert' && $event->event_date)<p class="lead">{{ $event->event_date->format('j M Y') }}</p>@endif
</div></section>
<section class="section"><div class="container">
    @if($is_preview)<p class="library-note">Sample layout using local review media. This is not a purchase or download.</p>@endif
    @if($is_preview && $kind === 'competition')
        <div class="library-grid">
            @foreach($items as $item)
                <a class="card library-item" href="{{ $item['preview_url'] }}" target="_blank" rel="noopener" aria-label="Open watermarked photo preview">
                    <span class="library-image"><img loading="lazy" src="{{ $item['thumbnail_url'] }}" alt="Watermarked competition photo"></span>
                </a>
            @endforeach
        </div>
    @elseif($is_preview)
        <div class="library-grid">
            @foreach($items as $item)
                <div class="card library-item"><div class="library-image">@if($item['thumbnail_url'])<img src="{{ $item['thumbnail_url'] }}" alt="Concert cover">@else<span>Concert media</span>@endif</div><p class="library-item-title">Concert media sample</p></div>
            @endforeach
        </div>
    @elseif($assets->isNotEmpty())
        <div class="library-grid">
            @foreach($assets as $asset)
                <div class="card library-item">
                    <div class="library-image">
                        @if($asset->thumbnail_storage_disk && $asset->thumbnail_storage_key)
                            <img loading="lazy" src="{{ route('customer.media.library.thumbnail', ['kind' => $kind, 'event' => $event->uuid, 'asset' => $asset->uuid]) }}" alt="{{ $asset->display_name ?? $asset->original_filename }} thumbnail">
                        @else<span>{{ ucfirst($asset->media_type->value) }}</span>@endif
                    </div>
                    <p class="library-item-title">{{ $asset->display_name ?? $asset->original_filename }}</p>
                </div>
            @endforeach
        </div>
    @else
        <div class="empty"><h2>Media is being prepared</h2><p>Your order is linked to this event. Its files will appear when fulfilment is connected.</p></div>
    @endif
</div></section>
@endsection
