<style>
    .admin-media-group{margin-bottom:18px;overflow:hidden}.admin-media-head{display:flex;align-items:center;gap:14px;padding:16px 18px;border-bottom:1px solid var(--line)}.admin-media-head img{width:66px;height:48px;border-radius:7px;object-fit:contain;background:#101820}.admin-media-head h2{margin:0 0 2px}.admin-media-head p{margin:0}.admin-media-head .button{margin-left:auto;white-space:nowrap}.admin-media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:16px;padding:18px}.admin-media-card{min-width:0;overflow:hidden;border:1px solid var(--line);border-radius:10px;background:#fff;box-shadow:0 8px 22px #17364a0b}.admin-media-thumb{position:relative;display:block;aspect-ratio:4/3;overflow:hidden;background:#101820}.admin-media-thumb>img{width:100%;height:100%;object-fit:cover}.admin-media-thumb-button{width:100%;height:100%;padding:0;border:0;border-radius:0;background:transparent;cursor:zoom-in}.admin-media-thumb-button img{width:100%;height:100%;object-fit:cover}.admin-media-copy{display:grid;gap:3px;padding:13px}.admin-media-copy span{color:var(--muted);font-size:13px}.admin-media-copy strong{line-height:1.28}.admin-media-copy a{margin-top:7px;color:var(--brand-strong);font-weight:800}.admin-media-preview{width:min(1100px,calc(100% - 28px));max-height:calc(100% - 28px);padding:0;border:0;border-radius:12px;background:#101820;color:#fff;box-shadow:0 30px 90px #0009}.admin-media-preview::backdrop{background:#000c}.admin-media-preview-bar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 16px}.admin-media-preview-bar button{border:1px solid #58707d;border-radius:7px;background:#263944;color:#fff;padding:8px 12px;cursor:pointer}.admin-media-preview img{display:block;width:auto;max-width:100%;max-height:calc(100vh - 100px);margin:auto;object-fit:contain}@media(max-width:650px){.admin-media-head{align-items:flex-start;flex-wrap:wrap}.admin-media-head .button{width:100%;margin-left:0}.admin-media-grid{grid-template-columns:repeat(2,minmax(0,1fr));padding:12px;gap:10px}}
</style>

@forelse($selectionData['groups'] as $group)
    @php
        $competition = $group['kind'] === 'competition';
        $concertSchedulingEvent = $competition ? null : $group['event']->bookingItem?->schedulingEvent;
        $eventGalleryUrl = $competition
            ? route('admin.competition-gallery.show', $group['event'])
            : ($concertSchedulingEvent ? route('admin.concert-photo-gallery.show', $concertSchedulingEvent) : null);
    @endphp
    <section class="card admin-media-group">
        <header class="admin-media-head">
            @if($group['logo'])<img src="{{ $group['logo'] }}" alt="">@endif
            <div><h2>{{ $group['title'] }}</h2><p class="muted">{{ ucfirst($group['kind']) }} @if($group['date']) · {{ $group['date']->format('j M Y') }} @endif</p></div>
            @if($eventGalleryUrl)<a class="button secondary" href="{{ $eventGalleryUrl }}">View gallery</a>@endif
        </header>
        <div class="admin-media-grid">
            @foreach($group['selections'] as $selection)
                @php
                    $detail = $competition ? ($group['details'][$selection->id] ?? []) : [];
                    $asset = $competition ? null : $selection->asset;
                    $isPhoto = $competition ? $selection->product === 'photo' : true;
                    $photoUuid = $competition
                        ? ($selection->photo_uuid ?: ($detail['thumbnail_photo_uuid'] ?? null))
                        : data_get($asset?->metadata, 'programme_photo_file_uuid');
                    $thumbnailUrl = $competition
                        ? ($photoUuid ? route('admin.competition-gallery.image', ['schedulingEvent' => $group['event'], 'photo' => $photoUuid, 'size' => 'thumbnail']) : $group['logo'])
                        : ($concertSchedulingEvent && $photoUuid ? route('admin.concert-photo-gallery.image', ['schedulingEvent' => $concertSchedulingEvent, 'photo' => $photoUuid, 'size' => 'thumbnail']) : $group['logo']);
                    $previewUrl = $isPhoto
                        ? ($competition
                            ? ($photoUuid ? route('admin.competition-gallery.image', ['schedulingEvent' => $group['event'], 'photo' => $photoUuid]) : null)
                            : ($asset ? route('admin.customers.media-assets.show', ['customer' => $customer, 'asset' => $asset]) : null))
                        : null;
                    $thumbnailUrl = ! $competition && $asset
                        ? route('admin.customers.media-assets.show', ['customer' => $customer, 'asset' => $asset])
                        : $thumbnailUrl;
                    $itemGalleryUrl = $competition
                        ? route('admin.competition-gallery.show', ['schedulingEvent' => $group['event'], 'folder' => $selection->item_uuid])
                        : $eventGalleryUrl;
                    $kind = $competition
                        ? ($isPhoto ? 'competition_photo' : ($selection->product === 'routine_video' ? 'competition_video' : 'competition_item'))
                        : 'concert_photo';
                    $sourceUuid = $competition ? ($isPhoto ? $selection->photo_uuid : $selection->item_uuid) : $asset?->uuid;
                    $productLabel = $competition
                        ? match($selection->product) {'photo' => 'Individual photo', 'routine_photos' => 'All photos from this routine', 'routine_video' => 'Routine video', default => 'Photos + video package'}
                        : 'Individual concert photo';
                    $grantLabel = $competition ? (($detail['competitor'] ?? 'routine').' · '.$productLabel) : ($asset?->display_name ?: $asset?->original_filename ?: 'concert photo');
                @endphp
                <article class="admin-media-card">
                    <div class="admin-media-thumb media-grant-target">
                        @if($previewUrl)<button class="admin-media-thumb-button" type="button" data-admin-media-preview data-preview-src="{{ $previewUrl }}" data-preview-title="{{ $grantLabel }}"><img src="{{ $thumbnailUrl }}" alt="" loading="lazy"></button>
                        @elseif($itemGalleryUrl)<a href="{{ $itemGalleryUrl }}"><img src="{{ $thumbnailUrl }}" alt="" loading="lazy"></a>
                        @elseif($thumbnailUrl)<img src="{{ $thumbnailUrl }}" alt="" loading="lazy">
                        @endif
                        @if($sourceUuid)<x-admin.media-grant-button :source-kind="$kind" :event-uuid="$group['event']->uuid" :source-uuid="$sourceUuid" :include-video="$competition && $selection->product === 'routine_package'" :label="'Add '.$grantLabel.' to this account'" :aria-label="'Add '.$grantLabel.' to this account'" />@endif
                    </div>
                    <div class="admin-media-copy">
                        @if($competition)<span>{{ $group['title'] }}</span><span>{{ $detail['section'] ?? 'Section unavailable' }}</span><strong>{{ $detail['competitor'] ?? 'Routine unavailable' }}</strong>
                        @else<span>{{ $group['title'] }}</span><strong>{{ $asset?->collection?->name ?: 'Concert item' }}</strong>
                        @endif
                        <span>{{ $productLabel }}@if($mode === 'cart') · ${{ number_format(($group['lineAmounts'][$selection->id] ?? 0) / 100, 2) }}@endif</span>
                        @if($itemGalleryUrl)<a href="{{ $itemGalleryUrl }}">Go to gallery →</a>@endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@empty
    <p class="card card-pad">No current {{ $mode }}.</p>
@endforelse

@if($selectionData['groups']->isNotEmpty())
    <dialog class="admin-media-preview" data-admin-media-preview-dialog aria-label="Media preview"><div class="admin-media-preview-bar"><strong data-admin-preview-title>Media preview</strong><button type="button" data-admin-preview-close>Close ✕</button></div><img data-admin-preview-image alt="Media preview"></dialog>
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (() => {
            const dialog = document.querySelector('[data-admin-media-preview-dialog]');
            if (!dialog) return;
            const image = dialog.querySelector('[data-admin-preview-image]');
            const title = dialog.querySelector('[data-admin-preview-title]');
            document.querySelectorAll('[data-admin-media-preview]').forEach(button => button.addEventListener('click', () => {
                image.src = button.dataset.previewSrc;
                title.textContent = button.dataset.previewTitle || 'Media preview';
                dialog.showModal();
            }));
            dialog.querySelector('[data-admin-preview-close]').addEventListener('click', () => dialog.close());
            dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
            dialog.addEventListener('close', () => image.removeAttribute('src'));
        })();
    </script>
@endif
