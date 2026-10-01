Welcome to DancePro

Hi {!! $firstName !!},

Your email is verified and your account is ready. Here’s how to set it up and make the most of your DancePro photos and videos.

@foreach($sections as $section)
{!! $section['title'] !!}
{!! $section['body'] !!}
@if($section['url']){!! $section['label'] !!}: {!! $section['url'] !!}
@endif

@endforeach
@if($apps)
DancePro on your phone
Download the app and sign in with the same DancePro account.
@foreach($apps as $label => $url){!! $label !!}: {!! $url !!}
@endforeach

@endif
Open your DancePro account: {!! $accountUrl !!}

Keep your password and access codes private. If you didn’t create or verify this account, contact DancePro through Help: {!! $helpUrl !!}

A copy of this welcome is saved in My Messages → Notifications so you can return to these instructions later.

Kind regards,
DancePro Team
