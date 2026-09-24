@extends('layouts.public', ['title' => $title, 'footerLabel' => 'Competition photos'])

@section('content')
@php($previewMode = $previewMode ?? true)
@php($browseRoute = $browseRoute ?? 'admin.competition-gallery.customer-preview')
@php($imageRoute = $imageRoute ?? 'admin.competition-gallery.customer-preview.image')
@if($previewMode)
<div style="background:#e6f6fd;border-bottom:1px solid #b2dcec;padding:12px 0">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <span><strong>Customer Preview</strong> · Admin-only preview. Purchasing is not enabled.</span>
        <a class="button secondary" href="{{ route('admin.competition-gallery.show', $event) }}">← Back to management</a>
    </div>
</div>
@else
<div class="container" style="padding:18px 0"><a class="button secondary" href="{{ route('customer.competitions.index') }}">← My Competitions</a></div>
@endif
<section class="hero" style="padding:40px 0">
    <div class="container" style="display:flex;align-items:center;gap:28px;flex-wrap:wrap">
        @if($coverUrl)<img src="{{ $coverUrl }}" alt="{{ $title }} cover" style="width:auto;height:auto;max-width:180px;max-height:120px;border-radius:8px">@endif
        <div><div class="eyebrow">{{ $eventCode }}</div><h1 style="font-size:clamp(30px,5vw,52px);margin-bottom:0">{{ $title }}</h1></div>
    </div>
</section>
<section class="section"><div class="container">
    @unless($previewMode)
        <nav aria-label="Competition views" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px">
            <a class="button secondary" href="{{ route('customer.competitions.dancers', $event) }}">My Dancers</a>
            <a class="button secondary" href="{{ route('customer.competitions.studios', $event) }}">My Studios’ Routines</a>
            <a class="button secondary" href="{{ route('customer.competitions.show', $event) }}">Whole Event</a>
        </nav>
    @endunless
    <nav aria-label="Gallery folders" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px">
        <a href="{{ route($browseRoute, $event) }}">Whole event</a>
        @foreach($breadcrumbs as $crumb)
            <span aria-hidden="true">/</span>
            @if($loop->last)<strong aria-current="page">{{ $crumb['label'] }}</strong>
            @else<a href="{{ route($browseRoute, ['schedulingEvent' => $event, 'folder' => $crumb['uuid']]) }}">{{ $crumb['label'] }}</a>@endif
        @endforeach
    </nav>
    @if($children)
        <div class="grid">
            @foreach($children as $child)
                <a class="card card-body" style="text-decoration:none" href="{{ route($browseRoute, ['schedulingEvent' => $event, 'folder' => $child['uuid']]) }}"><h3>{{ $child['label'] }}</h3></a>
            @endforeach
        </div>
    @elseif($photos && $photos->isNotEmpty())
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;align-items:start">
            @foreach($photos as $photo)
                <a class="card" href="{{ route($imageRoute, ['schedulingEvent' => $event, 'photo' => $photo->uuid]) }}" target="_blank" rel="noopener" aria-label="Open photo preview">
                    <img src="{{ route($imageRoute, ['schedulingEvent' => $event, 'photo' => $photo->uuid, 'size' => 'thumbnail']) }}" alt="Photo from {{ $selected['label'] }}" loading="lazy" style="width:100%;height:auto">
                </a>
            @endforeach
        </div>
        <nav aria-label="Photo pages" style="display:flex;gap:16px;margin-top:24px">
            @if($photos->previousPageUrl())<a class="button secondary" href="{{ $photos->previousPageUrl() }}">Previous photos</a>@endif
            @if($photos->nextPageUrl())<a class="button secondary" href="{{ $photos->nextPageUrl() }}">More photos</a>@endif
        </nav>
    @else
        <div class="empty"><h2>Photos are being prepared</h2><p style="margin-bottom:0">Please check back soon.</p></div>
    @endif
</div></section>
@endsection
