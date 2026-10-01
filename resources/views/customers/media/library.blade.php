@extends('layouts.public', ['title' => $title.' · My Media', 'footerLabel' => 'DancePro Media'])
@section('headerParentUrl', ($selectedFolder ?? null) ? route('customer.media.library.show', ['kind' => $kind, 'event' => $event->uuid]) : route('customer.media.index', ['tab' => 'purchases']))

@push('styles')
<style>
    .library-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,230px)); gap:14px; align-items:start; }
    .library-folders { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,240px)); gap:16px; align-items:start; }
    .library-folder { display:flex; min-width:0; overflow:hidden; flex-direction:column; text-decoration:none; }
    .library-folder:hover,.library-folder:focus-visible { border-color:var(--accent); box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 16%,transparent); outline:0; }
    .library-folder-cover { display:grid; width:100%; aspect-ratio:4/3; place-items:center; background:#14212a; color:#8ddcff; font-size:36px; object-fit:cover; }
    .library-folder-copy { display:flex; min-height:142px; flex:1; flex-direction:column; padding:16px 18px; }
    .library-folder-context { color:var(--muted); font-size:13px; font-weight:750; line-height:1.35; }
    .library-folder-title { margin-top:5px; font-size:18px; font-weight:850; line-height:1.3; }
    .library-folder-meta { margin-top:6px; color:var(--muted); font-size:13px; }
    .library-folder-action { margin-top:auto; padding-top:14px; color:#0879a7; font-weight:850; }
    .library-item { overflow:hidden; }
    .library-image { position:relative; display:grid; place-items:center; aspect-ratio:4/3; background:#14212a; color:#fff; }
    .library-image img { width:100%; height:100%; object-fit:contain; }
    .library-item-title { padding:12px; margin:0; font-size:14px; overflow-wrap:anywhere; }
    .library-download { position:absolute; right:9px; bottom:9px; display:grid; width:42px; height:40px; place-items:center; border:1px solid #ffffffaa; border-radius:9px; background:#087eaeed; color:#fff; text-decoration:none; box-shadow:0 4px 13px #0005; }
    .library-download.downloaded { background:#18794e; }
    .library-download svg { width:21px; height:21px; fill:none; stroke:currentColor; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .library-download:hover::after,.library-download:focus-visible::after { position:absolute; right:0; bottom:calc(100% + 7px); padding:7px 9px; border-radius:6px; background:#10212c; color:#fff; content:attr(data-tooltip); font-size:12px; font-weight:700; white-space:nowrap; }
    .library-download-status { display:block; padding:0 12px 12px; color:#47705c; font-size:12px; font-weight:700; }
    .library-note { margin-bottom:24px; }
    @media (max-width:520px) { .library-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; } }
</style>
@endpush

@section('content')
<section class="hero" style="padding:38px 0"><div class="container">
    <div class="eyebrow">{{ ucfirst($kind) }} media</div><h1 style="font-size:clamp(32px,5vw,54px)">{{ $title }}</h1>
    @if($kind === 'concert' && $event->event_date)<p class="lead">{{ $event->event_date->format('j M Y') }}</p>@endif
</div></section>
<section class="section"><div class="container">
    @if($is_preview)<p class="library-note">Sample layout using local review media. This is not a purchase or download.</p>@endif
    @if(!$is_preview && ($selectedFolder ?? null))
        <div style="margin-bottom:24px"><p class="eyebrow" style="color:#087bab">{{ $selectedFolder['context'] }}</p><h2 style="margin-bottom:5px">{{ $selectedFolder['title'] }}</h2><p class="meta">{{ $selectedFolder['assets']->count() }} {{ Str::plural('file', $selectedFolder['assets']->count()) }}</p></div>
    @endif
    @if(!$is_preview && ($selectedFolder ?? null) && $assets->count() > 1)
        @php($totalBytes = (int) $assets->sum('size_bytes'))
        @php($totalSizeLabel = $totalBytes >= 1073741824 ? number_format($totalBytes / 1073741824, 1).' GB' : number_format($totalBytes / 1048576).' MB')
        <div class="actions" style="margin:0 0 24px"><a class="button" href="{{ route('customer.media.library.download-all', ['kind' => $kind, 'event' => $event->uuid, 'routine' => $selectedFolder['uuid']]) }}" data-download-link data-large-download="{{ $totalBytes >= 524288000 ? 'true' : 'false' }}" data-file-size="{{ $totalSizeLabel }}">Download all as ZIP · {{ $totalSizeLabel }}</a></div>
    @endif
    @if($is_preview && $kind === 'competition')
        <div class="library-grid">
            @foreach($items as $item)
                <a class="card library-item" href="{{ $item['preview_url'] }}" data-gallery-preview target="_blank" rel="noopener" aria-label="Open watermarked photo preview">
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
    @elseif(!($selectedFolder ?? null))
        <div class="library-folders">
            @foreach($folders as $folder)
                <a class="card library-folder" href="{{ route('customer.media.library.show', ['kind' => $kind, 'event' => $event->uuid, 'routine' => $folder['uuid']]) }}">
                    @if($folder['cover']?->thumbnail_storage_disk && $folder['cover']?->thumbnail_storage_key)<img class="library-folder-cover" src="{{ route('customer.media.library.thumbnail', ['kind' => $kind, 'event' => $event->uuid, 'asset' => $folder['cover']->uuid, 'size' => 'cover']) }}" alt="">
                    @else<span class="library-folder-cover" aria-hidden="true">▣</span>@endif
                    <span class="library-folder-copy"><span class="library-folder-context">{{ $folder['context'] }}</span><strong class="library-folder-title">{{ $folder['title'] }}</strong><span class="library-folder-meta">{{ $folder['assets']->count() }} {{ Str::plural('file', $folder['assets']->count()) }}</span><span class="library-folder-action">View routine →</span></span>
                </a>
            @endforeach
        </div>
    @elseif($assets->isNotEmpty())
        <div class="library-grid">
            @foreach($assets as $asset)
                @php($entitlement = $entitlementByAsset->get($asset->id))
                @php($download = $downloads->get($asset->id))
                @php($sizeLabel = $asset->size_bytes >= 1073741824 ? number_format($asset->size_bytes / 1073741824, 1).' GB' : number_format($asset->size_bytes / 1048576).' MB')
                @php($isLargeDownload = $asset->size_bytes >= 524288000)
                <div class="card library-item">
                    <div class="library-image">
                        @if($asset->thumbnail_storage_disk && $asset->thumbnail_storage_key)
                            <img loading="lazy" src="{{ route('customer.media.library.thumbnail', ['kind' => $kind, 'event' => $event->uuid, 'asset' => $asset->uuid]) }}" alt="{{ $asset->display_name ?? $asset->original_filename }} thumbnail">
                        @else<span>{{ ucfirst($asset->media_type->value) }}</span>@endif
                        @if($entitlement)
                            <a class="library-download {{ $download ? 'downloaded' : '' }}" href="{{ route('customer.purchases.download', [$entitlement, $asset]) }}" data-tooltip="{{ $download ? 'Download again' : 'Download' }} · {{ $sizeLabel }}" aria-label="{{ $download ? 'Download again' : 'Download' }} {{ $asset->display_name ?? $asset->original_filename }}, {{ $sizeLabel }}" data-download-link data-file-size="{{ $sizeLabel }}" data-large-download="{{ $isLargeDownload ? 'true' : 'false' }}" @if($download) data-repeat-download data-media-type="{{ $asset->media_type->value }}" data-last-downloaded="{{ $download->last_download_at?->format('j F Y') }}" @endif><svg viewBox="0 0 24 24">@if($download)<path d="m5 12 4 4L19 6"/>@else<path d="M12 3v12m-5-5 5 5 5-5M5 20h14"/>@endif</svg></a>
                        @endif
                    </div>
                    <p class="library-item-title">{{ $asset->display_name ?? $asset->original_filename }}</p>
                    @if($download)<span class="library-download-status">Download requested {{ $download->download_count }} {{ Str::plural('time', $download->download_count) }} · {{ $download->last_download_at?->format('j M Y') }}</span>@endif
                </div>
            @endforeach
        </div>
    @else
        <div class="empty"><h2>No files available</h2><p>There are no files available here. Check My orders for the current status, or contact us through Help.</p></div>
    @endif
</div></section>
<x-gallery-photo-viewer />
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.querySelectorAll('[data-download-link]').forEach(link => link.addEventListener('click', event => {
    const messages = [];
    if (link.dataset.largeDownload === 'true') messages.push(`This is a large ${link.dataset.fileSize} download. Before continuing, make sure this device has enough free storage. On a phone or tablet, Wi-Fi is recommended.`);
    if (link.hasAttribute('data-repeat-download')) {
        const noun = link.dataset.mediaType === 'video' ? 'video' : 'file';
        messages.push(`A download of this ${noun} was requested on ${link.dataset.lastDownloaded}. Check your device for a saved copy before downloading again.`);
    }
    if (messages.length && !confirm(`${messages.join('\n\n')}\n\nContinue with the download?`)) event.preventDefault();
}));
</script>
@endpush
