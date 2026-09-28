<form method="post" action="{{ route('customer.messages.store') }}" enctype="multipart/form-data" class="message-form">@csrf
    <details class="message-advanced" @if($errors->hasAny(['type', 'event_uuid', 'order_id', 'order_media_entitlement_id', 'attachment'])) open @endif>
        <summary>Advanced options</summary>
        <div class="message-advanced-fields">
            <label>Topic<select name="type" required><option value="general" @selected(old('type', request('type')) !== 'download_reactivation')>Account, event or order enquiry</option><option value="download_reactivation" @selected(old('type', request('type')) === 'download_reactivation')>Reactivate an expired download</option></select></label>
            <label>Event <span class="message-hint">Optional</span><select name="event_uuid"><option value="">No specific event</option>@foreach($events as $event)<option value="{{ $event->uuid }}" @selected(old('event_uuid', request('event')) === $event->uuid)>{{ $event->name }} · {{ $event->event_date?->format('j M Y') }}</option>@endforeach</select></label>
            <label>Order <span class="message-hint">Optional</span><select name="order_id"><option value="">No specific order</option>@foreach($orders as $order)<option value="{{ $order->id }}" @selected((string) old('order_id', request('order')) === (string) $order->id)>Order {{ $order->displayNumber() }} · {{ $order->created_at->format('j M Y') }}</option>@endforeach</select></label>
            <label>Expired media <span class="message-hint">Only needed for download reactivation</span><select name="order_media_entitlement_id"><option value="">Choose expired media</option>@foreach($orders as $order)@foreach($order->items as $item)@foreach($item->mediaEntitlements->filter(fn($entitlement) => $entitlement->ready_at && $entitlement->expires_at?->isPast()) as $entitlement)<option value="{{ $entitlement->id }}" @selected((string) old('order_media_entitlement_id', request('entitlement')) === (string) $entitlement->id)>Order {{ $order->displayNumber() }} · {{ ucfirst($entitlement->media_type) }} · {{ $item->snapshot_display_name ?: 'Media item' }}</option>@endforeach @endforeach @endforeach</select></label>
            <label>Attachment <span class="message-hint">Optional · JPG, PNG, WebP, PDF or TXT · up to 10 MB</span><input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt"></label>
        </div>
    </details>
    <label>Subject<input name="subject" maxlength="255" value="{{ old('subject', request('type') === 'download_reactivation' ? 'Please reactivate my download' : '') }}" required></label>
    <label>Message<textarea name="message" rows="6" maxlength="5000" required>{{ old('message') }}</textarea></label>
    <div><button class="button">Send message</button></div>
</form>
