<section class="release-workspace" id="prepare-release">
    <div class="card card-pad release-intro">
        <div><p class="release-eyebrow">ONE STUDIO · ONE RELEASE</p><h2>Release photos together</h2><p>Select the events to make available, then review separate emails for customer accounts, gallery guests and Studio Hub accounts. Send all three audiences together.</p></div>
        <div class="release-journey" aria-label="Release steps"><span><b>1</b> Select events</span><span><b>2</b> Release photos</span><span><b>3</b> Review &amp; send emails</span></div>
    </div>
    <form method="post" action="{{ route('admin.concert-photo-releases.store') }}" id="event-photo-release" class="card card-pad release-selection">@csrf
        <div class="release-section-heading"><div><h2>{{ $event->studio?->name ?? 'Studio' }} · {{ $event->event_date?->year }}</h2><p class="muted">Whole events are selected together, including all their photo albums.</p></div><button class="secondary" type="button" id="select-ready-events">Select all ready</button></div>
        <div class="release-event-grid">
        @forelse($releaseEvents as $card)
            @php($session = $card['event'])
            @php($photo = $card['cover'])
            <article class="release-event {{ $card['released'] ? 'is-released' : '' }}">
                <label class="release-event-choice">
                    <input type="checkbox" name="event_uuids[]" value="{{ $session->uuid }}" @checked(in_array($session->uuid, old('event_uuids', []))) @disabled(!$card['ready'])>
                    <span class="release-event-picture">
                    @if($photo && ($photo->metadata['programme_photo_file_uuid'] ?? null))<img src="{{ route('admin.concert-photo-gallery.image', ['schedulingEvent' => $session, 'photo' => $photo->metadata['programme_photo_file_uuid'], 'size' => 'cover']) }}" alt="">
                    @elseif($event->studio?->logoUrl())<img class="studio-logo" src="{{ $event->studio->logoUrl() }}" alt="">
                    @else<span aria-hidden="true">▧</span>@endif
                    </span>
                    <span class="release-event-copy"><span class="badge {{ $card['released'] ? 'success' : ($card['ready'] ? 'status-ready' : 'status-waiting') }}">{{ $card['label'] }}</span><strong>{{ $card['title'] }}</strong><span>{{ $session->event_date?->format('D j M Y') }}</span><span class="release-photo-count">{{ number_format($card['photos']) }} photos · {{ $card['albums'] }} {{ Str::plural('album', $card['albums']) }}</span></span>
                </label>
                <div class="release-event-footer"><span>{{ $card['reason'] ?? 'All required photo uploads are complete.' }}</span><a href="{{ route('admin.photo-uploads.show', $session) }}">View photos →</a></div>
            </article>
        @empty<div class="release-empty">No approved photo events are available for this studio yet.</div>@endforelse
        </div>
        <details class="release-options"><summary>Advanced options</summary><div class="release-option-fields"><label>Release name<input name="name" maxlength="255" required value="{{ old('name', $defaultReleaseName) }}"><small>For your release history. Customers still see the individual event names.</small></label><label>Watermark<select name="watermark_uuid"><option value="">Default concert watermark</option>@foreach($watermarks as $watermark)<option value="{{ $watermark->uuid }}" @selected(old('watermark_uuid') === $watermark->uuid)>{{ $watermark->name }}</option>@endforeach</select></label></div></details>
        <div class="release-confirm"><div><strong id="release-selection-count" role="status">0 events selected</strong><p>Releasing makes these photos available immediately. You’ll review all three messages before sending them with one button.</p></div><button id="release-selected-events" disabled>Release selected events</button></div>
    </form>
    <div class="card card-pad release-customer-path"><div><h3>Easy for families to find</h3><p>One studio gallery, with a card for each event. Release groups don’t add another gallery folder.</p></div><div><span>{{ $event->studio?->name ?? 'Studio' }}</span><b>→</b><span>Choose an event</span><b>→</b><span>Photos</span></div><small>Dress Rehearsal Portraits opens straight to the images. Concerts keep their routine albums.</small></div>
    <section id="release-groups"><h2>Release history &amp; prepared releases</h2>
    @forelse($groups as $group)
        @php($groupEvents = $group->collections->groupBy('concert_id'))
        @php($groupBlockers = $releaseBlockers[$group->id] ?? [])
        <details class="card card-pad release-history" @if(!$group->released_at) open @endif>
            <summary><span class="badge {{ $group->released_at ? 'success' : 'status-waiting' }}">{{ $group->released_at ? 'Released' : ($groupBlockers ? 'Not ready to release' : 'Ready to release') }}</span><strong>{{ $group->name }}</strong><span>{{ $groupEvents->count() }} {{ Str::plural('event', $groupEvents->count()) }}@if($group->released_at) · {{ $group->released_at->format('j M Y, g:i a') }}@endif</span></summary>
            <div class="release-history-events">@foreach($groupEvents as $eventAlbums)<div><strong>{{ $eventAlbums->first()->concert->photoGalleryTitle() }}</strong><span>{{ $eventAlbums->first()->concert->event_date?->format('j M Y') }} · {{ $eventAlbums->count() }} {{ Str::plural('album', $eventAlbums->count()) }}</span></div>@endforeach</div>
            @if($group->released_at)<a class="button secondary" href="{{ route('admin.concert-photo-releases.email', $group) }}">Announcements &amp; delivery history</a>
            @else
                @include('admin.tracking._action-blockers', ['blockers' => $groupBlockers, 'blockerHeading' => 'Gallery cannot be released yet'])
                <form method="post" action="{{ route('admin.concert-photo-releases.release', $group) }}">@csrf<button @disabled(count($groupBlockers) > 0)>Release photos &amp; review emails</button></form>
            @endif
        </details>
    @empty<p class="muted">Your combined releases will appear here, with their announcement history.</p>@endforelse
    </section>
</section>
@push('styles')
<style>
.release-workspace .status-ready{background:#ffe5e7;color:#b42335}.release-workspace{display:grid;gap:24px;margin-top:22px}.release-workspace h2,.release-workspace h3{margin-top:0}.release-intro{display:flex;align-items:center;justify-content:space-between;gap:24px;border-left:5px solid #39a779}.release-eyebrow{color:#247b59;font-size:12px;letter-spacing:.1em;font-weight:800}.release-journey{display:flex;gap:18px;flex-wrap:wrap}.release-journey span{display:flex;align-items:center;gap:8px;white-space:nowrap;font-weight:700;font-size:14px}.release-journey b{display:grid;place-items:center;width:28px;height:28px;background:#e1f5eb;color:#247b59;border-radius:50%}.release-section-heading{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:20px}.release-event-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:16px}.release-event{border:2px solid #dce7ed;border-radius:12px;overflow:hidden;background:#fff}.release-event:has(input:checked){border-color:#0799c7;background:#f2fbff}.release-event-choice{display:flex!important;align-items:center;gap:12px;padding:16px;cursor:pointer;margin:0!important}.release-event-choice input{width:20px!important;height:20px;flex:0 0 20px;margin:0!important;accent-color:#0799c7}.release-event-choice:has(input:disabled){cursor:default}.release-event-picture{display:grid;place-items:center;width:104px;height:100px;flex:0 0 104px;background:#edf5f8;border-radius:8px;overflow:hidden;font-size:40px;color:#7c9aa9}.release-event-picture img{width:100%;height:100%;object-fit:cover}.release-event-picture .studio-logo{object-fit:contain;padding:8px}.release-event-copy{display:flex;align-items:flex-start;flex-direction:column;gap:6px;min-width:0;color:#62727d;font-size:13px;font-weight:500}.release-event-copy strong{font-size:18px;color:#172630;line-height:1.3;overflow-wrap:anywhere}.release-photo-count{font-weight:750;color:#294454}.release-event-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-top:1px solid #e0e9ed;font-size:12px;color:#647783}.release-event-footer a{white-space:nowrap;font-weight:750}.release-options{margin-top:22px;border-top:1px solid #dce7ed;padding-top:16px}.release-option-fields{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-top:14px}.release-option-fields small{display:block;margin-top:6px;font-weight:400}.release-confirm{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-top:22px;padding:20px;background:#edf8f3;border-radius:9px}.release-confirm p{margin-bottom:0;font-size:14px}.release-customer-path{display:flex;align-items:center;gap:18px;flex-wrap:wrap}.release-customer-path>div:first-child{flex:1 1 300px}.release-customer-path>div:nth-child(2){display:flex;gap:10px;align-items:center;flex-wrap:wrap}.release-customer-path span{padding:10px 14px;border-radius:8px;background:#edf5f8;font-weight:700}.release-customer-path small{flex-basis:100%;color:#62727d}.release-history{margin-bottom:12px}.release-history summary{display:flex;align-items:center;gap:12px;flex-wrap:wrap;cursor:pointer}.release-history summary:after{content:'⌄';margin-left:auto}.release-history[open] summary:after{content:'⌃'}.release-history-events{display:flex;gap:12px;flex-wrap:wrap;margin:18px 0}.release-history-events>div{display:flex;flex-direction:column;gap:6px;padding:12px 16px;border:1px solid #dce7ed;border-radius:8px}.release-history-events span{font-size:13px;color:#62727d}.release-empty{padding:20px}.release-history summary>span:last-of-type{font-size:13px;color:#62727d}@media(max-width:900px){.release-intro,.release-section-heading,.release-confirm{align-items:flex-start;flex-direction:column}.release-option-fields{grid-template-columns:1fr}.release-event-picture{width:76px;height:84px;flex-basis:76px}}
</style>
@endpush
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(()=>{const form=document.getElementById('event-photo-release');if(!form)return;const choices=Array.from(form.querySelectorAll('[name="event_uuids[]"]:not(:disabled)')),count=document.getElementById('release-selection-count'),submit=document.getElementById('release-selected-events'),all=document.getElementById('select-ready-events');const update=()=>{const n=choices.filter(c=>c.checked).length;count.textContent=`${n} event${n===1?'':'s'} selected · grouped announcements`;submit.disabled=!n;all.disabled=!choices.length;all.textContent=choices.length&&n===choices.length?'Clear selection':'Select all ready'};choices.forEach(c=>c.addEventListener('change',update));all.addEventListener('click',()=>{const select=choices.some(c=>!c.checked);choices.forEach(c=>c.checked=select);update()});update()})();
</script>
@endpush
