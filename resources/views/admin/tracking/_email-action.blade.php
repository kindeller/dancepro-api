@php
    $emailBlockers = $emailBlockers ?? [];
    if ($event->cancelled_at) $emailBlockers[] = 'This event has been cancelled. Emails cannot be sent or scheduled.';
    if (!$template || !$template->is_active) $emailBlockers[] = 'The email template is missing or inactive. Activate the template before sending.';
    $guardedEmail = in_array($definition['key'], \App\Features\Tracking\Support\ConcertTrackerTaskCatalog::GUARDED_EMAIL_TASKS, true);
    $actionDeliveries = isset($invoice) ? $task->deliveries->where('studio_invoice_id', $invoice->id) : $task->deliveries;
    $sentEmail = $guardedEmail ? $actionDeliveries->where('status', 'sent')->sortByDesc('id')->first() : null;
    $pendingEmail = $guardedEmail ? $actionDeliveries->whereIn('status', ['queued', 'scheduled'])->sortByDesc('id')->first() : null;
@endphp
@if(!empty($emailBlockers))
<section class="card card-pad" role="status" style="margin-bottom:18px;border:1px solid var(--line);background:#eaf8fd">
    <h2>Not ready to send</h2>
    <p>This email cannot be sent until the following requirements are resolved:</p>
    <ul>@foreach($emailBlockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
    @if(in_array($definition['key'], ['approval_link_email', 'director_corrections'], true))
    <a class="button secondary" href="{{ route('admin.concert-tracker.tasks.show', [$event, 'video_uploaded']) }}">3.4 Video uploads</a>
    <a class="button secondary" href="{{ route('admin.concert-tracker.tasks.show', [$event, 'quality_check']) }}">3.3 Final quality check</a>
    @endif
</section>
@endif
@if($sentEmail)
<section class="card card-pad" role="status" style="margin-bottom:18px;border:2px solid #8cd9a9;background:#e7f8ed">
    <h2>{{ $sentEmail->is_simulated ? 'Test email captured — no live email sent' : 'Email sent' }}</h2>
    <p><strong>{{ $sentEmail->recipient_email }}</strong> · {{ $sentEmail->sent_at?->format('j M Y, g:i a') }} · {{ $sentEmail->createdBy?->name ?? 'Automation' }}</p>
    <p>This task has a successful delivery recorded. Only send another copy if it is needed.</p>
</section>
@endif
@if($pendingEmail)
<section class="card card-pad" role="status" style="margin-bottom:18px;background:#eaf8fd">
    <h2>{{ $pendingEmail->status === 'scheduled' ? 'Email scheduled' : 'Email queued — awaiting delivery' }}</h2>
    <p>{{ $pendingEmail->recipient_email }}@if($pendingEmail->scheduled_at) · {{ $pendingEmail->scheduled_at->format('j M Y, g:i a') }}@endif</p>
    <p>Another send is blocked while this message is pending.</p>
</section>
@endif
        @if($template && $status['key'] !== 'not_required')
        <section class="card card-pad"><h2>Email action</h2><p class="muted">{{ $definition['key'] === 'confirmation_information_email' ? 'Send the confirmation as the final step of 1.1 Booking & services. The exact message and delivery result are recorded below.' : ($definition['key'] === 'invoice_sent' ? 'Sending starts the 14-day payment deadline. This task completes when the invoice is paid. Resending does not extend the deadline.' : ($definition['key'] === 'slideshow_received' ? 'Sending this request records the message. The task completes when the received files are approved, or you confirm receipt outside the portal.' : 'Sending this email records the exact rendered message and completes the manual task after successful delivery.')) }}</p>
            <div class="email-preview"><div style="margin-bottom:16px;padding:14px;background:#101820;color:#fff"><img src="{{ $emailBranding['logoUrl'] }}" alt="{{ $emailBranding['businessName'] }}" style="display:block;width:240px;max-width:100%;height:auto;object-fit:contain"></div><strong>{{ $renderedTemplate['subject'] }}</strong><div class="email-preview-body">{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($renderedTemplate['body']) !!}</div><div style="margin-top:18px"><p>{{ $emailBranding['signature']['signOff'] }}</p><strong>{{ $emailBranding['signature']['name'] }}</strong>@if($emailBranding['signature']['position'])<br><span class="muted">{{ $emailBranding['signature']['position'] }}</span>@endif</div></div>
            @if(!$pendingEmail && empty($emailBlockers))
            @if($sentEmail)<details style="margin-top:18px"><summary><strong>Send another copy…</strong></summary>@endif
            <form method="post" action="{{ route('admin.concert-tracker.tasks.email.send', [$event, $definition['key']]) }}" class="grid" style="margin-top:14px">@csrf
                @isset($invoice)<input type="hidden" name="studio_invoice_uuid" value="{{ $invoice->uuid }}">@endisset
                <label>Recipient name<input name="recipient_name" value="{{ old('recipient_name', $recipient['name']) }}"></label>
                <label>Recipient email<input type="email" name="recipient_email" value="{{ old('recipient_email', $recipient['email']) }}" required></label>
                <label>Send later <span class="muted">(optional)</span><input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"></label>
                @if($sentEmail)
                <input type="hidden" name="previous_delivery_id" value="{{ $sentEmail->id }}">
                <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="confirm_resend" value="1" required> I have reviewed the previous delivery and want to send another copy.</label>
                @endif
                <div><button>{{ $sentEmail ? 'Confirm and resend email' : ($definition['key'] === 'confirmation_information_email' ? 'Send booking confirmation' : 'Send email') }}</button></div>
            </form>
            @if($sentEmail)</details>@endif
            @endif
        </section>

        <details class="card card-pad" style="margin-top:18px"><summary><strong>Edit email template</strong></summary><p class="muted">Event fields and conditional blocks are managed in the central Email templates page. The preview above shows the version generated for this booking.</p>
            <form method="post" action="{{ route('admin.concert-tracker.tasks.template.update', [$event, $definition['key']]) }}" class="grid">@csrf @method('PATCH')
                <label>Template name<input name="name" value="{{ $template->name }}" required></label>
                <label>Subject<input name="subject" value="{{ $template->subject }}" required></label>
                <x-email-composer :value="$template->body" :rows="12" />
                <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_active" value="1" @checked($template->is_active)> Active</label>
                <div><button>Save template</button></div>
            </form>
        </details>

        <section class="card card-pad" style="margin-top:18px"><h2>Delivery history</h2>@forelse($task->deliveries as $delivery)<div class="note"><strong>{{ $delivery->recipient_email }}</strong> <span class="badge {{ $delivery->status === 'sent' ? 'success' : ($delivery->status === 'failed' ? 'status-overdue' : 'status-waiting') }}">{{ $delivery->is_simulated ? 'Test · ' : '' }}{{ $delivery->status }}</span><div class="muted">{{ $delivery->sent_at?->format('j M Y g:i a') ?? $delivery->scheduled_at?->format('j M Y g:i a') ?? $delivery->queued_at?->format('j M Y g:i a') }} · {{ $delivery->createdBy?->name ?? 'Automation' }}</div><div class="toolbar" style="margin-top:8px">@if($delivery->invalidated_at)<p>{{ $delivery->cancellation_reason }} Use the current template above to prepare a new message.</p>@endif @if($delivery->status === 'scheduled')<form method="post" action="{{ route('admin.concert-tracker.deliveries.cancel', $delivery) }}">@csrf<button class="secondary">Cancel</button></form>@endif @if(!$guardedEmail && !$delivery->invalidated_at && in_array($delivery->status, ['sent', 'failed', 'cancelled'], true))<form method="post" action="{{ route('admin.concert-tracker.deliveries.resend', $delivery) }}">@csrf<button class="secondary">Resend</button></form>@endif</div></div>@empty<p class="muted">No messages have been queued.</p>@endforelse</section>
        @else
        <section class="card card-pad"><h2>Task guidance</h2><p>{{ $status['key'] === 'not_required' ? 'This email is not required for the selected services.' : 'No email template is available for this task. Configure it in Email templates before sending.' }}</p></section>
        @endif
