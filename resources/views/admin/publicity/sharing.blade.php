@extends('layouts.admin', ['title' => 'Publicity · QR codes & links', 'heading' => 'Publicity', 'subheading' => 'Share gallery access with organisers and studios'])
@section('content')
@include('admin.media._publicity-tabs')
@include('admin.publicity._styles')
<h2>QR codes &amp; links</h2>
<p>Send the link or QR image to the organiser or studio to share with their families. Customers sign in or create and verify an account, then the gallery access is added automatically.</p>
<p class="muted">Competition links add that event. Studio links add access to the studio’s released concert photo galleries. Purchases and concert video access remain separate. Changing the gallery access code replaces its link and QR code.</p>
@if(in_array(parse_url(config('app.url'), PHP_URL_HOST), ['localhost', '127.0.0.1', '10.0.2.2']))
<p class="notice" role="status">Local preview only: these links point to this computer. Set the public site address before sending links or QR codes to families.</p>
@endif
<nav class="publicity-switch" aria-label="Gallery type">@foreach(['competition'=>'Competitions', 'studio'=>'Studios · concert photos'] as $value=>$label)<a href="{{ route('admin.publicity.sharing.index', ['type'=>$value]) }}" @if($type===$value) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
<form method="GET" class="publicity-toolbar" style="margin:20px 0;justify-content:flex-start;gap:12px"><input type="hidden" name="type" value="{{ $type }}"><label>Search {{ $type === 'studio' ? 'studios' : 'competitions' }}<input type="search" name="q" value="{{ $search }}" maxlength="150"></label><button class="button secondary">Search</button></form>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));gap:20px">
@forelse($entries as $entry)
<article class="card card-pad" data-share-card>
    <h3>{{ $entry['name'] }}</h3>@if($entry['date'])<p class="muted">{{ $entry['date'] }}</p>@endif
    @if($entry['url'])
    <img src="{{ $entry['qr'] }}" alt="QR code to add {{ $entry['name'] }} to My Media" width="180" height="180" loading="lazy" style="display:block;max-width:100%;margin-bottom:16px">
    <label>Gallery access link<input type="text" value="{{ $entry['url'] }}" readonly data-share-url style="width:100%" onclick="this.select()"></label>
    <div class="publicity-toolbar" style="margin-top:16px;justify-content:flex-start;gap:12px"><button type="button" class="button" data-copy-link>Copy link</button><a class="button secondary" href="{{ $entry['qr'] }}" download>Download QR</a></div>
    <p class="muted" role="status" data-copy-status></p>
    @else
    <p>Set a {{ $type === 'studio' ? 'studio gallery' : 'competition media' }} access code to enable sharing.</p><a class="button secondary" href="{{ $entry['settings'] }}">Open {{ $type === 'studio' ? 'studio' : 'gallery' }} settings</a>
    @endif
</article>
@empty<p class="card card-pad">No matching {{ $type === 'studio' ? 'studios' : 'competitions' }}.</p>@endforelse
</div>
<div class="publicity-toolbar" style="margin-top:20px">@if($entries->previousPageUrl())<a href="{{ $entries->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $entries->currentPage() }} of {{ $entries->lastPage() }}</span>@if($entries->nextPageUrl())<a href="{{ $entries->nextPageUrl() }}">Next</a>@endif</div>
<script>
document.querySelectorAll('[data-copy-link]').forEach(button => button.addEventListener('click', async () => {
    const card = button.closest('[data-share-card]');
    const input = card.querySelector('[data-share-url]');
    const status = card.querySelector('[data-copy-status]');
    try { await navigator.clipboard.writeText(input.value); status.textContent = 'Link copied.'; }
    catch { input.focus(); input.select(); status.textContent = 'Link selected. Use Copy to copy it.'; }
}));
</script>
@endsection
