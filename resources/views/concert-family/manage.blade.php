@extends('layouts.public', ['title' => 'Family sharing'])
@section('content')
@include('concert-family._styles')
<section class="hero"><div class="container">@include('customers._heading', ['headingImageUrl' => $concert->studio->logoUrl(), 'eyebrow' => 'DancePro Media', 'heading' => 'Family sharing', 'description' => $concert->name.' · Invite family to enjoy the released concert videos.'])</div></section>
<section class="section"><div class="container family-stack">
<div class="family-actions"><a class="button secondary" href="{{ route('concerts.show', $concert) }}">← Back to concert</a><a class="button secondary" href="{{ route('customer.media.index') }}">My Media</a>@if($managing && auth()->user()->canAccessAdmin())<a class="button secondary" href="{{ route('admin.concerts.edit', $concert) }}">Back to admin</a>@endif</div>
@if(session('status'))<p role="status" class="family-status">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="family-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@include('concert-family._terms')
@if($managing)
<form class="card family-card" action="{{ route('customer.concert-family.policy', $concert) }}" method="post">@csrf @method('PATCH')
<h2>Studio sharing settings</h2><p>These settings apply to every concert for {{ $concert->studio->name }}. Turning sharing off revokes existing invitations. Turning it back on does not automatically restore them.</p>
<input type="hidden" name="family_sharing_enabled" value="0"><label class="family-choice"><input type="checkbox" name="family_sharing_enabled" value="1" @checked($concert->studio->family_sharing_enabled)> Allow family invitations</label>
<input type="hidden" name="family_downloads_enabled" value="0"><label class="family-choice"><input type="checkbox" name="family_downloads_enabled" value="1" @checked($concert->studio->family_downloads_enabled)> Allow family downloads, with three requests per video</label>
<button class="button">Save studio settings</button></form>
@elseif($canShare && $shares->count() < 3)
<form class="card family-card" action="{{ route('customer.concert-family.store', $concert) }}" method="post">@csrf
<h2>Invite family</h2><p>{{ 3 - $shares->count() }} recipient places remaining for this concert.</p>
<label>Family member’s name<input name="recipient_name" maxlength="100" value="{{ old('recipient_name') }}" required autocomplete="off"></label>
<label>Family member’s email<input type="email" name="recipient_email" maxlength="255" value="{{ old('recipient_email') }}" required autocomplete="off"></label>
<fieldset><legend>Videos to share</legend><p>Choose the routines you want your family to see.</p><button class="button secondary" type="button" data-family-select-all>Select all videos</button>
@foreach($assets as $asset)<label class="family-choice"><input type="checkbox" name="assets[]" value="{{ $asset->uuid }}" @checked(in_array($asset->uuid, old('assets', [])))>{{ $asset->display_name ?? $asset->original_filename }}</label>@endforeach
</fieldset>
<label class="family-choice"><input type="checkbox" name="terms" value="1" required>I understand the expiry and download limits and will share only with family for private viewing.</label>
<button class="button">Create family invitation</button></form>
@elseif(!$managing)
<p>{{ $canShare ? 'Three family recipients are already recorded. Renew an existing invitation below, or contact DancePro if you need help.' : 'New invitations are unavailable because your concert access has ended or the studio has disabled sharing.' }}</p>
@endif
<h2>{{ $managing ? 'Customer invitations for this concert' : 'Your family invitations' }}</h2>
@forelse($shares as $share)
<article class="card family-card"><h3>{{ $share->recipient_name }}</h3><p>{{ $share->recipient_email }}@if($managing) · Shared by {{ $share->user?->name }}@endif</p>
<p class="family-status">{{ $share->revoked_at ? 'Revoked' : (!$access->available($share) ? 'Expired or unavailable' : ($share->accepted_at ? 'Accepted' : 'Awaiting acceptance')) }}</p>
<p>Created {{ $share->created_at->format('j M Y') }} · {{ $share->link_count }} invitation{{ $share->link_count === 1 ? '' : 's' }} created/renewed</p>
<p>{{ $share->accepted_at ? 'Access ends' : 'Accept by' }} {{ $access->end($share)->format('j M Y, g:ia T') }}@if($share->accepted_at) · Streaming ends {{ $access->end($share, true)->format('j M Y, g:ia T') }}@endif</p>
@if(!$managing && $access->available($share))
<label>Private invitation link<input class="family-link" readonly value="{{ route('concert-family.show', $share) }}" aria-label="Invitation for {{ $share->recipient_name }}"></label>
<div class="family-actions"><button type="button" class="button secondary" data-copy-family>Copy link</button><span class="family-copy-state" role="status"></span></div>
@endif
<details><summary>Viewing and download activity</summary><p>Approximate playback activity; download requests do not confirm completed transfers.</p><ul class="family-list">@foreach($share->videos as $video)<li><strong>{{ $video->asset?->display_name ?? $video->asset?->original_filename ?? 'Unavailable video' }}</strong> · {{ $video->playback_starts }} playback starts · {{ $video->playback_completed_at ? 'Played to end' : 'Not played to end' }} · {{ $video->download_requests }} / 3 download requests @if($video->last_played_at) · Last played {{ $video->last_played_at->format('j M Y, g:ia') }}@endif</li>@endforeach</ul></details>
<div class="family-actions">
@if(!$managing && $canShare && !$share->contact_redacted_at)<form method="post" action="{{ route('customer.concert-family.renew', [$concert, $share]) }}">@csrf<button class="button secondary">Renew invitation</button></form>@endif
@if(!$share->revoked_at)<form method="post" action="{{ route('customer.concert-family.revoke', [$concert, $share]) }}">@csrf<button class="button secondary">Revoke access</button></form>@endif
</div></article>
@empty<p>No family invitations have been created.</p>@endforelse
@if($managing){{ $shares->links() }}@endif
</div></section>
@endsection
@push('scripts')<script>document.querySelector('[data-family-select-all]')?.addEventListener('click', event => {const boxes=[...document.querySelectorAll('input[name="assets[]"]')],selected=boxes.every(box=>box.checked);boxes.forEach(box=>box.checked=!selected);event.target.textContent=selected?'Select all videos':'Clear selection';});document.querySelectorAll('[data-copy-family]').forEach(button=>button.addEventListener('click',async()=>{const card=button.closest('article'),input=card.querySelector('.family-link'),state=card.querySelector('.family-copy-state');try{await navigator.clipboard.writeText(input.value);state.textContent='Link copied.';}catch{input.select();state.textContent='Select and copy the link above.';}}));</script>@endpush
