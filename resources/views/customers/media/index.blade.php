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
    button.media-card { width:100%; border:1px solid var(--line); border-radius:var(--radius); background:#fff; font:inherit; text-align:left; cursor:pointer; }
    .media-card:hover, .media-card:focus-visible { outline:2px solid var(--accent); outline-offset:2px; }
    .media-card-image { display:grid; place-items:center; width:100%; aspect-ratio:4/3; background:#14212a; color:#dbeaf0; overflow:hidden; }
    .media-card-image img { width:100%; height:100%; object-fit:contain; }
    .media-card-body { padding:17px; }
    .media-card-body h3 { margin:0 0 6px; font-size:19px; }
    .media-card-body p { margin:0 0 8px; }
    .media-card-status { color:#12668c; font-weight:750; font-size:14px; }
    .compact-media-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,230px)); gap:14px; }
    .media-grid-more { display:flex; justify-content:flex-end; margin-top:14px; }
    .media-grid-more[hidden] { display:none; }
    .media-grid-more button { border:0; background:transparent; color:#0879a7; font:inherit; font-weight:850; cursor:pointer; padding:7px 0; }
    .media-grid-more button:hover,.media-grid-more button:focus-visible { text-decoration:underline; text-underline-offset:4px; }
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
    .account-notifications { display:grid; gap:10px; margin-bottom:22px; }
    .account-notification { display:flex; align-items:center; justify-content:space-between; gap:18px; padding:15px 18px; border:1px solid #9bdcf4; border-radius:10px; background:#eefaff; text-decoration:none; }
    .account-notification strong { display:block; }
    .media-account-actions { display:flex; justify-content:flex-end; gap:9px; margin-bottom:22px; }
    .media-account-actions a { display:inline-flex; align-items:center; gap:8px; }
    .media-account-actions svg { width:19px; height:19px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
    .media-account-count { display:grid; min-width:23px; height:23px; place-items:center; padding:0 6px; border-radius:999px; background:#087bab; color:#fff; font-size:12px; }
    .media-access-dialog { width:min(560px,calc(100% - 28px)); padding:0; border:0; border-radius:16px; background:#fff; color:var(--ink); box-shadow:0 24px 80px #07151d66; }
    .media-access-dialog::backdrop { background:#07151db8; backdrop-filter:blur(2px); }
    .media-access-dialog-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; padding:22px 24px 18px; border-bottom:1px solid var(--line); }
    .media-access-dialog-head h2 { margin:0; font-size:25px; }
    .media-access-dialog-close { border:0; background:transparent; color:var(--muted); font:inherit; font-size:25px; line-height:1; cursor:pointer; }
    .media-access-dialog-body { padding:22px 24px 24px; }
    .media-access-event { margin:0 0 16px; padding:12px 14px; border-radius:9px; background:#edf8fc; color:#075f84; font-weight:800; }
    .media-access-dialog .error { margin-top:10px; color:#b42318; }
</style>
<section class="hero"><div class="container"><div class="eyebrow">Your DancePro account</div><h1>My Media</h1><p class="lead">Your competition galleries and concert media, together.</p></div></section>
<section class="section"><div class="container">
    <nav class="media-account-actions" aria-label="Saved media"><a class="button secondary" href="{{ route('customer.media.favourites') }}">♡ Favourites @if($selectionCounts['favourites'])<span class="media-account-count">{{ $selectionCounts['favourites'] }}</span>@endif</a><a class="button secondary" href="{{ route('customer.media.cart') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>Cart @if($selectionCounts['cart'])<span class="media-account-count">{{ $selectionCounts['cart'] }}</span>@endif</a></nav>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($notifications->isNotEmpty())<div class="account-notifications" aria-label="Account notifications">@foreach($notifications as $notification)<a class="account-notification" href="{{ $notification->data['url'] ?? route('customer.media.index') }}"><span><strong>{{ $notification->data['title'] ?? 'DancePro update' }}</strong><span class="meta">{{ $notification->data['body'] ?? '' }}</span></span><span aria-hidden="true">→</span></a>@endforeach</div>@endif
    @if($creditBalance > 0)<div class="account-credit"><div><strong style="font-size:16px;color:inherit">Account credit</strong><div class="meta">Available to use on your next DancePro media order.</div></div><strong>${{ number_format($creditBalance / 100, 2) }}</strong></div>@endif
    <section class="card card-body" style="margin-bottom:32px" aria-labelledby="add-media-heading">
        <h2 id="add-media-heading">Add media</h2>
        <div class="add-media-options">
            <section class="add-media-option" id="add-competition">
                <div class="add-media-option-head"><span class="add-media-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 15l5-5 4 4 3-3 6 6"/><circle cx="16.5" cy="8.5" r="1"/></svg></span><h3>Add competition media</h3></div>
                @if($dancers->isEmpty())
                    <p>Add a dancer in My Profile before adding a competition.</p>
                    <a class="button" href="{{ route('customer.profile.edit', ['tab' => 'dancers']) }}">Add now</a>
                @else
                    <form method="POST" action="{{ route('customer.competitions.store') }}">@csrf
                        <label>Competition event<select name="event_uuid" required><option value="">Select an event</option>@foreach($availableEvents as $event)<option value="{{ $event->uuid }}" @selected(old('event_uuid', request()->query('event')) === $event->uuid)>{{ $event->name }} · {{ $event->event_date?->format('j M Y') }}</option>@endforeach</select></label>
                        @error('event_uuid')<p role="alert">{{ $message }}</p>@enderror
                        <label>Event media access code<input type="password" name="login_code" required maxlength="64" autocomplete="off"></label>
                        @error('login_code')<p role="alert">{{ $message }}</p>@enderror
                        <button class="button">Add competition media</button>
                    </form>
                @endif
            </section>
            <section class="add-media-option">
                <div class="add-media-option-head"><span class="add-media-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16v12H4zM8 7l1-3h6l1 3"/><circle cx="12" cy="13" r="3"/></svg></span><h3>View/order concert photos</h3></div>
                @if($studios->isEmpty())
                    <p>Add your studio in My Profile to view and order your concert photos.</p>
                    <a class="button" href="{{ route('customer.profile.edit', ['tab' => 'studios']) }}">Add now</a>
                @else
                    @foreach($studios as $studio)<p><a class="button secondary" href="{{ route('customer.profile.studio', $studio) }}">{{ $studio->name }}</a></p>@endforeach
                @endif
            </section>
            <section class="add-media-option">
                <div class="add-media-option-head"><span class="add-media-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M10 8l6 4-6 4z"/></svg></span><h3>Download concert video</h3></div>
                @if($studios->isEmpty())
                    <p>Add your studio in My Profile to access your concert videos.</p>
                    <a class="button" href="{{ route('customer.profile.edit', ['tab' => 'studios']) }}">Add now</a>
                @else
                    <form method="POST" action="{{ route('customer.media.concerts.store') }}">@csrf
                        <label>Concert year<select name="year" required><option value="">Select a year</option>@foreach($videoYears as $year)<option value="{{ $year }}" @selected((string) old('year') === (string) $year)>{{ $year }}</option>@endforeach</select></label>
                        @if($videoYears->isEmpty())<p>No concert videos are listed for your studios yet.</p>@endif
                        @error('year')<p role="alert">{{ $message }}</p>@enderror
                        <label>Video access code<input type="password" name="concert_code" required maxlength="255" autocomplete="off"></label>
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
                    <div class="empty"><p>No ready routines to recommend yet.</p><a href="{{ route('customer.profile.edit', ['tab' => $group === 'dancers' ? 'dancers' : 'studios']) }}">Check your profile</a></div>
                @else
                    <div class="compact-media-grid" data-collapsible-grid>
                        @foreach($recommendations[$group] as $suggestion)
                            @php($event = $suggestion['event'])
                            @if($suggestion['registered'])
                            <a class="card media-card compact-media-card" href="{{ route('customer.competitions.show', ['schedulingEvent' => $event, 'folder' => $suggestion['routine']['uuid']]) }}">
                            @else
                            <button class="card media-card compact-media-card" type="button" data-open-event-access data-event-uuid="{{ $event->uuid }}" data-event-title="{{ $event->name }}">
                            @endif
                                <div class="media-card-image">
                                    @if($suggestion['photo'])<img loading="lazy" src="{{ route('customer.media.recommendations.thumbnail', ['schedulingEvent' => $event, 'item' => $suggestion['routine']['uuid'], 'photo' => $suggestion['photo']]) }}" alt="Watermarked preview of {{ $suggestion['routine']['label'] }}">
                                    @elseif($event->crewLogoUrl())<img loading="lazy" src="{{ $event->crewLogoUrl() }}" alt="">
                                    @else<span>Routine photo coming soon</span>@endif
                                </div>
                                <div class="media-card-body"><h3>{{ $event->name }}</h3><p class="meta" title="{{ $suggestion['routine']['section'] }}">{{ $suggestion['routine']['section'] }}</p><p title="{{ $suggestion['routine']['label'] }}">{{ $suggestion['routine']['label'] }}</p>
                                    <span class="media-card-status">{{ $suggestion['registered'] ? 'View routine →' : 'Add event login to view →' }}</span>
                                </div>
                            @if($suggestion['registered'])</a>@else</button>@endif
                        @endforeach
                    </div>
                    <div class="media-grid-more" data-grid-more hidden><button type="button" aria-expanded="false">View more ↓</button></div>
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
                @else<div class="compact-media-grid" data-collapsible-grid>
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
                </div><div class="media-grid-more" data-grid-more hidden><button type="button" aria-expanded="false">View more ↓</button></div>@endif
            </div>
        @endforeach
    </section>
    <section class="media-section" aria-labelledby="events-heading">
        <div class="media-section-head"><h2 id="events-heading">My Events</h2></div>
        @if(empty($events))
            <div class="empty"><p>Events you add, or can view through your studio, will appear here.</p></div>
        @else
            <div class="compact-media-grid" data-collapsible-grid>
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
            <div class="media-grid-more" data-grid-more hidden><button type="button" aria-expanded="false">View more ↓</button></div>
        @endif
    </section>
    @php($accessEventUuid = old('event_uuid', request()->query('event')))
    @php($accessEvent = $accessEventUuid ? $availableEvents->firstWhere('uuid', $accessEventUuid) : null)
    <dialog class="media-access-dialog" id="media-access-dialog" @if($errors->has('event_uuid') || $errors->has('login_code')) data-open-on-load @endif>
        <div class="media-access-dialog-head"><div><div class="eyebrow">Competition media access</div><h2>Add this event</h2></div><button class="media-access-dialog-close" type="button" data-close-event-access aria-label="Close">×</button></div>
        <div class="media-access-dialog-body">
            <p>You don't have access to this event yet. Please enter the event media access code to view and order your media.</p>
            <p class="media-access-event" id="media-access-event-name">{{ $accessEvent?->name ?? 'Competition event' }}</p>
            <form method="POST" action="{{ route('customer.competitions.store') }}">@csrf
                <input type="hidden" name="event_uuid" id="media-access-event-uuid" value="{{ $accessEventUuid }}">
                <label>Event media access code<input type="password" name="login_code" required maxlength="64" autocomplete="off" autofocus></label>
                @error('event_uuid')<p class="error" role="alert">{{ $message }}</p>@enderror
                @error('login_code')<p class="error" role="alert">{{ $message }}</p>@enderror
                <div class="actions"><button class="button">Add competition media</button><button class="button secondary" type="button" data-close-event-access>Cancel</button></div>
            </form>
        </div>
    </dialog>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
const accessDialog = document.getElementById('media-access-dialog');
const accessEventUuid = document.getElementById('media-access-event-uuid');
const accessEventName = document.getElementById('media-access-event-name');
document.querySelectorAll('[data-open-event-access]').forEach(button => button.addEventListener('click', () => {
    accessEventUuid.value = button.dataset.eventUuid;
    accessEventName.textContent = button.dataset.eventTitle;
    accessDialog.showModal();
}));
document.querySelectorAll('[data-close-event-access]').forEach(button => button.addEventListener('click', () => accessDialog.close()));
accessDialog?.addEventListener('click', event => {
    if (event.target === accessDialog) accessDialog.close();
});
if (accessDialog?.hasAttribute('data-open-on-load')) accessDialog.showModal();

const mediaGridControllers = new Map();
document.querySelectorAll('[data-collapsible-grid]').forEach(grid => {
    const control = grid.nextElementSibling?.matches('[data-grid-more]') ? grid.nextElementSibling : null;
    const button = control?.querySelector('button');
    const cards = [...grid.children];
    if (!control || !button || cards.length < 2) return;
    let expanded = false;

    const render = () => {
        cards.forEach(card => { card.hidden = false; });
        if (expanded || grid.closest('[hidden]')) {
            control.hidden = cards.length < 2;
            return;
        }
        const firstRowTop = cards[0]?.offsetTop;
        const hiddenCards = cards.filter(card => card.offsetTop > firstRowTop + 2);
        hiddenCards.forEach(card => { card.hidden = true; });
        control.hidden = hiddenCards.length === 0;
    };
    button.addEventListener('click', () => {
        expanded = !expanded;
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        button.textContent = expanded ? 'View less ↑' : 'View more ↓';
        render();
    });
    mediaGridControllers.set(grid, render);
    render();
});
let mediaGridResizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(mediaGridResizeTimer);
    mediaGridResizeTimer = setTimeout(() => mediaGridControllers.forEach(render => render()), 100);
});

document.querySelectorAll('[data-media-tabs]').forEach(group => {
    const tabs = [...group.querySelectorAll('[data-media-tab]')];
    function activate(tab) {
        tabs.forEach(candidate => {
            const selected = candidate === tab;
            candidate.setAttribute('aria-selected', selected ? 'true' : 'false');
            candidate.tabIndex = selected ? 0 : -1;
            document.getElementById(candidate.dataset.mediaTab).hidden = !selected;
        });
        requestAnimationFrame(() => {
            const panel = document.getElementById(tab.dataset.mediaTab);
            panel?.querySelectorAll('[data-collapsible-grid]').forEach(grid => mediaGridControllers.get(grid)?.());
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
