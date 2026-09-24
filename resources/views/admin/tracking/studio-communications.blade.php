@extends('layouts.admin', ['title' => 'Studio communications', 'heading' => $studio->name, 'subheading' => 'Studio communication thread'])
@section('content')
<div class="toolbar"><a class="button secondary" href="{{ route('admin.concert-tracker.index') }}">← Concert Tracker</a></div>
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif
<section class="card card-pad" style="margin-bottom:18px"><form method="post" action="{{ route('admin.concert-tracker.studio-communications.assign', $studio) }}" style="display:flex;gap:10px;align-items:end;margin-bottom:20px">@csrf @method('PATCH')<label style="flex:1">Assigned staff member<select name="assigned_user_id"><option value="">Unassigned</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected($thread->assigned_user_id === $member->id)>{{ $member->name }}</option>@endforeach</select></label><button>Save owner</button></form><h2>Reply to studio</h2><form method="post" action="{{ route('admin.concert-tracker.studio-communications.reply', $studio) }}" class="grid" enctype="multipart/form-data">@csrf
    <label>Recipient<select name="recipient_email" required>@foreach($contactEmails as $email)<option value="{{ $email }}">{{ $email }}</option>@endforeach</select></label>
    <label>Concert <span class="muted">(optional)</span><select name="event_uuid"><option value="">General studio message</option>@foreach($events as $event)<option value="{{ $event->uuid }}">{{ $event->name }} · {{ $event->event_date?->format('j M Y') }}</option>@endforeach</select></label>
    <label>Subject<input name="subject" maxlength="255" value="{{ old('subject', 'Message from DancePro') }}"></label>
    <x-email-composer :value="old('body', '')" :rows="6" />
    <label>Attachment <span class="muted">(optional, up to 10 MB)</span><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"></label>
    <div><button>Send reply</button></div>
</form></section>
<section class="card card-pad"><h2>Conversation</h2><p class="muted">Email and DancePro account messages for this studio.</p>
@forelse($communications as $message)<details class="card card-pad" style="margin:12px 0"><summary style="cursor:pointer"><strong>{{ $message['subject'] }}</strong> <span class="muted">· {{ $message['direction'] === 'from_dancepro' ? 'From DancePro' : 'From studio' }} · {{ $message['occurred_at']?->format('j M Y g:i a') }}</span></summary><p class="muted">{{ $message['event'] ?: 'General studio message' }} · {{ $message['contact'] }}</p><div>{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($message['body']) !!}</div>@foreach($message['attachments'] as $attachment)<p><a href="{{ route('admin.concert-tracker.studio-communications.attachments.show', $attachment) }}">📎 {{ $attachment->original_name }}</a> <span class="muted">({{ number_format($attachment->size_bytes / 1024) }} KB)</span></p>@endforeach</details>@empty<p>No messages yet.</p>@endforelse
</section>
@endsection
