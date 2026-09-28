@extends('layouts.admin', ['title' => $event->name.' · TV Slideshows', 'heading' => $event->name, 'subheading' => 'Publicity · TV Slideshows'])
@section('content')
@include('admin.media._publicity-tabs')
@include('admin.publicity._styles')
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
<div class="publicity-toolbar"><a class="button secondary" href="{{ route('admin.publicity.tv-slideshows', ['type'=>$type]) }}">← TV Slideshows</a><div class="publicity-actions"><a class="button secondary" href="{{ route('admin.top-pics.show', $event) }}">View Top Pics</a><a class="button" href="{{ route('admin.publicity.preview', $event) }}" target="_blank" rel="noopener">Preview slideshow</a></div></div>
<section class="card card-pad"><h2>Playback settings</h2><p>Top Pics update automatically after uploader changes sync. Standard advertising and event slides are mixed into the photo sequence. Preview works while TV playback is disabled.</p>
<form method="post" action="{{ route('admin.publicity.update', $event) }}">@csrf @method('PUT')<div class="publicity-fields">
<label class="wide">TV playback<select name="enabled"><option value="0" @selected(!$settings->enabled)>Disabled</option><option value="1" @selected($settings->enabled)>Enabled for paired screens</option></select></label>
<label>Seconds per photo<input name="photo_seconds" type="number" min="2" max="60" required value="{{ $settings->photo_seconds }}"></label><label>Photos between image / QR adverts<input name="promotion_every" type="number" min="1" max="50" required value="{{ $settings->promotion_every }}"></label></div><p class="muted">Video adverts are spaced evenly through the photos and play to completion.</p><div class="publicity-actions"><button>Save playback settings</button></div></form>
<p class="publicity-note">{{ count($playlist['slides']) }} playlist entries currently available. TV photos retain their proportions and have no watermark. Advertising plays even when there are no Top Pics. Connected screens check for changes every minute.</p></section>
<section class="publicity-section"><h2>Event information &amp; advertising</h2><p>Six competition adverts automatically use this event’s scan-to-add QR code. Each plays for 8 seconds. The artwork stays unchanged outside the QR box; event and DancePro logos appear as separate corner overlays during playback.</p>@if(!$eventQrAvailable)<p class="notice">A general event message plays until access is configured. Set the {{ $type === 'concert' ? 'studio gallery' : 'competition media access' }} code to activate the six QR adverts.</p>@endif
@if(in_array(parse_url(config('app.url'), PHP_URL_HOST), ['localhost', '127.0.0.1', '10.0.2.2']))<p class="publicity-note">Local preview: the QR links to {{ config('app.url') }}. To scan from a phone, use a DancePro server address reachable from that phone.</p>@endif
<div class="publicity-slide-list">
@foreach(collect($playlist['slides'])->filter(fn ($slide) => str_starts_with($slide['id'], 'template-'))->unique('id') as $template)
<figure class="card" style="margin:0;overflow:hidden"><a href="{{ route('admin.publicity.image', ['schedulingEvent'=>$event, 'key'=>$template['id']]) }}" target="_blank" rel="noopener" aria-label="Open competition advert {{ substr($template['id'], -1) }}"><img src="{{ route('admin.publicity.image', ['schedulingEvent'=>$event, 'key'=>$template['id']]) }}" alt="Competition advert {{ substr($template['id'], -1) }} with this event’s QR code" loading="lazy" width="1920" height="1080" style="display:block;width:100%;height:auto"></a><figcaption style="padding:12px">Competition advert {{ substr($template['id'], -1) }} · 8 seconds</figcaption></figure>
@endforeach
</div>
<h3>Additional event slides</h3>
@include('admin.publicity._slides')</section>
<section class="card card-pad publicity-section"><h2>Pair a screen</h2><p>Open <a href="{{ route('publicity.player') }}" target="_blank" rel="noopener">the screen player</a> on the display, choose Pair screen and enter its six-digit code below.</p>
<form method="post" action="{{ route('admin.publicity.pair', $event) }}">@csrf<div class="publicity-fields"><label>Pairing code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="123456"></label><label>Screen name<input name="name" maxlength="100" required placeholder="Foyer TV"></label></div><div class="publicity-actions"><button>Pair screen</button></div></form><p class="publicity-note">Codes expire after 10 minutes. Pairing authorises this crew screen to choose any event enabled for TV. This event is its initial selection. Downloaded playback can continue offline for up to 24 hours after its last update.</p>
@include('admin.publicity._screens')</section>
@endsection
