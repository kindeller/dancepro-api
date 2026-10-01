{{ $active ? 'Your Studio Hub is ready' : 'Your Studio Hub invitation' }}

{{ $firstName ? 'Hi '.$firstName.',' : 'Hi,' }}

@if($active)
You now have {{ $roleLabel }} access to {{ $studioName }} in DancePro. This has been added to your existing account, so you're ready to get started.
Sign in with {{ $email }}, then choose Studio Hub from your account menu.
@else
DancePro has added you as a contact for {{ $studioName }} and invited you to its Studio Hub with the role of {{ $roleLabel }}. This will give you one place to manage the studio's events and communications with us.
{{ $existingAccount ? 'Sign in to your existing DancePro account' : 'Create a DancePro account' }} using {{ $email }} and verify your email address to activate this access.
@endif

What you can do:
- View concert bookings, event timelines and outstanding actions.
- Confirm event information and upload programmes, videos and slideshows.
- Read studio messages and view, download or pay event invoices.
@if($canRelease)
- Review concert videos and release them to families when they're ready.
@endif

{{ $actionLabel }}:
{!! $actionUrl !!}

Was this added incorrectly?
If you don't recognise this studio or shouldn't have this access, reply to this email or contact {{ $supportEmail }} so we can correct it.

{{ $branding['signature']['signOff'] }}
{{ $branding['signature']['name'] }}
{{ $branding['businessName'] }}
{{ $branding['website'] }}
