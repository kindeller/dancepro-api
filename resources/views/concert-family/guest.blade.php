@extends('layouts.public', ['title' => 'Family concert invitation'])
@section('content')
@include('concert-family._styles')
<section class="hero"><div class="container">@include('customers._heading', ['headingImageUrl' => $verified ? $share->concert->studio->logoUrl() : null, 'eyebrow' => 'DancePro Media', 'heading' => $verified ? $share->concert->name : 'Family concert invitation', 'description' => 'Private concert videos shared with you by family.'])</div></section>
<section class="section"><div class="container family-stack">
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="family-error">{{ $errors->first() }}</div>@endif
@if(!$available)
<section class="card family-card"><h2>This invitation is no longer available</h2><p>It may have expired, been revoked, or the concert’s access period may have ended. Contact the family member who shared it with you.</p></section>
@elseif(!$verified)
<section class="card family-card"><h2>Verify your email</h2><p>We’ll send a code to the email address your family member invited. There is no need to create an account.</p>
<form method="post" action="{{ route('concert-family.code', $share) }}">@csrf<button class="button">Email me a verification code</button></form>
<form method="post" action="{{ route('concert-family.verify', $share) }}" style="margin-top:20px">@csrf<label>Six-digit verification code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><button class="button">Verify and open videos</button></form></section>
@include('concert-family._terms')
@else
<section class="card family-card"><h2>Welcome, {{ $share->recipient_name }}</h2><p>Shared by {{ $share->user->name }}. Access ends <strong>{{ $access->end($share)->format('j M Y, g:ia T') }}</strong>.</p><p>Streaming ends {{ $access->end($share, true)->format('j M Y, g:ia T') }}. Downloads {{ $share->concert->studio->family_downloads_enabled ? 'are enabled, with up to three requests per video.' : 'are disabled by this studio.' }}</p><details><summary>Access limits and privacy</summary>@include('concert-family._terms')</details></section>
<div class="family-videos" data-family-viewer data-csrf="{{ csrf_token() }}">
@foreach($videos as $video)@php($asset = $video->asset)
<article class="card family-card" data-family-video data-playback="{{ route('concert-family.playback', [$share, $asset]) }}" data-activity="{{ route('concert-family.activity', [$share, $asset]) }}">
<h3>{{ $asset->display_name ?? $asset->original_filename }}</h3>
@if($access->end($share, true)->isFuture())<button class="button" type="button" data-family-play>Watch video</button><video controls controlslist="nodownload" preload="none" playsinline hidden></video>@else<p>Streaming access has ended.</p>@endif
<p data-family-status role="status"></p>
@if($share->concert->studio->family_downloads_enabled)
<p data-download-count>{{ $video->download_requests }} of 3 download requests used. Save one copy; extra requests allow for mistakes.</p>
<form method="post" data-family-download action="{{ route('concert-family.download.request', [$share, $asset]) }}">@csrf<input type="hidden" name="request_uuid" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><button class="button secondary" @disabled($video->download_requests >= 3)>Download video</button></form>
@endif
</article>@endforeach
</div>
@endif
</div></section>
@endsection
@push('scripts')@if($verified && (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))))@vite('resources/js/concert-family.js')@endif @endpush
