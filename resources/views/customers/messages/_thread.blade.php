<h2 class="message-title">{{ $conversation->subject }}</h2>
<p class="inbox-meta">{{ $conversation->status === 'pending' ? 'Awaiting DancePro reply' : 'Answered' }}@if($conversation->order) · Order {{ $conversation->order->displayNumber() }}@endif @if(data_get($conversation->context, 'event_name')) · {{ $conversation->context['event_name'] }}@endif</p>
@if($conversation->accountEmail)<p class="message-hint">Email: {{ $conversation->accountEmail->status }}@if($conversation->accountEmail->sent_at) · {{ $conversation->accountEmail->sent_at->format('j M Y, g:i a') }}@endif</p>@endif
<div class="message-thread">@foreach($conversation->messages as $message)
    <article class="message-bubble {{ $message->direction === 'to_customer' ? 'from-dancepro' : '' }}"><div class="inbox-meta"><strong>{{ $message->direction === 'to_customer' ? 'DancePro' : ($adminConversation ? $conversation->user->name : 'You') }}</strong><span>{{ $message->sent_at->format('j M Y, g:i a') }}</span></div><p class="inbox-body">{{ $message->body }}</p>@if($message->attachment)<p><a href="{{ route($adminConversation ? 'admin.media-support.attachment' : 'customer.messages.attachment', $message) }}">Download {{ $message->attachment['name'] }}</a></p>@endif</article>
@endforeach</div>
<form method="post" action="{{ route($adminConversation ? 'admin.media-support.reply' : 'customer.messages.reply', $conversation) }}" enctype="multipart/form-data" class="message-form">@csrf
    <label>Reply<textarea name="message" rows="5" maxlength="5000" required>{{ old('message') }}</textarea></label>
    <label>Attachment <span class="message-hint">Optional · JPG, PNG, WebP, PDF or TXT · up to 10 MB</span><input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt"></label>
    <div><button class="button">{{ $adminConversation ? 'Send reply & mark answered' : 'Send reply' }}</button></div>
</form>
