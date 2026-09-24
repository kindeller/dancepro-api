@extends('layouts.public', ['title' => 'My Media', 'footerLabel' => 'DancePro Media'])
@section('content')
<style>
    .media-section { margin: 0 0 34px; }
    .media-section-head { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
    .media-section-head h2 { margin:0; }
    .media-tabs { display:inline-flex; gap:4px; padding:4px; border:1px solid var(--line); border-radius:999px; background:#eef4f7; }
    .media-tabs button { border:0; border-radius:999px; padding:8px 16px; background:transparent; color:var(--ink); font:inherit; font-weight:750; cursor:pointer; }
    .media-tabs button[aria-selected="true"] { background:var(--ink); color:#fff; }
    .media-card { display:block; padding:0; color:inherit; text-decoration:none; }
    .media-card:hover, .media-card:focus-visible { outline:2px solid var(--accent); outline-offset:2px; }
    .media-card-image { display:grid; place-items:center; width:100%; aspect-ratio:4/3; background:#14212a; color:#dbeaf0; overflow:hidden; }
    .media-card-image img { width:100%; height:100%; object-fit:contain; }
    .media-card-body { padding:17px; }
    .media-card-body h3 { margin:0 0 6px; font-size:19px; }
    .media-card-body p { margin:0 0 8px; }
    .media-card-status { color:#12668c; font-weight:750; font-size:14px; }
    .compact-media-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,230px)); gap:14px; }
    .compact-media-card .media-card-body { display:grid; gap:4px; padding:12px; }
    .compact-media-card .media-card-body > * { min-width:0; margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .compact-media-card .media-card-body h3 { font-size:15px; line-height:1.3; overflow:visible; text-overflow:clip; white-space:normal; overflow-wrap:anywhere; }
    .compact-media-card .media-card-body p, .compact-media-card .media-card-status { font-size:13px; }
    .media-card-image { position:relative; }
    .media-review-badge { position:absolute; top:8px; left:8px; padding:3px 7px; border-radius:5px; background:#fff; color:#263640; font-size:11px; font-weight:800; }
    @media (max-width: 520px) { .compact-media-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; } }
    .add-media-options { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr)); gap:16px; align-items:stretch; }
    .add-media-option { padding:20px; border:1px solid var(--line); border-radius:12px; background:#f8fbfc; }
    .add-media-option-head { display:flex; align-items:center; gap:12px; min-height:46px; margin-bottom:18px; }
    .add-media-option-head h3 { margin:0; font-size:19px; line-height:1.25; }
    .add-media-icon { display:grid; flex:0 0 42px; width:42px; height:42px; place-items:center; border-radius:10px; background:#e2f4fb; color:#0879a6; }
    .add-media-option:nth-child(2) .add-media-icon { background:#edf0fc; color:#465bb5; }
    .add-media-option:nth-child(3) .add-media-icon { background:#ebf7ef; color:#267448; }
    .add-media-icon svg { width:24px; height:24px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
    .add-media-option p { margin-bottom:14px; }
    .add-media-option .button { margin-top:8px; }
    .account-credit { display:flex; align-items:center; justify-content:space-between; gap:18px; margin-bottom:22px; padding:18px 20px; border:1px solid #9bdcf4; border-radius:12px; background:#eaf8fd; }
    .account-credit strong { font-size:24px; color:#0879a6; white-space:nowrap; }
</style>
<section class="hero"><div class="container"><div class="eyebrow">Your DancePro account</div><h1>My Media</h1><p class="lead">Your competition galleries and concert media, together.</p></div></section>
<section class="section"><div class="container">
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($creditBalance > 0)<div class="account-credit"><div><strong style="font-size:16px;color:inherit">Account credit</strong><div class="meta">Available to use on your next DancePro media order.</div></div><strong>${{ number_format($creditBalance / 100, 2) }}</strong></div>@endif
    <section class="card card-body" style="margin-bottom:32px" aria-labelledby="add-media-heading">
        <h2 id="add-media-heading">Add media</h2>
        <div class="add-media-options">
            <section class="add-media-option" id="add-competition">
                <div class="add-media-option-head"><span class="add-media-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 15l5-5 4 4 3-3 6 6"/><circle cx="16.5" cy="8.5" r="1"/></svg></span><h3>Add competition media</h3></div>
                @if($dancers->isEmpty())
                    <p>Add a dancer in My Profile before adding a competition.</p>
                    <a class="button" href="{{ route('customer.profile.edit') }}#dancers">Add now</a>
                @else
                    <form method="POST" action="{{ route('customer.competitions.store') }}">@csrf
                        <label>Competition event<select name="event_uuid" required><option value="">Select an event</option>@foreach($availableEvents as $event)<option value="{{ $event->uuid }}" @selected(old('event_uuid', request()->query('event')) === $event->uuid)>{{ $event->name }} · {{ $event->event_date?->format('j M Y') }}</option>@endforeach</select></label>
                        @error('event_uuid')<p role="alert">{{ $message }}</p>@enderror
                        <label>Event login code<input type="password" name="login_code" required maxlength="64" autocomplete="off"></label>
                        @error('login_code')<p role="alert">{{ $message }}</p>@enderror
                        <button class="button">Add competition media</button>
                    </form>
                @endif
            </section>
            <section class="add-media-option">
                <div class="add-media-option-head"><span class="add-media-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16v12H4zM8 7l1-3h6l1 3"/><circle cx="12" cy="13" r="3"/></svg></span><h3>View/order concert photos</h3></div>
                @if($studios->isEmpty())
                    <p>Add your studio in My Profile to view and order your concert photos.</p>
                    <a class="button" href="{{ route('customer.profile.edit') }}#studios">Add now</a>
                @else
                    @foreach($studios as $studio)<p><a class="button secondary" href="{{ route('customer.profile.studio', $studio) }}">{{ $studio->name }}</a></p>@endforeach
                @endif
            </section>
            <section class="add-media-option">
                <div class="add-media-option-head"><span class="add-media-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M10 8l6 4-6 4z"/></svg></span><h3>Download concert video</h3></div>
                @if($studios->isEmpty())
                    <p>Add your studio in My Profile to access your concert videos.</p>
                    <a class="button" href="{{ route('customer.profile.edit') }}#studios">Add now</a>
                @else
                    <form method="POST" action="{{ route('customer.media.concerts.store') }}">@csrf
                        <label>Concert year<select name="year" required><option value="">Select a year</option>@foreach($videoYears as $year)<option value="{{ $year }}" @selected((string) old('year') === (string) $year)>{{ $year }}</option>@endforeach</select></label>
                        @if($videoYears->isEmpty())<p>No concert videos are listed for your studios yet.</p>@endif
                        @error('year')<p role="alert">{{ $message }}</p>@enderror
                        <label>Video login code<input type="password" name="concert_code" required maxlength="255" autocomplete="off"></label>
                        @error('concert_code')<p role="alert">{{ $message }}</p>@enderror
                        <button class="button">Download concert video</button>
                    </form>
                @endif
            </section>
        </div>
    </section>
    <section class="media-section" aria-labelledby="recommended-heading" data-media-tabs>
        <div class="media-section-head"><h2 id="recommended-heading">Recommended Media</h2>
            <div class="media-tabs" role="tablist" aria-label="Recommended media">
                <button type="button" role="tab" id="recommended-dancers-tab" aria-controls="recommended-dancers" aria-selected="true" data-media-tab="recommended-dancers">My Dancers</button>
                <button type="button" role="tab" id="recommended-studios-tab" aria-controls="recommended-studios" aria-selected="false" tabindex="-1" data-media-tab="recommended-studios">My Studio</button>
            </div>
        </div>
        @foreach(['dancers' => 'My Dancers', 'studios' => 'My Studio'] as $group => $label)
            <div id="recommended-{{ $group }}" role="tabpanel" aria-labelledby="recommended-{{ $group }}-tab" @if($group !== 'dancers') hidden @endif>
                @if(empty($recommendations[$group]))
                    <div class="empty"><p>No ready routines to recommend yet.</p><a href="{{ route('customer.profile.edit') }}#{{ $group === 'dancers' ? 'dancers' : 'studios' }}">Check your profile</a></div>
                @else
                    <div class="compact-media-grid">
                        @foreach($recommendations[$group] as $suggestion)
                            @php($event = $suggestion['event'])
                            <a class="card media-card compact-media-card" href="{{ $suggestion['registered'] ? route('customer.competitions.show', ['schedulingEvent' => $event, 'folder' => $suggestion['routine']['uuid']]) : route('customer.media.index', ['event' => $event->uuid]).'#add-competition' }}">
                                <div class="media-card-image">
                                    @if($suggestion['photo'])<img loading="lazy" src="{{ route('customer.media.recommendations.thumbnail', ['schedulingEvent' => $event, 'item' => $suggestion['routine']['uuid'], 'photo' => $suggestion['photo']]) }}" alt="Watermarked preview of {{ $suggestion['routine']['label'] }}">
                                    @elseif($event->crewLogoUrl())<img loading="lazy" src="{{ $event->crewLogoUrl() }}" alt="">
                                    @else<span>Routine photo coming soon</span>@endif
                                </div>
                                <div class="media-card-body"><h3>{{ $event->name }}</h3><p class="meta" title="{{ $suggestion['routine']['section'] }}">{{ $suggestion['routine']['section'] }}</p><p title="{{ $suggestion['routine']['label'] }}">{{ $suggestion['routine']['label'] }}</p>
                                    <span class="media-card-status">{{ $suggestion['registered'] ? 'View routine →' : 'Add event login to view →' }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </section>
    <section class="media-section" aria-labelledby="purchased-heading" data-media-tabs>
        <div class="media-section-head"><h2 id="purchased-heading">My Media</h2><a class="button secondary" href="{{ route('customer.media.orders.index') }}">My orders</a>
            <div class="media-tabs" role="tablist" aria-label="Purchased media">
                <button type="button" role="tab" id="purchased-photos-tab" aria-controls="purchased-photos" aria-selected="true" data-media-tab="purchased-photos">Photos</button>
                <button type="button" role="tab" id="purchased-videos-tab" aria-controls="purchased-videos" aria-selected="false" tabindex="-1" data-media-tab="purchased-videos">Videos</button>
            </div>
        </div>
        @foreach(['photos' => 'Photos', 'videos' => 'Videos'] as $type => $label)
            <div id="purchased-{{ $type }}" role="tabpanel" aria-labelledby="purchased-{{ $type }}-tab" @if($type !== 'photos') hidden @endif>
                @if(empty($mediaLibrary[$type]))<div class="empty"><p>No purchased {{ strtolower($label) }} are linked to your account yet.</p></div>
                @else<div class="compact-media-grid">
                    @foreach($mediaLibrary[$type] as $purchase)
                        @php($event = $purchase['event'])
                        <a class="card media-card compact-media-card" href="{{ $purchase['can_browse'] ? route('customer.media.library.show', ['kind' => $purchase['kind'], 'event' => $event->uuid]) : route('customer.media.index', ['event' => $event->uuid]).'#add-competition' }}">
                            <div class="media-card-image">
                                @if($purchase['thumbnail_url'])<img loading="lazy" src="{{ $purchase['thumbnail_url'] }}" alt="">
                                @elseif($purchase['kind'] === 'concert' && $event->coverImageForDisplay())<img loading="lazy" src="{{ $event->coverImageForDisplay() }}" alt="">
                                @elseif($purchase['kind'] === 'concert' && $event->studio?->logoUrl())<img loading="lazy" src="{{ $event->studio->logoUrl() }}" alt="">
                                @elseif($purchase['kind'] === 'competition' && $event->crewLogoUrl())<img loading="lazy" src="{{ $event->crewLogoUrl() }}" alt="">
                                @else<span>{{ ucfirst($purchase['kind']) }}</span>@endif
                                @if($purchase['is_preview'])<span class="media-review-badge">Sample</span>@endif
                            </div>
                            <div class="media-card-body"><h3>{{ $purchase['title'] }}</h3><p class="meta">{{ $purchase['kind'] === 'concert' ? $event->event_date?->format('j M Y') : 'Competition' }}</p><span class="media-card-status">{{ $purchase['can_browse'] ? 'View media →' : 'Add event login to view →' }}</span></div>
                        </a>
                    @endforeach
                </div>@endif
            </div>
        @endforeach
    </section>
    <section class="media-section" aria-labelledby="events-heading">
        <div class="media-section-head"><h2 id="events-heading">My Events</h2></div>
        @if(empty($events))
            <div class="empty"><p>Events you add, or can view through your studio, will appear here.</p></div>
        @else
            <div class="compact-media-grid">
                @foreach($events as $entry)
                    @php($event = $entry['event'])
                    @php($externalGallery = $entry['kind'] === 'concert' && $entry['photos'] && !$entry['video'] && !$entry['native_photos'])
                    @php($eventUrl = $entry['kind'] === 'competition' ? ($entry['gallery'] ? route('customer.competitions.show', $event) : route('customer.media.index', ['event' => $event->uuid]).'#add-competition') : (($entry['native_photos'] ?? false) && !$entry['video'] ? route('customer.concerts.photos.show', $event) : ($externalGallery ? $event->galleryForDisplay() : route('concerts.show', $event))))
                    <a class="card media-card compact-media-card" href="{{ $eventUrl }}" @if($externalGallery) target="_blank" rel="noopener" @endif>
                        <div class="media-card-image">
                            @if($entry['kind'] === 'concert' && ($entry['thumbnail_url'] ?? null))<img loading="lazy" src="{{ $entry['thumbnail_url'] }}" alt="">
                            @elseif($entry['kind'] === 'concert' && $event->coverImageForDisplay())<img loading="lazy" src="{{ $event->coverImageForDisplay() }}" alt="">
                            @elseif($entry['kind'] === 'concert' && $event->studio?->logoUrl())<img loading="lazy" src="{{ $event->studio->logoUrl() }}" alt="">
                            @elseif($entry['kind'] === 'competition' && $event->crewLogoUrl())<img loading="lazy" src="{{ $event->crewLogoUrl() }}" alt="">
                            @else<span>{{ ucfirst($entry['kind']) }}</span>@endif
                        </div>
                        <div class="media-card-body">
                            <h3>{{ $entry['title'] }}</h3>
                            <p class="meta">{{ ucfirst($entry['kind']) }}</p>
                            <span class="media-card-status">{{ $entry['kind'] === 'competition' && !$entry['gallery'] ? 'Add event login to view →' : 'View event →' }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.querySelectorAll('[data-media-tabs]').forEach(group => {
    const tabs = [...group.querySelectorAll('[data-media-tab]')];
    function activate(tab) {
        tabs.forEach(candidate => {
            const selected = candidate === tab;
            candidate.setAttribute('aria-selected', selected ? 'true' : 'false');
            candidate.tabIndex = selected ? 0 : -1;
            document.getElementById(candidate.dataset.mediaTab).hidden = !selected;
        });
    }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab));
        tab.addEventListener('keydown', event => {
            if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
            event.preventDefault();
            const next = tabs[(index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
            activate(next);
            next.focus();
        });
    });
});
</script>
@endpush
