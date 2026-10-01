@extends('layouts.admin', ['title' => 'Photo release announcements', 'heading' => $group->studio->name, 'subheading' => 'Photo release announcements'])
@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<div style="width:100%;min-width:0"><section class="card card-pad"><h2>{{ $group->name }}</h2>
<p><strong>Each announcement covers {{ $includedEvents->count() }} {{ Str::plural('event', $includedEvents->count()) }}.</strong></p>
<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0">@foreach($includedEvents as $included)<div style="padding:12px 16px;border:1px solid #dce7ed;border-radius:8px"><strong>{{ $included->photoGalleryTitle() }}</strong><br>{{ $included->event_date?->format('j M Y') }}</div>@endforeach</div>
@php($audienceLabels = ['customer' => 'Linked customer accounts', 'guest' => 'Gallery guests', 'studio' => 'Studio Hub accounts'])
<div class="release-audience-grid">
@foreach($audienceLabels as $key => $label)
<section class="card card-pad"><strong>{{ $audience->where('audience', $key)->count() }}</strong><div>{{ $label }}</div></section>
@endforeach
</div>
<p>Each gallery announcement covers all events above. Customer and guest duplicates are combined. Studio Hub contacts also receive their separate studio instructions, so a linked customer with Studio Hub access receives both messages. Each email is private.</p>
<p>{{ $deliveries->where('status', 'sent')->count() }} sent · {{ $deliveries->whereIn('status', ['queued', 'scheduled'])->count() }} queued · {{ $deliveries->where('status', 'failed')->count() }} failed. Previously sent or queued messages are skipped separately for gallery updates and studio instructions; failed messages can be retried.</p>
@if(!$audience->contains('audience', 'studio'))<p class="notice">No active, verified Studio Hub contacts are available for this studio. Add Studio Hub access before sending if the studio should receive the family instructions.</p>@endif
@php($announcementBlockers = array_values(array_filter([
    $audience->isEmpty() ? 'No eligible recipients are available for this release yet.' : null,
    !$template?->is_active ? 'Activate the photo release email template before sending.' : null,
    $audience->contains('audience', 'studio') && !$studioJoinUrl ? 'Set an active studio access code before sending the family joining link.' : null,
])))
@include('admin.tracking._action-blockers', ['blockers' => $announcementBlockers, 'blockerHeading' => 'The gallery announcement cannot be sent yet'])
<form method="post" action="{{ route('admin.concert-photo-releases.email.send', $group) }}">@csrf
<details class="release-message" open><summary>Customer account announcement ({{ $audience->where('audience', 'customer')->count() }})</summary>
<p class="muted">Linked accounts also receive this in My Messages → Notifications. The button opens their studio in My Events.</p>
@if($renderedTemplate)<x-email-send-editor :subject="$renderedTemplate['subject']" :body="$renderedTemplate['body']" :personalised="true" />@endif
</details>
<details class="release-message"><summary>Guest gallery announcement ({{ $audience->where('audience', 'guest')->count() }})</summary>
<p class="muted">Explains browsing with an email and studio code, then creating an account to order. Each email includes a personal unsubscribe link. No extra per-event guest emails are sent.</p>
<x-email-send-editor :subject="$guestMessage['subject']" :body="$guestMessage['body']" subject-name="guest_email_subject" body-name="guest_email_body" :bulk="true" />
</details>
<details class="release-message"><summary>Studio announcement &amp; family instructions ({{ $audience->where('audience', 'studio')->count() }})</summary>
<p class="muted">Sent to Studio Hub accounts and saved once in Studio Messages. The family joining link adds this studio after sign-in or verified signup, without requiring a gallery code. Share it only with dance families.</p>
<x-email-send-editor :subject="$studioMessage['subject']" :body="$studioMessage['body']" subject-name="studio_email_subject" body-name="studio_email_body" :personalised="true" />
</details>
<label class="choice"><input type="checkbox" name="confirm_send" value="1" required> I have reviewed the messages and recipients for this release.</label>
<button @disabled(count($announcementBlockers) > 0)>Send all release announcements</button>
</form>
<details style="margin-top:20px"><summary>Review recipients ({{ $audience->count() }} emails)</summary>
@foreach($audienceLabels as $key => $label)<h3>{{ $label }}</h3>@forelse($audience->where('audience', $key) as $recipient)<p>{{ $recipient->name }} · {{ $recipient->email }}</p>@empty<p class="muted">None</p>@endforelse @endforeach
</details>
</section>
<details class="card card-pad" style="margin-top:18px"><summary>Edit standard customer template</summary><p class="muted">Changes here apply to future customer account announcements. Guest and studio messages are prepared for each release; use their previews above to customise this send only.</p><form method="post" action="{{ route('admin.concert-tracker.tasks.template.update', [$event, 'photo_release']) }}" class="grid">@csrf @method('PATCH')
<label>Template name<input name="name" value="{{ $template->name }}" required></label><label>Subject<input name="subject" value="{{ $template->subject }}" required></label><x-email-composer :value="$template->body" :rows="12" />
<label class="choice"><input type="checkbox" name="is_active" value="1" @checked($template->is_active)> Active</label><button>Save template</button></form></details>
<section class="card card-pad" style="margin-top:18px"><h2>Delivery history</h2>@forelse($deliveries as $delivery)<p><strong>{{ $delivery->recipient_email }}</strong> · {{ ucfirst($delivery->workflow_context['photo_release_audience'] ?? 'customer') }} · {{ $delivery->is_simulated ? 'Test · ' : '' }}{{ $delivery->status }} · {{ $delivery->sent_at?->format('j M Y, g:i a') }} · {{ $delivery->createdBy?->name }}</p><details><summary>View recorded message</summary><h3>{{ $delivery->subject }}</h3>{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($delivery->body) !!}</details>@empty<p>No emails sent yet.</p>@endforelse</section></div>
@endsection

@push('styles')
<style>
.release-audience-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:18px 0}.release-audience-grid strong{display:block;font-size:28px;color:var(--brand-strong)}.release-message{margin:20px 0;padding:16px;border:1px solid var(--line);border-radius:8px}.release-message>summary{font-weight:750;cursor:pointer}.release-message[open]>summary{margin-bottom:16px}
@media(max-width:650px){.release-audience-grid{grid-template-columns:1fr}.release-message{padding:10px}}
</style>
@endpush
