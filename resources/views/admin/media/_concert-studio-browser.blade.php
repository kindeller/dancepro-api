@php($indexParameters = $topPicsOnly ? ['type' => 'concert'] : [])
<x-admin.gallery-heading :title="$studio?->name ?? ($unlinked ? 'Unlinked events' : 'Studios')" :studio="$studio"
    :description="$showEvents ? ($topPicsOnly ? 'Choose an event to browse its Top Pics.' : 'Choose an event to browse its photo albums.') : 'Choose a studio to browse its concert events.'"
    :back-url="$showEvents ? route($indexRoute, $indexParameters) : null" back-label="Back to studios" />
@if($showEvents)
<x-gallery-breadcrumbs :items="[['label' => 'Studios', 'url' => route($indexRoute, $indexParameters)], ['label' => $studio?->name ?? 'Unlinked events']]" />
@endif
<div class="gallery-navigation-grid concert-browser-grid">
@forelse($cards as $card)
    @if(!$showEvents)
        <x-studio-gallery-card :studio="$card" :href="route($indexRoute, $indexParameters + ['studio' => $card->uuid])" />
    @else
        <x-gallery-navigation-card
            :title="$card->concertBookingItem?->title ?: $card->name"
            :image="$card->cover_uuid ? route('admin.concert-photo-gallery.image', ['schedulingEvent' => $card, 'photo' => $card->cover_uuid, 'size' => 'cover']) : null"
            :subtitle="$card->event_date?->format('j M Y') ?? 'Date to be confirmed'"
            :href="route($eventRoute, $card)" :action="$topPicsOnly ? 'View Top Pics' : 'View gallery'" />
    @endif
@empty
    <section class="card card-pad"><p>{{ $topPicsOnly ? 'No concert Top Pics yet. Star photos in the uploader and upload them to get started.' : 'No concert events in this view yet.' }}</p></section>
@endforelse
@if($unlinkedCount)
    <x-gallery-navigation-card title="Unlinked events" :subtitle="$unlinkedCount.' '.Str::plural('event', $unlinkedCount).' without a studio'" :href="route($indexRoute, $indexParameters + ['unlinked' => 1])" action="View events" />
@endif
</div>
@if($cards->hasPages())<nav class="toolbar" style="justify-content:flex-start;gap:16px;margin-top:22px" aria-label="Gallery pages">@if($cards->previousPageUrl())<a class="button secondary" href="{{ $cards->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $cards->currentPage() }} of {{ $cards->lastPage() }}</span>@if($cards->nextPageUrl())<a class="button secondary" href="{{ $cards->nextPageUrl() }}">Next</a>@endif</nav>@endif
