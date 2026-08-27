@extends('layouts.public')

@section('content')
<section class="hero"><div class="container"><div class="eyebrow">{{ $concert->studio->name }}</div><h1>{{ $concert->name }}</h1><p class="lead">{{ $concert->description }}</p><div class="meta" style="color:#d9e8ee">{{ $concert->event_date?->format('j F Y') }} @if($concert->venue_name) · {{ $concert->venue_name }} @endif</div></div></section>
<section class="section"><div class="container">
@php($assets = $concert->mediaCollections->flatMap->assets->filter(fn ($asset) => $asset->media_type === \App\Features\Media\Support\MediaType::Video)->values())
@if($assets->isEmpty())
    <div class="empty"><h3>Media is not available yet</h3><p class="muted">This concert has been released, but its playable media is still being prepared.</p></div>
@else
    @php($first = $assets->first())
    @php($playerScriptAvailable = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    <div class="section-head"><div><div class="eyebrow">Concert media</div><h2>Watch the performance</h2></div><div class="meta">{{ $assets->count() }} items</div></div>
    <div class="player-grid">
        <section class="card player"><video id="concert-player" controls preload="metadata" crossorigin="use-credentials" @unless($playerScriptAvailable) src="{{ route('concerts.media.stream', [$concert, $first]) }}" @endunless></video><div class="player-info"><h3 id="player-title">{{ $first->display_name ?? $first->original_filename }}</h3><div class="meta" id="player-status" style="color:#a9bec8">{{ $playerScriptAvailable ? 'Preparing playback…' : 'MP4 playback' }}</div><div class="actions"><label style="margin:0;color:#d9e8ee">Quality <select id="player-quality" disabled><option value="-1">Auto</option></select></label><a id="player-download" class="button" href="{{ $downloadUrls[$first->uuid] }}">Download original</a></div></div></section>
        <aside class="card playlist" aria-label="Concert playlist">
        @foreach($assets as $asset)
            <button class="playlist-item @if($loop->first) active @endif" type="button" data-playback="{{ route('concerts.media.playback', [$concert, $asset]) }}" data-download="{{ $downloadUrls[$asset->uuid] }}" data-title="{{ $asset->display_name ?? $asset->original_filename }}"><span class="playlist-thumb">▶</span><span><strong>{{ $asset->display_name ?? $asset->original_filename }}</strong><br><span class="meta">{{ $asset->duration_seconds ? gmdate('i:s', $asset->duration_seconds) : ucfirst($asset->media_type->value) }}</span></span></button>
        @endforeach
        </aside>
    </div>
    <div class="utility-grid">
        <article class="card utility"><h3>Download manager</h3><p class="muted" id="download-status">Ready to download {{ $assets->count() }} originals. Your browser may ask permission for multiple files.</p><div class="actions"><button class="button secondary" id="download-all" type="button">Start</button><button class="button secondary" id="download-pause" type="button" disabled>Pause</button><button class="button secondary" id="download-reset" type="button">Reset</button></div></article>
        <article class="card utility"><h3>Concert program</h3>@if($concert->program_url)<p class="muted">View the program supplied for this concert.</p><a class="button secondary" href="{{ $concert->program_url }}" target="_blank" rel="noopener">Open program</a>@else<p class="muted">No program is available for this concert.</p>@endif</article>
        <article class="card utility"><h3>Photo gallery</h3>@if($concert->external_gallery_url)<p class="muted">Continue to the studio's external gallery.</p><a class="button secondary" href="{{ $concert->external_gallery_url }}" target="_blank" rel="noopener">Open gallery</a>@else<p class="muted">No external gallery is available.</p>@endif</article>
    </div>
@endif
</div></section>
@endsection

@push('scripts')
@if($assets->isNotEmpty())
    @if($playerScriptAvailable)
        @vite('resources/js/concert-player.js')
    @endif
@endif
@endpush
