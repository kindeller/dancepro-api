@extends('layouts.admin', ['title' => 'Concert photo releases', 'heading' => 'Concert photo releases', 'subheading' => 'Group albums and release them together'])

@push('styles')
<style>
    .release-cover-grid { display:flex; flex-wrap:wrap; gap:9px; margin:12px 0; }
    .release-cover-option { position:relative; margin:0; }
    .release-cover-option button { display:block; padding:0; border:3px solid transparent; border-radius:9px; background:none; cursor:pointer; overflow:hidden; }
    .release-cover-option button[aria-pressed="true"] { border-color:#168bc1; }
    .release-cover-option img { display:block; width:112px; height:76px; object-fit:cover; }
    .release-parent-cover { display:block; width:min(100%,320px); aspect-ratio:16/9; object-fit:cover; border-radius:10px; margin:12px 0; background:#10202c; }
</style>
@endpush

@section('content')
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif
<div class="grid two-col">
<section class="card card-pad"><h2>Create release group</h2><p class="muted">Choose photo albums from one studio. Junior and senior concerts can be released separately, or several concerts can be released together.</p>
<form method="post" action="{{ route('admin.concert-photo-releases.store') }}" class="grid">@csrf
<label>Group name<input name="name" maxlength="255" required placeholder="2026 Senior concerts"></label>
<label>Watermark<select name="watermark_uuid"><option value="">Default concert watermark</option>@foreach($watermarks as $watermark)<option value="{{ $watermark->uuid }}">{{ $watermark->name }}</option>@endforeach</select></label>
<fieldset><legend>Unreleased albums</legend>
@forelse($albums->whereNull('published_at') as $album)
<label style="display:flex;gap:10px;align-items:start;margin:8px 0"><input type="checkbox" name="collection_uuids[]" value="{{ $album->uuid }}"><span><strong>{{ $album->concert->studio->name }} · {{ $album->concert->name }}</strong><br>{{ $album->name }}</span></label>
@empty<p class="muted">No unreleased concert photo albums.</p>@endforelse
</fieldset><div><button>Create group</button></div></form></section>
<section><h2>Release groups</h2>
@forelse($groups as $group)@php($topPics = $group->collections->flatMap->assets->filter(fn($asset) => ($asset->metadata['is_top_pic'] ?? false) && $asset->is_visible && $asset->status->value === 'available'))@php($cover = $group->displayCoverAsset())<article class="card card-pad" style="margin-bottom:14px"><h3>{{ $group->name }}</h3><p>{{ $group->studio->name }} · {{ $group->collections->count() }} album(s) · {{ $group->watermark?->name ?? 'Default watermark' }}</p>
@if($cover && ($cover->metadata['programme_photo_file_uuid'] ?? null))<img class="release-parent-cover" src="{{ route('admin.concert-photo-gallery.image', ['schedulingEvent' => $cover->collection->concert->bookingItem->schedulingEvent, 'photo' => $cover->metadata['programme_photo_file_uuid'], 'size' => 'cover']) }}" alt="{{ $group->name }} cover">@endif
<ul>@foreach($group->collections as $album)<li>{{ $album->concert->name }} · {{ $album->name }}</li>@endforeach</ul>
<details><summary><strong>Choose parent album cover</strong></summary>
@if($topPics->isNotEmpty())<p class="muted">Parent covers come from photos marked Top Pics in the uploader.</p><div class="release-cover-grid">@foreach($topPics as $asset)@if($asset->metadata['programme_photo_file_uuid'] ?? null)<form class="release-cover-option" method="post" action="{{ route('admin.concert-photo-releases.cover.update', $group) }}">@csrf<input type="hidden" name="asset_uuid" value="{{ $asset->uuid }}"><button aria-pressed="{{ $group->cover_media_asset_id === $asset->id ? 'true' : 'false' }}" title="Use as parent album cover"><img src="{{ route('admin.concert-photo-gallery.image', ['schedulingEvent' => $asset->collection->concert->bookingItem->schedulingEvent, 'photo' => $asset->metadata['programme_photo_file_uuid'], 'size' => 'thumbnail']) }}" alt="Top Pic cover option"></button></form>@endif @endforeach</div>
<form method="post" action="{{ route('admin.concert-photo-releases.cover.update', $group) }}">@csrf<button class="secondary">Use first Top Pic automatically</button></form>
@else<p class="muted">No uploaded photos in this group are marked Top Pics yet. Mark one in the uploader and sync the concert again.</p>@endif
</details>
@if($group->released_at)<p><strong>Released {{ $group->released_at->format('j M Y g:i a') }}</strong></p>
@else<form method="post" action="{{ route('admin.concert-photo-releases.release', $group) }}">@csrf<button>Release all albums</button></form>@endif
</article>@empty<p class="card card-pad">No release groups yet.</p>@endforelse
</section></div>
@endsection
