@extends('layouts.admin', ['title' => 'Publicity · TV Slideshows', 'heading' => 'Publicity', 'subheading' => 'Top Pics and event screens'])
@section('content')
@include('admin.media._publicity-tabs')
@include('admin.media._backend-gallery-styles')
@include('admin.publicity._styles')
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
<div class="publicity-toolbar"><div><h2 style="margin:0">TV Slideshows</h2><p class="muted">Competition screens mix live Top Pics with standard advertising and event information.</p></div><a class="button secondary" href="{{ route('publicity.player') }}" target="_blank" rel="noopener">Open screen player</a></div>
<details id="standard-advertising" class="card card-pad publicity-section" @if(request()->has('advertising_type')) open @endif>
    <summary style="font-weight:800;cursor:pointer">Standard advertising · {{ $advertisingType === 'concert' ? 'Concerts' : 'Competitions' }} · {{ $slides->count() }} slides</summary>
    <nav class="publicity-switch" aria-label="Standard advertising type" style="margin-top:18px">
        @foreach(['competition' => 'Competitions', 'concert' => 'Concerts'] as $value => $label)
        <a href="{{ route('admin.publicity.tv-slideshows', ['advertising_type' => $value]) }}#standard-advertising" @if($advertisingType === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <p>{{ $advertisingType === 'concert' ? 'Manage concert advertising separately. These adverts are saved for concert use; TV slideshows remain competition-only.' : 'Included in every enabled competition slideshow. Concert adverts are kept separate.' }} Hide a slide to remove it without deleting it.</p>
    @include('admin.publicity._slides')
</details>
<details class="card card-pad publicity-section"><summary style="font-weight:800;cursor:pointer">Paired screens · {{ $screens->whereNull('revoked_at')->count() }}</summary>@include('admin.publicity._screens')</details>
<h2>Choose a competition</h2>
<div class="backend-gallery-grid" style="margin-top:20px">
@forelse($events as $event)
<a class="card backend-gallery-card" href="{{ route('admin.publicity.show', $event) }}"><div class="backend-gallery-card-media">@if($event->cover_uuid)<img src="{{ route($type === 'competition' ? 'admin.competition-gallery.image' : 'admin.concert-photo-gallery.image', ['schedulingEvent'=>$event, 'photo'=>$event->cover_uuid, 'size'=>'cover']) }}" alt="" loading="lazy">@else<span class="backend-gallery-placeholder" aria-hidden="true">▱</span>@endif</div><div class="backend-gallery-card-body"><strong class="backend-gallery-card-title">{{ $event->name }}</strong><div class="backend-gallery-card-meta">{{ $event->event_date?->format('j M Y') }} · {{ $event->top_pic_count ?? 0 }} Top Pics</div><p>Manage slideshow →</p></div></a>
@empty<p class="card card-pad">No events yet. Create a competition to prepare its slideshow.</p>@endforelse
</div>
<div class="publicity-toolbar" style="margin-top:20px">@if($events->previousPageUrl())<a href="{{ $events->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $events->currentPage() }} of {{ $events->lastPage() }}</span>@if($events->nextPageUrl())<a href="{{ $events->nextPageUrl() }}">Next</a>@endif</div>
@endsection
