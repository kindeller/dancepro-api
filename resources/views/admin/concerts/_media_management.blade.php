<style>
    .concert-media-list { display:grid;gap:12px;margin-top:22px }
    .concert-media-line { display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;padding:14px;border:1px solid #d7e4ea;border-radius:8px }
    .concert-media-controls { display:flex;align-items:center;gap:8px;flex-wrap:wrap }
    .concert-media-controls button,.concert-media-controls .button { width:auto;margin:0 }
    .concert-media-name { display:flex;align-items:center;gap:8px;flex-wrap:wrap }
    .concert-media-name input { min-width:180px;max-width:320px }
    .concert-media-items { display:grid;gap:8px;padding-left:18px }
</style>
<section class="card card-pad concert-media-list" id="concert-media-management">
    <div class="concert-media-line" style="border:0;padding:0"><div><h2 style="margin:0">Collections and videos</h2><p class="muted">Edit titles and visibility here. Upload new videos on the upload page.</p></div><a class="button secondary" href="{{ route('admin.concerts.media.index', $concert) }}">Upload media</a></div>
    <p id="concert-media-message" role="status" aria-live="polite" hidden></p>
    @forelse($concert->mediaCollections as $collection)
        @php($managed = $collection->storage_disk === config('media.upload_disk') && $collection->catalogue_mode->value === 'managed')
        <div data-collection="{{ $collection->uuid }}">
            <div class="concert-media-line">
                <div class="concert-media-name"><strong>{{ $collection->name }}</strong><small class="muted">{{ $collection->status->value }} · {{ $collection->assets->count() }} videos</small></div>
                <div class="concert-media-controls">
                    <button type="button" class="button secondary" data-edit-collection aria-label="Edit collection name" title="Edit collection name">✎</button>
                    @if($managed || $collection->status->value === 'published')<button type="button" class="button secondary" data-collection-status="{{ $collection->status->value === 'published' ? 'draft' : 'published' }}">{{ $collection->status->value === 'published' ? 'Unpublish' : 'Publish' }}</button>@endif
                    @if($managed)<a class="button secondary" href="{{ route('admin.concerts.media.collections.confirm-delete', $collection) }}" aria-label="Delete {{ $collection->name }}" title="Delete collection and videos">🗑</a>@endif
                </div>
            </div>
            @if($collection->assets->isNotEmpty())
                <div class="concert-media-items">
                @foreach($collection->assets as $asset)
                    <div class="concert-media-line" data-asset="{{ $asset->uuid }}">
                        <div class="concert-media-name"><span>{{ $asset->display_name }}</span><small class="muted">{{ $asset->status->value }} · {{ $asset->is_visible ? 'visible' : 'hidden' }}</small></div>
                        <div class="concert-media-controls">
                            <button type="button" class="button secondary" data-edit-asset aria-label="Edit video title" title="Edit video title">✎</button>
                            @if($asset->verified_at)<button type="button" class="button secondary" data-asset-visible="{{ $asset->is_visible ? '0' : '1' }}">{{ $asset->is_visible ? 'Hide' : 'Show' }}</button>@endif
                            @if($managed && $asset->status->value !== 'processing')<a class="button secondary" href="{{ route('admin.concerts.media.assets.confirm-delete', $asset) }}" aria-label="Delete {{ $asset->display_name }}" title="Delete video and files">🗑</a>@endif
                        </div>
                    </div>
                @endforeach
                </div>
            @endif
        </div>
    @empty
        <p class="muted">No collections yet. Upload the first video to create one automatically.</p>
    @endforelse
</section>
<script type="application/json" id="concert-media-management-config">{!! json_encode(['base' => url('/admin/media-api'), 'csrf' => csrf_token()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/admin-concert-media-management.js') }}?v={{ filemtime(public_path('js/admin-concert-media-management.js')) }}" defer></script>
