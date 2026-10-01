<p style="color:#087fab;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase">Your DancePro account</p>
<h1 style="color:#101820;font-size:26px">{{ $confirmationUrl ? 'Confirm your new email address' : 'Your email address has changed' }}</h1>
<p>Hi {{ $firstName }},</p>
@if($confirmationUrl)
<p>You asked to use <strong>{{ $newEmail }}</strong> for your DancePro account. Confirm this address using the button below within 60 minutes. You may need to sign in with your current email address first.</p>
<p>Your current sign-in address stays active until you confirm. Your media, purchases and Studio Hub access stay with the same account.</p>
<p style="margin:24px 0"><a href="{{ $confirmationUrl }}" style="display:inline-block;background:#0aa0db;color:white;padding:14px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Confirm new email address</a></p>
<p>If you did not request this change, ignore this email. Your current address will stay unchanged.</p>
<p style="font-size:12px;word-break:break-all">Button not working? <a href="{{ $confirmationUrl }}">{{ $confirmationUrl }}</a></p>
@else
<p>Your DancePro account now uses <strong>{{ $newEmail }}</strong>. Use this address next time you sign in. Your media and purchases remain in your account.</p>
<p>If you did not make this change, contact <a href="mailto:{{ $branding['replyAddress'] ?: 'info@dancepro.com.au' }}">{{ $branding['replyAddress'] ?: 'info@dancepro.com.au' }}</a> straight away.</p>
@endif
