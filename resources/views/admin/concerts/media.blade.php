@extends('layouts.admin', ['title' => 'Upload media for '.$concert->name, 'heading' => 'Upload media', 'subheading' => $concert->name])

@section('content')
@php($managedCollections = $concert->mediaCollections->filter(fn ($collection) => $collection->storage_disk === config('media.upload_disk') && $collection->catalogue_mode->value === 'managed'))
<style>
    .media-page { display: grid; gap: 24px; max-width: 1050px; }
    .media-page h2, .media-page h3, .media-page p { margin-top: 0; }
    .media-page .card-pad { padding: 24px; }
    .media-stack { display: grid; gap: 18px; }
    .media-actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-top: 18px; }
    .media-actions button { width: auto; }
    .media-field { display: grid; gap: 6px; }
    .media-drop-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
    .media-drop { display: grid; gap: 8px; min-height: 140px; padding: 18px; border: 2px dashed #9acbdc; border-radius: 10px; background: #f4fbfe; cursor: pointer; }
    .media-drop.is-dragging { border-color: #087fb0; background: #e3f5fc; }
    .media-drop input[type=file] { max-width: 100%; }
    .media-drop strong { overflow-wrap: anywhere; }
    .media-progress { display: grid; gap: 5px; }
    .media-progress progress { width: 100%; height: 12px; accent-color: #087fb0; }
    .media-row { padding: 18px; border: 1px solid #d7e4ea; border-radius: 10px; background: #fff; }
    .media-row + .media-row { margin-top: 12px; }
    .media-secondary { border-top: 1px solid #d7e4ea; padding-top: 18px; }
    .media-bulk-list { display: grid; gap: 12px; }
    .media-bulk-pair { padding: 16px; border: 1px solid #d7e4ea; border-radius: 10px; display: grid; gap: 12px; }
    .media-bulk-files { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
    .media-bulk-error { color: #a32121; }
    .media-page details summary { cursor: pointer; font-weight: 700; }
</style>

<div class="media-page">
    <p><a href="{{ route('admin.concerts.edit', $concert) }}">← Back to concert</a></p>
    <div class="notice"><strong>Upload to concert storage</strong><br>The original and playback MP4 go directly from your browser to S3. Keep the concert in draft until you have tested playback. No video conversion happens here.</div>
    <div class="media-progress" aria-live="polite"><strong>Overall upload <span id="media-overall-percent">0%</span></strong><progress id="media-overall-progress" value="0" max="100"></progress><span id="media-upload-status" role="status">Ready to add a video.</span></div>

    @if($managedCollections->isEmpty())
    <section class="card card-pad media-stack">
        <div><h2>Add the first video</h2><p class="muted">A default collection named “{{ $concert->name }}” will be created automatically. You can split later shows into another collection.</p></div>
        <form class="new-media-asset media-stack" data-auto-collection="1" data-default-collection-name="{{ $concert->name }}">
            @include('admin.concerts._media_upload_fields', ['buttonLabel' => 'Create and upload video'])
        </form>
        @include('admin.concerts._media_bulk_upload', ['autoCollection' => true])
    </section>
    @endif

    @foreach($concert->mediaCollections as $collection)
    <details class="card card-pad media-stack" data-collection="{{ $collection->uuid }}">
        <summary>{{ $collection->name }} · {{ $collection->assets->count() }} {{ \Illuminate\Support\Str::plural('video', $collection->assets->count()) }} · {{ $collection->status->value }}</summary>
        <div class="media-stack" style="margin-top:18px">
        @if($collection->status->value === 'published' || $managedCollections->contains('id', $collection->id))
        <div class="media-actions"><button type="button" data-collection-status="{{ $collection->status->value === 'published' ? 'draft' : 'published' }}">{{ $collection->status->value === 'published' ? 'Unpublish show' : 'Publish show' }}</button><span class="muted">Only verified, visible videos appear on the public page.</span></div>
        @endif

        @if($managedCollections->contains('id', $collection->id))
        <div class="media-secondary"><h3>Add a video to this show</h3>
            <form class="new-media-asset media-stack">
                @include('admin.concerts._media_upload_fields', ['buttonLabel' => 'Upload video'])
            </form>
            @include('admin.concerts._media_bulk_upload', ['autoCollection' => false])
        </div>
        @else
        <p class="muted">This collection uses legacy storage and cannot receive web uploads.</p>
        @endif

        @if($collection->assets->isNotEmpty())
        <div class="media-secondary"><h3>Videos in this show</h3>
        @foreach($collection->assets as $asset)
            <div class="media-row media-stack" data-asset="{{ $asset->uuid }}">
                <div><strong>{{ $asset->display_name }}</strong> <span class="muted">· {{ $asset->status->value }} · {{ $asset->is_visible ? 'visible' : 'hidden' }}</span><br><span class="muted">Original: {{ $asset->original_filename }}@if(data_get($asset->metadata, 'ingest.source.fallback_filename')) · Playback file: {{ data_get($asset->metadata, 'ingest.source.fallback_filename') }}@endif</span></div>
                @if($asset->status->value === 'processing' && $managedCollections->contains('id', $collection->id))
                <form class="media-file-upload media-stack">
                    @include('admin.concerts._media_upload_fields', ['buttonLabel' => 'Continue upload and verify', 'showTitle' => false])
                </form>
                @elseif($asset->verified_at)
                <div class="media-actions"><button type="button" data-asset-visible="{{ $asset->is_visible ? '0' : '1' }}">{{ $asset->is_visible ? 'Hide video' : 'Make video visible' }}</button></div>
                @endif
            </div>
        @endforeach
        </div>
        @endif
        </div>
    </details>
    @endforeach

    @if($managedCollections->isNotEmpty())
    <details class="card card-pad"><summary>Add another show or collection</summary><p class="muted" style="margin-top:14px">Use this for a separate performance, such as a 5pm and 7pm show. The public playlist groups videos by show.</p>
        <form id="new-media-collection" class="media-stack"><label class="media-field">Show name<input name="name" maxlength="255" required placeholder="7pm show"></label><div class="media-actions"><button type="submit">Add show</button></div></form>
    </details>
    @endif
</div>

<template id="media-bulk-pair-template">
    <div class="media-bulk-pair">
        <label class="media-field">Video title<input data-bulk-title maxlength="255" required></label>
        <div class="media-bulk-files">
            <div class="media-progress" data-file-slot="original"><strong>Original: <span data-file-name></span></strong><progress value="0" max="100"></progress><small data-file-progress>Ready · 0%</small></div>
            <div class="media-progress" data-file-slot="fallback"><strong>Playback: <span data-file-name></span></strong><progress value="0" max="100"></progress><small data-file-progress>Ready · 0%</small></div>
        </div>
    </div>
</template>

<script type="application/json" id="media-uploader-config">{!! json_encode([
    'base' => url('/admin/media-api'),
    'concert' => $concert->uuid,
    'csrf' => csrf_token(),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/admin-concert-uploader.js') }}?v={{ filemtime(public_path('js/admin-concert-uploader.js')) }}" defer></script>
@endsection
