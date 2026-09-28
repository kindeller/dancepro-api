<section class="release-workspace" id="prepare-release">
    <div class="release-grid">
        <details class="card card-pad" @if($albums->isNotEmpty()) open @endif><summary><strong>Prepare a photo release</strong></summary>
            <p class="muted">Albums and release groups below belong to {{ $event->studio?->name ?? 'this event' }} in {{ $event->event_date?->year }}. Related concerts can be released together as one parent album.</p>
            <form method="post" action="{{ route('admin.concert-photo-releases.store') }}" class="grid">@csrf
                <label>Parent album name<input name="name" maxlength="255" required placeholder="2026 Senior concerts"></label>
                <label>Watermark<select name="watermark_uuid"><option value="">Default concert watermark</option>@foreach($watermarks as $watermark)<option value="{{ $watermark->uuid }}">{{ $watermark->name }}</option>@endforeach</select></label>
                <fieldset><legend>Albums ready to group</legend>
                @forelse($albums as $album)
                    <label style="display:flex;gap:10px;align-items:start;margin:8px 0"><input type="checkbox" name="collection_uuids[]" value="{{ $album->uuid }}"><span><strong>{{ $album->concert->studio->name }} · {{ $album->concert->name }}</strong><br>{{ $album->name }}</span></label>
                @empty<p class="muted">No ungrouped concert photo albums are waiting.</p>@endforelse
                </fieldset><div><button @disabled($albums->isEmpty())>Create release group</button></div>
            </form>
        </details>

        <div id="release-groups"><h2 style="margin-top:0">Photo release</h2>
        @forelse($groups as $group)
            @php($topPics = $group->collections->flatMap->assets->filter(fn($asset) => ($asset->metadata['is_top_pic'] ?? false) && $asset->is_visible && $asset->status->value === 'available'))
            @php($cover = $group->displayCoverAsset())
            @php($groupBlockers = $releaseBlockers[$group->id] ?? [])
            <article class="card card-pad" style="margin-bottom:14px"><div style="display:flex;justify-content:space-between;gap:12px;align-items:start"><div><h3 style="margin-top:0">{{ $group->name }}</h3><p>{{ $group->studio->name }} · {{ $group->collections->count() }} album(s) · {{ $group->watermark?->name ?? 'Default watermark' }}</p></div><span class="badge {{ $group->released_at ? 'success' : ($groupBlockers ? 'status-waiting' : 'status-ready') }}">{{ $group->released_at ? 'Released' : ($groupBlockers ? 'Not ready to release' : 'Ready to release') }}</span></div>
            @if($cover && ($cover->metadata['programme_photo_file_uuid'] ?? null) && $cover->collection->concert->bookingItem?->schedulingEvent)<img class="release-parent-cover" src="{{ route('admin.concert-photo-gallery.image', ['schedulingEvent' => $cover->collection->concert->bookingItem->schedulingEvent, 'photo' => $cover->metadata['programme_photo_file_uuid'], 'size' => 'thumbnail']) }}" alt="{{ $group->name }} cover">@endif
            <ul>@foreach($group->collections as $album)<li>{{ $album->concert->name }} · {{ $album->name }}</li>@endforeach</ul>
            <details><summary><strong>Choose parent album cover</strong></summary>
            @if($topPics->isNotEmpty())<p class="muted">Parent covers come from photos marked Top Pics in the uploader.</p><div class="release-cover-grid">@foreach($topPics as $asset)@if(($asset->metadata['programme_photo_file_uuid'] ?? null) && $asset->collection->concert->bookingItem?->schedulingEvent)<form class="release-cover-option" method="post" action="{{ route('admin.concert-photo-releases.cover.update', $group) }}">@csrf<input type="hidden" name="asset_uuid" value="{{ $asset->uuid }}"><button aria-pressed="{{ $group->cover_media_asset_id === $asset->id ? 'true' : 'false' }}" title="Use as parent album cover"><img src="{{ route('admin.concert-photo-gallery.image', ['schedulingEvent' => $asset->collection->concert->bookingItem->schedulingEvent, 'photo' => $asset->metadata['programme_photo_file_uuid'], 'size' => 'thumbnail']) }}" alt="Top Pic cover option"></button></form>@endif @endforeach</div>
            <form method="post" action="{{ route('admin.concert-photo-releases.cover.update', $group) }}">@csrf<button class="secondary">Use first Top Pic automatically</button></form>
            @else<p class="muted">No uploaded photos in this group are marked Top Pics yet.</p>@endif
            </details>
            @if($group->released_at)<a class="button secondary" href="{{ route('admin.concert-photo-releases.email', $group) }}">Photo release email</a><p><strong>Released {{ $group->released_at->format('j M Y g:i a') }}</strong></p>
            @else
                @include('admin.tracking._action-blockers', ['blockers' => $groupBlockers, 'blockerHeading' => 'Gallery cannot be released yet'])
                <form method="post" action="{{ route('admin.concert-photo-releases.release', $group) }}">@csrf<button @disabled(count($groupBlockers) > 0)>Release all albums</button></form>
            @endif
            </article>
        @empty<p class="card card-pad">No release groups yet.</p>@endforelse
        </div>
    </div>
</section>
