@extends('layouts.public')

@section('content')
<section class="hero"><div class="container">@include('customers._heading', ['headingImageUrl' => $concert->studio->logoUrl(), 'eyebrow' => $concert->studio->name, 'heading' => $concert->name, 'description' => $concert->description ?: 'Watch or download your released concert media.'])<div class="meta" style="color:#d9e8ee">{{ $concert->event_date?->format('j F Y') }} @if($concert->venue_name) · {{ $concert->venue_name }} @endif</div></div></section>
<section class="section"><div class="container">
@php($assets = $concert->mediaCollections->flatMap->assets->filter(fn ($asset) => $asset->media_type === \App\Features\Media\Support\MediaType::Video)->values())
@if($assets->isEmpty())
    <div class="empty"><h3>Media is not available yet</h3><p class="muted">This concert has been released, but its playable media is still being prepared.</p></div>
@else
    @php($first = $assets->first())
    @php($playerScriptAvailable = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    <div class="section-head"><div><div class="eyebrow">Concert media</div><h2>{{ $canStream ? 'Watch the performance' : 'Download the performance' }}</h2></div><div class="meta">{{ $assets->count() }} items</div></div>
    @if($canStream)
    <div class="player-grid">
        <section class="card player"><video id="concert-player" controls preload="metadata" @unless($playerScriptAvailable) src="{{ route('concerts.media.stream', [$concert, $first]) }}" @endunless></video><div class="player-info"><h3 id="player-title">{{ $first->display_name ?? $first->original_filename }}</h3><div class="meta" id="player-status" style="color:#a9bec8">{{ $playerScriptAvailable ? 'Preparing playback…' : 'MP4 playback' }}</div><div class="actions"><label style="margin:0;color:#d9e8ee">Quality <select id="player-quality" disabled><option value="-1">Auto</option></select></label><a id="player-download" class="button" href="{{ $downloadUrls[$first->uuid] }}">Download original</a></div></div></section>
        <aside class="card playlist" aria-label="Concert playlist">
        @foreach($concert->mediaCollections as $collection)
            @php($collectionVideos = $collection->assets->filter(fn ($asset) => $asset->media_type === \App\Features\Media\Support\MediaType::Video))
            @if($collectionVideos->isNotEmpty())
                <div class="meta" style="padding:14px 16px 6px;font-weight:700">{{ $collection->name }}</div>
                @foreach($collectionVideos as $asset)
                    <button class="playlist-item @if($asset->is($first)) active @endif" type="button" data-playback="{{ route('concerts.media.playback', [$concert, $asset]) }}" data-concert-download="{{ $downloadUrls[$asset->uuid] }}" data-download="{{ $downloadUrls[$asset->uuid] }}" data-title="{{ $asset->display_name ?? $asset->original_filename }}"><span class="playlist-thumb">▶</span><span><strong>{{ $asset->display_name ?? $asset->original_filename }}</strong><br><span class="meta">{{ $asset->duration_seconds ? gmdate('i:s', $asset->duration_seconds) : ucfirst($asset->media_type->value) }}</span></span></button>
                @endforeach
            @endif
        @endforeach
        </aside>
    </div>
    @else
        <div class="card card-body"><p>Streaming access has ended. Your downloads remain available until the download access period ends.</p>
            @foreach($assets as $asset)<p><a class="button secondary" data-concert-download="{{ $downloadUrls[$asset->uuid] }}" href="{{ $downloadUrls[$asset->uuid] }}">Download {{ $asset->display_name ?? $asset->original_filename }}</a></p>@endforeach
        </div>
    @endif
@endif
<div class="utility-grid">
@auth
    @if(app(\App\Features\Concerts\Services\ConcertFamilyAccess::class)->canShare(auth()->user(), $concert))<article class="card utility"><h3>Share with family</h3><p>Invite up to three family recipients with private, time-limited access.</p><a class="button secondary" href="{{ route('customer.concert-family.index', $concert) }}">Family sharing</a></article>@endif
    @if(app(\App\Features\Concerts\Services\ConcertFamilyAccess::class)->canManage(auth()->user(), $concert))<article class="card utility"><h3>Family sharing settings</h3><a class="button secondary" href="{{ route('customer.concert-family.settings', $concert) }}">Manage studio sharing</a></article>@endif
@endauth

    <article class="card utility"><h3>Watching, saving &amp; sharing</h3><p><a href="{{ route('website.help') }}#article-website-downloading-several-concert-videos">Download manager guide</a></p><p><a href="{{ route('website.help') }}#article-website-sharing-concert-videos-with-family">Family invitations and access limits</a></p></article>
    @if($assets->isNotEmpty())<article class="card utility"><h3>Download manager</h3><p class="muted" id="download-status" role="status" aria-live="polite">Ready to download {{ $assets->count() }} originals. Your browser may ask permission for multiple files.</p><p class="muted">Pause stops the next files being requested; downloads already started continue in your browser.</p><div class="actions"><button class="button secondary" id="download-all" type="button">Start</button><button class="button secondary" id="download-pause" type="button" disabled>Pause</button><button class="button secondary" id="download-reset" type="button">Reset</button></div></article>@endif
    <article class="card utility"><h3>Concert programme</h3>@if($concert->programForDisplay())<p class="muted">View the program supplied for this concert.</p><a class="button secondary" href="{{ $concert->programForDisplay() }}" target="_blank" rel="noopener">Open program</a>@else<p class="muted">No program is available for this concert.</p>@endif</article>
    <article class="card utility"><h3>Photo gallery</h3>@if($concert->galleryForDisplay())<p class="muted">Continue to the studio's external gallery.</p><a class="button secondary" href="{{ $concert->galleryForDisplay() }}" target="_blank" rel="noopener">Open gallery</a>@else<p class="muted">No external gallery is available.</p>@endif</article>
</div>
</div></section>
@endsection

@push('scripts')
@if($assets->isNotEmpty())
    @if($playerScriptAvailable)
        @vite('resources/js/concert-player.js')
    @endif
@endif
@endpush
