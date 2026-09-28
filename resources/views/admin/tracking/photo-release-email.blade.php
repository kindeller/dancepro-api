@extends('layouts.admin', ['title' => 'Photo release email', 'heading' => $group->studio->name, 'subheading' => 'Photo release email'])
@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<div style="max-width:1000px"><section class="card card-pad"><h2>{{ $group->name }}</h2>
<p>{{ $recipients->count() }} active, verified accounts registered for this studio. {{ $deliveries->where('status', 'sent')->count() }} sent; {{ $deliveries->whereIn('status', ['queued', 'scheduled'])->count() }} pending.</p>
<p>Previously sent or queued recipients are skipped. Failed deliveries can be retried using the same button.</p>
@if($renderedTemplate)<h3>{{ $renderedTemplate['subject'] }}</h3><div>{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($renderedTemplate['body']) !!}</div>@endif
@php($announcementBlockers = array_values(array_filter([
    $recipients->isEmpty() ? 'No active, verified accounts are registered for this studio yet.' : null,
    !$template?->is_active ? 'Activate the photo release email template before sending.' : null,
])))
@include('admin.tracking._action-blockers', ['blockers' => $announcementBlockers, 'blockerHeading' => 'The gallery announcement cannot be sent yet'])
<form method="post" action="{{ route('admin.concert-photo-releases.email.send', $group) }}">@csrf
<label class="choice"><input type="checkbox" name="confirm_send" value="1" required> Send this gallery announcement to the registered studio accounts listed below.</label>
<button @disabled(count($announcementBlockers) > 0)>Send release emails</button></form>
<details style="margin-top:20px"><summary>Registered recipients</summary>@foreach($recipients as $recipient)<p>{{ $recipient->name }} · {{ $recipient->email }}</p>@endforeach</details>
</section>
<details class="card card-pad" style="margin-top:18px"><summary>Edit email template</summary><form method="post" action="{{ route('admin.concert-tracker.tasks.template.update', [$event, 'photo_release']) }}" class="grid">@csrf @method('PATCH')
<label>Template name<input name="name" value="{{ $template->name }}" required></label><label>Subject<input name="subject" value="{{ $template->subject }}" required></label><x-email-composer :value="$template->body" :rows="12" />
<label class="choice"><input type="checkbox" name="is_active" value="1" @checked($template->is_active)> Active</label><button>Save template</button></form></details>
<section class="card card-pad" style="margin-top:18px"><h2>Delivery history</h2>@forelse($deliveries as $delivery)<p><strong>{{ $delivery->recipient_email }}</strong> · {{ $delivery->is_simulated ? 'Test · ' : '' }}{{ $delivery->status }} · {{ $delivery->sent_at?->format('j M Y, g:i a') }} · {{ $delivery->createdBy?->name }}</p>@empty<p>No emails sent yet.</p>@endforelse</section></div>
@endsection
