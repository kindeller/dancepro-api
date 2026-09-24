@extends('layouts.admin', ['title' => 'Studio messages', 'heading' => 'Studio messages', 'subheading' => 'Email and DancePro account conversations'])
@section('content')
<div class="toolbar"><a class="button secondary" href="{{ route('admin.concert-tracker.index') }}">← Concert Tracker</a></div>
<section class="card"><table><thead><tr><th>Studio</th><th>Latest account message</th><th>Unread</th><th></th></tr></thead><tbody>
@forelse($studios as $studio)@php($message = $latest->get($studio->id))<tr><td><strong>{{ $studio->name }}</strong><br><span class="muted">{{ $threads->get($studio->id)?->assignedUser?->name ?: 'Unassigned' }}</span></td><td>@if($message)<strong>{{ $message->subject }}</strong><br><span class="muted">{{ $message->event?->name ?: 'General' }} · {{ $message->sent_at?->format('j M Y g:i a') }}</span>@else<span class="muted">Email history available; no account replies yet.</span>@endif</td><td>@if($unread->get($studio->id))<span class="badge attention">{{ $unread->get($studio->id) }}</span>@else<span class="muted">0</span>@endif</td><td><a class="button secondary" href="{{ route('admin.concert-tracker.studio-communications.show', $studio) }}">Open conversation</a></td></tr>@empty<tr><td colspan="4">No concert studios found.</td></tr>@endforelse
</tbody></table></section>
@endsection
