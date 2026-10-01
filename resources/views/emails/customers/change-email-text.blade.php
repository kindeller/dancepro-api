Hi {{ $firstName }},

@if($confirmationUrl)
You asked to use {{ $newEmail }} for your DancePro account. Confirm this address within 60 minutes:
{!! $confirmationUrl !!}

You may need to sign in with your current email address first. Your current address stays active until you confirm. Your media, purchases and Studio Hub access stay with the same account.
If you did not request this change, ignore this email. Your current address will stay unchanged.
@else
Your DancePro account now uses {{ $newEmail }}. Use this address next time you sign in. Your media and purchases remain in your account.
If you did not make this change, contact {{ $branding['replyAddress'] ?: 'info@dancepro.com.au' }} straight away.
@endif

{{ $branding['signature']['signOff'] }}
{{ $branding['signature']['name'] }}
