<p class="message-notice" data-notification-feedback role="status" hidden></p>
<div class="inbox-list">
    @forelse($communications as $message)
        @php($incoming = $message['direction'] === 'from_dancepro')
        @php($unread = $incoming && !$message['read_at'])
        @php($formId = 'studio-read-'.$message['source'].'-'.$message['uuid'])
        <details class="card inbox-row notification-row {{ $unread ? 'unread' : '' }}">
            <summary class="notification-summary">
                <time datetime="{{ $message['occurred_at']?->toIso8601String() }}"><span>{{ $message['occurred_at']?->format('j M Y') }}</span> <span>{{ $message['occurred_at']?->format('g:i a') }}</span></time>
                <span class="notification-status {{ $unread ? 'is-unread' : '' }}" aria-live="polite">{{ !$incoming ? 'Sent' : ($unread ? 'Unread' : 'Read') }}</span>
                <span class="notification-heading">{{ $message['subject'] }}</span>
                @if($unread)<button class="button secondary notification-read" type="submit" form="{{ $formId }}" data-notification-read>Mark as read</button>@endif
                <span class="notification-toggle" aria-hidden="true">⌄</span>
            </summary>
            <div class="notification-details">
                <p class="message-hint">{{ $message['event'] ?: 'Studio communication' }} · {{ $incoming ? 'From DancePro' : 'Sent to DancePro' }} · {{ $message['contact'] }}</p>
                <div>{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($message['body']) !!}</div>
                <p class="message-hint">{{ ucfirst($message['status']) }}</p>
                <div class="inbox-actions">
                    @foreach($message['attachments'] as $attachment)<a class="button secondary" href="{{ route('customer.studio-communications.attachments.show', [$studio, $attachment]) }}">Download {{ $attachment->original_name }}</a>@endforeach
                    @if($incoming)<button class="button secondary message-reply" type="button" data-event="{{ $message['event_uuid'] }}" data-subject="{{ $message['subject'] }}">Reply</button>@endif
                    @if($unread)<form id="{{ $formId }}" method="post" action="{{ route('customer.studio-communications.read', [$studio, $message['source'], $message['uuid']]) }}" data-notification-read-form hidden>@csrf</form>@endif
                </div>
            </div>
        </details>
    @empty
        <div class="card inbox-empty"><h3>No messages yet</h3><p class="muted">DancePro emails and your account replies will appear here.</p></div>
    @endforelse
</div>
