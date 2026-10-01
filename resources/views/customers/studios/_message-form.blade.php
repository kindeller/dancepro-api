<details id="contact" class="card notification-row" style="margin-bottom:32px" @if($errors->any()) open @endif>
    <summary class="notification-summary"><span class="notification-heading">Send a message</span><span class="notification-toggle" aria-hidden="true">⌄</span></summary>
    <div class="notification-details">
        <p class="muted">Send DancePro a message about your studio or booking. Use Advanced options to select an event or attach a file.</p>
        <form class="message-form" style="max-width:none" method="post" action="{{ route('customer.studio-communications.store', $studio) }}" enctype="multipart/form-data">@csrf
            <details class="message-advanced" @if(old('event_uuid') || $errors->has('attachment')) open @endif><summary>Advanced options</summary><div class="message-advanced-fields">
                <label>Event<select id="hub-message-event" name="event_uuid"><option value="">General studio message</option>@foreach($events as $event)<option value="{{ $event->uuid }}" @selected(old('event_uuid') === $event->uuid)>{{ $event->concertBookingItem?->title ?: $event->name }} · {{ $event->event_date?->format('j M Y') }}</option>@endforeach</select></label>
                <label>Attachment <span class="meta">optional, up to 10 MB</span><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"></label>
            </div></details>
            <label>Subject<input id="hub-message-subject" name="subject" maxlength="255" value="{{ old('subject') }}" placeholder="What can we help with?"></label>
            <x-email-composer :value="old('body', '')" :rows="7" />
            <div class="inbox-actions"><button class="button" type="submit">Send to DancePro</button></div>
        </form>
    </div>
</details>
