<h2>{{ $studio->name }}</h2>
<form method="post" action="{{ route('admin.concert-tracker.studio-communications.assign', $studio) }}" class="toolbar">@csrf @method('PATCH')<label>Assigned staff member<select name="assigned_user_id"><option value="">Unassigned</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected($thread->assigned_user_id === $member->id)>{{ $member->name }}</option>@endforeach</select></label><button class="secondary">Save owner</button></form>
<div class="centre-history">
@forelse($communications as $message)
<article class="centre-message {{ $message['direction'] === 'from_dancepro' ? 'sent' : '' }}">
<strong>{{ $message['subject'] }}</strong><p class="muted">{{ $message['direction'] === 'from_dancepro' ? 'From DancePro' : 'From studio' }} · {{ $message['occurred_at']?->format('j M Y, g:i a') }} · {{ $message['status'] }}<br>{{ $message['contact'] }}@if($message['event']) · {{ $message['event'] }}@endif</p>
<div class="centre-message-body">{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($message['body']) !!}</div>
@foreach($message['attachments'] as $attachment)<p><a href="{{ route('admin.concert-tracker.studio-communications.attachments.show', $attachment) }}">Download {{ $attachment->original_name }}</a></p>@endforeach
</article>
@empty<p>No messages yet.</p>@endforelse
</div>
@include('admin.messages._compose', ['targetThread'=>$studio->uuid])
