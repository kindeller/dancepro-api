<form class="centre-compose" method="post" action="{{ route('admin.messages.send') }}" enctype="multipart/form-data">
@csrf
<input type="hidden" name="channel" value="{{ $channel }}">
@if(isset($targetRecipient))<input type="hidden" name="recipient" value="{{ $targetRecipient }}">@else<input type="hidden" name="thread" value="{{ $targetThread }}">@endif
@if($channel === 'studio')
@if($contactEmails->isEmpty())<p class="notice" role="alert">Add a studio contact email before sending a message. <a href="{{ route('admin.studios.edit', $studio) }}">Edit studio</a></p>@endif
<details><summary>Advanced options</summary>
<label>Recipient email<select name="recipient_email" required>@foreach($contactEmails as $email)<option value="{{ $email }}" @selected(old('recipient_email') === $email)>{{ $email }}</option>@endforeach</select></label>
<label>Event<select name="event_uuid"><option value="">General studio message</option>@foreach($events as $event)<option value="{{ $event->uuid }}" @selected(old('event_uuid') === $event->uuid)>{{ $event->name }} · {{ $event->event_date?->format('j M Y') }}</option>@endforeach</select></label>
</details>
@endif
@if(isset($targetRecipient) || $channel === 'studio')<label>Subject<input name="subject" value="{{ old('subject', isset($targetRecipient) ? '' : 'Message from DancePro') }}" maxlength="255" required></label>@endif
<x-email-composer :value="old('body', '')" :rows="7" />
<label>Attachment <span class="muted">Optional · images, PDF, Word, Excel, CSV or text · up to 10 MB</span><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"></label>
<p class="muted">Saved in the account conversation and sent by email. Attachments are available securely in the conversation.</p>
<div><button type="submit" @disabled($channel === 'studio' && $contactEmails->isEmpty())>{{ isset($targetRecipient) ? 'Send message' : 'Send reply' }}</button></div>
</form>
