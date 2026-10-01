@props(['title', 'description' => null, 'studio' => null, 'event' => null, 'backUrl' => null, 'backLabel' => null, 'release' => false])

@once
@push('styles')
<style>
    .gallery-page-heading { margin-bottom:24px; }
    .gallery-page-back { display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px; }
    .gallery-page-summary { display:flex;align-items:center;gap:16px;flex-wrap:wrap; }
    .gallery-page-copy { flex:1;min-width:min(220px,100%); }
    .gallery-page-copy h2 { margin:0;font-size:22px;line-height:1.3;overflow-wrap:anywhere; }
    .gallery-page-copy p { margin:6px 0 0; }
    .gallery-page-action { margin-left:auto; }
    .gallery-view-tabs { display:flex;gap:0;margin:20px 0 0;border-bottom:1px solid var(--line); }
    .gallery-view-tabs a { padding:11px 18px;color:var(--muted);font-weight:750;border-bottom:3px solid transparent;text-decoration:none; }
    .gallery-view-tabs a[aria-current="page"] { color:var(--brand-strong);border-bottom-color:var(--brand); }
    .gallery-view-tabs a:hover,.gallery-view-tabs a:focus-visible { color:var(--brand-strong);text-decoration:none; }
    @media(max-width:600px){.gallery-page-action{margin-left:0;width:100%}}
</style>
@endpush
@endonce
<section class="gallery-page-heading">
    @if($backUrl)<nav class="gallery-page-back" aria-label="Gallery navigation"><a class="button secondary" href="{{ $backUrl }}">← {{ $backLabel }}</a></nav>@endif
    <div class="card card-pad gallery-page-summary">
        @if($studio || $event)<x-event-artwork :url="$studio?->logoUrl() ?? $event?->crewLogoUrl()" :name="$studio?->name ?? $title" />@endif
        <div class="gallery-page-copy">
            <h2>{{ $title }}</h2>
            @if($event)<p>{{ $studio?->name ?? 'Studio not linked' }} · {{ $event->concertBookingItem?->title ?: $event->name }} · {{ $event->event_date?->format('j M Y') ?? 'Date to be confirmed' }}</p>@endif
            @if($description)<p class="muted">{{ $description }}</p>@endif
        </div>
        @if($release && $event)<a class="button gallery-page-action" href="{{ route('admin.concert-tracker.tasks.show', [$event, 'photo_release']) }}#prepare-release">Photo release</a>@endif
    </div>
    @if($event)
    <nav class="gallery-view-tabs" aria-label="Photo gallery views">
        <a href="{{ route('admin.concert-photo-gallery.show', $event) }}" @if(!request()->routeIs('admin.top-pics.*')) aria-current="page" @endif>Albums</a>
        <a href="{{ route('admin.top-pics.show', $event) }}" @if(request()->routeIs('admin.top-pics.*')) aria-current="page" @endif>Top Pics</a>
    </nav>
    @endif
</section>
