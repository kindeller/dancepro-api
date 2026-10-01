@extends('layouts.public', ['title' => 'Review concert video'])
@section('content')
<div class="container concert-review-page">
<a class="button secondary" href="{{ route('customer.studio-communications.index', $event->studio) }}">← Back to Studio Hub</a>
<h1>{{ $event->name }}</h1><h2>Review concert video</h2>
<p><a href="{{ route('customer.media.help') }}#article-guide-studio-review-guide">How review, corrections and release work</a></p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
@if($released)
<section class="card card-pad"><h2>Concert released</h2>@if($event->concertBookingItem?->concert)<p><a class="button secondary" href="{{ route('customer.concert-family.settings', $event->concertBookingItem->concert) }}">Family sharing settings</a></p>@endif<p>Share this code with your dancers and families so they can add the video to their DancePro account.</p>
@if($loginCode)<strong class="badge">Access Code: {{ $loginCode['value'] ?? 'Contact DancePro' }}</strong>@endif</section>
@elseif(!$uploads['ready'])<p>Your concert video is still being prepared. DancePro will email you when it is ready.</p>
@else
<p>Watch every item, including titles, picture, sound and any supplied studio videos. Send corrections below, or release the concert when you are happy with it.</p>
@endif
@if($uploads['ready'] || $released)
@foreach($uploads['files'] as $file)<section class="card card-pad" style="margin:18px 0"><h3>{{ $file->original_filename }}</h3>
<video controls preload="none" style="width:100%;max-height:65vh;background:#000" src="{{ route('customer.concert-review.video', [$event, $file->uuid]) }}"></video></section>@endforeach
@endif
@if(!$released && $uploads['ready'])
<form method="post" action="{{ route('customer.concert-review.feedback', $event) }}" class="card card-pad">@csrf
<input type="hidden" name="revision" value="{{ $review->revision }}"><input type="hidden" name="batch_uuid" value="{{ $uploads['batch']->uuid }}">
<label>Corrections requested<textarea name="feedback" rows="6" maxlength="8000" required placeholder="Include the video filename, time in the video, and what needs changing."></textarea></label>
<button class="button">Send corrections to DancePro</button></form>
@if($canRelease)<form method="post" action="{{ route('customer.concert-review.release', $event) }}" class="card card-pad" style="margin-top:18px">@csrf
<input type="hidden" name="revision" value="{{ $review->revision }}"><input type="hidden" name="batch_uuid" value="{{ $uploads['batch']->uuid }}">
<label class="choice"><input type="checkbox" name="confirm_release" value="1" required> I have reviewed all concert videos and approve their release to families.</label>
<button class="button">Release concert video</button></form>
@else<p>DancePro is working through your corrections. Release becomes available after the corrections email is sent.</p>@endif
@endif
@if($review->feedback)<section class="card card-pad" style="margin-top:18px"><h2>Your feedback</h2>@foreach($review->feedback as $feedback)<p><strong>{{ $feedback['name'] }}</strong> · {{ \Carbon\Carbon::parse($feedback['created_at'])->format('j M Y, g:i a') }}</p><p style="white-space:pre-wrap">{{ $feedback['body'] }}</p>@endforeach</section>@endif
</div>
@endsection

@push('styles')
<style>
.concert-review-page{margin-block:30px}
.concert-review-page h1{font-size:clamp(28px,4vw,44px);line-height:1.15;margin:24px 0 12px;overflow-wrap:anywhere}
.concert-review-page h2{font-size:26px;line-height:1.25}
.concert-review-page h3{font-size:20px;overflow-wrap:anywhere}
.concert-review-page .card-pad{padding:24px}
.concert-review-page label{display:block;margin-bottom:18px}
.concert-review-page label.choice{display:flex;align-items:flex-start}
.concert-review-page textarea{width:100%;min-height:160px;margin-top:10px}
.concert-review-page .button{white-space:normal;text-align:center}
@media(max-width:600px){.concert-review-page .card-pad{padding:16px}.concert-review-page h2{font-size:23px}}
</style>
@endpush
