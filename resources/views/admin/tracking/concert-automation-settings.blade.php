@extends('layouts.admin', [
    'title' => 'Tracker automation settings',
    'heading' => 'Tracker automation settings',
    'subheading' => 'Communication timing, production targets and alert escalation',
])

@section('content')
<div class="toolbar" style="margin-bottom:18px"><a class="button secondary" href="{{ route('admin.concert-tracker.index') }}">← Concert tracker</a><a class="button secondary" href="{{ route('admin.concert-tracker.automation.index') }}">Communication schedule</a></div>
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif

<form method="post" action="{{ route('admin.concert-tracker.automation.settings.update') }}" class="grid">@csrf @method('PATCH')
<section class="card card-pad"><h2 style="margin-top:0">Standard emails</h2><p class="muted">Review requires staff approval in the Communication schedule. Automatic creates the scheduled delivery without approval. Disabled removes the email from the schedule.</p><div style="overflow:auto"><table><thead><tr><th>Email</th><th>Mode</th><th>Days from milestone</th><th>Send time</th></tr></thead><tbody>
@foreach($definitions as $taskKey => $definition)@php($rule = $rules->get($taskKey))<tr><td><strong>{{ $definition['label'] }}</strong><br><span class="muted">{{ $definition['basis'] === 'approved' ? 'Booking approval' : ($definition['basis'] === 'booking_first' ? 'First concert' : 'Event date') }}</span></td><td><select name="rules[{{ $taskKey }}][mode]"><option value="disabled" @selected($rule->mode === 'disabled')>Disabled</option><option value="review" @selected($rule->mode === 'review')>Review first</option><option value="automatic" @selected($rule->mode === 'automatic')>Automatic</option></select></td><td><input type="number" name="rules[{{ $taskKey }}][offset_days]" min="-365" max="365" value="{{ $rule->offset_days }}" required></td><td><input type="time" name="rules[{{ $taskKey }}][send_time]" value="{{ substr($rule->send_time, 0, 5) }}" required></td></tr>@endforeach
</tbody></table></div></section>

<section class="card card-pad"><h2 style="margin-top:0">Production targets</h2><div class="grid two-col"><label>Editing target after event (days)<input type="number" name="editing_target_days" min="1" max="90" value="{{ $settings->editing_target_days }}" required></label><label>Director approval target after event (days)<input type="number" name="approval_target_days" min="1" max="90" value="{{ $settings->approval_target_days }}" required></label></div></section>

<section class="card card-pad"><h2 style="margin-top:0">Alert escalation</h2><div class="grid two-col"><label>Owner for unassigned alerts<select name="unassigned_alert_user_id"><option value="">All active administrators</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected($settings->unassigned_alert_user_id === $person->id)>{{ $person->name }}</option>@endforeach</select></label><label>Escalate assigned alerts after (days)<input type="number" name="escalation_after_days" min="1" max="30" value="{{ $settings->escalation_after_days }}" required></label></div></section>

<section class="card card-pad"><h2 style="margin-top:0">Email branding</h2><p class="muted">Used for Tracker templates and Management Hub replies. Leave the logo URL empty to use the DancePro app logo.</p><div class="grid two-col">
    <label>Business name<input name="email_business_name" value="{{ old('email_business_name', $settings->email_business_name ?: 'DancePro Photography & Video') }}" required></label>
    <label>Logo URL <span class="muted">(optional)</span><input name="email_logo_url" value="{{ old('email_logo_url', $settings->email_logo_url) }}" placeholder="/images/brand/dancepro-logo-inverse-landscape.png"></label>
    <label>Phone<input name="email_phone" value="{{ old('email_phone', $settings->email_phone) }}"></label>
    <label>Reply email<input type="email" name="email_reply_address" value="{{ old('email_reply_address', $settings->email_reply_address) }}"></label>
    <label>Website<input type="url" name="email_website" value="{{ old('email_website', $settings->email_website ?: 'https://www.dancepro.com.au') }}" required></label>
    <label>Business address<input name="email_address" value="{{ old('email_address', $settings->email_address) }}"></label>
</div><label>Legal/footer note<textarea name="email_legal_footer" rows="3" maxlength="1000">{{ old('email_legal_footer', $settings->email_legal_footer) }}</textarea></label></section>

<section class="card card-pad"><h2 style="margin-top:0">My email signature</h2><p class="muted">Manual replies use the signature of the staff member sending them. Tracker emails use the staff member who queued the message; fully automatic emails use the DancePro Team signature.</p><div class="grid two-col">
    <label>Display name<input name="signature_display_name" value="{{ old('signature_display_name', $signature->display_name ?: auth()->user()->name) }}" required></label>
    <label>Position<input name="signature_position" value="{{ old('signature_position', $signature->position) }}" placeholder="Concert Coordinator"></label>
    <label>Direct phone <span class="muted">(optional)</span><input name="signature_phone" value="{{ old('signature_phone', $signature->phone) }}"></label>
    <label>Sign-off<input name="signature_sign_off" value="{{ old('signature_sign_off', $signature->sign_off ?: 'Kind regards,') }}"></label>
</div><input type="hidden" name="signature_is_enabled" value="0"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="signature_is_enabled" value="1" @checked(old('signature_is_enabled', $signature->is_enabled ?? true))> Include my signature on emails I send</label></section>
<div><button>Save automation settings</button></div></form>

<section class="card card-pad" style="margin-top:18px"><h2 style="margin-top:0">Test your email</h2><p>Send a sample using the saved logo, email formatting and your signature to <strong>{{ auth()->user()->email }}</strong>.</p><p class="muted">Save any changes above before sending the preview.</p><form method="post" action="{{ route('admin.concert-tracker.automation.settings.test-email') }}">@csrf<button type="submit">Send test email</button></form></section>
@endsection
